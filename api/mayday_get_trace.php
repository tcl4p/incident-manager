<?php
// Quick tracer for hangs in mayday_get
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." START\n", FILE_APPEND);

require_once __DIR__ . '/_mayday_common.php';
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after _mayday_common\n", FILE_APPEND);

$incident_id = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." incident_id=$incident_id\n", FILE_APPEND);

// 1) incident lookup
$incident = require_incident($conn, $incident_id);
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after require_incident dept_id=".(int)$incident['dept_id']."\n", FILE_APPEND);

// 2) active mayday
$mayday = load_active_mayday($conn, $incident_id);
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after load_active_mayday mayday_id=".($mayday['id'] ?? 'null')."\n", FILE_APPEND);

// 3) tacs
$tacs = load_tac_channels($conn, $incident_id);
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after load_tac_channels count=".count($tacs)."\n", FILE_APPEND);

// 4) statuses
$statuses = load_mayday_statuses($conn, (int)$incident['dept_id']);
file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after load_mayday_statuses count=".count($statuses)."\n", FILE_APPEND);

// 5) firefighters (only if mayday exists)
$firefighters = [];
if ($mayday) {
  $firefighters = load_mayday_firefighters($conn, (int)$mayday['id']);
  file_put_contents(__DIR__ . '/_trace_mayday.txt', date('c')." after load_mayday_firefighters count=".count($firefighters)."\n", FILE_APPEND);
}

json_out([
  'ok' => true,
  'trace' => 'completed',
  'incident_id' => $incident_id
]);
