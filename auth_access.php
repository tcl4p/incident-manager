<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "db_connect.php"; // must define $conn (mysqli)

/**
 * Best-effort auth logging (won't break login if table missing).
 */
function log_auth_error(mysqli $conn, string $type, string $msg, array $info = []): void {
    try {
        $additional = $info ? json_encode($info, JSON_UNESCAPED_SLASHES) : null;
        $stmt = $conn->prepare(
            "INSERT INTO error_log (error_type, error_message, source_file, additional_info) VALUES (?,?,?,?)"
        );
        if ($stmt) {
            $src = basename(__FILE__);
            $stmt->bind_param("ssss", $type, $msg, $src, $additional);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $t) {
        // swallow
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$accessCode = trim($_POST['access_code'] ?? '');

// Transition: allow legacy 6-digit codes while moving to 8 digits for new departments.
// When all departments are upgraded, tighten this back to /^\d{8}$/.
if (!preg_match('/^\d{6,8}$/', $accessCode)) {
    log_auth_error($conn, 'auth', 'Access code failed format validation', [
        'len' => strlen($accessCode),
        'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    header("Location: index.php?error=format");
    exit;
}

try {
    $sql = "SELECT id, dept_name, dept_short_name, access_code_hash
            FROM department
            WHERE is_active = 1";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $matchedDept = null;
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['access_code_hash']) && password_verify($accessCode, $row['access_code_hash'])) {
            $matchedDept = $row;
            break;
        }
    }
    $result->free();

    if ($matchedDept === null) {
        log_auth_error($conn, 'auth', 'Access code not recognized', [
            'len' => strlen($accessCode),
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        header("Location: index.php?error=invalid&ts=" . time());
        exit;
    }

    // Match found: store in session (this is what the rest of the app expects)
    session_regenerate_id(true);
    $_SESSION['dept_id']         = (int)$matchedDept['id'];
    $_SESSION['dept_name']       = $matchedDept['dept_name'];
    $_SESSION['dept_short_name'] = $matchedDept['dept_short_name'];

    header("Location: incidents.php");
    exit;

} catch (Throwable $ex) {
    log_auth_error($conn, 'auth', 'Exception during login', [
        'message' => $ex->getMessage(),
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    header("Location: index.php?error=invalid&ts=" . time());
    exit;
}
