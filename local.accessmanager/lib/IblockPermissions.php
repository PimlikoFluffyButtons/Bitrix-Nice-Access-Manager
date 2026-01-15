<?php
namespace Local\AccessManager;

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;

/**
 * Класс для работы с правами инфоблоков
 */
class IblockPermissions
{
    /**
     * Уровни прав инфоблоков
     */
    const PERMISSIONS = [
        'D' => 'Доступ закрыт',
        'R' => 'Чтение',
        'U' => 'Редактирование своих элементов',
        'S' => 'Редактирование элементов в своих разделах',
        'W' => 'Редактирование всех элементов',
        'X' => 'Полный доступ',
    ];

    /**
     * Получить дерево типов инфоблоков и инфоблоков
     * 
     * @param string|null $search Строка поиска
     * @return array
     */
    public static function getTree(?string $search = null): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $tree = [];

        // Получаем типы инфоблоков через старый API
        $types = [];
        $typesRes = \CIBlockType::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
        while ($type = $typesRes->Fetch()) {
            $typeId = $type['ID'];
            // Получаем языковое название
            $langRes = \CIBlockType::GetByIDLang($typeId, LANGUAGE_ID);
            $typeName = $langRes['NAME'] ?: $typeId;
            $types[$typeId] = $typeName;
        }

        // Получаем инфоблоки
        $iblockFilter = ['ACTIVE' => 'Y'];
        if ($search) {
            $iblockFilter['%NAME'] = $search;
        }

        $iblocksRes = IblockTable::getList([
            'select' => ['ID', 'NAME', 'IBLOCK_TYPE_ID', 'CODE'],
            'filter' => $iblockFilter,
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        $iblocksByType = [];
        while ($iblock = $iblocksRes->fetch()) {
            $typeId = $iblock['IBLOCK_TYPE_ID'];
            if (!isset($iblocksByType[$typeId])) {
                $iblocksByType[$typeId] = [];
            }
            $iblocksByType[$typeId][] = [
                'id' => $iblock['ID'],
                'name' => $iblock['NAME'],
                'code' => $iblock['CODE'],
            ];
        }

        // Формируем дерево
        foreach ($types as $typeId => $typeName) {
            if (!isset($iblocksByType[$typeId]) || empty($iblocksByType[$typeId])) {
                continue;
            }

            $typeNode = [
                'id' => 'type_' . $typeId,
                'type' => 'iblock_type',
                'typeId' => $typeId,
                'name' => $typeName,
                'children' => [],
            ];

            foreach ($iblocksByType[$typeId] as $iblock) {
                $typeNode['children'][] = [
                    'id' => 'iblock_' . $iblock['id'],
                    'type' => 'iblock',
                    'iblockId' => (int)$iblock['id'],
                    'name' => $iblock['name'],
                    'code' => $iblock['code'],
                ];
            }

            $tree[] = $typeNode;
        }

        return $tree;
    }

    /**
     * Получить текущие права инфоблока
     * 
     * @param int $iblockId
     * @return array
     */
    public static function getPermissions(int $iblockId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $iblock = new \CIBlock();
        $permissions = $iblock->GetGroupPermissions($iblockId);

        $result = [];
        foreach ($permissions as $groupId => $permission) {
            $groupName = self::getGroupName($groupId);
            $result[] = [
                'subjectType' => 'group',
                'subjectId' => (int)$groupId,
                'subjectName' => $groupName,
                'permission' => $permission,
                'permissionName' => self::PERMISSIONS[$permission] ?? $permission,
                'source' => 'explicit',
            ];
        }

        return $result;
    }

    /**
     * Установить права для группы на инфоблок
     * 
     * @param int $iblockId
     * @param int $groupId
     * @param string $permission
     * @return bool
     */
    public static function setGroupPermission(int $iblockId, int $groupId, string $permission): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $iblock = new \CIBlock();
        $currentPermissions = $iblock->GetGroupPermissions($iblockId);
        $currentPermissions[$groupId] = $permission;

        $iblock->SetPermission($iblockId, $currentPermissions);

        return true;
    }

    /**
     * Удалить группу из прав инфоблока
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

        $iblock = new \CIBlock();
        $currentPermissions = $iblock->GetGroupPermissions($iblockId);
        
        if (isset($currentPermissions[$groupId])) {
            unset($currentPermissions[$groupId]);
            $iblock->SetPermission($iblockId, $currentPermissions);
        }

        return true;
    }

    /**
     * Сбросить права инфоблока к дефолтным
     * Дефолт: Все пользователи [2] = R, Администраторы [1] = X
     * 
     * @param int $iblockId
     * @return bool
     */
    public static function resetToDefault(int $iblockId): bool
    {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $defaultPermissions = [
            1 => 'X', // Администраторы - полный доступ
            2 => 'R', // Все пользователи - чтение
        ];

        $iblock = new \CIBlock();
        $iblock->SetPermission($iblockId, $defaultPermissions);

        return true;
    }

    /**
     * Массовое применение прав
     * 
     * @param array $iblockIds
     * @param int $groupId
     * @param string $permission
     * @return array ['success' => int, 'errors' => array]
     */
    public static function bulkSetPermission(array $iblockIds, int $groupId, string $permission): array
    {
        $result = ['success' => 0, 'errors' => []];

        foreach ($iblockIds as $iblockId) {
            try {
                if (self::setGroupPermission((int)$iblockId, $groupId, $permission)) {
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
     * Получить все инфоблоки типа
     * 
     * @param string $typeId
     * @return array
     */
    public static function getIblocksByType(string $typeId): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $result = [];
        $iblocksRes = IblockTable::getList([
            'select' => ['ID'],
            'filter' => ['IBLOCK_TYPE_ID' => $typeId, 'ACTIVE' => 'Y'],
        ]);

        while ($iblock = $iblocksRes->fetch()) {
            $result[] = (int)$iblock['ID'];
        }

        return $result;
    }
}