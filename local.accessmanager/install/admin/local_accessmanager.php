<?php
/**
 * Административная страница модуля управления доступом
 */

// Сначала проверяем AJAX ДО подключения пролога
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    isset($_POST['action']) && !empty($_POST['action'])
);

if ($isAjax) {
    // Минимальная инициализация для AJAX
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
    
    // Подключаем модуль
    \Bitrix\Main\Loader::includeModule('local.accessmanager');
    
    // Подключаем AJAX-обработчик
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/local.accessmanager/admin/accessmanager_ajax.php';
    die();
}

// Обычная загрузка страницы
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

// Подключаем модуль
\Bitrix\Main\Loader::includeModule('local.accessmanager');

// Подключаем основной обработчик
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/local.accessmanager/admin/accessmanager_main.php';