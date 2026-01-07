<?php
require_once __DIR__ . '/_mayday_common.php';

$incident_id = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
$since = isset($_GET['since']) && $_GET['since'] !== '' ? (string)$_GET['since'] : null;

$incident = require_incident($conn, $incident_id);
$dept_id = (int)$incident['dept_id'];

$mayday = load_active_mayday($conn, $incident_id);
$mayday_id = $mayday ? (int)$mayday['id'] : 0;

$tacs = load_tac_channels($conn, $incident_id);
$statuses = load_mayday_statuses($conn, $dept_id);

$firefighters = $mayday_id ? load_mayday_firefighters($conn, $mayday_id) : [];
$log_items = $mayday_id ? load_mayday_log($conn, $incident_id, $mayday_id, $since) : [];

$last_log_ts = null;
if ($log_items) {
  $last = end($log_items);
  $last_log_ts = $last['event_ts'] ?? null;
}

$checklist = load_mayday_checklist($conn, $mayday_id ?: null);

json_out([
  'ok' => true,
  'incident' => ['id' => $incident_id, 'dept_id' => $dept_id],
  'mayday' => $mayday,
  'tac_channels' => $tacs,
  'statuses' => $statuses,
  'firefighters' => $firefighters,
  'log_items' => $log_items,
  'last_log_ts' => $last_log_ts,
  'checklist_items' => $checklist,
]);
