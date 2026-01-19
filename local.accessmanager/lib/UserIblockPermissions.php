<?php
namespace Local\AccessManager;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

/**
 * Класс для работы с правами ПОЛЬЗОВАТЕЛЕЙ на инфоблоки
 * 
 * Bitrix хранит права пользователей в таблице b_iblock_group с отрицательным GROUP_ID:
 * - Для группы ID=5 → GROUP_ID=5
 * - Для пользователя ID=12 → GROUP_ID=-12
 */
class UserIblockPermissions
{
    /**
     * Установить права пользователя на инфоблок
     * 
     * @param int $iblockId ID инфоблока
     * @param int $userId ID пользователя
     * @param string $permission Уровень доступа (D, R, U, S, W, X)
     * @return bool
     */
    public static function setPermission(int $iblockId, int $userId, string $permission): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        
        // Для пользователей используем отрицательный GROUP_ID
        $groupId = -abs($userId);
        
        // Проверяем существование записи
        $existing = $connection->query(
            "SELECT IBLOCK_ID FROM b_iblock_group 
             WHERE IBLOCK_ID = {$iblockId} AND GROUP_ID = {$groupId}"
        )->fetch();
        
        if ($existing) {
            // Обновляем существующую запись
            $connection->query(
                "UPDATE b_iblock_group 
                 SET PERMISSION = '{$sqlHelper->forSql($permission)}'
                 WHERE IBLOCK_ID = {$iblockId} AND GROUP_ID = {$groupId}"
            );
        } else {
            // Создаём новую запись
            $connection->query(
                "INSERT INTO b_iblock_group (IBLOCK_ID, GROUP_ID, PERMISSION) 
                 VALUES ({$iblockId}, {$groupId}, '{$sqlHelper->forSql($permission)}')"
            );
        }
        
        return true;
    }

    /**
     * Удалить права пользователя на инфоблок
     * 
     * @param int $iblockId ID инфоблока
     * @param int $userId ID пользователя
     * @return bool
     */
    public static function removePermission(int $iblockId, int $userId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $connection = Application::getConnection();
        $groupId = -abs($userId);
        
        $connection->query(
            "DELETE FROM b_iblock_group 
             WHERE IBLOCK_ID = {$iblockId} AND GROUP_ID = {$groupId}"
        );
        
        return true;
    }

    /**
     * Получить права пользователя на инфоблок
     * 
     * @param int $iblockId ID инфоблока
     * @param int $userId ID пользователя
     * @return string|null Уровень доступа или null
     */
    public static function getPermission(int $iblockId, int $userId): ?string
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        $connection = Application::getConnection();
        $groupId = -abs($userId);
        
        $result = $connection->query(
            "SELECT PERMISSION FROM b_iblock_group 
             WHERE IBLOCK_ID = {$iblockId} AND GROUP_ID = {$groupId}"
        )->fetch();
        
        return $result ? $result['PERMISSION'] : null;
    }

    /**
     * Получить всех пользователей с правами на инфоблок
     * 
     * @param int $iblockId ID инфоблока
     * @return array [['userId' => int, 'permission' => string, 'userName' => string], ...]
     */
    public static function getUsersWithPermissions(int $iblockId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $connection = Application::getConnection();
        
        // Получаем только пользователей (GROUP_ID < 0)
        $result = $connection->query(
            "SELECT GROUP_ID, PERMISSION FROM b_iblock_group 
             WHERE IBLOCK_ID = {$iblockId} AND GROUP_ID < 0
             ORDER BY GROUP_ID"
        );
        
        $users = [];
        while ($row = $result->fetch()) {
            $userId = abs((int)$row['GROUP_ID']);
            $userName = self::getUserName($userId);
            
            $users[] = [
                'userId' => $userId,
                'permission' => $row['PERMISSION'],
                'userName' => $userName,
            ];
        }
        
        return $users;
    }

    /**
     * Получить имя пользователя
     * 
     * @param int $userId ID пользователя
     * @return string Имя пользователя
     */
    private static function getUserName(int $userId): string
    {
        static $cache = [];
        
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        
        $user = \CUser::GetByID($userId)->Fetch();
        if ($user) {
            $name = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
            $cache[$userId] = $name ?: $user['LOGIN'];
        } else {
            $cache[$userId] = "Пользователь #{$userId}";
        }
        
        return $cache[$userId];
    }

    /**
     * Массовое применение прав
     * 
     * @param array $iblockIds Массив ID инфоблоков
     * @param int $userId ID пользователя
     * @param string $permission Уровень доступа
     * @return array ['success' => int, 'errors' => array]
     */
    public static function bulkSetPermission(array $iblockIds, int $userId, string $permission): array
    {
        $result = ['success' => 0, 'errors' => []];

        foreach ($iblockIds as $iblockId) {
            try {
                if (self::setPermission((int)$iblockId, $userId, $permission)) {
                    $result['success']++;
                } else {
                    $result['errors'][] = [
                        'iblockId' => $iblockId,
                        'message' => 'Не удалось установить права',
                    ];
                }
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'iblockId' => $iblockId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Удалить все права пользователя со всех инфоблоков
     * 
     * @param int $userId ID пользователя
     * @return bool
     */
    public static function removeAllPermissions(int $userId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $connection = Application::getConnection();
        $groupId = -abs($userId);
        
        $connection->query(
            "DELETE FROM b_iblock_group WHERE GROUP_ID = {$groupId}"
        );
        
        return true;
    }

    /**
     * Получить все инфоблоки, на которые у пользователя есть права
     * 
     * @param int $userId ID пользователя
     * @return array [['iblockId' => int, 'permission' => string, 'iblockName' => string], ...]
     */
    public static function getIblocksWithPermissions(int $userId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $connection = Application::getConnection();
        $groupId = -abs($userId);
        
        $result = $connection->query(
            "SELECT ig.IBLOCK_ID, ig.PERMISSION, i.NAME
             FROM b_iblock_group ig
             INNER JOIN b_iblock i ON ig.IBLOCK_ID = i.ID
             WHERE ig.GROUP_ID = {$groupId}
             ORDER BY i.NAME"
        );
        
        $iblocks = [];
        while ($row = $result->fetch()) {
            $iblocks[] = [
                'iblockId' => (int)$row['IBLOCK_ID'],
                'permission' => $row['PERMISSION'],
                'iblockName' => $row['NAME'],
            ];
        }
        
        return $iblocks;
    }
}
