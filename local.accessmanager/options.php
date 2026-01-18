<?php
/**
 * Настройки модуля - редирект на основную страницу управления
 */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

global $USER, $APPLICATION;

if (!$USER->IsAdmin()) {
    return;
}

Loc::loadMessages(__FILE__);

// Редирект на основную страницу модуля
LocalRedirect('/bitrix/admin/local_accessmanager.php?lang=' . LANGUAGE_ID);