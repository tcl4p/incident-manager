<?php
declare(strict_types=1);
/**
 * File: command_board.php
 * Version: 2025-12-30 10:40
 * Notes: Added alarm stars + MAYDAY button (touch UI)
 */
session_start();
require_once __DIR__ . '/../db_connect.php';

// Be compatible with projects that use $conn or $mysqli in db_connect.php
if (!isset($conn) && isset($mysqli)) { $conn = $mysqli; }
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Database connection ($conn) not available. Check db_connect.php']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Convert mysqli exceptions into JSON instead of HTML
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $loggedDeptId = isset($_SESSION['dept_id']) ? (int)$_SESSION['dept_id'] : 0;
    if ($loggedDeptId <= 0) {
        json_out(['ok' => false, 'error' => 'Not authorized'], 401);
    }

    // GET: list incident command resources
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
        if ($incidentId <= 0) json_out(['ok' => false, 'error' => 'Missing incident_id'], 400);

        // Verify incident belongs to this dept
        $ok = false;
        $stmt = $conn->prepare("SELECT id FROM incidents WHERE id = ? AND dept_id = ? LIMIT 1");
        $stmt->bind_param("ii", $incidentId, $loggedDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = ($res && $res->num_rows > 0);
        $stmt->close();

        if (!$ok) {
            json_out(['ok' => false, 'error' => 'Incident not found or not authorized'], 403);
            exit;
        }

        $items = [];
        $sql = "
            SELECT
                id,
                incident_id,
                department_name AS ma_dept_name,
                designation     AS dept_designation,
                station_id,
                officer_display,
                role,
                created_at,
                TRIM(CONCAT(
                    COALESCE(designation, ''),
                    CASE WHEN COALESCE(designation,'')<>'' THEN ' - ' ELSE '' END,
                    COALESCE(department_name, ''),
                    CASE WHEN COALESCE(station_id,'')<>'' THEN CONCAT(' - ', station_id) ELSE '' END
                )) AS dept_label
            FROM incident_command_resources
            WHERE incident_id = ?
            ORDER BY id DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $items[] = $row;
        $stmt->close();

        json_out(['ok' => true, 'items' => $items]);
    }

    // POST: add/delete actions (support both form-encoded POST and JSON POST)
    $data = $_POST;
    if (empty($data)) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded;
        }
    }

    $action = (string)($data['action'] ?? '');

    if ($action === 'add') {
        $incidentId = isset($data['incident_id']) ? (int)$data['incident_id'] : 0;
        $deptName   = trim((string)($data['ma_dept_name'] ?? $data['department_name'] ?? ''));
        $desig      = trim((string)($data['dept_designation'] ?? $data['designation'] ?? ''));
        $stationId  = trim((string)($data['station_id'] ?? ''));
        $officer    = trim((string)($data['officer_display'] ?? ''));
        $role       = trim((string)($data['role'] ?? ''));

        if ($incidentId <= 0) json_out(['ok' => false, 'error' => 'Missing incident_id'], 400);
        if ($deptName === '' && $desig === '') json_out(['ok' => false, 'error' => 'Missing department'], 400);
        if ($officer === '' && $stationId === '') json_out(['ok' => false, 'error' => 'Missing resource label'], 400);

        // Authorize incident ownership
        $stmt = $conn->prepare("SELECT id FROM incidents WHERE id = ? AND dept_id = ? LIMIT 1");
        $stmt->bind_param("ii", $incidentId, $loggedDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = ($res && $res->num_rows > 0);
        $stmt->close();
        if (!$ok) {
            json_out(['ok' => false, 'error' => 'Incident not found or not authorized'], 403);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO incident_command_resources
                (incident_id, department_name, designation, station_id, officer_display, role, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("isssss", $incidentId, $deptName, $desig, $stationId, $officer, $role);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        json_out(['ok' => true, 'id' => $newId]);
    }

    if ($action === 'delete') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) json_out(['ok' => false, 'error' => 'Missing id'], 400);

        // Delete only if the resource belongs to an incident owned by this dept
        $stmt = $conn->prepare("
            DELETE icr
            FROM incident_command_resources icr
            INNER JOIN incidents i ON i.id = icr.incident_id
            WHERE icr.id = ? AND i.dept_id = ?
        ");
        $stmt->bind_param("ii", $id, $loggedDeptId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        json_out(['ok' => true, 'deleted' => $affected]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action'], 400);

} catch (Throwable $e) {
    // Ensure any fatal/exception becomes JSON, not HTML
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
