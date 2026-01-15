<?php
namespace Local\AccessManager;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;

/**
 * Класс для логирования операций и управления снапшотами
 */
class Logger
{
    /**
     * Типы операций
     */
    const OP_SET_PERMISSION = 'set_permission';
    const OP_REMOVE_PERMISSION = 'remove_permission';
    const OP_RESET_DEFAULT = 'reset_default';
    const OP_BULK_SET = 'bulk_set';
    const OP_ROLLBACK = 'rollback';

    /**
     * Типы объектов
     */
    const OBJ_IBLOCK = 'iblock';
    const OBJ_IBLOCK_TYPE = 'iblock_type';
    const OBJ_FILE = 'file';
    const OBJ_FOLDER = 'folder';

    /**
     * Записать лог операции
     * 
     * @param string $operationType
     * @param string $objectType
     * @param string $objectId
     * @param string $subjectType
     * @param int $subjectId
     * @param array|null $oldPermissions
     * @param array|null $newPermissions
     * @return int|false
     */
    public static function log(
        string $operationType,
        string $objectType,
        string $objectId,
        string $subjectType,
        int $subjectId,
        ?array $oldPermissions = null,
        ?array $newPermissions = null
    ) {
        global $DB, $USER;

        $userId = $USER->GetID();

        $result = $DB->Insert('local_accessmanager_log', [
            'USER_ID' => (int)$userId,
            'OPERATION_TYPE' => "'" . $DB->ForSql($operationType) . "'",
            'OBJECT_TYPE' => "'" . $DB->ForSql($objectType) . "'",
            'OBJECT_ID' => "'" . $DB->ForSql($objectId) . "'",
            'SUBJECT_TYPE' => "'" . $DB->ForSql($subjectType) . "'",
            'SUBJECT_ID' => (int)$subjectId,
            'OLD_PERMISSIONS' => $oldPermissions ? "'" . $DB->ForSql(json_encode($oldPermissions, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL',
            'NEW_PERMISSIONS' => $newPermissions ? "'" . $DB->ForSql(json_encode($newPermissions, JSON_UNESCAPED_UNICODE)) . "'" : 'NULL',
            'CREATED_AT' => "NOW()",
        ]);

        return $result;
    }

    /**
     * Получить список логов
     * 
     * @param array $filter
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getLogs(array $filter = [], int $limit = 50, int $offset = 0): array
    {
        global $DB;

        $where = [];
        
        if (!empty($filter['USER_ID'])) {
            $where[] = "l.USER_ID = " . (int)$filter['USER_ID'];
        }
        if (!empty($filter['OPERATION_TYPE'])) {
            $where[] = "l.OPERATION_TYPE = '" . $DB->ForSql($filter['OPERATION_TYPE']) . "'";
        }
        if (!empty($filter['OBJECT_TYPE'])) {
            $where[] = "l.OBJECT_TYPE = '" . $DB->ForSql($filter['OBJECT_TYPE']) . "'";
        }
        if (!empty($filter['DATE_FROM'])) {
            $where[] = "l.CREATED_AT >= '" . $DB->ForSql($filter['DATE_FROM']) . "'";
        }
        if (!empty($filter['DATE_TO'])) {
            $where[] = "l.CREATED_AT <= '" . $DB->ForSql($filter['DATE_TO']) . "'";
        }

        $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT 
                l.*,
                u.LOGIN,
                u.NAME as USER_NAME,
                u.LAST_NAME as USER_LAST_NAME
            FROM local_accessmanager_log l
            LEFT JOIN b_user u ON u.ID = l.USER_ID
            {$whereStr}
            ORDER BY l.CREATED_AT DESC
            LIMIT {$offset}, {$limit}
        ";

        $result = [];
        $res = $DB->Query($sql);
        while ($row = $res->Fetch()) {
            $row['OLD_PERMISSIONS'] = $row['OLD_PERMISSIONS'] ? json_decode($row['OLD_PERMISSIONS'], true) : null;
            $row['NEW_PERMISSIONS'] = $row['NEW_PERMISSIONS'] ? json_decode($row['NEW_PERMISSIONS'], true) : null;
            $row['USER_FULL_NAME'] = trim($row['USER_NAME'] . ' ' . $row['USER_LAST_NAME']) ?: $row['LOGIN'];
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Создать снапшот перед массовой операцией
     * 
     * @param string $objectType
     * @param array $objects [['id' => ..., 'permissions' => [...]], ...]
     * @return string Batch ID
     */
    public static function createSnapshot(string $objectType, array $objects): string
    {
        global $DB, $USER;

        $batchId = self::generateBatchId();
        $userId = $USER->GetID();

        foreach ($objects as $obj) {
            $DB->Insert('local_accessmanager_snapshots', [
                'BATCH_ID' => "'" . $DB->ForSql($batchId) . "'",
                'USER_ID' => (int)$userId,
                'OBJECT_TYPE' => "'" . $DB->ForSql($objectType) . "'",
                'OBJECT_ID' => "'" . $DB->ForSql($obj['id']) . "'",
                'PERMISSIONS_BEFORE' => "'" . $DB->ForSql(json_encode($obj['permissions'], JSON_UNESCAPED_UNICODE)) . "'",
                'CREATED_AT' => "NOW()",
                'ROLLED_BACK' => 0,
            ]);
        }

        return $batchId;
    }

    /**
     * Получить список снапшотов для отката
     * 
     * @param int $limit
     * @return array
     */
    public static function getSnapshots(int $limit = 20): array
    {
        global $DB;

        $sql = "
            SELECT 
                s.BATCH_ID,
                MIN(s.CREATED_AT) as CREATED_AT,
                s.USER_ID,
                s.OBJECT_TYPE,
                COUNT(*) as OBJECTS_COUNT,
                MAX(s.ROLLED_BACK) as ROLLED_BACK,
                u.LOGIN,
                u.NAME as USER_NAME,
                u.LAST_NAME as USER_LAST_NAME
            FROM local_accessmanager_snapshots s
            LEFT JOIN b_user u ON u.ID = s.USER_ID
            GROUP BY s.BATCH_ID, s.USER_ID, s.OBJECT_TYPE, u.LOGIN, u.NAME, u.LAST_NAME
            ORDER BY CREATED_AT DESC
            LIMIT {$limit}
        ";

        $result = [];
        $res = $DB->Query($sql);
        while ($row = $res->Fetch()) {
            $row['USER_FULL_NAME'] = trim($row['USER_NAME'] . ' ' . $row['USER_LAST_NAME']) ?: $row['LOGIN'];
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Откатить снапшот
     * 
     * @param string $batchId
     * @return array ['success' => int, 'errors' => array]
     */
    public static function rollbackSnapshot(string $batchId): array
    {
        global $DB;

        $result = ['success' => 0, 'errors' => []];

        // Получаем данные снапшота
        $sql = "
            SELECT * FROM local_accessmanager_snapshots 
            WHERE BATCH_ID = '" . $DB->ForSql($batchId) . "'
            AND ROLLED_BACK = 0
        ";

        $res = $DB->Query($sql);
        while ($row = $res->Fetch()) {
            $permissions = json_decode($row['PERMISSIONS_BEFORE'], true);
            
            try {
                if ($row['OBJECT_TYPE'] === self::OBJ_IBLOCK) {
                    $iblock = new \CIBlock();
                    $iblock->SetPermission((int)$row['OBJECT_ID'], $permissions);
                    $result['success']++;
                } elseif (in_array($row['OBJECT_TYPE'], [self::OBJ_FILE, self::OBJ_FOLDER])) {
                    global $APPLICATION;
                    $APPLICATION->SetFileAccessPermission($row['OBJECT_ID'], $permissions);
                    $result['success']++;
                }
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'objectId' => $row['OBJECT_ID'],
                    'message' => $e->getMessage(),
                ];
            }
        }

        // Помечаем снапшот как откаченный
        if ($result['success'] > 0) {
            $DB->Query("
                UPDATE local_accessmanager_snapshots 
                SET ROLLED_BACK = 1 
                WHERE BATCH_ID = '" . $DB->ForSql($batchId) . "'
            ");
        }

        return $result;
    }

    /**
     * Генерация уникального ID пакета
     * 
     * @return string
     */
    private static function generateBatchId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Очистить старые логи
     * 
     * @param int $daysOld
     * @return int Количество удалённых записей
     */
    public static function cleanupOldLogs(int $daysOld = 90): int
    {
        global $DB;

        $DB->Query("
            DELETE FROM local_accessmanager_log 
            WHERE CREATED_AT < DATE_SUB(NOW(), INTERVAL {$daysOld} DAY)
        ");

        return $DB->AffectedRowsCount();
    }

    /**
     * Очистить старые снапшоты
     * 
     * @param int $daysOld
     * @return int
     */
    public static function cleanupOldSnapshots(int $daysOld = 30): int
    {
        global $DB;

        $DB->Query("
            DELETE FROM local_accessmanager_snapshots 
            WHERE CREATED_AT < DATE_SUB(NOW(), INTERVAL {$daysOld} DAY)
        ");

        return $DB->AffectedRowsCount();
    }
}
