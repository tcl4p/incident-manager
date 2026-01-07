<?php
require_once __DIR__ . '/_mayday_common.php';

$incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;

$incident = require_incident($conn, $incident_id);
$dept_id = (int)$incident['dept_id'];

/**
 * If there is already an ACTIVE Mayday, return it
 */
$existing = load_active_mayday($conn, $incident_id);
if ($existing) {
  json_out([
    'ok' => true,
    'mayday' => $existing,
    'message' => 'Mayday already active'
  ]);
}

/**
 * Find ACTIVE status_id for this department
 */
$status_id = null;
$sql = "SELECT id FROM mayday_status
        WHERE dept_id = ? AND code = 'ACTIVE'
        LIMIT 1";
if ($st = $conn->prepare($sql)) {
  $st->bind_param("i", $dept_id);
  $st->execute();
  $r = $st->get_result();
  if ($row = $r->fetch_assoc()) {
    $status_id = (int)$row['id'];
  }
  $st->close();
}
if (!$status_id) {
  json_out(['ok'=>false,'error'=>'ACTIVE status not configured for department'], 500);
}

/**
 * Create Mayday event
 */
$sql = "INSERT INTO mayday
          (incident_id, status, started_at, created_at, updated_at)
        VALUES
          (?, 'ACTIVE', NOW(), NOW(), NOW())";
if (!($st = $conn->prepare($sql))) {
  json_out(['ok'=>false,'error'=>'Failed to create Mayday'], 500);
}
$st->bind_param("i", $incident_id);
$st->execute();
$mayday_id = $st->insert_id;
$st->close();

/**
 * Create first firefighter
 */
$sql = "INSERT INTO mayday_firefighter
          (mayday_id, name, status_id, created_at, updated_at)
        VALUES
          (?, 'Unknown Firefighter', ?, NOW(), NOW())";
if ($st = $conn->prepare($sql)) {
  $st->bind_param("ii", $mayday_id, $status_id);
  $st->execute();
  $st->close();
}

/**
 * Log event
 */
add_log($conn, $incident_id, $mayday_id, 'SYSTEM', 'Mayday confirmed');

/**
 * Return new event
 */
$mayday = load_active_mayday($conn, $incident_id);

json_out([
  'ok' => true,
  'mayday' => $mayday,
  'message' => 'Mayday started'
]);
