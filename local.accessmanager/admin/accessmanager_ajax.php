<?php
/**
 * AJAX обработчики для модуля управления доступом
 */

// ВРЕМЕННО: Включаем вывод ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../accessmanager_debug.log');

use Bitrix\Main\Loader;
use Local\AccessManager\IblockPermissions;
use Local\AccessManager\FilePermissions;
use Local\AccessManager\UserIblockRights;
use Local\AccessManager\Logger;

global $USER, $APPLICATION;

header('Content-Type: application/json; charset=utf-8');

// Проверка админа
if (!$USER->IsAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещён']);
    die();
}

// Проверка сессии
if (!check_bitrix_sessid()) {
    echo json_encode(['success' => false, 'error' => 'Ошибка сессии']);
    die();
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$action = $request->getPost('action');

try {
    switch ($action) {
        case 'get_permissions':
            handleGetPermissions($request);
            break;
            
        case 'get_children':
            handleGetChildren($request);
            break;
            
        case 'preview':
            handlePreview($request);
            break;
            
        case 'apply':
            handleApply($request);
            break;
            
        case 'reset_default':
            handleResetDefault($request);
            break;
            
        case 'remove_subject':
            handleRemoveSubject($request);
            break;
            
        case 'rollback':
            handleRollback($request);
            break;
            
        case 'search_users':
            handleSearchUsers($request);
            break;

        case 'bx_access_get':
            handleBXAccessGet($request);
            break;

        case 'bx_access_set':
            handleBXAccessSet($request);
            break;

        case 'bx_access_sync':
            handleBXAccessSync($request);
            break;

        case 'load_all_users':
            handleLoadAllUsers($request);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . $action]);
            die();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
    die();
}

/**
 * Получить права объекта
 */
function handleGetPermissions($request)
{
    $type = $request->getPost('type');
    $id = $request->getPost('id');
    
    if ($type === 'iblock') {
        Loader::includeModule('iblock');
        $permissions = IblockPermissions::getPermissions((int)$id);
    } elseif (in_array($type, ['folder', 'file'])) {
        $permissions = FilePermissions::getPermissions($id);
    } else {
        echo json_encode(['success' => false, 'error' => 'Неизвестный тип объекта']);
        die();
    }
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
    die();
}

/**
 * Получить дочерние элементы папки
 */
function handleGetChildren($request)
{
    $path = $request->getPost('path');
    
    if (!$path) {
        echo json_encode(['success' => false, 'error' => 'Путь не указан']);
        die();
    }
    
    $children = FilePermissions::getChildren($path);
    echo json_encode(['success' => true, 'children' => $children]);
    die();
}

/**
 * Предпросмотр изменений
 */
function handlePreview($request)
{
    $mode = $request->getPost('mode');
    $selected = json_decode($request->getPost('selected'), true);
    $subject = json_decode($request->getPost('subject'), true);
    $permission = $request->getPost('permission');
    
    if (!$selected || !$subject || !$permission) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }
    
    $preview = [];
    
    if ($mode === 'iblocks') {
        Loader::includeModule('iblock');

        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                $iblock = \CIBlock::GetByID($iblockId)->Fetch();
                $currentPerms = IblockPermissions::getPermissions($iblockId);

                $wasPermission = null;
                foreach ($currentPerms as $perm) {
                    if ($perm['subjectType'] === $subject['type'] && $perm['subjectId'] === $subject['id']) {
                        $wasPermission = $perm['permission'];
                        break;
                    }
                }

                $preview[] = [
                    'objectId' => $iblockId,
                    'objectName' => $iblock['NAME'] ?? "Инфоблок #{$iblockId}",
                    'wasPermission' => $wasPermission,
                    'willBePermission' => $permission,
                ];
            }
        }
    } else {
        // Проверка: работаем только с группами
        if ($subject['type'] !== 'group') {
            echo json_encode([
                'success' => false,
                'error' => 'Назначение прав пользователям для файлов будет доступно в следующей версии'
            ]);
            die();
        }

        foreach ($selected as $item) {
            $path = $item['path'];
            $currentPerms = FilePermissions::getPermissions($path);

            $wasPermission = null;
            foreach ($currentPerms as $perm) {
                if ($perm['subjectType'] === $subject['type'] && $perm['subjectId'] === $subject['id'] && $perm['source'] === 'explicit') {
                    $wasPermission = $perm['permission'];
                    break;
                }
            }

            $preview[] = [
                'objectId' => $path,
                'objectName' => $path,
                'wasPermission' => $wasPermission,
                'willBePermission' => $permission,
            ];
        }
    }
    
    echo json_encode(['success' => true, 'preview' => $preview]);
    die();
}

/**
 * Применить права
 */
function handleApply($request)
{
    $mode = $request->getPost('mode');
    $selected = json_decode($request->getPost('selected'), true);
    $subject = json_decode($request->getPost('subject'), true);
    $permission = $request->getPost('permission');
    
    // ОТЛАДКА - записываем в файл
    file_put_contents(
        $_SERVER['DOCUMENT_ROOT'] . '/local/modules/local.accessmanager/accessmanager_debug.log',
        date('Y-m-d H:i:s') . ' - APPLY REQUEST: ' . json_encode([
            'mode' => $mode,
            'selected' => $selected,
            'subject' => $subject,
            'permission' => $permission,
        ], JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND
    );
    
    if (!$selected || !$subject || !$permission) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }
    
    $snapshotData = [];
    $successCount = 0;
    $errors = [];
    
    if ($mode === 'iblocks') {
        Loader::includeModule('iblock');
        $iblock = new \CIBlock();
        
        // Создаём снапшот ДО изменений
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                $currentPerms = $iblock->GetGroupPermissions($iblockId);
                
                $snapshotData[] = [
                    'id' => $iblockId,
                    'permissions' => $currentPerms,
                ];
            }
        }
        
        if (!empty($snapshotData)) {
            Logger::createSnapshot(Logger::OBJ_IBLOCK, $snapshotData);
        }
        
        // Применяем права
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];

                try {
                    // Проверяем, включен ли расширенный режим прав
                    $isExtendedMode = UserIblockRights::isExtendedModeEnabled($iblockId);

                    if ($subject['type'] === 'group') {
                        $groupId = (int)$subject['id'];

                        if ($isExtendedMode) {
                            // РРУП: используем CIBlockRights
                            $oldPerm = UserIblockRights::getGroupPermission($iblockId, $groupId);

                            if (UserIblockRights::setGroupPermission($iblockId, $groupId, $permission)) {
                                // Логируем
                                Logger::log(
                                    Logger::OP_SET_PERMISSION,
                                    Logger::OBJ_IBLOCK,
                                    (string)$iblockId,
                                    $subject['type'],
                                    $subject['id'],
                                    $oldPerm ? [$groupId => $oldPerm['PERMISSION']] : null,
                                    [$groupId => $permission]
                                );

                                $successCount++;
                            } else {
                                $errors[] = [
                                    'id' => $iblockId,
                                    'message' => 'Не удалось установить права группе в расширенном режиме'
                                ];
                            }
                        } else {
                            // Стандартный режим: используем SetPermission
                            $currentPerms = $iblock->GetGroupPermissions($iblockId);
                            $oldPerm = $currentPerms[$groupId] ?? null;

                            // Устанавливаем новое право (числовой ключ)
                            $currentPerms[$groupId] = $permission;
                            $iblock->SetPermission($iblockId, $currentPerms);

                            // Логируем
                            Logger::log(
                                Logger::OP_SET_PERMISSION,
                                Logger::OBJ_IBLOCK,
                                (string)$iblockId,
                                $subject['type'],
                                $subject['id'],
                                $oldPerm ? [$groupId => $oldPerm] : null,
                                [$groupId => $permission]
                            );

                            $successCount++;
                        }
                    } elseif ($subject['type'] === 'user') {
                        // ПОЛЬЗОВАТЕЛЬ: всегда используем расширенный режим прав
                        $userId = (int)$subject['id'];
                        $oldPerm = UserIblockRights::getUserPermission($iblockId, $userId);

                        if (UserIblockRights::setUserPermission($iblockId, $userId, $permission)) {
                            // Логируем
                            Logger::log(
                                Logger::OP_SET_PERMISSION,
                                Logger::OBJ_IBLOCK,
                                (string)$iblockId,
                                $subject['type'],
                                $subject['id'],
                                $oldPerm ? ['U' . $userId => $oldPerm['PERMISSION']] : null,
                                ['U' . $userId => $permission]
                            );

                            $successCount++;
                        } else {
                            $errors[] = [
                                'id' => $iblockId,
                                'message' => 'Не удалось установить права пользователю. Проверьте доступность расширенного режима.'
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = ['id' => $iblockId, 'message' => $e->getMessage()];
                }
            }
        }
    } else {
        // Файлы/папки
        global $APPLICATION;
        
        foreach ($selected as $item) {
            $path = $item['path'];
            $currentPerms = $APPLICATION->GetFileAccessPermission($path);
            
            $snapshotData[] = [
                'id' => $path,
                'permissions' => $currentPerms ?: [],
            ];
        }
        
        $objType = Logger::OBJ_FOLDER;
        if (!empty($snapshotData)) {
            Logger::createSnapshot($objType, $snapshotData);
        }
        
        foreach ($selected as $item) {
            $path = $item['path'];

            try {
                // Проверка: работаем только с группами
                if ($subject['type'] !== 'group') {
                    $errors[] = [
                        'path' => $path,
                        'message' => 'Назначение прав пользователям для файлов будет доступно в следующей версии'
                    ];
                    continue;
                }

                $currentPerms = $APPLICATION->GetFileAccessPermission($path);
                if (!is_array($currentPerms)) {
                    $currentPerms = [];
                }

                $groupId = (int)$subject['id'];
                $oldPerm = $currentPerms[$groupId] ?? null;

                // Устанавливаем право для группы (числовой ключ)
                $currentPerms[$groupId] = $permission;
                $APPLICATION->SetFileAccessPermission($path, $currentPerms);

                Logger::log(
                    Logger::OP_SET_PERMISSION,
                    $item['type'] === 'folder' ? Logger::OBJ_FOLDER : Logger::OBJ_FILE,
                    $path,
                    $subject['type'],
                    $subject['id'],
                    $oldPerm ? [$groupId => $oldPerm] : null,
                    [$groupId => $permission]
                );

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['path' => $path, 'message' => $e->getMessage()];
            }
        }
    }
    
    // ========================================
    // НОВОЕ: Инвалидировать BX.Access кэш
    // ========================================
    $invalidationSignal = generateBXAccessCacheInvalidation(
        $selected,
        $mode,
        'update'
    );

    echo json_encode([
        'success' => true,
        'successCount' => $successCount,
        'errors' => $errors,
        'bx_access_cache_invalidation' => $invalidationSignal,  // НОВОЕ
    ]);
    die();
}


/**
 * Сброс к дефолтным правам
 */
function handleResetDefault($request)
{
    $mode = $request->getPost('mode');
    $selected = json_decode($request->getPost('selected'), true);
    
    if (!$selected) {
        echo json_encode(['success' => false, 'error' => 'Не выбраны объекты']);
        die();
    }
    
    $snapshotData = [];
    $successCount = 0;
    $errors = [];
    
    if ($mode === 'iblocks') {
        Loader::includeModule('iblock');
        $iblock = new \CIBlock();
        
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                $currentPerms = $iblock->GetGroupPermissions($iblockId);
                $snapshotData[] = ['id' => $iblockId, 'permissions' => $currentPerms];
            }
        }
        
        if (!empty($snapshotData)) {
            Logger::createSnapshot(Logger::OBJ_IBLOCK, $snapshotData);
        }
        
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                try {
                    IblockPermissions::resetToDefault($iblockId);
                    Logger::log(Logger::OP_RESET_DEFAULT, Logger::OBJ_IBLOCK, (string)$iblockId, 'system', 0);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = ['id' => $iblockId, 'message' => $e->getMessage()];
                }
            }
        }
    } else {
        global $APPLICATION;
        
        foreach ($selected as $item) {
            $path = $item['path'];
            $currentPerms = $APPLICATION->GetFileAccessPermission($path);
            $snapshotData[] = ['id' => $path, 'permissions' => $currentPerms ?: []];
        }
        
        if (!empty($snapshotData)) {
            Logger::createSnapshot(Logger::OBJ_FOLDER, $snapshotData);
        }
        
        foreach ($selected as $item) {
            $path = $item['path'];
            try {
                FilePermissions::resetToDefault($path);
                Logger::log(Logger::OP_RESET_DEFAULT, $item['type'] === 'folder' ? Logger::OBJ_FOLDER : Logger::OBJ_FILE, $path, 'system', 0);
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['path' => $path, 'message' => $e->getMessage()];
            }
        }
    }
    
    echo json_encode(['success' => true, 'successCount' => $successCount, 'errors' => $errors]);
    die();
}

/**
 * Удалить субъекта из прав
 */
function handleRemoveSubject($request)
{
    $mode = $request->getPost('mode');
    $selected = json_decode($request->getPost('selected'), true);
    $subject = json_decode($request->getPost('subject'), true);
    
    if (!$selected || !$subject) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }
    
    $successCount = 0;
    $errors = [];
    
    if ($mode === 'iblocks') {
        Loader::includeModule('iblock');
        $iblock = new \CIBlock();

        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                try {
                    // Проверяем, включен ли расширенный режим прав
                    $isExtendedMode = UserIblockRights::isExtendedModeEnabled($iblockId);

                    if ($subject['type'] === 'group') {
                        $groupId = (int)$subject['id'];

                        if ($isExtendedMode) {
                            // РРУП: используем CIBlockRights
                            if (UserIblockRights::removeGroupPermission($iblockId, $groupId)) {
                                Logger::log(
                                    Logger::OP_REMOVE_PERMISSION,
                                    Logger::OBJ_IBLOCK,
                                    (string)$iblockId,
                                    $subject['type'],
                                    $subject['id']
                                );

                                $successCount++;
                            } else {
                                $errors[] = [
                                    'id' => $iblockId,
                                    'message' => 'Не удалось удалить права группы в расширенном режиме'
                                ];
                            }
                        } else {
                            // Стандартный режим: используем SetPermission
                            $currentPerms = $iblock->GetGroupPermissions($iblockId);

                            if (isset($currentPerms[$groupId])) {
                                unset($currentPerms[$groupId]);
                                $iblock->SetPermission($iblockId, $currentPerms);

                                Logger::log(
                                    Logger::OP_REMOVE_PERMISSION,
                                    Logger::OBJ_IBLOCK,
                                    (string)$iblockId,
                                    $subject['type'],
                                    $subject['id']
                                );

                                $successCount++;
                            }
                        }
                    } elseif ($subject['type'] === 'user') {
                        // ПОЛЬЗОВАТЕЛЬ: используем расширенный режим прав
                        $userId = (int)$subject['id'];

                        if (UserIblockRights::removeUserPermission($iblockId, $userId)) {
                            Logger::log(
                                Logger::OP_REMOVE_PERMISSION,
                                Logger::OBJ_IBLOCK,
                                (string)$iblockId,
                                $subject['type'],
                                $subject['id']
                            );

                            $successCount++;
                        } else {
                            $errors[] = [
                                'id' => $iblockId,
                                'message' => 'Не удалось удалить права пользователя'
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = ['id' => $iblockId, 'message' => $e->getMessage()];
                }
            }
        }
    } elseif ($mode === 'files') {
        global $APPLICATION;
        
        foreach ($selected as $item) {
            $path = $item['path'];
            try {
                // Проверка: работаем только с группами
                if ($subject['type'] !== 'group') {
                    $errors[] = [
                        'path' => $path,
                        'message' => 'Удаление прав пользователей для файлов будет доступно в следующей версии'
                    ];
                    continue;
                }

                $currentPerms = $APPLICATION->GetFileAccessPermission($path);
                $groupId = (int)$subject['id'];

                if (is_array($currentPerms) && isset($currentPerms[$groupId])) {
                    unset($currentPerms[$groupId]);
                    $APPLICATION->SetFileAccessPermission($path, $currentPerms);

                    Logger::log(
                        Logger::OP_REMOVE_PERMISSION,
                        $item['type'] === 'folder' ? Logger::OBJ_FOLDER : Logger::OBJ_FILE,
                        $path,
                        $subject['type'],
                        $subject['id']
                    );

                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['path' => $path, 'message' => $e->getMessage()];
            }
        }
    }
    
    echo json_encode(['success' => true, 'successCount' => $successCount, 'errors' => $errors]);
    die();
}

/**
 * Откат снапшота
 */
function handleRollback($request)
{
    $batchId = $request->getPost('batch_id');
    
    if (!$batchId) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID пакета']);
        die();
    }
    
    $result = Logger::rollbackSnapshot($batchId);
    
    echo json_encode([
        'success' => true,
        'successCount' => $result['success'],
        'errors' => $result['errors'],
    ]);
    die();
}

/**
 * Поиск пользователей
 */
function handleSearchUsers($request)
{
    $query = $request->getPost('query');
    
    if (!$query || strlen($query) < 2) {
        echo json_encode(['success' => false, 'error' => 'Короткий запрос']);
        die();
    }
    
    $users = [];
    $res = \CUser::GetList(
        'last_name',
        'asc',
        [
            'ACTIVE' => 'Y',
            'NAME' => '%' . $query . '%',
        ],
        ['FIELDS' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME']]
    );
    
    while ($user = $res->Fetch()) {
        $users[] = [
            'ID' => $user['ID'],
            'LOGIN' => $user['LOGIN'],
            'NAME' => trim($user['NAME'] . ' ' . $user['LAST_NAME']),
        ];
        
        if (count($users) >= 20) break;
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
    die();
}

/**
 * Валидировать выбор против BX.Access кэша
 *
 * @param array $selected Выбранные объекты
 * @param array $subject Субъект доступа
 * @param string $mode 'iblocks' или 'files'
 * @return array ['valid' => bool, 'error' => string]
 */
function validateBXAccessSelection($selected, $subject, $mode)
{
    // Получить список валидных объектов из системы
    if ($mode === 'iblocks') {
        Loader::includeModule('iblock');
        $selectedIds = array_column($selected, 'id');

        // Проверить, все ли выбранные объекты существуют
        $iblockRes = \Bitrix\Iblock\IblockTable::getList([
            'select' => ['ID'],
            'filter' => ['ID' => $selectedIds],
        ]);

        $validIds = [];
        while ($iblock = $iblockRes->fetch()) {
            $validIds[] = $iblock['ID'];
        }

        if (count($validIds) !== count($selectedIds)) {
            return [
                'valid' => false,
                'error' => 'Some selected infoblocks no longer exist'
            ];
        }
    } else {
        // Для файлов проверить пути
        foreach ($selected as $item) {
            $path = $item['path'];
            if (!FilePermissions::isPathSafe($path)) {
                return [
                    'valid' => false,
                    'error' => 'Invalid file path: ' . $path
                ];
            }
        }
    }

    return ['valid' => true];
}

/**
 * Сгенерировать сигнал инвалидации кэша BX.Access
 */
function generateBXAccessCacheInvalidation($selected, $mode, $action)
{
    return [
        'action' => $action,      // 'update', 'delete', 'reset'
        'mode' => $mode,          // 'iblocks', 'files'
        'itemCount' => count($selected),
        'itemIds' => array_column($selected, 'id'),
        'invalidateAll' => false, // true = очистить весь кэш
        'timestamp' => time()
    ];
}

/**
 * Получить BX.Access права объекта
 */
function handleBXAccessGet($request)
{
    $objectId = $request->getPost('objectId');
    $objectType = $request->getPost('objectType');

    if (!$objectId || !$objectType) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }

    // Заглушка: в реальной реализации здесь будет вызов BX.Access API
    // Пока возвращаем mock данные для тестирования UI
    $accessLevel = null;

    // Здесь можно добавить логику получения из IndexedDB кэша или серверного хранилища
    // if (function_exists('\\BX\\Access::get')) {
    //     $accessLevel = \BX\Access::get($objectId);
    // }

    echo json_encode([
        'success' => true,
        'data' => [
            'level' => $accessLevel,
            'cached' => false,
            'source' => 'server'
        ]
    ]);
    die();
}

/**
 * Установить BX.Access права
 */
function handleBXAccessSet($request)
{
    $objectId = $request->getPost('objectId');
    $objectType = $request->getPost('objectType');
    $level = $request->getPost('level');
    $syncToOurSystem = $request->getPost('syncToOurSystem') === 'true';

    if (!$objectId || !$objectType || !$level) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }

    // Валидация уровня прав
    $validLevels = ['READ', 'WRITE', 'FULL'];
    if (!in_array($level, $validLevels)) {
        echo json_encode(['success' => false, 'error' => 'Неверный уровень прав']);
        die();
    }

    try {
        // Заглушка: в реальной реализации здесь будет вызов BX.Access API
        // if (function_exists('\\BX\\Access::set')) {
        //     \BX\Access::set($objectId, $level);
        // }

        // Если требуется синхронизация с нашей системой
        if ($syncToOurSystem) {
            // Здесь можно добавить логику синхронизации с IblockPermissions или FilePermissions
            // Например, конвертировать BX.Access уровни в наши уровни прав (D, R, W, X)
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'objectId' => $objectId,
                'level' => $level,
                'synced' => $syncToOurSystem
            ]
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    die();
}

/**
 * Синхронизировать BX.Access кэш
 */
function handleBXAccessSync($request)
{
    $mode = $request->getPost('mode');
    $objectIds = json_decode($request->getPost('objectIds'), true);

    if (!$mode || !$objectIds) {
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        die();
    }

    try {
        $syncedCount = 0;
        $errors = [];

        // Заглушка: в реальной реализации здесь будет синхронизация
        // Пока просто возвращаем успешный результат для тестирования
        foreach ($objectIds as $objectId) {
            // Здесь можно добавить логику синхронизации:
            // 1. Получить текущие права из нашей системы
            // 2. Преобразовать в BX.Access формат
            // 3. Обновить BX.Access кэш
            // 4. Инвалидировать IndexedDB кэш на клиенте

            $syncedCount++;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'syncedCount' => $syncedCount,
                'errors' => $errors,
                'timestamp' => time()
            ],
            'bx_access_cache_invalidation' => [
                'action' => 'sync',
                'mode' => $mode,
                'itemCount' => count($objectIds),
                'itemIds' => $objectIds,
                'invalidateAll' => false,
                'timestamp' => time()
            ]
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    die();
}
/**
 * Загрузить всех пользователей для IndexedDB кэширования
 */
function handleLoadAllUsers($request)
{
    $limit = (int)$request->getPost('limit', 100);
    $offset = (int)$request->getPost('offset', 0);

    if ($limit > 500) {
        $limit = 500; // Safety limit
    }

    try {
        $users = [];
        $groups = [];

        // Получить пользователей
        $dbUser = \CUser::GetList(
            'ID',
            'ASC',
            ['ACTIVE' => 'Y'],
            [
                'SELECT' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'EMAIL'],
                'NAV_PARAMS' => [
                    'nPageSize' => $limit,
                    'iNumPage' => ($offset / $limit) + 1
                ]
            ]
        );

        while ($arUser = $dbUser->Fetch()) {
            $users[] = [
                'id' => 'user_' . $arUser['ID'],
                'provider' => 'user',
                'name' => trim(($arUser['NAME'] ?? '') . ' ' . ($arUser['LAST_NAME'] ?? '')),
                'email' => $arUser['EMAIL'] ?? '',
                'login' => $arUser['LOGIN'] ?? '',
                'timestamp' => time() * 1000
            ];
        }

        // Получить группы пользователей
        $dbGroup = \CGroup::GetList('c_sort', 'asc', ['ACTIVE' => 'Y']);
        while ($arGroup = $dbGroup->Fetch()) {
            $groups[] = [
                'id' => 'group_' . $arGroup['ID'],
                'provider' => 'group',
                'name' => $arGroup['NAME'] ?? 'Group #' . $arGroup['ID'],
                'timestamp' => time() * 1000
            ];
        }

        // Объединить users и groups
        $allSubjects = array_merge($users, $groups);

        echo json_encode([
            'success' => true,
            'users' => $allSubjects,
            'count' => count($allSubjects),
            'offset' => $offset,
            'limit' => $limit,
            'hasMore' => count($users) >= $limit
        ]);
    } catch (\Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    die();
}
