<?php
use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

global $APPLICATION;

?>
<form action="<?= $APPLICATION->GetCurPage() ?>" method="get">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="local.accessmanager">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    
    <div class="adm-info-message-wrap">
        <div class="adm-info-message">
            <?= Loc::getMessage('LOCAL_ACCESSMANAGER_UNINSTALL_WARNING') ?>
        </div>
    </div>
    
    <p>
        <label>
            <input type="checkbox" name="savedata" value="Y" checked>
            <?= Loc::getMessage('LOCAL_ACCESSMANAGER_UNINSTALL_SAVE_DATA') ?>
        </label>
    </p>
    
    <input type="submit" name="inst" value="<?= Loc::getMessage('LOCAL_ACCESSMANAGER_UNINSTALL_SUBMIT') ?>" class="adm-btn-save">
</form>