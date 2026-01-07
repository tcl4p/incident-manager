<?php
require_once __DIR__ . '/_mayday_common.php';
/**
 * File: mayday_update.php
 * Version: 2026-1-1 1:15
 
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'POST required'], 405);
}

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$mayday_id = isset($_POST['mayday_id']) ? (int)$_POST['mayday_id'] : 0;
$event_type = trim((string)($_POST['event_type'] ?? 'NOTE'));
$message = trim((string)($_POST['message'] ?? ''));
$entered_by = trim((string)($_POST['entered_by'] ?? ''));

require_incident($conn, $incident_id);

$existing = load_mayday_by_id($conn, $incident_id, $mayday_id);
if (!$existing) {
  json_out(['ok'=>false,'error'=>'MAYDAY not found'], 404);
}

if ($message === '') {
  json_out(['ok'=>false,'error'=>'Message required'], 400);
}

add_log($conn, $incident_id, $mayday_id, $event_type, $message, $entered_by);

$last_ts = latest_log_ts($conn, $incident_id, $mayday_id);
$items = load_mayday_log($conn, $incident_id, $mayday_id, null);

json_out(['ok'=>true,'last_log_ts'=>$last_ts,'log_items'=>$items]);
