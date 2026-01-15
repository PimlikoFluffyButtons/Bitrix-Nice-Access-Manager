<?php
/**
 * AJAX обработчики для модуля управления доступом
 */

// Отключаем вывод ошибок в JSON
error_reporting(0);
ini_set('display_errors', 0);

use Bitrix\Main\Loader;
use Local\AccessManager\IblockPermissions;
use Local\AccessManager\FilePermissions;
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
        
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                
                try {
                    $currentPerms = $iblock->GetGroupPermissions($iblockId);
                    $oldPerm = $currentPerms[$subject['id']] ?? null;
                    
                    if ($subject['type'] === 'group') {
                        $currentPerms[$subject['id']] = $permission;
                        $iblock->SetPermission($iblockId, $currentPerms);
                        
                        Logger::log(
                            Logger::OP_SET_PERMISSION,
                            Logger::OBJ_IBLOCK,
                            (string)$iblockId,
                            'group',
                            $subject['id'],
                            $oldPerm ? [$subject['id'] => $oldPerm] : null,
                            [$subject['id'] => $permission]
                        );
                        
                        $successCount++;
                    }
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
                $currentPerms = $APPLICATION->GetFileAccessPermission($path);
                if (!is_array($currentPerms)) {
                    $currentPerms = [];
                }
                
                $oldPerm = $currentPerms[$subject['id']] ?? null;
                
                if ($subject['type'] === 'group') {
                    $currentPerms[$subject['id']] = $permission;
                    $APPLICATION->SetFileAccessPermission($path, $currentPerms);
                    
                    Logger::log(
                        Logger::OP_SET_PERMISSION,
                        $item['type'] === 'folder' ? Logger::OBJ_FOLDER : Logger::OBJ_FILE,
                        $path,
                        'group',
                        $subject['id'],
                        $oldPerm ? [$subject['id'] => $oldPerm] : null,
                        [$subject['id'] => $permission]
                    );
                    
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = ['path' => $path, 'message' => $e->getMessage()];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'successCount' => $successCount,
        'errors' => $errors,
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
    
    if ($mode === 'iblocks' && $subject['type'] === 'group') {
        Loader::includeModule('iblock');
        foreach ($selected as $item) {
            if ($item['type'] === 'iblock') {
                $iblockId = (int)$item['id'];
                try {
                    IblockPermissions::removeGroupPermission($iblockId, $subject['id']);
                    Logger::log(Logger::OP_REMOVE_PERMISSION, Logger::OBJ_IBLOCK, (string)$iblockId, 'group', $subject['id']);
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = ['id' => $iblockId, 'message' => $e->getMessage()];
                }
            }
        }
    } elseif ($mode === 'files' && $subject['type'] === 'group') {
        foreach ($selected as $item) {
            $path = $item['path'];
            try {
                FilePermissions::removeGroupPermission($path, $subject['id']);
                Logger::log(Logger::OP_REMOVE_PERMISSION, $item['type'] === 'folder' ? Logger::OBJ_FOLDER : Logger::OBJ_FILE, $path, 'group', $subject['id']);
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