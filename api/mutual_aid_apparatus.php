<?php
// api/mutual_aid_apparatus.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../db_connect.php';

function json_out($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

// Accept JSON or form POST
$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) $data = $decoded;
}
if (empty($data)) $data = $_POST;

$action = isset($data['action']) ? (string)$data['action'] : 'add';
if ($action !== 'add') {
  json_out(['ok'=>false,'error'=>'Unsupported action'], 400);
}

$incident_id = isset($data['incident_id']) ? (int)$data['incident_id'] : 0;
$mutual_aid_dept = isset($data['mutual_aid_dept']) ? trim((string)$data['mutual_aid_dept']) : '';
$apparatus_id = isset($data['apparatus_id']) ? trim((string)$data['apparatus_id']) : '';
$apparatus_label = isset($data['apparatus_label']) ? trim((string)$data['apparatus_label']) : $apparatus_id;
$apparatus_type_id = isset($data['apparatus_type_id']) ? (int)$data['apparatus_type_id'] : 0;
$firefighter_count = isset($data['firefighter_count']) ? (int)$data['firefighter_count'] : 0;

if ($incident_id <= 0) json_out(['ok'=>false,'error'=>'Missing incident_id'], 400);
if ($mutual_aid_dept === '') json_out(['ok'=>false,'error'=>'Missing mutual_aid_dept'], 400);
if ($apparatus_id === '') json_out(['ok'=>false,'error'=>'Missing apparatus_id'], 400);
if ($firefighter_count < 0) $firefighter_count = 0;

// Verify incident exists
$stmt = $conn->prepare("SELECT ID FROM incidents WHERE ID = ? LIMIT 1");
if (!$stmt) json_out(['ok'=>false,'error'=>'DB prepare failed: '.$conn->error], 500);
$stmt->bind_param("i", $incident_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$row) json_out(['ok'=>false,'error'=>'Incident not found'], 404);

// Optional: derive apparatus type label for legacy columns if desired
$typeLabel = '';
if ($apparatus_type_id > 0) {
  if ($stmt = $conn->prepare("SELECT ApparatusType AS type_name FROM apparatus_types WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $apparatus_type_id);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r && ($tr = $r->fetch_assoc())) $typeLabel = (string)$tr['type_name'];
    $stmt->close();
  }
}

$sql = "INSERT INTO apparatus_responding
          (incident_id, Label, Type, ApparatusLabel, apparatus_type, apparatus_ID,
           firefighter_count, mutual_aid_dept, status, dispatch_time, notes, is_mutual_aid)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, 'Responding', NOW(), '', 1)
        ON DUPLICATE KEY UPDATE
          id = LAST_INSERT_ID(id),
          Label = VALUES(Label),
          Type = VALUES(Type),
          ApparatusLabel = VALUES(ApparatusLabel),
          apparatus_type = VALUES(apparatus_type),
          firefighter_count = VALUES(firefighter_count),
          mutual_aid_dept = VALUES(mutual_aid_dept),
          status = 'Responding',
          dispatch_time = NOW(),
          is_mutual_aid = 1";
$stmt = $conn->prepare($sql);
if (!$stmt) json_out(['ok'=>false,'error'=>'DB prepare failed: '.$conn->error], 500);

$labelCol = $apparatus_label;
$typeCol = $typeLabel;
$appLabelCol = $apparatus_label;

$stmt->bind_param("isssisis",
  $incident_id,
  $labelCol,
  $typeCol,
  $appLabelCol,
  $apparatus_type_id,
  $apparatus_id,
  $firefighter_count,
  $mutual_aid_dept
);

if (!$stmt->execute()) {
  $err = $stmt->error;
  $stmt->close();
  json_out(['ok'=>false,'error'=>'Insert failed: '.$err], 500);
}
$newId = $stmt->insert_id;
$stmt->close();

json_out(['ok'=>true,'id'=>$newId]);
