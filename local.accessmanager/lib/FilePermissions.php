<?php
namespace Local\AccessManager;

use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;

/**
 * Класс для работы с правами файлов и папок
 */
class FilePermissions
{
    /**
     * Уровни прав файловой системы Битрикс
     */
    const PERMISSIONS = [
        'D' => 'Доступ закрыт',
        'R' => 'Чтение',
        'W' => 'Запись (создание файлов)',
        'X' => 'Полный доступ',
    ];

    /**
     * Максимальная глубина сканирования
     */
    const MAX_DEPTH = 3;

    /**
     * Игнорируемые директории
     */
    const IGNORED_DIRS = [
        'bitrix',
        'upload',
        'local/modules',
        'local/php_interface',
    ];

    /**
     * Получить дерево файлов/папок
     * 
     * @param string $path Относительный путь от DocumentRoot
     * @param int $depth Текущая глубина
     * @return array
     */
    public static function getTree(string $path = '/', int $depth = 0): array
    {
        $documentRoot = Application::getDocumentRoot();
        $fullPath = self::normalizePath($documentRoot . $path);

        // Проверка безопасности
        if (!self::isPathSafe($fullPath, $documentRoot)) {
            return [];
        }

        if (!is_dir($fullPath)) {
            return [];
        }

        $tree = [];
        $items = scandir($fullPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Пропускаем скрытые файлы
            if (strpos($item, '.') === 0) {
                continue;
            }

            $itemPath = $path . ($path === '/' ? '' : '/') . $item;
            $itemFullPath = $documentRoot . $itemPath;

            // Пропускаем игнорируемые директории
            $relativePath = ltrim($itemPath, '/');
            $shouldIgnore = false;
            foreach (self::IGNORED_DIRS as $ignored) {
                if (strpos($relativePath, $ignored) === 0) {
                    $shouldIgnore = true;
                    break;
                }
            }
            if ($shouldIgnore) {
                continue;
            }

            $isDir = is_dir($itemFullPath);
            $hasCustomPermissions = self::hasCustomPermissions($itemPath);

            $node = [
                'id' => 'path_' . base64_encode($itemPath),
                'type' => $isDir ? 'folder' : 'file',
                'path' => $itemPath,
                'name' => $item,
                'hasCustomPermissions' => $hasCustomPermissions,
            ];

            // Для папок добавляем признак наличия детей
            if ($isDir && $depth < self::MAX_DEPTH) {
                $node['hasChildren'] = self::hasChildren($itemFullPath);
                $node['children'] = []; // Lazy load
            }

            $tree[] = $node;
        }

        // Сортировка: сначала папки, потом файлы
        usort($tree, function($a, $b) {
            if ($a['type'] === $b['type']) {
                return strcasecmp($a['name'], $b['name']);
            }
            return $a['type'] === 'folder' ? -1 : 1;
        });

        return $tree;
    }

    /**
     * Получить детей директории (lazy load)
     * 
     * @param string $path
     * @return array
     */
    public static function getChildren(string $path): array
    {
        return self::getTree($path, 1);
    }

    /**
     * Проверить наличие кастомных прав
     * 
     * @param string $path
     * @return bool
     */
    public static function hasCustomPermissions(string $path): bool
    {
        global $APPLICATION;
        
        $permissions = $APPLICATION->GetFileAccessPermission($path);
        
        if (empty($permissions) || !is_array($permissions)) {
            return false;
        }

        // Проверяем, отличаются ли права от дефолтных
        $defaultPerms = ['1' => 'X', '2' => 'R'];
        
        foreach ($permissions as $groupId => $perm) {
            if (!isset($defaultPerms[$groupId]) || $defaultPerms[$groupId] !== $perm) {
                return true;
            }
        }

        return count($permissions) !== count($defaultPerms);
    }

    /**
     * Получить текущие права файла/папки
     * 
     * @param string $path
     * @return array
     */
    public static function getPermissions(string $path): array
    {
        global $APPLICATION;

        $permissions = $APPLICATION->GetFileAccessPermission($path);
        $result = [];

        if (is_array($permissions)) {
            foreach ($permissions as $groupId => $permission) {
                $groupName = self::getGroupName((int)$groupId);
                $result[] = [
                    'subjectType' => 'group',
                    'subjectId' => (int)$groupId,
                    'subjectName' => $groupName,
                    'permission' => $permission,
                    'permissionName' => self::PERMISSIONS[$permission] ?? $permission,
                    'source' => 'explicit',
                ];
            }
        }

        // Проверяем наследуемые права
        $parentPath = dirname($path);
        if ($parentPath !== $path && $parentPath !== '.') {
            $parentPerms = $APPLICATION->GetFileAccessPermission($parentPath);
            if (is_array($parentPerms)) {
                foreach ($parentPerms as $groupId => $permission) {
                    $exists = false;
                    foreach ($result as $item) {
                        if ($item['subjectId'] === (int)$groupId) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $result[] = [
                            'subjectType' => 'group',
                            'subjectId' => (int)$groupId,
                            'subjectName' => self::getGroupName((int)$groupId),
                            'permission' => $permission,
                            'permissionName' => self::PERMISSIONS[$permission] ?? $permission,
                            'source' => 'inherited',
                            'inheritedFrom' => $parentPath,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Установить права для группы на файл/папку
     * 
     * @param string $path
     * @param int $groupId
     * @param string $permission
     * @return bool
     */
    public static function setGroupPermission(string $path, int $groupId, string $permission): bool
    {
        global $APPLICATION;

        $documentRoot = Application::getDocumentRoot();
        $fullPath = $documentRoot . $path;

        // Проверка безопасности
        if (!self::isPathSafe($fullPath, $documentRoot)) {
            return false;
        }

        // Получаем текущие права
        $currentPermissions = $APPLICATION->GetFileAccessPermission($path);
        if (!is_array($currentPermissions)) {
            $currentPermissions = [];
        }

        // Устанавливаем новые права
        $currentPermissions[$groupId] = $permission;

        $APPLICATION->SetFileAccessPermission($path, $currentPermissions);

        return true;
    }

    /**
     * Удалить группу из прав файла/папки
     * 
     * @param string $path
     * @param int $groupId
     * @return bool
     */
    public static function removeGroupPermission(string $path, int $groupId): bool
    {
        global $APPLICATION;

        $currentPermissions = $APPLICATION->GetFileAccessPermission($path);
        
        if (is_array($currentPermissions) && isset($currentPermissions[$groupId])) {
            unset($currentPermissions[$groupId]);
            $APPLICATION->SetFileAccessPermission($path, $currentPermissions);
        }

        return true;
    }

    /**
     * Сбросить права к дефолтным
     * Дефолт: Все пользователи [2] = R, Администраторы [1] = X
     * 
     * @param string $path
     * @return bool
     */
    public static function resetToDefault(string $path): bool
    {
        global $APPLICATION;

        $defaultPermissions = [
            1 => 'X', // Администраторы - полный доступ
            2 => 'R', // Все пользователи - чтение
        ];

        $APPLICATION->SetFileAccessPermission($path, $defaultPermissions);

        return true;
    }

    /**
     * Массовое применение прав
     * 
     * @param array $paths
     * @param int $groupId
     * @param string $permission
     * @return array
     */
    public static function bulkSetPermission(array $paths, int $groupId, string $permission): array
    {
        $result = ['success' => 0, 'errors' => []];

        foreach ($paths as $path) {
            try {
                if (self::setGroupPermission($path, $groupId, $permission)) {
                    $result['success']++;
                } else {
                    $result['errors'][] = [
                        'path' => $path,
                        'message' => 'Не удалось установить права',
                    ];
                }
            } catch (\Exception $e) {
                $result['errors'][] = [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Нормализация пути
     * 
     * @param string $path
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        // Убираем множественные слэши
        $path = preg_replace('#/+#', '/', $path);
        
        // Разбираем путь
        $parts = explode('/', $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                array_pop($result);
            } elseif ($part !== '' && $part !== '.') {
                $result[] = $part;
            }
        }

        return '/' . implode('/', $result);
    }

    /**
     * Проверка безопасности пути
     * 
     * @param string $path
     * @param string $documentRoot
     * @return bool
     */
    private static function isPathSafe(string $path, string $documentRoot): bool
    {
        $realPath = realpath($path);
        $realDocRoot = realpath($documentRoot);

        if ($realPath === false) {
            // Путь не существует - проверяем родителя
            $parentPath = dirname($path);
            $realParent = realpath($parentPath);
            if ($realParent === false) {
                return false;
            }
            return strpos($realParent, $realDocRoot) === 0;
        }

        return strpos($realPath, $realDocRoot) === 0;
    }

    /**
     * Проверка наличия дочерних элементов
     * 
     * @param string $path
     * @return bool
     */
    private static function hasChildren(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && strpos($item, '.') !== 0) {
                return true;
            }
        }

        return false;
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
}
