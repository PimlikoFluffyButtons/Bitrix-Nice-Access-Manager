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

// Подключаем BX.Access API для работы с диалогом выбора пользователей/групп/подразделений
\Bitrix\Main\UI\Extension::load('main.core');
$APPLICATION->AddHeadScript('/bitrix/js/main/core/core_access.js');

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
.accessmanager-extended-mode-badge {
    display: inline-block;
    margin-left: 5px;
    font-size: 14px;
    cursor: help;
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
.accessmanager-bx-access {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}
.accessmanager-bx-access h4 {
    margin: 0 0 10px 0;
    color: #3498db;
}
.accessmanager-selected-subjects {
    min-height: 60px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    margin-bottom: 10px;
}
.accessmanager-subject-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.accessmanager-subject-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #fff;
    border: 1px solid #d0d0d0;
    border-radius: 16px;
    font-size: 13px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.accessmanager-subject-item button {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0;
    font-size: 14px;
    color: #e74c3c;
    margin-left: 4px;
}
.accessmanager-subject-item button:hover {
    opacity: 0.7;
}
.accessmanager-mode-selector {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}
.accessmanager-mode-selector h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #333;
}
.accessmanager-mode-tabs {
    display: flex;
    gap: 10px;
}
.accessmanager-mode-tab {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #c8c8c8;
    border-radius: 6px;
    background: #f9f9f9;
    cursor: pointer;
    text-align: center;
    font-size: 14px;
    transition: all 0.2s;
}
.accessmanager-mode-tab:hover {
    background: #f0f0f0;
    border-color: #3498db;
}
.accessmanager-mode-tab.active {
    background: #3498db;
    color: #fff;
    border-color: #3498db;
}
.accessmanager-mode-tab small {
    display: block;
    font-size: 11px;
    margin-top: 4px;
    opacity: 0.8;
}
.accessmanager-mode-panel {
    display: block;
}
.accessmanager-info-box {
    padding: 12px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    margin-bottom: 15px;
    font-size: 13px;
    color: #856404;
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
                    <div class="accessmanager-tree-node" data-type="iblock" data-id="<?= (int)$iblock['iblockId'] ?>" data-extended-mode="<?= $iblock['isExtendedMode'] ? '1' : '0' ?>">
                        <div class="accessmanager-tree-node-content" onclick="AccessManager.selectSingle('iblock', <?= (int)$iblock['iblockId'] ?>)">
                            <span class="accessmanager-tree-toggle" style="visibility: hidden;">▼</span>
                            <input type="checkbox" class="accessmanager-tree-checkbox" data-type="iblock" data-id="<?= (int)$iblock['iblockId'] ?>" onclick="event.stopPropagation()">
                            <span class="accessmanager-tree-icon">📄</span>
                            <span class="accessmanager-tree-name"><?= htmlspecialcharsbx($iblock['name']) ?></span>
                            <?php if ($iblock['isExtendedMode']): ?>
                            <span class="accessmanager-extended-mode-badge" title="Расширенный режим прав (РРУП)">⚠️</span>
                            <?php endif; ?>
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
        <!-- РЕЖИМЫ: Стандартный / Расширенный -->
        <div class="accessmanager-mode-selector" id="iblocks-mode-selector" style="display: none;">
            <h4>Режим работы</h4>
            <div class="accessmanager-mode-tabs">
                <button type="button" class="accessmanager-mode-tab active"
                        data-mode="standard"
                        onclick="AccessManager.setMode('iblocks', 'standard')">
                    📋 Стандартный режим<br>
                    <small>Группы пользователей</small>
                </button>
                <button type="button" class="accessmanager-mode-tab"
                        data-mode="extended"
                        onclick="AccessManager.setMode('iblocks', 'extended')">
                    ⚙️ Расширенный режим<br>
                    <small>BX.Access (РРУП)</small>
                </button>
            </div>
        </div>

        <!-- ПАНЕЛЬ 1: Стандартный режим -->
        <div class="accessmanager-mode-panel" id="iblocks-mode-standard">
            <div class="accessmanager-form-group">
                <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SUBJECT_GROUP') ?></label>
                <select id="iblock-group">
                    <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_GROUP') ?></option>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= (int)$group['ID'] ?>"><?= htmlspecialcharsbx($group['NAME']) ?> [<?= $group['ID'] ?>]</option>
                    <?php endforeach; ?>
                </select>
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
        </div>

        <!-- ПАНЕЛЬ 2: Расширенный режим (BX.Access) -->
        <div class="accessmanager-mode-panel" id="iblocks-mode-extended" style="display: none;">
            <div class="accessmanager-info-box">
                ⚠️ <strong>Расширенный режим (РРУП)</strong><br>
                Выбранные инфоблоки используют Ролевые Разрешения Уровня Пользователя.
                Вы можете назначать права пользователям, группам и подразделениям.
            </div>

            <div class="accessmanager-form-group">
                <label>Выбранные субъекты:</label>
                <div class="accessmanager-selected-subjects" id="iblocks-mode-extended-subjects">
                    <p style="color: #888;">Нажмите кнопку ниже для добавления субъектов</p>
                </div>
            </div>

            <div class="accessmanager-form-group">
                <label><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERMISSION_LEVEL') ?></label>
                <select id="iblock-permission-extended">
                    <option value=""><?= Loc::getMessage('LOCAL_ACCESSMANAGER_SELECT_PERMISSION') ?></option>
                    <option value="D"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_DENIED') ?></option>
                    <option value="R"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_READ') ?></option>
                    <option value="W"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_WRITE') ?></option>
                    <option value="X"><?= Loc::getMessage('LOCAL_ACCESSMANAGER_PERM_FULL') ?></option>
                </select>
            </div>

            <div class="accessmanager-buttons">
                <button type="button" class="accessmanager-btn accessmanager-btn-success" onclick="AccessManager.openAccessDialog('iblocks')">
                    ➕ Добавить субъектов (BX.Access)
                </button>
                <button type="button" class="accessmanager-btn accessmanager-btn-danger" onclick="AccessManager.removeAllSubjects('iblocks')">
                    ❌ Удалить всех
                </button>
            </div>

            <div class="accessmanager-buttons" style="margin-top: 15px;">
                <button type="button"
                        class="accessmanager-btn accessmanager-btn-primary"
                        onclick="AccessManager.applyBXAccessPermissions('iblocks')"
                        style="font-size: 16px; padding: 12px 24px;">
                    ✅ Применить права для выбранных субъектов
                </button>
            </div>
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

        <!-- BX.Access Integration Section (НОВАЯ РЕАЛИЗАЦИЯ) -->
        <div class="accessmanager-bx-access" id="iblocks-bx-access">
            <h4>BX.Access: Расширенный выбор субъектов</h4>
            <div class="accessmanager-form-group">
                <label>Выбранные субъекты:</label>
                <div class="accessmanager-selected-subjects" id="iblocks-selected-subjects">
                    <p style="color: #888;">Нажмите кнопку ниже для выбора пользователей, групп или подразделений</p>
                </div>
            </div>
            <div class="accessmanager-buttons">
                <button type="button" class="accessmanager-btn accessmanager-btn-success" onclick="AccessManager.openAccessDialog('iblocks')">
                    ➕ Открыть диалог BX.Access
                </button>
                <button type="button" class="accessmanager-btn accessmanager-btn-danger" onclick="AccessManager.removeSelectedSubjects('iblocks')">
                    ❌ Удалить всех выбранных
                </button>
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

        <!-- BX.Access Integration Section (НОВАЯ РЕАЛИЗАЦИЯ) -->
        <div class="accessmanager-bx-access" id="files-bx-access">
            <h4>BX.Access: Расширенный выбор субъектов</h4>
            <div class="accessmanager-form-group">
                <label>Выбранные субъекты:</label>
                <div class="accessmanager-selected-subjects" id="files-selected-subjects">
                    <p style="color: #888;">Нажмите кнопку ниже для выбора пользователей, групп или подразделений</p>
                </div>
            </div>
            <div class="accessmanager-buttons">
                <button type="button" class="accessmanager-btn accessmanager-btn-success" onclick="AccessManager.openAccessDialog('files')">
                    ➕ Открыть диалог BX.Access
                </button>
                <button type="button" class="accessmanager-btn accessmanager-btn-danger" onclick="AccessManager.removeSelectedSubjects('files')">
                    ❌ Удалить всех выбранных
                </button>
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
    selectedSubjects: {
        iblocks: [],
        files: []
    },
    currentAccessMode: {
        iblocks: 'standard',
        files: 'standard'
    },

    // Переключение режима (Стандартный / Расширенный)
    setMode: function(mode, modeType) {
        console.log('setMode called:', mode, modeType);

        // Проверяем, есть ли выбранные инфоблоки
        const selected = this.getSelected(mode);
        if (selected.length === 0) {
            alert('Пожалуйста, выберите инфоблоки слева');
            return;
        }

        // Проверяем, есть ли среди выбранных инфоблоки с расширенным режимом
        const hasExtendedMode = this.checkSelectedIblocksMode(mode);

        if (modeType === 'extended' && !hasExtendedMode) {
            alert('Расширенный режим доступен только для инфоблоков с включенной РРУП (Ролевые Разрешения Уровня Пользователя).\n\nВыбранные инфоблоки не имеют расширенного режима (отмечены значком ⚠️).');
            return;
        }

        // Скрыть обе панели
        const standardPanel = document.getElementById(mode + '-mode-standard');
        const extendedPanel = document.getElementById(mode + '-mode-extended');

        if (standardPanel) standardPanel.style.display = 'none';
        if (extendedPanel) extendedPanel.style.display = 'none';

        // Показать выбранную панель
        if (modeType === 'standard' && standardPanel) {
            standardPanel.style.display = 'block';
        } else if (modeType === 'extended' && extendedPanel) {
            extendedPanel.style.display = 'block';
        }

        // Обновить активную кнопку
        document.querySelectorAll('#' + mode + '-mode-selector .accessmanager-mode-tab').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.mode === modeType) {
                btn.classList.add('active');
            }
        });

        // Сохранить текущий режим
        this.currentAccessMode[mode] = modeType;

        console.log('Mode switched to:', modeType);
    },

    // Проверка, есть ли среди выбранных инфоблоков с расширенным режимом
    checkSelectedIblocksMode: function(mode) {
        if (mode !== 'iblocks') return false;

        const selectedCheckboxes = document.querySelectorAll('#iblocks-tree .accessmanager-tree-checkbox:checked[data-type="iblock"]');

        for (let checkbox of selectedCheckboxes) {
            const node = checkbox.closest('.accessmanager-tree-node');
            if (node && node.dataset.extendedMode === '1') {
                return true;
            }
        }

        return false;
    },

    // Обновление видимости селектора режимов
    updateModeSelector: function(mode) {
        if (mode !== 'iblocks') return;

        const selector = document.getElementById('iblocks-mode-selector');
        if (!selector) return;

        const hasExtendedMode = this.checkSelectedIblocksMode(mode);

        if (hasExtendedMode) {
            // Показать селектор режимов
            selector.style.display = 'block';
        } else {
            // Скрыть селектор, переключить на стандартный режим
            selector.style.display = 'none';
            this.setMode(mode, 'standard');
        }
    },

    // Удаление всех выбранных субъектов (для расширенного режима)
    removeAllSubjects: function(mode) {
        if (!confirm('Удалить всех выбранных субъектов?')) {
            return;
        }

        const container = document.getElementById(mode + '-mode-extended-subjects');
        if (container) {
            container.innerHTML = '<p style="color: #888;">Нажмите кнопку ниже для добавления субъектов</p>';
        }

        this.selectedSubjects[mode] = [];
        console.log('All subjects removed for mode:', mode);
    },

    // Переключение типа субъекта (старый метод, теперь не используется)
    toggleSubjectType: function(mode, type) {
        const prefix = mode === 'iblocks' ? 'iblock' : 'file';
        document.getElementById(prefix + '-group-select').style.display = type === 'group' ? '' : 'none';
        document.getElementById(prefix + '-user-select').style.display = type === 'user' ? '' : 'none';
    },

    // НОВЫЙ МЕТОД: Открытие диалога BX.Access
    openAccessDialog: function(mode) {
        console.log('openAccessDialog called for mode:', mode);

        if (typeof BX === 'undefined' || typeof BX.Access === 'undefined') {
            alert('BX.Access не инициализирован. Убедитесь, что подключен /bitrix/js/main/core/core_access.js');
            console.error('BX.Access is not loaded');
            return;
        }

        // КРИТИЧНО: Уникальный bind ID для каждого режима и времени
        const bind = 'accessmanager_' + mode + '_' + Date.now();

        try {
            BX.Access.ShowForm({
                // 1. Уникальный bind ID
                bind: bind,

                // 2. Показывать уже выбранные элементы
                showSelected: true,

                // 3. Указать доступные провайдеры
                items: [
                    {entityType: 'users', title: 'Пользователи'},
                    {entityType: 'groups', title: 'Группы'},
                    {entityType: 'departments', title: 'Подразделения'}
                ],

                // 4. Callback при выборе
                callback: (selected) => {
                    console.log('BX.Access callback received:', selected);
                    this.onSubjectsSelected(mode, selected);
                },

                // 5. Опциональные параметры
                useContainer: true,
                multiple: true,
                enableAll: false,
                enableUsers: true,
                enableDepartments: true,
                enableSonetgroups: true
            });

            console.log('BX.Access dialog opened successfully');
        } catch (err) {
            console.error('Error opening BX.Access dialog:', err);
            alert('Ошибка открытия диалога BX.Access: ' + err.message);
        }
    },

    // НОВЫЙ МЕТОД: Обработка выбранных субъектов
    onSubjectsSelected: function(mode, selected) {
        console.log('onSubjectsSelected called:', mode, selected);

        if (!selected || Object.keys(selected).length === 0) {
            console.log('No subjects selected');
            return;
        }

        const subjects = [];

        // BX.Access возвращает объект формата:
        // {
        //   'users': { '1': {id: '1', name: 'Иван Петров', ...}, '2': {...} },
        //   'groups': { '5': {id: '5', name: 'Менеджеры', ...} },
        //   'departments': { '10': {id: '10', name: 'Отдел продаж', ...} }
        // }
        for (let provider in selected) {
            for (let id in selected[provider]) {
                const item = selected[provider][id];

                // КРИТИЧНО: Преобразуем в единый консистентный формат
                subjects.push({
                    provider: provider,           // 'users', 'groups', 'departments', 'sonetgroups'
                    id: id,                       // ID субъекта
                    name: item.name || item.title || item.label || ('ID: ' + id),
                    type: this.mapProviderToType(provider),  // 'user', 'group', 'department'

                    // Дополнительная информация для отображения
                    avatar: item.avatar || null,
                    email: item.email || null,
                    position: item.position || null
                });
            }
        }

        console.log('Processed subjects:', subjects);

        // Сохранить выбранные субъекты
        this.selectedSubjects[mode] = subjects;

        // Обновить отображение
        this.updateSelectedSubjectsDisplay(mode, subjects);

        // НОВОЕ: Автоматически установить права по умолчанию
        // После выбора субъектов предлагаем выбрать уровень прав
        const permissionSelect = document.getElementById('iblock-permission-extended');
        if (permissionSelect && permissionSelect.value) {
            // Если права уже выбраны - можем сразу применить
            console.log('Permission already selected:', permissionSelect.value);
        }
    },

    // НОВЫЙ ВСПОМОГАТЕЛЬНЫЙ МЕТОД: Маппинг типов провайдеров
    mapProviderToType: function(provider) {
        const map = {
            'users': 'user',
            'groups': 'group',
            'departments': 'department',
            'sonetgroups': 'group'  // Социальные группы трактуем как обычные группы
        };
        return map[provider] || 'user';
    },

    // НОВЫЙ МЕТОД: Отображение выбранных субъектов
    updateSelectedSubjectsDisplay: function(mode, subjects) {
        console.log('updateSelectedSubjectsDisplay called:', mode, subjects);

        // Для режима инфоблоков используем контейнер расширенного режима
        const containerId = mode === 'iblocks' ? mode + '-mode-extended-subjects' : mode + '-selected-subjects';
        const container = document.getElementById(containerId);

        if (!container) {
            console.error('Container not found:', containerId);
            return;
        }

        if (!subjects || subjects.length === 0) {
            container.innerHTML = '<p style="color: #888;">Нажмите кнопку ниже для добавления субъектов</p>';
            this.selectedSubjects[mode] = [];
            return;
        }

        // Улучшенные иконки для разных типов провайдеров
        const providerIcons = {
            'users': '👤',
            'groups': '👥',
            'sonetgroups': '🔵',  // Отличаем соц.группы
            'departments': '🏢'
        };

        const providerLabels = {
            'users': 'Пользователь',
            'groups': 'Группа',
            'sonetgroups': 'Соц.группа',
            'departments': 'Подразделение'
        };

        let html = '<div class="accessmanager-subject-list">';

        subjects.forEach((subject, index) => {
            const icon = providerIcons[subject.provider] || '❓';
            const label = providerLabels[subject.provider] || subject.provider;
            const escapedName = this.htmlEscape(subject.name);
            const escapedProvider = this.htmlEscape(subject.provider);
            const escapedId = this.htmlEscape(subject.id);

            // КРИТИЧНО: Используем data-атрибуты для удаления
            html += `<div class="accessmanager-subject-item"
                          data-provider="${escapedProvider}"
                          data-id="${escapedId}"
                          data-index="${index}">
                <span class="accessmanager-subject-icon">${icon}</span>
                <span class="accessmanager-subject-name">${escapedName}</span>
                <span class="accessmanager-subject-type">(${label})</span>
                <button type="button"
                        class="accessmanager-subject-remove"
                        onclick="AccessManager.removeSubject('${mode}', '${escapedProvider}', '${escapedId}')"
                        title="Удалить">×</button>
            </div>`;
        });

        html += '</div>';

        // НОВОЕ: Показать количество выбранных субъектов
        html += `<div class="accessmanager-subject-count" style="margin-top: 10px; font-size: 12px; color: #666;">
            Выбрано субъектов: <strong>${subjects.length}</strong>
        </div>`;

        container.innerHTML = html;
        this.selectedSubjects[mode] = subjects;

        console.log('Display updated. Total subjects:', subjects.length);
    },

    // НОВЫЙ МЕТОД: Удаление всех выбранных субъектов
    removeSelectedSubjects: function(mode) {
        if (!confirm('Удалить всех выбранных субъектов?')) {
            return;
        }

        this.updateSelectedSubjectsDisplay(mode, []);
        console.log('All subjects removed for mode:', mode);
    },

    // НОВЫЙ МЕТОД: Удаление одного субъекта
    removeSubject: function(mode, provider, id) {
        console.log('removeSubject called:', mode, provider, id);

        const subjects = this.selectedSubjects[mode] || [];

        // КРИТИЧНО: Фильтруем по provider И id (строгое соответствие)
        const filtered = subjects.filter(s => {
            // Приводим к строке для сравнения
            const sameProvider = String(s.provider) === String(provider);
            const sameId = String(s.id) === String(id);
            return !(sameProvider && sameId);
        });

        console.log('Before removal:', subjects.length, 'After:', filtered.length);

        // Обновляем отображение с новым списком
        this.updateSelectedSubjectsDisplay(mode, filtered);

        // НОВОЕ: Если список пуст - сбросить выбор прав
        if (filtered.length === 0) {
            const permissionSelect = document.getElementById(
                mode === 'iblocks' ? 'iblock-permission-extended' : 'file-permission'
            );
            if (permissionSelect) {
                permissionSelect.value = '';
            }
        }

        console.log('Subject removed:', provider, id);
    },

    // НОВЫЙ МЕТОД: Применить права для выбранных BX.Access субъектов
    applyBXAccessPermissions: function(mode) {
        console.log('applyBXAccessPermissions called for mode:', mode);

        // 1. Получить выбранные инфоблоки
        const selected = this.getSelected(mode);
        if (selected.length === 0) {
            alert('Пожалуйста, выберите инфоблоки слева');
            return;
        }

        // 2. Получить выбранных субъектов
        const subjects = this.selectedSubjects[mode] || [];
        if (subjects.length === 0) {
            alert('Пожалуйста, выберите субъектов через BX.Access');
            return;
        }

        // 3. Получить уровень прав
        const permissionSelect = document.getElementById('iblock-permission-extended');
        const permission = permissionSelect ? permissionSelect.value : '';
        if (!permission) {
            alert('Пожалуйста, выберите уровень прав');
            return;
        }

        // 4. Подтверждение
        if (!confirm(`Применить права "${permission}" для ${subjects.length} субъект(ов) на ${selected.length} инфоблок(ов)?`)) {
            return;
        }

        // 5. Отправить запрос на сервер
        const progressEl = document.getElementById(mode + '-progress');
        const resultEl = document.getElementById(mode + '-result');

        if (progressEl) progressEl.style.display = 'block';
        if (resultEl) resultEl.style.display = 'none';

        fetch('/bitrix/admin/local_accessmanager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=apply_bx_access_subjects&sessid=' + this.sessid +
                  '&mode=' + encodeURIComponent(mode) +
                  '&selected=' + encodeURIComponent(JSON.stringify(selected)) +
                  '&subjects=' + encodeURIComponent(JSON.stringify(subjects)) +
                  '&permission=' + encodeURIComponent(permission)
        })
        .then(r => r.json())
        .then(data => {
            if (progressEl) progressEl.style.display = 'none';

            if (data.success) {
                if (resultEl) {
                    resultEl.className = 'accessmanager-result success';
                    resultEl.innerHTML = `Успешно обработано: ${data.successCount} операций`;
                    if (data.errors && data.errors.length > 0) {
                        resultEl.innerHTML += `<br>Ошибок: ${data.errors.length}`;
                    }
                    resultEl.style.display = 'block';
                }
                console.log('Permissions applied successfully:', data);
            } else {
                if (resultEl) {
                    resultEl.className = 'accessmanager-result error';
                    resultEl.innerHTML = data.error || 'Ошибка применения';
                    resultEl.style.display = 'block';
                }
                console.error('Error applying permissions:', data);
            }
        })
        .catch(err => {
            if (progressEl) progressEl.style.display = 'none';
            if (resultEl) {
                resultEl.className = 'accessmanager-result error';
                resultEl.innerHTML = 'Ошибка: ' + err.message;
                resultEl.style.display = 'block';
            }
            console.error('AJAX error:', err);
        });
    },

    // Вспомогательная функция: Экранирование HTML
    htmlEscape: function(str) {
        if (typeof str !== 'string') {
            str = String(str);
        }
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
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

        // Обновить видимость селектора режимов
        this.updateModeSelector('iblocks');
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

        // Обновить видимость селектора режимов для инфоблоков
        if (type === 'iblock' || type === 'iblock_type') {
            this.updateModeSelector('iblocks');
        }
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
        console.log('Preview called for mode:', mode); // ОТЛАДКА
        this.currentMode = mode;
        
        const selected = this.getSelected(mode);
        const subject = this.getSubject(mode);
        const permission = this.getPermission(mode);
        
        console.log('Preview data:', {selected, subject, permission}); // ОТЛАДКА
        
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
        
        // Сохраняем данные ДО отправки запроса
        this.previewData = {mode, selected, subject, permission};
        console.log('Preview data saved:', this.previewData); // ОТЛАДКА
        
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
        .then(r => {
            console.log('Preview response status:', r.status); // ОТЛАДКА
            return r.text();
        })
        .then(text => {
            console.log('Preview response text:', text); // ОТЛАДКА
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e); // ОТЛАДКА
                throw new Error('Ответ сервера не является JSON: ' + text.substring(0, 100));
            }
            return data;
        })
        .then(data => {
            console.log('Preview data received:', data); // ОТЛАДКА
            if (data.success) {
                this.renderPreview(data.preview);
            } else {
                document.getElementById('preview-content').innerHTML = '<p style="color: #e74c3c;">' + (data.error || 'Ошибка') + '</p>';
            }
        })
        .catch(err => {
            console.error('Preview error:', err); // ОТЛАДКА
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
        console.log('applyFromPreview called, previewData:', this.previewData); // ОТЛАДКА
        
        if (!this.previewData) {
            console.error('previewData is null!'); // ОТЛАДКА
            alert('Ошибка: данные превью не найдены. Попробуйте открыть превью заново.');
            return;
        }
        
        // Сохраняем данные ДО закрытия окна (closePreview обнуляет previewData)
        const savedData = {
            mode: this.previewData.mode,
            selected: this.previewData.selected,
            subject: this.previewData.subject,
            permission: this.previewData.permission
        };
        
        console.log('Saved data:', savedData); // ОТЛАДКА
        
        this.closePreview();
        
        console.log('Calling apply with saved data'); // ОТЛАДКА
        this.apply(savedData.mode, savedData.selected, savedData.subject, savedData.permission);
    },
		
		// Применить права
		apply: function(mode, selected, subject, permission) {
			console.log('Apply called:', {mode, selected, subject, permission}); // ОТЛАДКА
			
			const progressEl = document.getElementById(mode + '-progress');
			const resultEl = document.getElementById(mode + '-result');
			
			if (!progressEl || !resultEl) {
				console.error('Elements not found:', mode); // ОТЛАДКА
				alert('Ошибка: элементы интерфейса не найдены');
				return;
			}
			
			progressEl.style.display = 'block';
			resultEl.style.display = 'none';
			resultEl.className = 'accessmanager-result';
			
			const requestBody = 'action=apply&sessid=' + this.sessid + 
				  '&mode=' + mode + 
				  '&selected=' + encodeURIComponent(JSON.stringify(selected)) +
				  '&subject=' + encodeURIComponent(JSON.stringify(subject)) +
				  '&permission=' + permission;
			
			console.log('Sending request:', requestBody); // ОТЛАДКА
			
			fetch('/bitrix/admin/local_accessmanager.php', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: requestBody
			})
			.then(response => {
				console.log('Response status:', response.status); // ОТЛАДКА
				if (!response.ok) {
					throw new Error('HTTP error ' + response.status);
				}
				return response.text();
			})
			.then(text => {
				console.log('Response text:', text); // ОТЛАДКА
				let data;
				try {
					data = JSON.parse(text);
				} catch (e) {
					console.error('JSON parse error:', e, text); // ОТЛАДКА
					throw new Error('Ответ сервера не является JSON: ' + text.substring(0, 100));
				}
				return data;
			})
			.then(data => {
				console.log('Parsed data:', data); // ОТЛАДКА
				progressEl.style.display = 'none';
				
				if (data.success) {
					resultEl.className = 'accessmanager-result success';
					resultEl.innerHTML = 'Успешно обработано: ' + data.successCount;
					if (data.errors && data.errors.length > 0) {
						resultEl.innerHTML += '<br>Ошибок: ' + data.errors.length;
						console.error('Errors:', data.errors); // ОТЛАДКА
					}
				} else {
					resultEl.className = 'accessmanager-result error';
					resultEl.innerHTML = data.error || 'Ошибка применения';
				}
				resultEl.style.display = '';
			})
			.catch(err => {
				console.error('Fetch error:', err); // ОТЛАДКА
				progressEl.style.display = 'none';
				resultEl.className = 'accessmanager-result error';
				resultEl.innerHTML = 'Ошибка: ' + err.message;
				resultEl.style.display = '';
				alert('Ошибка: ' + err.message); // Показываем алерт для отладки
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
            // Try IndexedDB first for faster results
            if (window.BXFinder && BXFinder.isInitialized()) {
                BXFinder.search(query).then(results => {
                    if (results && results.length > 0) {
                        const select = input.parentElement.querySelector('select');
                        select.innerHTML = '<option value="">-- Выберите пользователя --</option>';
                        results.forEach(u => {
                            select.innerHTML += '<option value="' + u.id + '">' + u.name + ' (' + (u.email || u.login || u.id) + ')</option>';
                        });
                        return;
                    }
                    // Fallback to server if no results in cache
                    this.searchUsersServer(query, input);
                }).catch(() => {
                    // Fallback to server on error
                    this.searchUsersServer(query, input);
                });
            } else {
                // Fallback to server if IndexedDB not available
                this.searchUsersServer(query, input);
            }
        }, 300);
    },

    // Серверный поиск пользователей (fallback)
    searchUsersServer: function(query, input) {
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
    }
};

// ==============================================================
// BX.Finder IndexedDB Integration
// ==============================================================

const BXFinder = {
    DB_NAME: 'BitrixAccessManager',
    DB_VERSION: 1,
    CACHE_TTL: {
        subjects: 24 * 60 * 60 * 1000,      // 24 hours
        permissions: 1 * 60 * 60 * 1000,    // 1 hour
        search_index: 30 * 60 * 1000         // 30 minutes
    },

    db: null,
    initialized: false,

    /**
     * Initialize IndexedDB
     */
    init: function() {
        const self = this;

        return new Promise(function(resolve, reject) {
            if (self.db) {
                resolve(self.db);
                return;
            }

            // Check if IndexedDB is supported
            if (!window.indexedDB) {
                console.warn('IndexedDB not supported, falling back to server search');
                reject('IndexedDB not supported');
                return;
            }

            const request = indexedDB.open(self.DB_NAME, self.DB_VERSION);

            request.onerror = function() {
                console.error('IndexedDB open error:', request.error);
                reject(request.error);
            };

            request.onupgradeneeded = function(event) {
                const db = event.target.result;

                // Store 1: subjects (users and groups)
                if (!db.objectStoreNames.contains('subjects')) {
                    const subjectsStore = db.createObjectStore('subjects', { keyPath: 'id' });
                    subjectsStore.createIndex('provider', 'provider', { unique: false });
                    subjectsStore.createIndex('name', 'name', { unique: false });
                    subjectsStore.createIndex('timestamp', 'timestamp', { unique: false });
                    subjectsStore.createIndex('provider_timestamp', ['provider', 'timestamp'], { unique: false });
                }

                // Store 2: permissions cache
                if (!db.objectStoreNames.contains('permissions')) {
                    const permStore = db.createObjectStore('permissions', { keyPath: 'id' });
                    permStore.createIndex('subject_id', 'subject_id', { unique: false });
                    permStore.createIndex('object_id', 'object_id', { unique: false });
                    permStore.createIndex('timestamp', 'timestamp', { unique: false });
                    permStore.createIndex('subject_object', ['subject_id', 'object_id'], { unique: false });
                }

                // Store 3: search index cache
                if (!db.objectStoreNames.contains('search_index')) {
                    const searchStore = db.createObjectStore('search_index', { keyPath: 'query_hash' });
                    searchStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };

            request.onsuccess = function(event) {
                self.db = event.target.result;
                self.initialized = true;
                console.log('IndexedDB initialized successfully');
                resolve(self.db);
            };
        });
    },

    /**
     * Check if IndexedDB is initialized
     */
    isInitialized: function() {
        return this.initialized && this.db !== null;
    },

    /**
     * Search in IndexedDB cache
     */
    search: function(query) {
        const self = this;

        return new Promise(function(resolve, reject) {
            if (!self.db) {
                reject('IndexedDB not initialized');
                return;
            }

            const normalizedQuery = query.toLowerCase().trim();

            if (!normalizedQuery || normalizedQuery.length < 2) {
                resolve([]);
                return;
            }

            // Generate hash for search index
            const queryHash = self.simpleHash(normalizedQuery);

            // Step 1: Check search index cache
            const transaction = self.db.transaction(['search_index'], 'readonly');
            const store = transaction.objectStore('search_index');
            const getRequest = store.get(queryHash);

            getRequest.onsuccess = function() {
                const cached = getRequest.result;

                // Check if cache is fresh (< 30 minutes)
                if (cached && (Date.now() - cached.timestamp) < self.CACHE_TTL.search_index) {
                    console.log('Search from cache:', normalizedQuery, '(' + cached.results.length + ' results)');
                    resolve(cached.results);
                    return;
                }

                // Step 2: Search in subjects store
                self.searchInSubjects(normalizedQuery).then(function(results) {
                    // Cache the results
                    self.cacheSearchResults(queryHash, normalizedQuery, results);
                    resolve(results);
                }).catch(reject);
            };

            getRequest.onerror = function() {
                // Fallback to direct search
                self.searchInSubjects(normalizedQuery).then(resolve).catch(reject);
            };
        });
    },

    /**
     * Search directly in subjects store
     */
    searchInSubjects: function(query) {
        const self = this;

        return new Promise(function(resolve, reject) {
            const transaction = self.db.transaction(['subjects'], 'readonly');
            const store = transaction.objectStore('subjects');
            const index = store.index('name');

            // Use IDBKeyRange for prefix search
            const range = IDBKeyRange.bound(query, query + '\uffff');
            const request = index.openCursor(range);
            const results = [];

            request.onsuccess = function(event) {
                const cursor = event.target.result;

                if (cursor) {
                    const subject = cursor.value;

                    // Check if not stale (< 24 hours)
                    if ((Date.now() - subject.timestamp) < self.CACHE_TTL.subjects) {
                        // Additional filtering for substring match
                        if (subject.name.toLowerCase().includes(query)) {
                            results.push({
                                id: subject.id,
                                name: subject.name,
                                provider: subject.provider,
                                email: subject.email,
                                login: subject.login
                            });
                        }
                    }

                    // Limit to 50 results for performance
                    if (results.length < 50) {
                        cursor.continue();
                    } else {
                        resolve(results);
                    }
                } else {
                    // End of results
                    resolve(results);
                }
            };

            request.onerror = function() {
                reject(request.error);
            };
        });
    },

    /**
     * Cache search results
     */
    cacheSearchResults: function(queryHash, query, results) {
        const self = this;

        try {
            const transaction = self.db.transaction(['search_index'], 'readwrite');
            const store = transaction.objectStore('search_index');

            store.put({
                query_hash: queryHash,
                query: query,
                results: results,
                resultCount: results.length,
                timestamp: Date.now(),
                ttl: self.CACHE_TTL.search_index
            });
        } catch (e) {
            console.error('Error caching search results:', e);
        }
    },

    /**
     * Save subjects to IndexedDB
     */
    saveSubjects: function(subjects) {
        const self = this;

        return new Promise(function(resolve, reject) {
            if (!self.db) {
                reject('IndexedDB not initialized');
                return;
            }

            const transaction = self.db.transaction(['subjects'], 'readwrite');
            const store = transaction.objectStore('subjects');

            subjects.forEach(function(subject) {
                // Add timestamp if not present
                if (!subject.timestamp) {
                    subject.timestamp = Date.now();
                }
                store.put(subject);
            });

            transaction.oncomplete = function() {
                console.log('Saved ' + subjects.length + ' subjects to IndexedDB');
                resolve();
            };

            transaction.onerror = function() {
                reject(transaction.error);
            };
        });
    },

    /**
     * Load all users from server and cache
     */
    loadAllUsers: function() {
        const self = this;

        return new Promise(function(resolve, reject) {
            fetch('/bitrix/admin/local_accessmanager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=load_all_users&sessid=' + AccessManager.sessid + '&limit=100&offset=0'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.users) {
                    // Save to IndexedDB
                    self.saveSubjects(data.users).then(function() {
                        resolve(data.users);
                    }).catch(reject);
                } else {
                    reject(data.error || 'Failed to load users');
                }
            })
            .catch(reject);
        });
    },

    /**
     * Simple hash function for query strings
     */
    simpleHash: function(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return 'q_' + Math.abs(hash).toString(36);
    },

    /**
     * Cleanup old data
     */
    cleanup: function() {
        const self = this;

        if (!self.db) return;

        const now = Date.now();

        // Cleanup subjects older than 24 hours
        self.cleanupStore('subjects', self.CACHE_TTL.subjects, now);

        // Cleanup permissions older than 1 hour
        self.cleanupStore('permissions', self.CACHE_TTL.permissions, now);

        // Cleanup search index older than 30 minutes
        self.cleanupStore('search_index', self.CACHE_TTL.search_index, now);
    },

    /**
     * Cleanup a specific store
     */
    cleanupStore: function(storeName, maxAge, now) {
        const self = this;

        try {
            const transaction = self.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const index = store.index('timestamp');

            const range = IDBKeyRange.upperBound(now - maxAge);
            const request = index.openCursor(range);

            let deletedCount = 0;

            request.onsuccess = function(event) {
                const cursor = event.target.result;

                if (cursor) {
                    cursor.delete();
                    deletedCount++;
                    cursor.continue();
                } else {
                    if (deletedCount > 0) {
                        console.log('Cleanup: Deleted ' + deletedCount + ' old records from ' + storeName);
                    }
                }
            };
        } catch (e) {
            console.error('Error during cleanup:', e);
        }
    }
};

// Initialize IndexedDB on page load
document.addEventListener('DOMContentLoaded', function() {
    BXFinder.init().then(function() {
        console.log('BX.Finder initialized');

        // Load users in background
        BXFinder.loadAllUsers().then(function(users) {
            console.log('Loaded ' + users.length + ' users into IndexedDB cache');
        }).catch(function(err) {
            console.error('Error loading users:', err);
        });

        // Setup cleanup interval (every hour)
        setInterval(function() {
            BXFinder.cleanup();
        }, 60 * 60 * 1000);

    }).catch(function(err) {
        console.warn('IndexedDB initialization failed, using server search:', err);
    });
});
</script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
