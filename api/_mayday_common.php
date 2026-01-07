<?php
// api/_mayday_common.php
// Common helpers for Mayday API endpoints (mysqli)

ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

ob_start();

require_once __DIR__ . '/../db_connect.php'; // must define $conn (mysqli)

function json_out(array $data, int $status = 200): void {
  if (!headers_sent()) http_response_code($status);
  // clean any accidental output so JSON stays valid
  if (ob_get_length()) { @ob_clean(); }
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

set_exception_handler(function($ex){
  json_out(['ok'=>false,'error'=>$ex->getMessage()], 500);
});

set_error_handler(function($severity, $message, $file, $line){
  throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Require incident, and (if session dept_id exists) enforce authorization.
 * Returns incident row (at least id, dept_id).
 */
function require_incident(mysqli $conn, int $incident_id): array {
  if ($incident_id <= 0) json_out(['ok'=>false,'error'=>'Missing incident_id'], 400);

  $sql = "SELECT id, dept_id FROM incidents WHERE id = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $incident_id);
  $st->execute();
  $r = $st->get_result();
  $row = $r ? $r->fetch_assoc() : null;
  $st->close();

  if (!$row) json_out(['ok'=>false,'error'=>'Incident not found'], 404);

  // Optional auth check if your app stores dept_id in session
  if (session_status() === PHP_SESSION_NONE) @session_start();
  if (!empty($_SESSION['dept_id'])) {
    $sessDept = (int)$_SESSION['dept_id'];
    if ($sessDept > 0 && (int)$row['dept_id'] !== $sessDept) {
      json_out(['ok'=>false,'error'=>'Incident not found or not authorized'], 403);
    }
  }

  return $row;
}

function load_active_mayday(mysqli $conn, int $incident_id): ?array {
  $sql = "SELECT * FROM mayday
          WHERE incident_id = ?
            AND status = 'ACTIVE'
          ORDER BY id DESC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $incident_id);
  $st->execute();
  $r = $st->get_result();
  $row = $r ? $r->fetch_assoc() : null;
  $st->close();
  return $row ?: null;
}

function load_tac_channels(mysqli $conn, int $incident_id): array {
  // Table in your DB: tac_channels (ID, incident_id, ChannelLabel, UsageLabel, AssignedUnits, ...)
  $sql = "SELECT ID AS id, ChannelLabel AS label, UsageLabel AS usage_label, AssignedUnits AS assigned_units
          FROM tac_channels
          WHERE incident_id = ?
          ORDER BY ID ASC";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $incident_id);
  $st->execute();
  $r = $st->get_result();
  $rows = [];
  while ($r && ($row = $r->fetch_assoc())) $rows[] = $row;
  $st->close();
  return $rows;
}

function load_mayday_statuses(mysqli $conn, int $dept_id): array {
  $sql = "SELECT id, code, label, sort_order, color_class, is_terminal, is_system
          FROM mayday_status
          WHERE dept_id = ?
          ORDER BY sort_order ASC, id ASC";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $dept_id);
  $st->execute();
  $r = $st->get_result();
  $rows = [];
  while ($r && ($row = $r->fetch_assoc())) $rows[] = $row;
  $st->close();
  return $rows;
}

function load_mayday_firefighters(mysqli $conn, int $mayday_id): array {
  $sql = "SELECT id, mayday_id, status_id, name, who_text, what_text, where_text, air_text, needs_text, created_at, updated_at, cleared_at
          FROM mayday_firefighter
          WHERE mayday_id = ?
          ORDER BY id ASC";
  $st = $conn->prepare($sql);
  $st->bind_param("i", $mayday_id);
  $st->execute();
  $r = $st->get_result();
  $rows = [];
  while ($r && ($row = $r->fetch_assoc())) $rows[] = $row;
  $st->close();
  return $rows;
}

function load_mayday_log(mysqli $conn, int $incident_id, int $mayday_id, ?string $since = null): array {
  if ($since) {
    $sql = "SELECT id, mayday_id, incident_id, event_ts, event_type, message, entered_by
            FROM mayday_log
            WHERE incident_id = ? AND mayday_id = ? AND event_ts > ?
            ORDER BY event_ts ASC, id ASC";
    $st = $conn->prepare($sql);
    $st->bind_param("iis", $incident_id, $mayday_id, $since);
  } else {
    $sql = "SELECT id, mayday_id, incident_id, event_ts, event_type, message, entered_by
            FROM mayday_log
            WHERE incident_id = ? AND mayday_id = ?
            ORDER BY event_ts ASC, id ASC";
    $st = $conn->prepare($sql);
    $st->bind_param("ii", $incident_id, $mayday_id);
  }
  $st->execute();
  $r = $st->get_result();
  $rows = [];
  while ($r && ($row = $r->fetch_assoc())) $rows[] = $row;
  $st->close();
  return $rows;
}

function load_mayday_checklist(mysqli $conn, ?int $mayday_id): array {
  // Items are global list; status is per-mayday in mayday_checklist_status
  if ($mayday_id) {
    $sql = "SELECT i.id, i.sort_order, i.label,
                   COALESCE(s.is_done, 0) AS is_done,
                   s.done_at
            FROM mayday_checklist_items i
            LEFT JOIN mayday_checklist_status s
              ON s.item_id = i.id AND s.mayday_id = ?
            WHERE i.is_active = 1
            ORDER BY i.sort_order ASC, i.id ASC";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $mayday_id);
  } else {
    $sql = "SELECT id, sort_order, label, 0 AS is_done, NULL AS done_at
            FROM mayday_checklist_items
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC";
    $st = $conn->prepare($sql);
  }

  $st->execute();
  $r = $st->get_result();
  $rows = [];
  while ($r && ($row = $r->fetch_assoc())) $rows[] = $row;
  $st->close();
  return $rows;
}

function add_log(mysqli $conn, int $incident_id, int $mayday_id, string $event_type, string $message, ?string $entered_by = null): void {
  $sql = "INSERT INTO mayday_log (incident_id, mayday_id, event_type, message, entered_by, event_ts)
          VALUES (?, ?, ?, ?, ?, NOW(6))";
  $entered_by = $entered_by ?? '';
  $st = $conn->prepare($sql);
  $st->bind_param("iisss", $incident_id, $mayday_id, $event_type, $message, $entered_by);
  $st->execute();
  $st->close();
}
