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
            'sla_tto_message'       => "\u{23F0} *SLA en riesgo \u{2014} Primera Respuesta (TTO)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{26A0} Un analista debe *tomar el caso* en *{tiempo_restante}*\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'sla_tto_breach_active' => 1,
            'sla_tto_breach_message'=> "\u{1F6A8} *SLA VENCIDO \u{2014} Primera Respuesta (TTO)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{274C} Sin tomar *{tiempo_vencido}* (TTO = tiempo de primera respuesta)\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'sla_ttr_active'        => 1,
            'sla_ttr_message'       => "\u{23F0} *SLA en riesgo \u{2014} Tiempo de Resoluci\u{00F3}n (TTR)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{26A0} El ticket debe *resolverse* en *{tiempo_restante}*\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'sla_ttr_breach_active' => 1,
            'sla_ttr_breach_message'=> "\u{1F6A8} *SLA VENCIDO \u{2014} Tiempo de Resoluci\u{00F3}n (TTR)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{274C} Sin resolver *{tiempo_vencido}* (TTR = tiempo de resoluci\u{00F3}n)\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'ola_tto_active'        => 0,
            'ola_tto_message'       => "\u{23F0} *OLA en riesgo \u{2014} Primera Respuesta Interna (TTO)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{26A0} El equipo interno debe *tomar el caso* en *{tiempo_restante}*\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'ola_tto_breach_active' => 0,
            'ola_tto_breach_message'=> "\u{1F6A8} *OLA VENCIDO \u{2014} Primera Respuesta Interna (TTO)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{274C} Sin tomar *{tiempo_vencido}* (OLA interna \u{2014} primera respuesta)\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'ola_ttr_active'        => 0,
            'ola_ttr_message'       => "\u{23F0} *OLA en riesgo \u{2014} Resoluci\u{00F3}n Interna (TTR)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{26A0} El equipo interno debe *resolver el caso* en *{tiempo_restante}*\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
            'ola_ttr_breach_active' => 0,
            'ola_ttr_breach_message'=> "\u{1F6A8} *OLA VENCIDO \u{2014} Resoluci\u{00F3}n Interna (TTR)*\n\n\u{1F4CB} Ticket *#{ticket_id}*: _{ticket_name}_\n\u{274C} Sin resolver *{tiempo_vencido}* (OLA interna \u{2014} resoluci\u{00F3}n)\n\n\u{1F517} {ticket_link}\n\u{1F4C1} Caso SecOps: {case_link}",
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
            'mode'      => CronTask::MODE_EXTERNAL,
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
