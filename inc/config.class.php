<?php

class PluginSlaalertConfig extends CommonGLPI {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return 'SLA Alert';
    }

    static function getMenuName() {
        return 'SLA Alert';
    }

    static function getMenuContent() {
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = '/plugins/slaalert/front/config.form.php';
        $menu['icon']  = 'fas fa-bell';
        return $menu;
    }

    static function getConfig() {
        global $DB;
        $result = $DB->request(['FROM' => 'glpi_plugin_slaalert_config', 'LIMIT' => 1]);
        foreach ($result as $row) {
            return $row;
        }
        return [];
    }

    static function saveConfig($input) {
        global $DB;
        $DB->update('glpi_plugin_slaalert_config', [
            'webhook_url'            => $input['webhook_url'] ?? '',
            'sla_tto_active'         => isset($input['sla_tto_active']) ? 1 : 0,
            'sla_tto_message'        => $input['sla_tto_message'] ?? '',
            'sla_tto_breach_active'  => isset($input['sla_tto_breach_active']) ? 1 : 0,
            'sla_tto_breach_message' => $input['sla_tto_breach_message'] ?? '',
            'sla_ttr_active'         => isset($input['sla_ttr_active']) ? 1 : 0,
            'sla_ttr_message'        => $input['sla_ttr_message'] ?? '',
            'sla_ttr_breach_active'  => isset($input['sla_ttr_breach_active']) ? 1 : 0,
            'sla_ttr_breach_message' => $input['sla_ttr_breach_message'] ?? '',
            'ola_tto_active'         => isset($input['ola_tto_active']) ? 1 : 0,
            'ola_tto_message'        => $input['ola_tto_message'] ?? '',
            'ola_tto_breach_active'  => isset($input['ola_tto_breach_active']) ? 1 : 0,
            'ola_tto_breach_message' => $input['ola_tto_breach_message'] ?? '',
            'ola_ttr_active'         => isset($input['ola_ttr_active']) ? 1 : 0,
            'ola_ttr_message'        => $input['ola_ttr_message'] ?? '',
            'ola_ttr_breach_active'  => isset($input['ola_ttr_breach_active']) ? 1 : 0,
            'ola_ttr_breach_message' => $input['ola_ttr_breach_message'] ?? '',
        ], ['id' => 1]);
        return true;
    }
}
