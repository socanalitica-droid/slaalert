<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    PluginSlaalertConfig::saveConfig($_POST);
    Session::addMessageAfterRedirect('Configuración guardada correctamente.', true, INFO);
    Html::back();
    exit();
}

Html::header('SLA Alert — Configuración', $_SERVER['REQUEST_URI'], 'config', 'PluginSlaalertConfig');

$config = PluginSlaalertConfig::getConfig();

$chk = fn($key) => !empty($config[$key]) ? 'checked' : '';
$val = fn($key) => htmlspecialchars($config[$key] ?? '');

$sections = [
    'sla_tto' => ['label' => 'SLA — Time To Own',      'icon' => 'ti ti-clock',        'color' => 'primary'],
    'sla_ttr' => ['label' => 'SLA — Time To Resolve',  'icon' => 'ti ti-clock-check',  'color' => 'success'],
    'ola_tto' => ['label' => 'OLA — Internal TTO',     'icon' => 'ti ti-circle-dot',   'color' => 'warning'],
    'ola_ttr' => ['label' => 'OLA — Internal TTR',     'icon' => 'ti ti-circle-check', 'color' => 'danger'],
];

?>
<div class="container-fluid mt-3" style="max-width:860px">

  <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
    <input type="hidden" name="_glpi_csrf_token" value="<?= htmlspecialchars(Session::getNewCSRFToken()) ?>">

    <!-- ── Webhook ─────────────────────────────────────────────────────── -->
    <div class="card mb-4">
      <div class="card-header fw-bold d-flex align-items-center gap-2">
        <i class="ti ti-webhook fs-5"></i>
        <span>Webhook de Google Spaces</span>
      </div>
      <div class="card-body">
        <label class="form-label fw-semibold">URL del Webhook</label>
        <input type="text"
               name="webhook_url"
               class="form-control font-monospace"
               value="<?= htmlspecialchars($config['webhook_url'] ?? '') ?>"
               placeholder="https://chat.googleapis.com/v1/spaces/…/messages?key=…">
        <div class="form-text mt-2">
          <span class="fw-semibold">Variables disponibles:</span>
          &nbsp;<code class="bg-light px-1 rounded">{ticket_id}</code>
          &nbsp;<code class="bg-light px-1 rounded">{ticket_name}</code>
          &nbsp;<code class="bg-light px-1 rounded">{tiempo_restante}</code>
          &nbsp;<code class="bg-light px-1 rounded">{tiempo_vencido}</code>
          &nbsp;<code class="bg-light px-1 rounded">{case_id}</code>
          &nbsp;<code class="bg-light px-1 rounded">{ticket_link}</code>
          &nbsp;<code class="bg-light px-1 rounded">{case_link}</code>
        </div>
      </div>
    </div>

    <!-- ── SLA / OLA sections ───────────────────────────────────────────── -->
    <?php foreach ($sections as $prefix => $meta): ?>
    <div class="card mb-4">
      <div class="card-header fw-bold d-flex align-items-center gap-2 bg-<?= $meta['color'] ?> bg-opacity-10 text-<?= $meta['color'] ?>">
        <i class="<?= $meta['icon'] ?> fs-5"></i>
        <span><?= $meta['label'] ?></span>
      </div>
      <div class="card-body p-0">

        <!-- Alerta previa -->
        <div class="p-3 border-bottom">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <i class="ti ti-bell-ringing text-warning fs-5"></i>
              <span class="fw-semibold">Alerta previa al vencimiento</span>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox"
                     name="<?= $prefix ?>_active" value="1"
                     id="<?= $prefix ?>_active" <?= $chk("{$prefix}_active") ?>>
              <label class="form-check-label" for="<?= $prefix ?>_active">Activo</label>
            </div>
          </div>
          <label class="form-label text-muted small mb-1">
            Mensaje &mdash; usa <code>{tiempo_restante}</code> para mostrar el tiempo que falta
          </label>
          <textarea name="<?= $prefix ?>_message"
                    class="form-control font-monospace"
                    rows="3"
                    placeholder="Ej: ⚠️ Ticket #{ticket_id} — {ticket_name} vence en {tiempo_restante}"><?= $val("{$prefix}_message") ?></textarea>
        </div>

        <!-- Alerta breach -->
        <div class="p-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <i class="ti ti-alert-triangle text-danger fs-5"></i>
              <span class="fw-semibold">Alerta de vencimiento <span class="badge bg-danger ms-1">BREACH</span></span>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox"
                     name="<?= $prefix ?>_breach_active" value="1"
                     id="<?= $prefix ?>_breach_active" <?= $chk("{$prefix}_breach_active") ?>>
              <label class="form-check-label" for="<?= $prefix ?>_breach_active">Activo</label>
            </div>
          </div>
          <label class="form-label text-muted small mb-1">
            Mensaje &mdash; usa <code>{tiempo_vencido}</code> — se envía una sola vez cuando el SLA ya venció
          </label>
          <textarea name="<?= $prefix ?>_breach_message"
                    class="form-control font-monospace"
                    rows="3"
                    placeholder="Ej: 🚨 Ticket #{ticket_id} — {ticket_name} lleva {tiempo_vencido} vencido"><?= $val("{$prefix}_breach_message") ?></textarea>
        </div>

      </div>
    </div>
    <?php endforeach; ?>

    <!-- ── Save ─────────────────────────────────────────────────────────── -->
    <div class="text-end mb-5">
      <button type="submit" name="update" value="1" class="btn btn-primary px-4">
        <i class="ti ti-device-floppy me-1"></i>Guardar configuración
      </button>
    </div>

  </form>
</div>


<?php Html::footer(); ?>
