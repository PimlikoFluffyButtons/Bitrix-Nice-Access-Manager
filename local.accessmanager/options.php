<?php
/**
 * Настройки модуля и регистрация в меню админки
 */

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Регистрация пункта меню в событии OnBuildGlobalMenu
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'main',
    'OnBuildGlobalMenu',
    function (&$adminMenu, &$moduleMenu) {
        global $USER;
        
        if (!$USER->IsAdmin()) {
            return;
        }
        
        // Добавляем в раздел "Настройки"
        $moduleMenu[] = [
            'parent_menu' => 'global_menu_settings',
            'section' => 'local_accessmanager',
            'sort' => 1000,
            'text' => 'Управление доступом (массово)',
            'title' => 'Массовое управление правами доступа к инфоблокам и файлам',
            'url' => 'local_accessmanager.php',
            'icon' => 'security_menu_icon',
            'page_icon' => 'security_page_icon',
            'items_id' => 'menu_local_accessmanager',
        ];
    }
);
