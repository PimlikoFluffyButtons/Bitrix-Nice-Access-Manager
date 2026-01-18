<?php
/**
 * Регистрация пункта меню модуля
 */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

global $USER;

if (!$USER->IsAdmin()) {
    return [];
}

return [
    [
        'parent_menu' => 'global_menu_settings',
        'section' => 'local_accessmanager',
        'sort' => 1000,
        'text' => Loc::getMessage('LOCAL_ACCESSMANAGER_MENU_TITLE'),
        'title' => Loc::getMessage('LOCAL_ACCESSMANAGER_MENU_DESC'),
        'url' => 'local_accessmanager.php?lang=' . LANGUAGE_ID,
        'icon' => 'security_menu_icon',
        'page_icon' => 'security_page_icon',
        'items_id' => 'menu_local_accessmanager',
    ],
];