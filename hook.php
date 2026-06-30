<?php

function plugin_slaalert_install() {
    global $DB;

    include_once(Plugin::getPhpDir('slaalert') . '/inc/config.class.php');
    include_once(Plugin::getPhpDir('slaalert') . '/inc/slaalert.class.php');

    if (!$DB->tableExists('glpi_plugin_slaalert_config')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_slaalert_config` (
                `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `webhook_url` text NOT NULL,
                `sla_tto_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                `sla_tto_message` text NOT NULL,
                `sla_tto_breach_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                `sla_tto_breach_message` text NOT NULL,
                `sla_ttr_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                `sla_ttr_message` text NOT NULL,
                `sla_ttr_breach_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
                `sla_ttr_breach_message` text NOT NULL,
                `ola_tto_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                `ola_tto_message` text NOT NULL,
                `ola_tto_breach_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                `ola_tto_breach_message` text NOT NULL,
                `ola_ttr_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                `ola_ttr_message` text NOT NULL,
                `ola_ttr_breach_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
                `ola_ttr_breach_message` text NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC
        ");

        $DB->insert('glpi_plugin_slaalert_config', [
            'id'                    => 1,
            'webhook_url'           => '',
            'sla_tto_active'        => 1,
            'sla_tto_message'       => "🔴 *ALERTA TTO* Ticket #{ticket_id}: {ticket_name} — vence en {tiempo_restante}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'sla_tto_breach_active' => 1,
            'sla_tto_breach_message'=> "🚨 *VENCIDO TTO* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'sla_ttr_active'        => 1,
            'sla_ttr_message'       => "🟠 *ALERTA TTR* Ticket #{ticket_id}: {ticket_name} — vence en {tiempo_restante}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'sla_ttr_breach_active' => 1,
            'sla_ttr_breach_message'=> "🚨 *VENCIDO TTR* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'ola_tto_active'        => 0,
            'ola_tto_message'       => "🔵 *ALERTA OLA TTO* Ticket #{ticket_id}: {ticket_name} — vence en {tiempo_restante}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'ola_tto_breach_active' => 0,
            'ola_tto_breach_message'=> "🚨 *VENCIDO OLA TTO* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'ola_ttr_active'        => 0,
            'ola_ttr_message'       => "🟣 *ALERTA OLA TTR* Ticket #{ticket_id}: {ticket_name} — vence en {tiempo_restante}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
            'ola_ttr_breach_active' => 0,
            'ola_ttr_breach_message'=> "🚨 *VENCIDO OLA TTR* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}\n🔗 Ticket: {ticket_link}\n📁 Caso: {case_link}",
        ]);
    } else {
        // Migration: add breach columns if upgrading from a version without them
        $breach_columns = [
            'sla_tto_breach_active'  => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `sla_tto_message`",
            'sla_tto_breach_message' => "TEXT NOT NULL AFTER `sla_tto_breach_active`",
            'sla_ttr_breach_active'  => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `sla_ttr_message`",
            'sla_ttr_breach_message' => "TEXT NOT NULL AFTER `sla_ttr_breach_active`",
            'ola_tto_breach_active'  => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `ola_tto_message`",
            'ola_tto_breach_message' => "TEXT NOT NULL AFTER `ola_tto_breach_active`",
            'ola_ttr_breach_active'  => "TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `ola_ttr_message`",
            'ola_ttr_breach_message' => "TEXT NOT NULL AFTER `ola_ttr_breach_active`",
        ];
        foreach ($breach_columns as $col => $definition) {
            if (!$DB->fieldExists('glpi_plugin_slaalert_config', $col)) {
                $DB->doQuery("ALTER TABLE `glpi_plugin_slaalert_config` ADD COLUMN `$col` $definition");
            }
        }
        // Seed default breach messages for existing row
        $DB->update('glpi_plugin_slaalert_config', [
            'sla_tto_breach_message' => '🚨 *VENCIDO TTO* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}',
            'sla_ttr_breach_message' => '🚨 *VENCIDO TTR* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}',
            'ola_tto_breach_message' => '🚨 *VENCIDO OLA TTO* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}',
            'ola_ttr_breach_message' => '🚨 *VENCIDO OLA TTR* Ticket #{ticket_id}: {ticket_name} — venció hace {tiempo_vencido}',
        ], ['id' => 1]);
    }

    if (!$DB->tableExists('glpi_plugin_slaalert_sent')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_slaalert_sent` (
                `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
                `tickets_id` int UNSIGNED NOT NULL,
                `alert_type` varchar(12) NOT NULL,
                `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_alert` (`tickets_id`, `alert_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC
        ");
    }

    CronTask::register(
        'PluginSlaalertSlaAlert',
        'SlaAlert',
        60,
        [
            'comment'   => 'Send webhook alerts when SLA/OLA is about to expire or has breached',
            'mode'      => CronTask::MODE_INTERNAL,
            'allowmode' => 3,
            'state'     => CronTask::STATE_WAITING,
        ]
    );

    return true;
}

function plugin_slaalert_uninstall() {
    global $DB;

    CronTask::unregister('PluginSlaalertSlaAlert');

    foreach (['glpi_plugin_slaalert_config', 'glpi_plugin_slaalert_sent'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}
