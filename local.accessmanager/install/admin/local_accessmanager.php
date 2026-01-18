<?php
/**
 * Административная страница модуля управления доступом
 * Этот файл копируется в /bitrix/admin/ при установке модуля
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

// Подключаем модуль
\Bitrix\Main\Loader::includeModule('local.accessmanager');

// Подключаем основной обработчик
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/local.accessmanager/admin/accessmanager_main.php';
