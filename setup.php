<?php

define('PLUGIN_SLAALERT_VERSION', '1.0.0');
define('PLUGIN_SLAALERT_MIN_GLPI', '11.0');
define('PLUGIN_SLAALERT_MAX_GLPI', '12.0');

function plugin_init_slaalert() {
    global $PLUGIN_HOOKS;

    // Classes must be loaded before any reference to them (menu, cron, hooks)
    include_once(Plugin::getPhpDir('slaalert') . '/inc/config.class.php');
    include_once(Plugin::getPhpDir('slaalert') . '/inc/slaalert.class.php');

    $PLUGIN_HOOKS['csrf_compliant']['slaalert'] = true;

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['slaalert'] = ['config' => 'PluginSlaalertConfig'];
    }
}

function plugin_version_slaalert() {
    return [
        'name'         => 'SLA Alert',
        'version'      => PLUGIN_SLAALERT_VERSION,
        'author'       => 'SOC Team - Linktic',
        'license'      => 'GPL v2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SLAALERT_MIN_GLPI,
                'max' => PLUGIN_SLAALERT_MAX_GLPI,
            ]
        ]
    ];
}

function plugin_slaalert_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_SLAALERT_MIN_GLPI, 'lt')) {
        echo "This plugin requires GLPI >= " . PLUGIN_SLAALERT_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_slaalert_check_config() {
    return true;
}
