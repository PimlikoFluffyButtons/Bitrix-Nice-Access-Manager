<?php
/**
 * Прологовый файл модуля
 * Подключение автозагрузки классов
 */

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('local.accessmanager', [
    'Local\\AccessManager\\IblockPermissions' => 'lib/IblockPermissions.php',
    'Local\\AccessManager\\FilePermissions' => 'lib/FilePermissions.php',
    'Local\\AccessManager\\Logger' => 'lib/Logger.php',
]);
