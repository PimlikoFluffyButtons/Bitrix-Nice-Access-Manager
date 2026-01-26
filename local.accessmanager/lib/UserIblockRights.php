<?php
namespace Local\AccessManager;

use Bitrix\Main\Loader;
use CIBlockRights;
use CIBlock;

/**
 * Класс для работы с правами пользователей на инфоблоки
 * Использует расширенный режим прав через CIBlockRights
 */
class UserIblockRights
{
    /**
     * Маппинг символьных кодов прав на ID задач
     * Задачи (tasks) - это стандартные уровни доступа в Bitrix
     */
    const TASK_MAP = [
        'D' => 7,  // Запрет доступа
        'R' => 2,  // Чтение
        'U' => 3,  // Редактирование через документооборот (workflow)
        'S' => 5,  // Редактирование элементов в своих разделах
        'W' => 4,  // Редактирование всех элементов
        'X' => 1,  // Полный доступ (включая управление правами)
    ];

    /**
     * Обратный маппинг: ID задачи -> символьный код
     */
    const TASK_CODE_MAP = [
        7 => 'D',
        2 => 'R',
        3 => 'U',
        5 => 'S',
        4 => 'W',
        1 => 'X',
    ];

    /**
     * Проверить, включен ли расширенный режим прав для инфоблока
     *
     * @param int $iblockId
     * @return bool
     */
    public static function isExtendedModeEnabled(int $iblockId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $iblock = CIBlock::GetArrayByID($iblockId);
        return $iblock && $iblock['RIGHTS_MODE'] === 'E';
    }

    /**
     * Включить расширенный режим прав для инфоблока
     *
     * @param int $iblockId
     * @return bool
     */
    public static function enableExtendedMode(int $iblockId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $iblock = new CIBlock();
        $result = $iblock->Update($iblockId, ['RIGHTS_MODE' => 'E']);

        return (bool)$result;
    }

    /**
     * Отключить расширенный режим прав для инфоблока (вернуться к стандартному)
     * ВНИМАНИЕ: Все индивидуальные права пользователей будут удалены!
     * Права групп сохраняются и переносятся в стандартный режим.
     *
     * @param int $iblockId
     * @return bool
     */
    public static function disableExtendedMode(int $iblockId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        try {
            // Сохраняем права групп перед отключением расширенного режима
            $groupPermissions = [];
            $obRights = new CIBlockRights($iblockId);
            $arRights = $obRights->GetRights();

            if (is_array($arRights)) {
                foreach ($arRights as $rightId => $right) {
                    // Отбираем только права групп (GROUP_CODE начинается с 'G')
                    if (isset($right['GROUP_CODE']) && strpos($right['GROUP_CODE'], 'G') === 0) {
                        $groupId = (int)substr($right['GROUP_CODE'], 1);
                        $taskId = (int)($right['TASK_ID'] ?? 0);
                        $permission = self::TASK_CODE_MAP[$taskId] ?? 'R';
                        $groupPermissions[$groupId] = $permission;
                    }
                }
            }

            // Переключаем режим обратно на стандартный
            $iblock = new CIBlock();
            $result = $iblock->Update($iblockId, ['RIGHTS_MODE' => 'S']);

            if (!$result) {
                return false;
            }

            // Восстанавливаем права групп через стандартный API
            // Если не было сохраненных прав - устанавливаем дефолтные
            if (empty($groupPermissions)) {
                $groupPermissions = [
                    1 => 'X', // Администраторы - полный доступ
                    2 => 'R', // Все пользователи - чтение
                ];
            }

            $iblock->SetPermission($iblockId, $groupPermissions);

            return true;
        } catch (\Exception $e) {
            error_log('UserIblockRights::disableExtendedMode error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить права пользователя на инфоблок
     *
     * @param int $iblockId
     * @param int $userId
     * @return array|null Массив вида ['RIGHT_ID' => ..., 'TASK_ID' => ..., 'PERMISSION' => 'X'] или null
     */
    public static function getUserPermission(int $iblockId, int $userId): ?array
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return null;
        }

        $groupCode = 'U' . $userId;

        $obRights = new CIBlockRights($iblockId);
        $arRights = $obRights->GetRights(); // GetRights() возвращает массив, а не объект

        if (is_array($arRights)) {
            foreach ($arRights as $rightId => $right) {
                if (isset($right['GROUP_CODE']) && $right['GROUP_CODE'] === $groupCode) {
                    $taskId = (int)($right['TASK_ID'] ?? 0);
                    return [
                        'RIGHT_ID' => is_numeric($rightId) ? (int)$rightId : (isset($right['ID']) ? (int)$right['ID'] : 0),
                        'TASK_ID' => $taskId,
                        'PERMISSION' => self::TASK_CODE_MAP[$taskId] ?? 'R',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Получить все права пользователей на инфоблок (для инспектора)
     *
     * @param int $iblockId
     * @return array Массив прав пользователей
     */
    public static function getAllUserPermissions(int $iblockId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return [];
        }

        $result = [];

        $obRights = new CIBlockRights($iblockId);
        $arRights = $obRights->GetRights(); // GetRights() возвращает массив, а не объект

        if (is_array($arRights)) {
            foreach ($arRights as $rightId => $right) {
                // Отбираем только права пользователей (GROUP_CODE начинается с 'U')
                if (isset($right['GROUP_CODE']) && strpos($right['GROUP_CODE'], 'U') === 0) {
                    $userId = (int)substr($right['GROUP_CODE'], 1);
                    if ($userId > 0) {
                        $taskId = (int)($right['TASK_ID'] ?? 0);
                        $permission = self::TASK_CODE_MAP[$taskId] ?? 'R';

                        // НОВЫЙ ФОРМАТ: 'U_<id>' => 'permission'
                        $key = 'U_' . $userId;
                        $result[$key] = $permission;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Получить все права групп на инфоблок в расширенном режиме
     *
     * @param int $iblockId
     * @return array Массив прав групп
     */
    public static function getAllGroupPermissions(int $iblockId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return [];
        }

        $result = [];

        $obRights = new CIBlockRights($iblockId);
        $arRights = $obRights->GetRights(); // GetRights() возвращает массив, а не объект

        if (is_array($arRights)) {
            foreach ($arRights as $rightId => $right) {
                // Отбираем только права групп (GROUP_CODE начинается с 'G')
                if (isset($right['GROUP_CODE']) && strpos($right['GROUP_CODE'], 'G') === 0) {
                    $groupId = (int)substr($right['GROUP_CODE'], 1);
                    if ($groupId > 0) {
                        $taskId = (int)($right['TASK_ID'] ?? 0);
                        $permission = self::TASK_CODE_MAP[$taskId] ?? 'R';

                        // НОВЫЙ ФОРМАТ: 'G_<id>' => 'permission'
                        $key = 'G_' . $groupId;
                        $result[$key] = $permission;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Установить права группы на инфоблок в расширенном режиме
     *
     * @param int $iblockId
     * @param int $groupId
     * @param string $permission Символьный код: D, R, U, S, W, X
     * @return bool
     */
    public static function setGroupPermission(int $iblockId, int $groupId, string $permission): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        // Включаем расширенный режим, если не включен
        if (!self::isExtendedModeEnabled($iblockId)) {
            if (!self::enableExtendedMode($iblockId)) {
                return false;
            }
        }

        // Проверяем валидность уровня прав
        if (!isset(self::TASK_MAP[$permission])) {
            return false;
        }

        $taskId = self::TASK_MAP[$permission];
        $groupCode = 'G' . $groupId;

        // Проверяем, есть ли уже права для этой группы
        $existing = self::getGroupPermission($iblockId, $groupId);

        try {
            if ($existing) {
                // Обновляем существующее право
                $result = CIBlockRights::Update($existing['RIGHT_ID'], ['TASK_ID' => $taskId]);
            } else {
                // Добавляем новое право
                $arFields = [
                    'IBLOCK_ID' => $iblockId,
                    'GROUP_CODE' => $groupCode,
                    'TASK_ID' => $taskId,
                ];
                $result = CIBlockRights::Add($arFields);
            }

            return (bool)$result;
        } catch (\Exception $e) {
            error_log('UserIblockRights::setGroupPermission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить права группы на инфоблок в расширенном режиме
     *
     * @param int $iblockId
     * @param int $groupId
     * @return array|null
     */
    public static function getGroupPermission(int $iblockId, int $groupId): ?array
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return null;
        }

        $groupCode = 'G' . $groupId;

        $obRights = new CIBlockRights($iblockId);
        $arRights = $obRights->GetRights();

        if (is_array($arRights)) {
            foreach ($arRights as $rightId => $right) {
                if (isset($right['GROUP_CODE']) && $right['GROUP_CODE'] === $groupCode) {
                    $taskId = (int)($right['TASK_ID'] ?? 0);
                    return [
                        'RIGHT_ID' => is_numeric($rightId) ? (int)$rightId : (isset($right['ID']) ? (int)$right['ID'] : 0),
                        'TASK_ID' => $taskId,
                        'PERMISSION' => self::TASK_CODE_MAP[$taskId] ?? 'R',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Удалить права группы на инфоблок в расширенном режиме
     *
     * @param int $iblockId
     * @param int $groupId
     * @return bool
     */
    public static function removeGroupPermission(int $iblockId, int $groupId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return false;
        }

        $existing = self::getGroupPermission($iblockId, $groupId);

        if ($existing) {
            return (bool)CIBlockRights::Delete($existing['RIGHT_ID']);
        }

        return true; // Прав нет - считаем успехом
    }

    /**
     * Установить права пользователя на инфоблок
     *
     * @param int $iblockId
     * @param int $userId
     * @param string $permission Символьный код: D, R, U, S, W, X
     * @return bool
     */
    public static function setUserPermission(int $iblockId, int $userId, string $permission): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        // Включаем расширенный режим, если не включен
        if (!self::isExtendedModeEnabled($iblockId)) {
            if (!self::enableExtendedMode($iblockId)) {
                return false;
            }
        }

        // Проверяем валидность уровня прав
        if (!isset(self::TASK_MAP[$permission])) {
            return false;
        }

        $taskId = self::TASK_MAP[$permission];
        $groupCode = 'U' . $userId;

        // Проверяем, есть ли уже права для этого пользователя
        $existing = self::getUserPermission($iblockId, $userId);

        try {
            if ($existing) {
                // Обновляем существующее право
                $result = CIBlockRights::Update($existing['RIGHT_ID'], ['TASK_ID' => $taskId]);
            } else {
                // Добавляем новое право
                $arFields = [
                    'IBLOCK_ID' => $iblockId,
                    'GROUP_CODE' => $groupCode,
                    'TASK_ID' => $taskId,
                ];
                $result = CIBlockRights::Add($arFields);
            }

            return (bool)$result;
        } catch (\Exception $e) {
            error_log('UserIblockRights::setUserPermission error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить права пользователя на инфоблок
     *
     * @param int $iblockId
     * @param int $userId
     * @return bool
     */
    public static function removeUserPermission(int $iblockId, int $userId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        if (!self::isExtendedModeEnabled($iblockId)) {
            return false; // Нет расширенного режима - нечего удалять
        }

        $existing = self::getUserPermission($iblockId, $userId);

        if ($existing) {
            return (bool)CIBlockRights::Delete($existing['RIGHT_ID']);
        }

        return true; // Прав нет - считаем успехом
    }

    /**
     * Массовое применение прав пользователю на несколько инфоблоков
     *
     * @param array $iblockIds Массив ID инфоблоков
     * @param int $userId ID пользователя
     * @param string $permission Символьный код прав
     * @return array ['success' => int, 'errors' => array]
     */
    public static function bulkSetUserPermission(array $iblockIds, int $userId, string $permission): array
    {
        $result = ['success' => 0, 'errors' => []];

        foreach ($iblockIds as $iblockId) {
            try {
                if (self::setUserPermission((int)$iblockId, $userId, $permission)) {
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
     * Массовое удаление прав пользователя на несколько инфоблоков
     *
     * @param array $iblockIds
     * @param int $userId
     * @return array ['success' => int, 'errors' => array]
     */
    public static function bulkRemoveUserPermission(array $iblockIds, int $userId): array
    {
        $result = ['success' => 0, 'errors' => []];

        foreach ($iblockIds as $iblockId) {
            try {
                if (self::removeUserPermission((int)$iblockId, $userId)) {
                    $result['success']++;
                } else {
                    $result['errors'][] = [
                        'iblockId' => $iblockId,
                        'message' => 'Не удалось удалить права',
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
     * Получить имя пользователя
     *
     * @param int $userId
     * @return string
     */
    private static function getUserName(int $userId): string
    {
        static $users = null;

        if ($users === null) {
            $users = [];
        }

        if (!isset($users[$userId])) {
            $res = \CUser::GetByID($userId);
            if ($user = $res->Fetch()) {
                $userName = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
                $users[$userId] = $userName ?: $user['LOGIN'];
            } else {
                $users[$userId] = "Пользователь #{$userId}";
            }
        }

        return $users[$userId];
    }

    /**
     * Получить название группы
     *
     * @param int $groupId
     * @return string
     */
    private static function getGroupName(int $groupId): string
    {
        static $groups = null;

        if ($groups === null) {
            $groups = [];
            $res = \CGroup::GetList('c_sort', 'asc', ['ACTIVE' => 'Y']);
            while ($group = $res->Fetch()) {
                $groups[(int)$group['ID']] = $group['NAME'];
            }
        }

        return $groups[$groupId] ?? "Группа #{$groupId}";
    }

    /**
     * Проверить права пользователя на инфоблок (вспомогательный метод)
     *
     * @param int $iblockId
     * @param int $userId
     * @return string|null Символьный код прав или null
     */
    public static function checkUserAccess(int $iblockId, int $userId): ?string
    {
        $permission = self::getUserPermission($iblockId, $userId);
        return $permission ? $permission['PERMISSION'] : null;
    }

    /**
     * Получить список инфоблоков с расширенным режимом прав
     *
     * @return array Массив ID инфоблоков
     */
    public static function getIblocksWithExtendedMode(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        global $DB;
        $result = [];

        $sql = "SELECT ID FROM b_iblock WHERE RIGHTS_MODE = 'E' AND ACTIVE = 'Y'";
        $res = $DB->Query($sql);

        while ($row = $res->Fetch()) {
            $result[] = (int)$row['ID'];
        }

        return $result;
    }
}
