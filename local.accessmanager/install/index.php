<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;

Loc::loadMessages(__FILE__);

class local_accessmanager extends CModule
{
    public $MODULE_ID = 'local.accessmanager';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('LOCAL_ACCESSMANAGER_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('LOCAL_ACCESSMANAGER_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('LOCAL_ACCESSMANAGER_PARTNER_NAME');
        $this->PARTNER_URI = '';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!$this->isVersionCompatible()) {
            $APPLICATION->ThrowException(Loc::getMessage('LOCAL_ACCESSMANAGER_INSTALL_ERROR_VERSION'));
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallFiles();
        $this->InstallDB();

        return true;
    }

    public function DoUninstall()
    {
        global $APPLICATION, $step;

        $step = (int)$step;

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('LOCAL_ACCESSMANAGER_UNINSTALL_TITLE'),
                __DIR__ . '/unstep1.php'
            );
        } elseif ($step === 2) {
            $this->UnInstallFiles();
            
            if ($_REQUEST['savedata'] !== 'Y') {
                $this->UnInstallDB();
            }
            
            ModuleManager::unRegisterModule($this->MODULE_ID);
        }

        return true;
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );

        return true;
    }

    public function InstallDB()
    {
        global $DB;
        
        // Регистрируем обработчик для меню
        RegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, '\\Local\\AccessManager\\EventHandlers', 'onBuildGlobalMenu');

        // Таблица для snapshot'ов (rollback)
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `local_accessmanager_snapshots` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `CREATED_AT` datetime NOT NULL,
                `CREATED_BY` int(11) NOT NULL,
                `OPERATION_TYPE` varchar(50) NOT NULL,
                `OBJECT_TYPE` varchar(20) NOT NULL COMMENT 'iblock or file',
                `OBJECTS_DATA` longtext NOT NULL COMMENT 'JSON with objects and their previous permissions',
                `DESCRIPTION` varchar(255) DEFAULT NULL,
                `IS_ROLLED_BACK` char(1) DEFAULT 'N',
                PRIMARY KEY (`ID`),
                KEY `idx_created_at` (`CREATED_AT`),
                KEY `idx_created_by` (`CREATED_BY`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Таблица для журнала операций
        $DB->Query("
            CREATE TABLE IF NOT EXISTS `local_accessmanager_log` (
                `ID` int(11) NOT NULL AUTO_INCREMENT,
                `CREATED_AT` datetime NOT NULL,
                `USER_ID` int(11) NOT NULL,
                `ACTION` varchar(50) NOT NULL,
                `OBJECT_TYPE` varchar(20) NOT NULL,
                `OBJECT_ID` varchar(255) NOT NULL,
                `SUBJECT_TYPE` varchar(20) NOT NULL COMMENT 'user or group',
                `SUBJECT_ID` int(11) NOT NULL,
                `PERMISSION_OLD` varchar(50) DEFAULT NULL,
                `PERMISSION_NEW` varchar(50) DEFAULT NULL,
                `SNAPSHOT_ID` int(11) DEFAULT NULL,
                PRIMARY KEY (`ID`),
                KEY `idx_created_at` (`CREATED_AT`),
                KEY `idx_user_id` (`USER_ID`),
                KEY `idx_snapshot_id` (`SNAPSHOT_ID`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return true;
    }

    public function UnInstallDB()
    {
        global $DB;
        
        // Удаляем обработчик меню
        UnRegisterModuleDependences('main', 'OnBuildGlobalMenu', $this->MODULE_ID, '\\Local\\AccessManager\\EventHandlers', 'onBuildGlobalMenu');

        $DB->Query("DROP TABLE IF EXISTS `local_accessmanager_snapshots`");
        $DB->Query("DROP TABLE IF EXISTS `local_accessmanager_log`");

        return true;
    }

    private function isVersionCompatible(): bool
    {
        return CheckVersion(ModuleManager::getVersion('main'), '20.00.00');
    }

    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'W'],
            'reference' => [
                Loc::getMessage('LOCAL_ACCESSMANAGER_RIGHT_DENIED'),
                Loc::getMessage('LOCAL_ACCESSMANAGER_RIGHT_FULL'),
            ],
        ];
    }
}
