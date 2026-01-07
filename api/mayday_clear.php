<?php
/**
 * File: mayday_update.php
 * Version: 2026-1-1 12:45
 
 */
require_once __DIR__ . '/_mayday_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'POST required'], 405);
}

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
$mayday_id   = isset($_POST['mayday_id']) ? (int)$_POST['mayday_id'] : 0;
$discard = false;
$raw = file_get_contents('php://input');
if ($raw) {
  $in = json_decode($raw, true);
  if (is_array($in)) {
    if (isset($in['incident_id'])) $incident_id = (int)$in['incident_id'];
    if (isset($in['mayday_id']))   $mayday_id   = (int)$in['mayday_id'];
    if (!empty($in['discard']))    $discard     = true;
  }
}
require_incident($conn, $incident_id);



// If discard=1: delete the MAYDAY record + log entries (user confirmed discard)
if ($discard) {
  // Validate ownership/authorization via require_incident already
  if ($mayday_id > 0) {
    if ($st = $conn->prepare('DELETE FROM mayday_log WHERE incident_id = ? AND mayday_id = ?')) {
      $st->bind_param('ii', $incident_id, $mayday_id);
      $st->execute();
      $st->close();
    }
    if ($st = $conn->prepare('DELETE FROM mayday WHERE incident_id = ? AND id = ?')) {
      $st->bind_param('ii', $incident_id, $mayday_id);
      $st->execute();
      $st->close();
    }
  }
  json_out(['ok'=>true,'mayday'=>null]);
  exit;
}
$existing = load_mayday_by_id($conn, $incident_id, $mayday_id);
if (!$existing) {
  json_out(['ok'=>false,'error'=>'MAYDAY not found'], 404);
}

$sql = "UPDATE mayday SET status='CLEARED', cleared_at = NOW(6) WHERE incident_id=? AND id=? LIMIT 1";
if (!($st = $conn->prepare($sql))) {
  json_out(['ok'=>false,'error'=>'DB prepare failed'], 500);
}
$st->bind_param('ii', $incident_id, $mayday_id);
if (!$st->execute()) {
  $st->close();
  json_out(['ok'=>false,'error'=>'DB update failed'], 500);
}
$st->close();

add_log($conn, $incident_id, $mayday_id, 'CLEAR', 'MAYDAY cleared');

$mayday = load_mayday_by_id($conn, $incident_id, $mayday_id);
json_out(['ok'=>true,'mayday'=>$mayday]);
