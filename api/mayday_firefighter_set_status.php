<?php
require_once __DIR__ . '/_mayday_common.php';

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$ff_id       = isset($_POST['firefighter_id']) ? (int)$_POST['firefighter_id'] : 0;
$status_id   = isset($_POST['status_id']) ? (int)$_POST['status_id'] : 0;

$incident = require_incident($conn, $incident_id);
$mayday = load_active_mayday($conn, $incident_id);
if (!$mayday) json_out(['ok'=>false,'error'=>'No active MAYDAY'], 409);
$mayday_id = (int)$mayday['id'];

if ($ff_id <= 0) json_out(['ok'=>false,'error'=>'Missing firefighter_id'], 400);
if ($status_id <= 0) json_out(['ok'=>false,'error'=>'Missing status_id'], 400);

// Ensure FF belongs to this mayday
$chk = $conn->prepare("SELECT name FROM mayday_firefighter WHERE id=? AND mayday_id=? LIMIT 1");
if (!$chk) json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);
$chk->bind_param("ii", $ff_id, $mayday_id);
$chk->execute();
$r = $chk->get_result();
$row = $r ? $r->fetch_assoc() : null;
$chk->close();
if (!$row) json_out(['ok'=>false,'error'=>'Firefighter not found for this MAYDAY'], 404);

$name = (string)$row['name'];

$st = $conn->prepare("UPDATE mayday_firefighter SET status_id=?, updated_at=NOW() WHERE id=? AND mayday_id=?");
if (!$st) json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);
$st->bind_param("iii", $status_id, $ff_id, $mayday_id);
$st->execute();
$st->close();

add_log($conn, $incident_id, $mayday_id, 'STATUS', "Status change for {$name}");

json_out(['ok'=>true]);
