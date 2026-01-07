<?php
require_once __DIR__ . '/_mayday_common.php';

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$ff_id       = isset($_POST['firefighter_id']) ? (int)$_POST['firefighter_id'] : 0;

$incident = require_incident($conn, $incident_id);
$mayday = load_active_mayday($conn, $incident_id);
if (!$mayday) json_out(['ok'=>false,'error'=>'No active MAYDAY'], 409);
$mayday_id = (int)$mayday['id'];

if ($ff_id <= 0) json_out(['ok'=>false,'error'=>'Missing firefighter_id'], 400);

$name      = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$who_text  = isset($_POST['who_text']) ? (string)$_POST['who_text'] : null;
$what_text = isset($_POST['what_text']) ? (string)$_POST['what_text'] : null;
$where_text= isset($_POST['where_text']) ? (string)$_POST['where_text'] : null;
$air_text  = isset($_POST['air_text']) ? (string)$_POST['air_text'] : null;
$needs_text= isset($_POST['needs_text']) ? (string)$_POST['needs_text'] : null;

if ($name === '') $name = 'Unknown Firefighter';

// Ensure ff belongs to this mayday
$chk = $conn->prepare("SELECT id FROM mayday_firefighter WHERE id=? AND mayday_id=? LIMIT 1");
if (!$chk) json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);
$chk->bind_param("ii", $ff_id, $mayday_id);
$chk->execute();
$r = $chk->get_result();
$row = $r ? $r->fetch_assoc() : null;
$chk->close();
if (!$row) json_out(['ok'=>false,'error'=>'Firefighter not found for this MAYDAY'], 404);

$sql = "UPDATE mayday_firefighter
        SET name=?,
            who_text=?,
            what_text=?,
            where_text=?,
            air_text=?,
            needs_text=?,
            updated_at=NOW()
        WHERE id=? AND mayday_id=?";

$st = $conn->prepare($sql);
if (!$st) json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);

$st->bind_param("ssssssii", $name, $who_text, $what_text, $where_text, $air_text, $needs_text, $ff_id, $mayday_id);
$st->execute();
$st->close();

add_log($conn, $incident_id, $mayday_id, 'NOTE', "Updated firefighter: {$name}");

json_out(['ok'=>true]);
