<?php
declare(strict_types=1);

// /api/command_resources.php
header('Content-Type: application/json');

function out(bool $ok, array $payload = []): void {
  echo json_encode(array_merge(['ok' => $ok], $payload));
  exit;
}

// IMPORTANT:
// 1) Set ONE of the requires below (not both)
// 2) If your normal db_connect.php causes HTTP 500 when included from /api, use db_api_connect.php instead.

// require_once __DIR__ . '/../inc/db_connect.php';
require_once __DIR__ . '/../inc/db_api_connect.php';

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
  out(false, ['error' => 'DB connection not found ($mysqli). Update api/command_resources.php to match your db include.']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) out(false, ['error' => 'Invalid JSON']);

$action = (string)($data['action'] ?? '');
$incident_id = (int)($data['incident_id'] ?? 0);
if ($incident_id <= 0) out(false, ['error' => 'Missing incident_id']);

try {
  if ($action === 'list') {
    $sql = "
      SELECT id, department_name, designation, station_id, officer_display, role, assignment_name, created_at
      FROM incident_command_resources
      WHERE incident_id = ?
      ORDER BY id DESC
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) out(false, ['error' => 'Prepare failed: ' . $mysqli->error]);

    $stmt->bind_param('i', $incident_id);
    if (!$stmt->execute()) out(false, ['error' => 'Execute failed: ' . $stmt->error]);

    $res = $stmt->get_result();
    $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    out(true, ['items' => $items]);
  }

  if ($action === 'save') {
    $department_name = trim((string)($data['ma_dept_name'] ?? ''));
    $designation     = trim((string)($data['ma_designation'] ?? ''));
    $station_id      = trim((string)($data['ma_station_id'] ?? ''));
    $officer_display = trim((string)($data['officer_display'] ?? ''));
    $role            = trim((string)($data['role'] ?? ''));
    $assignment_name = trim((string)($data['assignment_name'] ?? ''));

    if ($officer_display === '') out(false, ['error' => 'Officer Display is required.']);
    if ($role === '') out(false, ['error' => 'Role is required.']);

    $sql = "
      INSERT INTO incident_command_resources
        (incident_id, department_name, designation, station_id, officer_display, role, assignment_name, created_at)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) out(false, ['error' => 'Prepare failed: ' . $mysqli->error]);

    $stmt->bind_param(
      'issssss',
      $incident_id,
      $department_name,
      $designation,
      $station_id,
      $officer_display,
      $role,
      $assignment_name
    );

    if (!$stmt->execute()) out(false, ['error' => 'Execute failed: ' . $stmt->error]);

    out(true, ['message' => 'Saved', 'id' => $stmt->insert_id]);
  }

  out(false, ['error' => 'Unknown action']);
} catch (Throwable $e) {
  out(false, ['error' => $e->getMessage()]);
}
