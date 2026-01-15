<?php
/**
 * Массовое управление доступом - главная страница администрирования
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Local\AccessManager\IblockPermissions;
use Local\AccessManager\FilePermissions;
use Local\AccessManager\Logger;

Loc::loadMessages(__FILE__);

// Проверка прав администратора
global $USER, $APPLICATION;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('LOCAL_ACCESSMANAGER_ACCESS_DENIED'));
}

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

// AJAX обработчики
if ($request->isAjaxRequest() && $request->isPost()) {
    require_once __DIR__ . '/accessmanager_ajax.php';
    exit;
}

// Подключаем визуальную часть
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

// Формируем табы
$aTabs = [
    [
        'DIV' => 'iblocks',
        'TAB' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_IBLOCKS'),
        'TITLE' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_IBLOCKS'),
    ],
    [
        'DIV' => 'files',
        'TAB' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_FILES'),
        'TITLE' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_FILES'),
    ],
    [
        'DIV' => 'log',
        'TAB' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_LOG'),
        'TITLE' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_LOG'),
    ],
    [
        'DIV' => 'rollback',
        'TAB' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_ROLLBACK'),
        'TITLE' => Loc::getMessage('LOCAL_ACCESSMANAGER_TAB_ROLLBACK'),
    ],
];

$tabControl = new CAdminTabControl('accessManagerTabs', $aTabs);

// Получаем список групп пользователей
$groups = [];
$groupRes = \CGroup::GetList('c_sort', 'asc', ['ACTIVE' => 'Y']);
while ($group = $groupRes->Fetch()) {
    $groups[] = [
        'ID' => $group['ID'],
        'NAME' => $group['NAME'],
    ];
}

// Получаем дерево инфоблоков
$iblockTree = IblockPermissions::getTree();

// Получаем журнал операций
$logs = Logger::getLogs([], 50);

// Получаем снапшоты
$snapshots = Logger::getSnapshots(20);
?>

<style>
.accessmanager-container {
    display: flex;
    gap: 20px;
    min-height: 500px;
}
.accessmanager-left {
    flex: 1;
    min-width: 300px;
    max-width: 50%;
    border: 1px solid #c8c8c8;
    border-radius: 4px;
    background: #fff;
}
.accessmanager-right {
    flex: 1;
    min-width: 300px;
    border: 1px solid #c8c8c8;
    border-radius: 4px;
    background: #fff;
    padding: 15px;
}
.accessmanager-search {
    padding: 10px;
    border-bottom: 1px solid #e0e0e0;
}
.accessmanager-search input {
    width: 100%;
    padding: 8px;
    border: 1px solid #c8c8c8;
    border-radius: 4px;
}
.accessmanager-tree {
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}
.accessmanager-tree-node {
    margin: 2px 0;
}
.accessmanager-tree-node-content {
    display: flex;
    align-items: center;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 3px;
}
.accessmanager-tree-node-content:hover {
    background: #f0f0f0;
}
.accessmanager-tree-node-content.selected {
    background: #e3f2fd;
}
.accessmanager-tree-toggle {
    width: 20px;
    text-align: center;
    color: #666;
    cursor: pointer;
}
.accessmanager-tree-checkbox {
    margin-right: 8px;
}
.accessmanager-tree-icon {
    margin-right: 8px;
    color: #666;
}
.accessmanager-tree-children {
    margin-left: 24px;
}
.accessmanager-tree-children.collapsed {
    display: none;
}
.accessmanager-form-group {
    margin-bottom: 15px;
}
.accessmanager-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.accessmanager-form-group select,
.accessmanager-form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #c8c8c8;
    border-radius: 4px;
}
.accessmanager-radio-group {
    display: flex;
    gap: 20px;
    margin-bottom: 10px;
}
.accessmanager-radio-group label {
    font-weight: normal;
    cursor: pointer;
}
.accessmanager-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}
.accessmanager-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
.accessmanager-btn-primary {
    background: #3498db;
    color: #fff;
}
.accessmanager-btn-success {
    background: #27ae60;
    color: #fff;
}
.accessmanager-btn-warning {
    background: #f39c12;
    color: #fff;
}
.accessmanager-btn-danger {
    background: #e74c3c;
    color: #fff;
}
.accessmanager-btn:hover {
    opacity: 0.9;
}
.accessmanager-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.accessmanager-inspector {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}
.accessmanager-inspector h4 {
    margin: 0 0 10px 0;
}
.accessmanager-inspector-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.accessmanager-inspector-table th,
.accessmanager-inspector-table td {
    padding: 8px;
    border: 1px solid #e0e0e0;
    text-align: left;
}
.accessmanager-inspector-table th {
    background: #f5f5f5;
}
.accessmanager-inspector-table tr.inherited {
    color: #888;
    font-style: italic;
}
.accessmanager-custom-perm {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #f39c12;
    border-radius: 50%;
    margin-left: 5px;
    title: "Нестандартные права";
}
.accessmanager-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.accessmanager-modal.active {
    display: flex;
}
.accessmanager-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    width: 90%;
}
.accessmanager-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}
.accessmanager-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #666;
}
.accessmanager-preview-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}
.accessmanager-preview-table th,
.accessmanager-preview-table td {
    padding: 10px;
    border: 1px solid #e0e0e0;
    text-align: left;
}
.accessmanager-preview-table th {
    background: #f5f5f5;
}
.accessmanager-preview-table .change-add {
    color: #27ae60;
}
.accessmanager-preview-table .change-remove {
    color: #e74c3c;
}
.accessmanager-preview-table .change-modify {
    color: #f39c12;
}
.accessmanager-log-table {
    width: 100%;
    border-collapse: collapse;
}
.accessmanager-log-table th,
.accessmanager-log-table td {
    padding: 10px;
    border: 1px solid #e0e0e0;
    text-align: left;
    font-size: 13px;
}
.accessmanager-log-table th {
    background: #f5f5f5;
}
.accessmanager-progress {
    display: none;
    margin: 15px 0;
}
.accessmanager-progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}
.accessmanager-progress-fill {
    height: 100%;
    background: #3498db;
    width: 0%;
    transition: width 0.3s;
}
.accessmanager-progress-text {
    text-align: center;
    margin-top: 5px;
    font-size: 13px;
}
.accessmanager-result {
    display: none;
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}
.accessmanager-result.success {
    background: #d4edda;
    color: #155724;
    display: block;
}
.accessmanager-result.error {
    background: #f8d7da;
    color: #721c24;
    display: block;
}
</style>

<?php
$APPLICATION->SetTitle(Loc::getMessage('LOCAL_ACCESSMANAGER_TITLE'));

$tabControl->Begin();
?>

<!-- Таб: Инфоблоки -->
<?php $tabControl->BeginNextTab(); ?>

<div class="accessmanager-container" id="iblocks-container">
    <div class="accessmanager-left">
        <div class="accessmanager-search">
            <input type="text" id="iblock-search" placeholder="<?= Loc::getMessage('LOCAL_ACCESSMANAGER_SEARCH_PLACEHOLDER') ?>">
        </div>
        <div class="accessmanager-toolbar" style="padding: 10px; border-bottom: 1px solid #e0e0e0;">
            <button type="button" class="accessmanager-btn" onclick="AccessManager.selectAll('iblocks')"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_ALL') ?></button>
            <button type="button" class="accessmanager-btn" onclick="AccessManager.deselectAll('iblocks')"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_DESELECT_ALL') ?></button>
        </div>
        <div class="accessmanager-tree" id="iblocks-tree">
            <?php foreach ($iblockTree as $type): ?>
            <div class="accessmanager-tree-node" data-type="iblock_type" data-id="<?= htmlspecialcharsbx($type['typeId']) ?>">
                <div class="accessmanager-tree-node-content">
                    <span class="accessmanager-tree-toggle" onclick="AccessManager.toggleNode(this)">▼</span>
                    <input type="checkbox" class="accessmanager-tree-checkbox" data-type="iblock_type" data-id="<?= htmlspecialcharsbx($type['typeId']) ?>" onchange="AccessManager.onTypeCheck(this)">
                    <span class="accessmanager-tree-icon">📁</span>
                    <span class="accessmanager-tree-name"><?= htmlspecialcharsbx($type['name']) ?></span>
                </div>
                <div class="accessmanager-tree-children">
                    <?php foreach ($type['children'] as $iblock): ?>
                    <div class="accessmanager-tree-node" data-type="iblock" data-id="<?= (int)$iblock['iblockId'] ?>">
                        <div class="accessmanager-tree-node-content" onclick="AccessManager.selectSingle('iblock', <?= (int)$iblock['iblockId'] ?>)">
                            <span class="accessmanager-tree-toggle" style="visibility: hidden;">▼</span>
                            <input type="checkbox" class="accessmanager-tree-checkbox" data-type="iblock" data-id="<?= (int)$iblock['iblockId'] ?>" onclick="event.stopPropagation()">
                            <span class="accessmanager-tree-icon">📄</span>
                            <span class="accessmanager-tree-name"><?= htmlspecialcharsbx($iblock['name']) ?></span>
                            <?php if ($iblock['code']): ?>
                            <span style="color: #888; margin-left: 5px;">[<?= htmlspecialcharsbx($iblock['code']) ?>]</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="accessmanager-right">
        <div class="accessmanager-form-group">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_TYPE') ?></label>
            <div class="accessmanager-radio-group">
                <label>
                    <input type="radio" name="iblock_subject_type" value="group" checked onchange="AccessManager.toggleSubjectType('iblocks', 'group')">
                    <?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_GROUP') ?>
                </label>
                <label>
                    <input type="radio" name="iblock_subject_type" value="user" onchange="AccessManager.toggleSubjectType('iblocks', 'user')">
                    <?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_USER') ?>
                </label>
            </div>
        </div>
        
        <div class="accessmanager-form-group" id="iblock-group-select">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_GROUP') ?></label>
            <select id="iblock-group">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_GROUP') ?></option>
                <?php foreach ($groups as $group): ?>
                <option value="<?= (int)$group['ID'] ?>"><?= htmlspecialcharsbx($group['NAME']) ?> [<?= $group['ID'] ?>]</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="accessmanager-form-group" id="iblock-user-select" style="display: none;">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_USER') ?></label>
            <input type="text" id="iblock-user-search" placeholder="<?= Loc::getMessage('LOCAL_ACCESSMANAGER_USER_SEARCH') ?>" oninput="AccessManager.searchUsers(this, 'iblock-user-results')">
            <select id="iblock-user" style="margin-top: 5px;">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_USER') ?></option>
            </select>
            <div id="iblock-user-results"></div>
        </div>
        
        <div class="accessmanager-form-group">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERMISSION_LEVEL') ?></label>
            <select id="iblock-permission">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_PERMISSION') ?></option>
                <option value="D"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_DENIED') ?></option>
                <option value="R"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_READ') ?></option>
                <option value="W"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_WRITE') ?></option>
                <option value="X"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_FULL') ?></option>
            </select>
        </div>
        
        <div class="accessmanager-buttons">
            <button type="button" class="accessmanager-btn accessmanager-btn-primary" onclick="AccessManager.preview('iblocks')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_PREVIEW') ?>
            </button>
            <button type="button" class="accessmanager-btn accessmanager-btn-warning" onclick="AccessManager.resetDefault('iblocks')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_RESET_DEFAULT') ?>
            </button>
            <button type="button" class="accessmanager-btn accessmanager-btn-danger" onclick="AccessManager.removeSubject('iblocks')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_REMOVE_SUBJECT') ?>
            </button>
        </div>
        
        <div class="accessmanager-progress" id="iblocks-progress">
            <div class="accessmanager-progress-bar">
                <div class="accessmanager-progress-fill"></div>
            </div>
            <div class="accessmanager-progress-text">0%</div>
        </div>
        
        <div class="accessmanager-result" id="iblocks-result"></div>
        
        <div class="accessmanager-inspector" id="iblocks-inspector">
            <h4><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_TITLE') ?></h4>
            <div id="iblocks-inspector-content">
                <p style="color: #888;"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_SELECT_ONE') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Таб: Файлы и папки -->
<?php $tabControl->BeginNextTab(); ?>

<div class="accessmanager-container" id="files-container">
    <div class="accessmanager-left">
        <div class="accessmanager-search">
            <input type="text" id="file-search" placeholder="<?= Loc::getMessage('LOCAL_ACCESSMANAGER_SEARCH_PLACEHOLDER') ?>">
        </div>
        <div class="accessmanager-toolbar" style="padding: 10px; border-bottom: 1px solid #e0e0e0;">
            <button type="button" class="accessmanager-btn" onclick="AccessManager.selectAll('files')"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_ALL') ?></button>
            <button type="button" class="accessmanager-btn" onclick="AccessManager.deselectAll('files')"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_DESELECT_ALL') ?></button>
            <button type="button" class="accessmanager-btn" onclick="AccessManager.expandAll('files')"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_EXPAND_ALL') ?></button>
        </div>
        <div class="accessmanager-tree" id="files-tree">
            <div class="accessmanager-tree-node" data-type="folder" data-path="/">
                <div class="accessmanager-tree-node-content" onclick="AccessManager.selectSingle('folder', '/')">
                    <span class="accessmanager-tree-toggle" onclick="event.stopPropagation(); AccessManager.loadChildren(this, '/')">▶</span>
                    <input type="checkbox" class="accessmanager-tree-checkbox" data-type="folder" data-path="/" onclick="event.stopPropagation()">
                    <span class="accessmanager-tree-icon">🏠</span>
                    <span class="accessmanager-tree-name"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROOT') ?></span>
                </div>
                <div class="accessmanager-tree-children collapsed" id="tree-children-root"></div>
            </div>
        </div>
    </div>
    
    <div class="accessmanager-right">
        <div class="accessmanager-form-group">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_TYPE') ?></label>
            <div class="accessmanager-radio-group">
                <label>
                    <input type="radio" name="file_subject_type" value="group" checked onchange="AccessManager.toggleSubjectType('files', 'group')">
                    <?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_GROUP') ?>
                </label>
                <label>
                    <input type="radio" name="file_subject_type" value="user" onchange="AccessManager.toggleSubjectType('files', 'user')">
                    <?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_USER') ?>
                </label>
            </div>
        </div>
        
        <div class="accessmanager-form-group" id="file-group-select">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_GROUP') ?></label>
            <select id="file-group">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_GROUP') ?></option>
                <?php foreach ($groups as $group): ?>
                <option value="<?= (int)$group['ID'] ?>"><?= htmlspecialcharsbx($group['NAME']) ?> [<?= $group['ID'] ?>]</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="accessmanager-form-group" id="file-user-select" style="display: none;">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_USER') ?></label>
            <input type="text" id="file-user-search" placeholder="<?= Loc::getMessage('LOCAL_ACCESSMANAGER_USER_SEARCH') ?>" oninput="AccessManager.searchUsers(this, 'file-user-results')">
            <select id="file-user" style="margin-top: 5px;">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_USER') ?></option>
            </select>
            <div id="file-user-results"></div>
        </div>
        
        <div class="accessmanager-form-group">
            <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERMISSION_LEVEL') ?></label>
            <select id="file-permission">
                <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_PERMISSION') ?></option>
                <option value="D"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_FILE_PERM_DENIED') ?></option>
                <option value="R"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_FILE_PERM_READ') ?></option>
                <option value="W"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_FILE_PERM_WRITE') ?></option>
                <option value="X"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_FILE_PERM_FULL') ?></option>
            </select>
        </div>
        
        <div class="accessmanager-buttons">
            <button type="button" class="accessmanager-btn accessmanager-btn-primary" onclick="AccessManager.preview('files')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_PREVIEW') ?>
            </button>
            <button type="button" class="accessmanager-btn accessmanager-btn-warning" onclick="AccessManager.resetDefault('files')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_RESET_DEFAULT') ?>
            </button>
            <button type="button" class="accessmanager-btn accessmanager-btn-danger" onclick="AccessManager.removeSubject('files')">
                <?= Loc::getMessage('LOCAL_ACCESSMANAGER_BTN_REMOVE_SUBJECT') ?>
            </button>
        </div>
        
        <div class="accessmanager-progress" id="files-progress">
            <div class="accessmanager-progress-bar">
                <div class="accessmanager-progress-fill"></div>
            </div>
            <div class="accessmanager-progress-text">0%</div>
        </div>
        
        <div class="accessmanager-result" id="files-result"></div>
        
        <div class="accessmanager-inspector" id="files-inspector">
            <h4><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_TITLE') ?></h4>
            <div id="files-inspector-content">
                <p style="color: #888;"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_SELECT_ONE') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Таб: Журнал операций -->
<?php $tabControl->BeginNextTab(); ?>

<div style="padding: 15px;">
    <table class="accessmanager-log-table">
        <thead>
            <tr>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_DATE') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_USER') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_OPERATION') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_OBJECT') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_SUBJECT') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_CHANGES') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #888;">Журнал пуст</td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialcharsbx($log['CREATED_AT']) ?></td>
                <td><?= htmlspecialcharsbx($log['USER_FULL_NAME']) ?></td>
                <td><?= htmlspecialcharsbx($log['OPERATION_TYPE']) ?></td>
                <td><?= htmlspecialcharsbx($log['OBJECT_TYPE']) ?>: <?= htmlspecialcharsbx($log['OBJECT_ID']) ?></td>
                <td><?= htmlspecialcharsbx($log['SUBJECT_TYPE']) ?> #<?= (int)$log['SUBJECT_ID'] ?></td>
                <td>
                    <?php if ($log['OLD_PERMISSIONS'] || $log['NEW_PERMISSIONS']): ?>
                    <details>
                        <summary>Показать</summary>
                        <pre style="font-size: 11px;"><?= htmlspecialcharsbx(json_encode(['old' => $log['OLD_PERMISSIONS'], 'new' => $log['NEW_PERMISSIONS']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </details>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Таб: Откат изменений -->
<?php $tabControl->BeginNextTab(); ?>

<div style="padding: 15px;">
    <table class="accessmanager-log-table">
        <thead>
            <tr>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_BATCH') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_DATE') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_USER') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_LOG_OBJECT') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_OBJECTS') ?></th>
                <th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_STATUS') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($snapshots)): ?>
            <tr>
                <td colspan="7" style="text-align: center; color: #888;">Нет доступных снапшотов</td>
            </tr>
            <?php else: ?>
            <?php foreach ($snapshots as $snap): ?>
            <tr>
                <td><code style="font-size: 11px;"><?= htmlspecialcharsbx(substr($snap['BATCH_ID'], 0, 8)) ?>...</code></td>
                <td><?= htmlspecialcharsbx($snap['CREATED_AT']) ?></td>
                <td><?= htmlspecialcharsbx($snap['USER_FULL_NAME']) ?></td>
                <td><?= htmlspecialcharsbx($snap['OBJECT_TYPE']) ?></td>
                <td><?= (int)$snap['OBJECTS_COUNT'] ?></td>
                <td>
                    <?php if ($snap['ROLLED_BACK']): ?>
                    <span style="color: #888;"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_REVERTED') ?></span>
                    <?php else: ?>
                    <span style="color: #27ae60;"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_ACTIVE') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$snap['ROLLED_BACK']): ?>
                    <button type="button" class="accessmanager-btn accessmanager-btn-warning" onclick="AccessManager.rollback('<?= htmlspecialcharsbx($snap['BATCH_ID']) ?>')">
                        <?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_BTN') ?>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$tabControl->End();
?>

<!-- Модальное окно превью -->
<div class="accessmanager-modal" id="preview-modal">
    <div class="accessmanager-modal-content">
        <div class="accessmanager-modal-header">
            <h3><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_TITLE') ?></h3>
            <span class="accessmanager-modal-close" onclick="AccessManager.closePreview()">&times;</span>
        </div>
        <div id="preview-content"></div>
        <div style="margin-top: 15px; text-align: right;">
            <button type="button" class="accessmanager-btn" onclick="AccessManager.closePreview()"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_CANCEL') ?></button>
            <button type="button" class="accessmanager-btn accessmanager-btn-success" onclick="AccessManager.applyFromPreview()"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_CONFIRM') ?></button>
        </div>
    </div>
</div>

<script>
const AccessManager = {
    sessid: '<?= bitrix_sessid() ?>',
    currentMode: 'iblocks',
    previewData: null,
    
    // Переключение типа субъекта
    toggleSubjectType: function(mode, type) {
        const prefix = mode === 'iblocks' ? 'iblock' : 'file';
        document.getElementById(prefix + '-group-select').style.display = type === 'group' ? '' : 'none';
        document.getElementById(prefix + '-user-select').style.display = type === 'user' ? '' : 'none';
    },
    
    // Свернуть/развернуть узел
    toggleNode: function(el) {
        const node = el.closest('.accessmanager-tree-node');
        const children = node.querySelector('.accessmanager-tree-children');
        if (children) {
            children.classList.toggle('collapsed');
            el.textContent = children.classList.contains('collapsed') ? '▶' : '▼';
        }
    },
    
    // При выборе типа инфоблока - выбрать все инфоблоки внутри
    onTypeCheck: function(checkbox) {
        const node = checkbox.closest('.accessmanager-tree-node');
        const childCheckboxes = node.querySelectorAll('.accessmanager-tree-children .accessmanager-tree-checkbox');
        childCheckboxes.forEach(cb => cb.checked = checkbox.checked);
    },
    
    // Выбрать все
    selectAll: function(mode) {
        const containerId = mode === 'iblocks' ? 'iblocks-tree' : 'files-tree';
        document.querySelectorAll('#' + containerId + ' .accessmanager-tree-checkbox').forEach(cb => cb.checked = true);
    },
    
    // Снять выделение
    deselectAll: function(mode) {
        const containerId = mode === 'iblocks' ? 'iblocks-tree' : 'files-tree';
        document.querySelectorAll('#' + containerId + ' .accessmanager-tree-checkbox').forEach(cb => cb.checked = false);
    },
    
    // Выбор одного объекта для инспектора
    selectSingle: function(type, id) {
        this.loadInspector(type, id);
    },
    
    // Загрузка инспектора прав
    loadInspector: function(type, id) {
        const mode = (type === 'iblock' || type === 'iblock_type') ? 'iblocks' : 'files';
        const inspectorContent = document.getElementById(mode + '-inspector-content');
        
        inspectorContent.innerHTML = '<p>Загрузка...</p>';
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_permissions&sessid=' + this.sessid + '&type=' + type + '&id=' + encodeURIComponent(id)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.permissions) {
                let html = '<table class="accessmanager-inspector-table"><thead><tr>' +
                    '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_SUBJECT') ?></th>' +
                    '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_PERMISSION') ?></th>' +
                    '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_SOURCE') ?></th>' +
                    '</tr></thead><tbody>';
                
                data.permissions.forEach(p => {
                    const rowClass = p.source === 'inherited' ? 'inherited' : '';
                    html += '<tr class="' + rowClass + '">' +
                        '<td>' + p.subjectName + ' [' + p.subjectId + ']</td>' +
                        '<td>' + p.permissionName + ' (' + p.permission + ')</td>' +
                        '<td>' + (p.source === 'inherited' ? '<?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_INHERITED') ?>' : '<?= Loc::getMessage('LOCAL_ACCESSMANAGER_INSPECTOR_EXPLICIT') ?>') + '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                inspectorContent.innerHTML = html;
            } else {
                inspectorContent.innerHTML = '<p style="color: #e74c3c;">' + (data.error || 'Ошибка загрузки') + '</p>';
            }
        })
        .catch(err => {
            inspectorContent.innerHTML = '<p style="color: #e74c3c;">Ошибка: ' + err.message + '</p>';
        });
    },
    
    // Lazy load дочерних элементов для файлов
    loadChildren: function(toggle, path) {
        const node = toggle.closest('.accessmanager-tree-node');
        const childrenContainer = node.querySelector('.accessmanager-tree-children');
        
        // Если уже загружено - просто переключаем
        if (childrenContainer.dataset.loaded === 'true') {
            childrenContainer.classList.toggle('collapsed');
            toggle.textContent = childrenContainer.classList.contains('collapsed') ? '▶' : '▼';
            return;
        }
        
        toggle.textContent = '⏳';
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=get_children&sessid=' + this.sessid + '&path=' + encodeURIComponent(path)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.children) {
                let html = '';
                data.children.forEach(item => {
                    const icon = item.type === 'folder' ? '📁' : '📄';
                    const hasToggle = item.type === 'folder' && item.hasChildren;
                    const customMark = item.hasCustomPermissions ? '<span class="accessmanager-custom-perm" title="Нестандартные права"></span>' : '';
                    
                    html += '<div class="accessmanager-tree-node" data-type="' + item.type + '" data-path="' + item.path + '">' +
                        '<div class="accessmanager-tree-node-content" onclick="AccessManager.selectSingle(\'' + item.type + '\', \'' + item.path + '\')">' +
                        '<span class="accessmanager-tree-toggle" ' + (hasToggle ? 'onclick="event.stopPropagation(); AccessManager.loadChildren(this, \'' + item.path + '\')"' : 'style="visibility:hidden"') + '>' + (hasToggle ? '▶' : '▼') + '</span>' +
                        '<input type="checkbox" class="accessmanager-tree-checkbox" data-type="' + item.type + '" data-path="' + item.path + '" onclick="event.stopPropagation()">' +
                        '<span class="accessmanager-tree-icon">' + icon + '</span>' +
                        '<span class="accessmanager-tree-name">' + item.name + customMark + '</span>' +
                        '</div>';
                    
                    if (item.type === 'folder') {
                        html += '<div class="accessmanager-tree-children collapsed"></div>';
                    }
                    
                    html += '</div>';
                });
                
                childrenContainer.innerHTML = html;
                childrenContainer.dataset.loaded = 'true';
                childrenContainer.classList.remove('collapsed');
                toggle.textContent = '▼';
            } else {
                toggle.textContent = '▶';
            }
        })
        .catch(err => {
            console.error(err);
            toggle.textContent = '▶';
        });
    },
    
    // Получить выбранные объекты
    getSelected: function(mode) {
        const containerId = mode === 'iblocks' ? 'iblocks-tree' : 'files-tree';
        const selected = [];
        
        document.querySelectorAll('#' + containerId + ' .accessmanager-tree-checkbox:checked').forEach(cb => {
            if (mode === 'iblocks') {
                if (cb.dataset.type === 'iblock') {
                    selected.push({type: 'iblock', id: cb.dataset.id});
                }
                // Для типа инфоблока - все дочерние уже отмечены через onTypeCheck
            } else {
                selected.push({type: cb.dataset.type, path: cb.dataset.path});
            }
        });
        
        return selected;
    },
    
    // Получить выбранного субъекта
    getSubject: function(mode) {
        const prefix = mode === 'iblocks' ? 'iblock' : 'file';
        const subjectType = document.querySelector('input[name="' + prefix + '_subject_type"]:checked').value;
        
        if (subjectType === 'group') {
            const groupId = document.getElementById(prefix + '-group').value;
            return groupId ? {type: 'group', id: parseInt(groupId)} : null;
        } else {
            const userId = document.getElementById(prefix + '-user').value;
            return userId ? {type: 'user', id: parseInt(userId)} : null;
        }
    },
    
    // Получить выбранный уровень прав
    getPermission: function(mode) {
        const prefix = mode === 'iblocks' ? 'iblock' : 'file';
        return document.getElementById(prefix + '-permission').value;
    },
    
    // Предпросмотр изменений
    preview: function(mode) {
        this.currentMode = mode;
        
        const selected = this.getSelected(mode);
        const subject = this.getSubject(mode);
        const permission = this.getPermission(mode);
        
        if (selected.length === 0) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_SELECTION') ?>');
            return;
        }
        if (!subject) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_SUBJECT') ?>');
            return;
        }
        if (!permission) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_PERMISSION') ?>');
            return;
        }
        
        document.getElementById('preview-content').innerHTML = '<p>Загрузка предпросмотра...</p>';
        document.getElementById('preview-modal').classList.add('active');
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=preview&sessid=' + this.sessid + 
                  '&mode=' + mode + 
                  '&selected=' + encodeURIComponent(JSON.stringify(selected)) +
                  '&subject=' + encodeURIComponent(JSON.stringify(subject)) +
                  '&permission=' + permission
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.previewData = {mode, selected, subject, permission};
                this.renderPreview(data.preview);
            } else {
                document.getElementById('preview-content').innerHTML = '<p style="color: #e74c3c;">' + (data.error || 'Ошибка') + '</p>';
            }
        })
        .catch(err => {
            document.getElementById('preview-content').innerHTML = '<p style="color: #e74c3c;">Ошибка: ' + err.message + '</p>';
        });
    },
    
    // Отрисовка превью
    renderPreview: function(preview) {
        let html = '<table class="accessmanager-preview-table"><thead><tr>' +
            '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_OBJECT') ?></th>' +
            '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_WAS') ?></th>' +
            '<th><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PREVIEW_WILL_BE') ?></th>' +
            '</tr></thead><tbody>';
        
        preview.forEach(item => {
            const changeClass = item.wasPermission === item.willBePermission ? '' : 
                               (item.wasPermission ? 'change-modify' : 'change-add');
            html += '<tr>' +
                '<td>' + item.objectName + '</td>' +
                '<td>' + (item.wasPermission || '-') + '</td>' +
                '<td class="' + changeClass + '">' + item.willBePermission + '</td>' +
                '</tr>';
        });
        
        html += '</tbody></table>';
        document.getElementById('preview-content').innerHTML = html;
    },
    
    // Закрыть превью
    closePreview: function() {
        document.getElementById('preview-modal').classList.remove('active');
        this.previewData = null;
    },
    
    // Применить из превью
    applyFromPreview: function() {
			if (!this.previewData) return;
			
			this.closePreview();
			this.apply(this.previewData.mode, this.previewData.selected, this.previewData.subject, this.previewData.permission);
		},
		
		// Применить права
		apply: function(mode, selected, subject, permission) {
		const progressEl = document.getElementById(mode + '-progress');
		const resultEl = document.getElementById(mode + '-result');
		
		progressEl.style.display = 'block';
		resultEl.style.display = 'none';
		resultEl.className = 'accessmanager-result';
		
		fetch('/bitrix/admin/local_accessmanager.php', {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: 'action=apply&sessid=' + this.sessid + 
				  '&mode=' + mode + 
				  '&selected=' + encodeURIComponent(JSON.stringify(selected)) +
				  '&subject=' + encodeURIComponent(JSON.stringify(subject)) +
				  '&permission=' + permission
		})
		.then(r => r.json())
		.then(data => {
			progressEl.style.display = 'none';
			
			if (data.success) {
				resultEl.className = 'accessmanager-result success';
				resultEl.innerHTML = '<?= Loc::getMessage('LOCAL_ACCESSMANAGER_RESULT_SUCCESS') ?>: ' + data.successCount;
				if (data.errors && data.errors.length > 0) {
					resultEl.innerHTML += '<br><?= Loc::getMessage('LOCAL_ACCESSMANAGER_RESULT_ERRORS') ?>: ' + data.errors.length;
				}
			} else {
				resultEl.className = 'accessmanager-result error';
				resultEl.innerHTML = data.error || 'Ошибка применения';
			}
			// ИСПРАВЛЕНИЕ: очищаем inline style чтобы класс работал
			resultEl.style.display = '';
		})
		.catch(err => {
			progressEl.style.display = 'none';
			resultEl.className = 'accessmanager-result error';
			resultEl.innerHTML = 'Ошибка: ' + err.message;
			resultEl.style.display = '';
		});
	},
    // Сброс к дефолту
    resetDefault: function(mode) {
        const selected = this.getSelected(mode);
        
        if (selected.length === 0) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_SELECTION') ?>');
            return;
        }
        
        if (!confirm('Сбросить права выбранных объектов к дефолтным (Все=чтение, Админы=полный)?')) {
            return;
        }
        
        const progressEl = document.getElementById(mode + '-progress');
        const resultEl = document.getElementById(mode + '-result');
        
        progressEl.style.display = 'block';
        resultEl.style.display = 'none';
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=reset_default&sessid=' + this.sessid + 
                  '&mode=' + mode + 
                  '&selected=' + encodeURIComponent(JSON.stringify(selected))
        })
        .then(r => r.json())
        .then(data => {
            progressEl.style.display = 'none';
            
            if (data.success) {
                resultEl.className = 'accessmanager-result success';
                resultEl.innerHTML = '<?= Loc::getMessage('LOCAL_ACCESSMANAGER_RESULT_SUCCESS') ?>: ' + data.successCount;
            } else {
                resultEl.className = 'accessmanager-result error';
                resultEl.innerHTML = data.error || 'Ошибка';
                }
			resultEl.style.display = ''; 
        })
        .catch(err => {
            progressEl.style.display = 'none';
            resultEl.className = 'accessmanager-result error';
            resultEl.innerHTML = 'Ошибка: ' + err.message;
		    resultEl.style.display = ''; 
        });
    },
    
    // Удалить субъекта из прав
    removeSubject: function(mode) {
        const selected = this.getSelected(mode);
        const subject = this.getSubject(mode);
        
        if (selected.length === 0) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_SELECTION') ?>');
            return;
        }
        if (!subject) {
            alert('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_WARN_NO_SUBJECT') ?>');
            return;
        }
        
        if (!confirm('Удалить выбранного субъекта из прав всех отмеченных объектов?')) {
            return;
        }
        
        const progressEl = document.getElementById(mode + '-progress');
        const resultEl = document.getElementById(mode + '-result');
        
        progressEl.style.display = 'block';
        resultEl.style.display = 'none';
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=remove_subject&sessid=' + this.sessid + 
                  '&mode=' + mode + 
                  '&selected=' + encodeURIComponent(JSON.stringify(selected)) +
                  '&subject=' + encodeURIComponent(JSON.stringify(subject))
        })
        .then(r => r.json())
        .then(data => {
            progressEl.style.display = 'none';
            
            if (data.success) {
                resultEl.className = 'accessmanager-result success';
                resultEl.innerHTML = '<?= Loc::getMessage('LOCAL_ACCESSMANAGER_RESULT_SUCCESS') ?>: ' + data.successCount;
            } else {
                resultEl.className = 'accessmanager-result error';
                resultEl.innerHTML = data.error || 'Ошибка';
            }
			resultEl.style.display = '';
        })
        .catch(err => {
            progressEl.style.display = 'none';
            resultEl.className = 'accessmanager-result error';
            resultEl.innerHTML = 'Ошибка: ' + err.message;
			resultEl.style.display = '';
        });
    },
    
    // Откат снапшота
    rollback: function(batchId) {
        if (!confirm('<?= Loc::getMessage('LOCAL_ACCESSMANAGER_ROLLBACK_CONFIRM') ?>')) {
            return;
        }
        
        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=rollback&sessid=' + this.sessid + '&batch_id=' + encodeURIComponent(batchId)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Откат выполнен успешно. Восстановлено объектов: ' + data.successCount);
                location.reload();
            } else {
                alert('Ошибка отката: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(err => {
            alert('Ошибка: ' + err.message);
        });
    },
    
    // Поиск пользователей
    searchUsers: function(input, resultsId) {
        const query = input.value.trim();
        if (query.length < 2) return;
        
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            fetch('/bitrix/admin/local_accessmanager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=search_users&sessid=' + this.sessid + '&query=' + encodeURIComponent(query)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.users) {
                    const select = input.parentElement.querySelector('select');
                    select.innerHTML = '<option value="">-- Выберите пользователя --</option>';
                    data.users.forEach(u => {
                        select.innerHTML += '<option value="' + u.ID + '">' + u.NAME + ' (' + u.LOGIN + ')</option>';
                    });
                }
            });
        }, 300);
    }
};
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
