<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Installs the Db::getRow() override on shops that already have the module.
 */
function upgrade_module_1_0_4($module)
{
    return $module->installOverrides();
}
