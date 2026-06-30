<?php

class PluginSlaalertSlaAlert extends CommonGLPI {

    static $rightname = 'config';

    const GLPI_BASE_URL   = 'https://glpi.soc.linktic.com';
    const SECOPS_BASE_URL = 'https://linktic-soc.backstory.chronicle.security/cases';

    static function getTypeName($nb = 0) {
        return 'SLA Alert';
    }

    /**
     * Returns the largest "warn before deadline" threshold in minutes.
     * Escalation levels with negative execution_time fire before the deadline.
     */
    static function getThresholdForSla($sla_id, $table, $field) {
        global $DB;
        $levels = $DB->request([
            'SELECT' => ['execution_time'],
            'FROM'   => $table,
            'WHERE'  => [$field => $sla_id],
        ]);
        $max = 0;
        foreach ($levels as $level) {
            if ($level['execution_time'] < 0) {
                $abs = abs($level['execution_time']);
                if ($abs > $max) {
                    $max = $abs;
                }
            }
        }
        return $max / 60; // seconds → minutes
    }

    static function buildMessage($template, $ticket_id, $ticket_name, $minutes, $case_id = '') {
        $hours       = floor($minutes / 60);
        $mins        = $minutes % 60;
        $time        = $hours > 0 ? "{$hours}h {$mins}min" : "{$mins}min";
        $ticket_link = self::GLPI_BASE_URL . '/front/ticket.form.php?id=' . $ticket_id;
        $case_link   = $case_id !== '' ? self::SECOPS_BASE_URL . '/' . $case_id . '?filterOperator=And' : '';
        // Remove the SecOps line entirely when case_id is not filled in the ticket
        if ($case_link === '') {
            $template = preg_replace('/\n[^\n]*\{case_link\}/', '', $template);
        }
        return str_replace(
            ['{ticket_id}', '{ticket_name}', '{tiempo_restante}', '{tiempo_vencido}', '{case_id}', '{ticket_link}', '{case_link}'],
            [$ticket_id,    $ticket_name,    $time,               $time,              $case_id,    $ticket_link,    $case_link],
            $template
        );
    }

    /**
     * Case ID values come from the "Fields" plugin (glpi_plugin_fields_ticketsecops),
     * not from slaalert's own tables — fetched in bulk to avoid one query per ticket.
     */
    static function getCaseIds() {
        global $DB;
        $case_ids = [];
        if ($DB->tableExists('glpi_plugin_fields_ticketsecops')) {
            $rows = $DB->request([
                'SELECT' => ['items_id', 'caseidfield'],
                'FROM'   => 'glpi_plugin_fields_ticketsecops',
                'WHERE'  => ['itemtype' => 'Ticket'],
            ]);
            foreach ($rows as $row) {
                $case_ids[$row['items_id']] = $row['caseidfield'];
            }
        }
        return $case_ids;
    }

    static function sendWebhook($webhook_url, $message) {
        if (empty($webhook_url)) {
            return false;
        }
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['text' => $message]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $http_code >= 200 && $http_code < 300;
    }

    static function alreadySent($ticket_id, $alert_type) {
        global $DB;
        $result = $DB->request([
            'FROM'  => 'glpi_plugin_slaalert_sent',
            'WHERE' => ['tickets_id' => $ticket_id, 'alert_type' => $alert_type],
        ]);
        return count($result) > 0;
    }

    static function markSent($ticket_id, $alert_type) {
        global $DB;
        $DB->insert('glpi_plugin_slaalert_sent', [
            'tickets_id' => $ticket_id,
            'alert_type' => $alert_type,
        ]);
    }

    /**
     * Cron callback. Returns 0 = nothing done, 1 = work done.
     *
     * Two alert types per SLA/OLA field:
     *   - Warning  (SLA_TTO, SLA_TTR, OLA_TTO, OLA_TTR):
     *       fires while deadline is approaching (within escalation threshold window).
     *       A 10-min grace period handles cron timing gaps.
     *   - Breach   (SLA_TTO_B, SLA_TTR_B, OLA_TTO_B, OLA_TTR_B):
     *       fires once when the deadline has passed. Sent only one time per ticket.
     */
    static function cronSlaAlert(CronTask $task) {
        global $DB;

        $config = PluginSlaalertConfig::getConfig();
        if (empty($config) || empty($config['webhook_url'])) {
            return 0;
        }

        // Column names verified against glpi_tickets GLPI 11 schema
        $tickets = $DB->request([
            'SELECT' => [
                'id', 'name',
                'time_to_own',          'time_to_resolve',
                'slas_id_tto',          'slas_id_ttr',
                'olas_id_tto',          'olas_id_ttr',
                'internal_time_to_own', 'internal_time_to_resolve',
            ],
            'FROM'  => 'glpi_tickets',
            'WHERE' => ['status' => [1, 2, 3, 4]], // INCOMING, ASSIGNED, PLANNED, WAITING
        ]);

        // Grace period: alert even if cron runs slightly after the deadline (handles
        // timing gaps between cron executions). Must be > cron period (5 min).
        $grace = 10;

        $case_ids = self::getCaseIds();
        $sent     = 0;

        foreach ($tickets as $ticket) {
            $id      = $ticket['id'];
            $name    = $ticket['name'];
            $url     = $config['webhook_url'];
            $case_id = $case_ids[$id] ?? '';

            // ── SLA TTO ──────────────────────────────────────────────────────
            if (!empty($ticket['time_to_own']) && !empty($ticket['slas_id_tto'])) {
                $mins = (strtotime($ticket['time_to_own']) - time()) / 60;

                // Warning: approaching deadline
                if ($config['sla_tto_active']) {
                    $threshold = self::getThresholdForSla($ticket['slas_id_tto'], 'glpi_slalevels', 'slas_id');
                    if ($threshold > 0 && $mins > -$grace && $mins <= $threshold && !self::alreadySent($id, 'SLA_TTO')) {
                        $msg = self::buildMessage($config['sla_tto_message'], $id, $name, (int) max(0, $mins), $case_id);
                        if (self::sendWebhook($url, $msg)) {
                            self::markSent($id, 'SLA_TTO');
                            $sent++;
                        }
                    }
                }

                // Breach: deadline already passed — send once
                if ($config['sla_tto_breach_active'] && $mins < 0 && !self::alreadySent($id, 'SLA_TTO_B')) {
                    $msg = self::buildMessage($config['sla_tto_breach_message'], $id, $name, (int) abs($mins), $case_id);
                    if (self::sendWebhook($url, $msg)) {
                        self::markSent($id, 'SLA_TTO_B');
                        $sent++;
                    }
                }
            }

            // ── SLA TTR ──────────────────────────────────────────────────────
            if (!empty($ticket['time_to_resolve']) && !empty($ticket['slas_id_ttr'])) {
                $mins = (strtotime($ticket['time_to_resolve']) - time()) / 60;

                if ($config['sla_ttr_active']) {
                    $threshold = self::getThresholdForSla($ticket['slas_id_ttr'], 'glpi_slalevels', 'slas_id');
                    if ($threshold > 0 && $mins > -$grace && $mins <= $threshold && !self::alreadySent($id, 'SLA_TTR')) {
                        $msg = self::buildMessage($config['sla_ttr_message'], $id, $name, (int) max(0, $mins), $case_id);
                        if (self::sendWebhook($url, $msg)) {
                            self::markSent($id, 'SLA_TTR');
                            $sent++;
                        }
                    }
                }

                if ($config['sla_ttr_breach_active'] && $mins < 0 && !self::alreadySent($id, 'SLA_TTR_B')) {
                    $msg = self::buildMessage($config['sla_ttr_breach_message'], $id, $name, (int) abs($mins), $case_id);
                    if (self::sendWebhook($url, $msg)) {
                        self::markSent($id, 'SLA_TTR_B');
                        $sent++;
                    }
                }
            }

            // ── OLA TTO ──────────────────────────────────────────────────────
            if (!empty($ticket['olas_id_tto']) && !empty($ticket['internal_time_to_own'])) {
                $mins = (strtotime($ticket['internal_time_to_own']) - time()) / 60;

                if ($config['ola_tto_active']) {
                    $threshold = self::getThresholdForSla($ticket['olas_id_tto'], 'glpi_olalevels', 'olas_id');
                    if ($threshold > 0 && $mins > -$grace && $mins <= $threshold && !self::alreadySent($id, 'OLA_TTO')) {
                        $msg = self::buildMessage($config['ola_tto_message'], $id, $name, (int) max(0, $mins), $case_id);
                        if (self::sendWebhook($url, $msg)) {
                            self::markSent($id, 'OLA_TTO');
                            $sent++;
                        }
                    }
                }

                if ($config['ola_tto_breach_active'] && $mins < 0 && !self::alreadySent($id, 'OLA_TTO_B')) {
                    $msg = self::buildMessage($config['ola_tto_breach_message'], $id, $name, (int) abs($mins), $case_id);
                    if (self::sendWebhook($url, $msg)) {
                        self::markSent($id, 'OLA_TTO_B');
                        $sent++;
                    }
                }
            }

            // ── OLA TTR ──────────────────────────────────────────────────────
            if (!empty($ticket['olas_id_ttr']) && !empty($ticket['internal_time_to_resolve'])) {
                $mins = (strtotime($ticket['internal_time_to_resolve']) - time()) / 60;

                if ($config['ola_ttr_active']) {
                    $threshold = self::getThresholdForSla($ticket['olas_id_ttr'], 'glpi_olalevels', 'olas_id');
                    if ($threshold > 0 && $mins > -$grace && $mins <= $threshold && !self::alreadySent($id, 'OLA_TTR')) {
                        $msg = self::buildMessage($config['ola_ttr_message'], $id, $name, (int) max(0, $mins), $case_id);
                        if (self::sendWebhook($url, $msg)) {
                            self::markSent($id, 'OLA_TTR');
                            $sent++;
                        }
                    }
                }

                if ($config['ola_ttr_breach_active'] && $mins < 0 && !self::alreadySent($id, 'OLA_TTR_B')) {
                    $msg = self::buildMessage($config['ola_ttr_breach_message'], $id, $name, (int) abs($mins), $case_id);
                    if (self::sendWebhook($url, $msg)) {
                        self::markSent($id, 'OLA_TTR_B');
                        $sent++;
                    }
                }
            }
        }

        $task->addVolume($sent);
        return $sent > 0 ? 1 : 0;
    }
}
