<?php
require_once __DIR__ . '/_mayday_common.php';
/**
 * File: mayday_update.php
 * Version: 2026-1-1 12:00
 
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'POST required'], 405);
}

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$mayday_id = isset($_POST['mayday_id']) ? (int)$_POST['mayday_id'] : 0;

require_incident($conn, $incident_id);

$existing = load_mayday_by_id($conn, $incident_id, $mayday_id);
if (!$existing) {
  json_out(['ok'=>false,'error'=>'MAYDAY not found'], 404);
}

// Allowed fields
$fields = [];
$params = [];
$types = '';

$map = [
  'tac_channel_id' => 'i',
  'who_text' => 's',
  'what_text' => 's',
  'where_text' => 's',
  'air_text' => 's',
  'needs_text' => 's',
  'mayday_commander_source' => 's',
  'mayday_commander_display' => 's'
];

foreach ($map as $key => $t) {
  if (array_key_exists($key, $_POST)) {
    $val = $_POST[$key];
    if ($key === 'tac_channel_id') {
      $val = ($val === '' || $val === null) ? null : (int)$val;
    } else {
      $val = trim((string)$val);
    }
    $fields[] = "$key = ?";
    $types .= $t;
    $params[] = $val;
  }
}

if (!$fields) {
  json_out(['ok'=>true,'mayday'=>$existing]);
}

$sql = "UPDATE mayday SET ".implode(', ', $fields)." WHERE incident_id = ? AND id = ? LIMIT 1";
$types .= 'ii';
$params[] = $incident_id;
$params[] = $mayday_id;

if (!($st = $conn->prepare($sql))) {
  json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);
}
$st->bind_param($types, ...$params);
if (!$st->execute()) {
  $st->close();
  json_out(['ok'=>false,'error'=>'DB update failed'], 500);
}
$st->close();

add_log($conn, $incident_id, $mayday_id, 'UPDATE', 'MAYDAY updated');

$mayday = load_mayday_by_id($conn, $incident_id, $mayday_id);
$checklist = [
  ['label' => 'Acknowledge MAYDAY. Transmit: "MAYDAY acknowledged"', 'is_done' => false],
  ['label' => 'Announce: "All units hold radio traffic"', 'is_done' => false],
  ['label' => 'Confirm who / what / where (LUNAR if possible)', 'is_done' => false],
  ['label' => 'Assign a dedicated MAYDAY IC (separate from Incident IC if possible)', 'is_done' => false],
  ['label' => 'Request PAR (as appropriate) and verify accountability', 'is_done' => false],
  ['label' => 'Switch to / confirm MAYDAY TAC channel', 'is_done' => false],
  ['label' => 'Deploy RIT / RIC and confirm entry point', 'is_done' => false],
  ['label' => 'Request additional alarm / resources early', 'is_done' => false],
  ['label' => 'Control hazard: hose line / ventilation / lighting as needed', 'is_done' => false],
  ['label' => 'Establish EMS / transport plan; notify receiving', 'is_done' => false],
  ['label' => 'Keep a MAYDAY timeline log (events, times, actions)', 'is_done' => false],
  ['label' => 'Reassess frequently; update strategy (rescue vs fire control)', 'is_done' => false],
];


json_out(['ok'=>true,'mayday'=>$mayday,'checklist_items'=>$checklist]);
