<?php
require_once __DIR__ . '/_mayday_common.php';

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;

$incident = require_incident($conn, $incident_id);
$mayday = load_active_mayday($conn, $incident_id);
if (!$mayday) json_out(['ok'=>false,'error'=>'No active MAYDAY'], 409);

$mayday_id = (int)$mayday['id'];
$dept_id = (int)$incident['dept_id'];

// Default to first status in dept list (usually ACTIVE)
$statuses = load_mayday_statuses($conn, $dept_id);
if (!$statuses) json_out(['ok'=>false,'error'=>'No MAYDAY statuses configured'], 500);
$default_status_id = (int)$statuses[0]['id'];

$name = isset($_POST['name']) && trim($_POST['name']) !== '' ? trim((string)$_POST['name']) : 'Unknown Firefighter';

$sql = "INSERT INTO mayday_firefighter (mayday_id, status_id, name, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())";
$st = $conn->prepare($sql);
if (!$st) json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);

$st->bind_param("iis", $mayday_id, $default_status_id, $name);
$st->execute();
$new_id = (int)$st->insert_id;
$st->close();

add_log($conn, $incident_id, $mayday_id, 'SYSTEM', "Added firefighter: {$name}");

json_out([
  'ok' => true,
  'firefighter_id' => $new_id
]);
