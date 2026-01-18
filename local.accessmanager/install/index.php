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
		// Копируем административные файлы
		CopyDirFiles(
			__DIR__ . '/admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
			true,
			true
		);
		
		// Копируем файл меню
		CopyDirFiles(
			__DIR__ . '/../admin',
			$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/' . $this->MODULE_ID . '/admin',
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

		// Таблица для журнала операций
		$DB->Query("
			CREATE TABLE IF NOT EXISTS `local_accessmanager_log` (
				`ID` INT(11) NOT NULL AUTO_INCREMENT,
				`USER_ID` INT(11) NOT NULL,
				`OPERATION_TYPE` VARCHAR(50) NOT NULL,
				`OBJECT_TYPE` VARCHAR(50) NOT NULL,
				`OBJECT_ID` VARCHAR(255) NOT NULL,
				`SUBJECT_TYPE` VARCHAR(20) NOT NULL,
				`SUBJECT_ID` INT(11) NOT NULL,
				`OLD_PERMISSIONS` TEXT,
				`NEW_PERMISSIONS` TEXT,
				`CREATED_AT` DATETIME NOT NULL,
				PRIMARY KEY (`ID`),
				INDEX `ix_created` (`CREATED_AT`),
				INDEX `ix_user` (`USER_ID`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		// Таблица для snapshot'ов (rollback)
		$DB->Query("
			CREATE TABLE IF NOT EXISTS `local_accessmanager_snapshots` (
				`ID` INT(11) NOT NULL AUTO_INCREMENT,
				`BATCH_ID` VARCHAR(36) NOT NULL,
				`USER_ID` INT(11) NOT NULL,
				`OBJECT_TYPE` VARCHAR(50) NOT NULL,
				`OBJECT_ID` VARCHAR(255) NOT NULL,
				`PERMISSIONS_BEFORE` TEXT NOT NULL,
				`CREATED_AT` DATETIME NOT NULL,
				`ROLLED_BACK` TINYINT(1) DEFAULT 0,
				PRIMARY KEY (`ID`),
				INDEX `ix_batch` (`BATCH_ID`),
				INDEX `ix_created` (`CREATED_AT`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		return true;
	}

    public function UnInstallDB()
	{
    global $DB;

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
