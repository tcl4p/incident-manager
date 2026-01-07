<?php
// department_settings.php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php'; // expects $conn (mysqli)

// -------------------------------------------
// PRG (Post/Redirect/Get) helper
// Prevents "added but not visible until tab switch" and avoids double-submit on refresh.
// Stores success message in session so it survives the redirect.
// -------------------------------------------
if (!isset($_SESSION['flash'])) { $_SESSION['flash'] = ''; }

function prg_success_redirect(string $tab, int $ma_dept_id = 0, string $message = 'Saved.'): void {
    $_SESSION['flash'] = $message;

    $qs = '?tab=' . urlencode($tab);
    if ($tab === 'mutual_aid' && $ma_dept_id > 0) {
        $qs .= '&ma_dept_id=' . (int)$ma_dept_id;
    }

    $url = 'department_settings.php' . $qs;

    // Prefer a real HTTP redirect, but fall back to a JS redirect if output already started.
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo "<script>window.location=" . json_encode($url) . ";</script>";
    exit;
}


/**
 * Prepare statement or die with a useful error message.
 * This prevents "Page Not Working" with no clues.
 */
function prepare_or_die(mysqli $conn, string $sql): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "<pre style='white-space:pre-wrap'>";
        echo "DB PREPARE FAILED\n";
        echo "Error: " . $conn->error . "\n\n";
        echo "SQL:\n" . $sql . "\n";
        echo "</pre>";
        exit;
    }
    return $stmt;
}


function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$deptId = isset($_SESSION['dept_id']) ? (int)$_SESSION['dept_id'] : 0;
if ($deptId <= 0) {
    header('Location: index.php');
    exit;
}

$activeTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'info';
if (!in_array($activeTab, ['info', 'apparatus', 'command_staff', 'mutual_aid'], true)) {
    $activeTab = 'info';
}

$flash = '';
// Pull one-time flash message from session (PRG)
if (empty($flash) && !empty($_SESSION['flash'])) { $flash = (string)$_SESSION['flash']; $_SESSION['flash'] = ''; }

$error = '';

/** Load department row */
$dept = null;
$stmt = prepare_or_die($conn, 'SELECT id, dept_name, dept_short_name, designation, station_id, is_active, contact_name, contact_email, contact_phone, contact_address
                        FROM department WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $deptId);
$stmt->execute();
$res = $stmt->get_result();
$dept = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$dept) {
    $error = 'Department record not found.';
}

/** Handle Department Info save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_dept') {
    $activeTab = 'info';

    $dept_name = trim((string)($_POST['dept_name'] ?? ''));
    $dept_short_name = trim((string)($_POST['dept_short_name'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $station_id = trim((string)($_POST['station_id'] ?? ''));
    $contact_name = trim((string)($_POST['contact_name'] ?? ''));
    $contact_email = trim((string)($_POST['contact_email'] ?? ''));
    $contact_phone = trim((string)($_POST['contact_phone'] ?? ''));
    $contact_address = trim((string)($_POST['contact_address'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($dept_name === '' || $dept_short_name === '') {
        $error = 'Department Name and Short Name are required.';
    } else {
        
        // --- Uniqueness guard: designation must be unique (except for this department) ---
        if ($designation !== '') {
            $chk = prepare_or_die($conn, "SELECT id, dept_name FROM department WHERE designation = ? AND id <> ? LIMIT 1");
            $chk->bind_param('si', $designation, $deptId);
            $chk->execute();
            $r = $chk->get_result();
            if ($conflict = ($r ? $r->fetch_assoc() : null)) {
                $error = "Another department already uses designation '{$designation}' (ID {$conflict['id']}: {$conflict['dept_name']}).";
            }
            $chk->close();
        }

        if (empty($error)) {
$stmt = prepare_or_die($conn, 'UPDATE department
            SET dept_name=?, dept_short_name=?, designation=?, station_id=?, is_active=?,
                contact_name=?, contact_email=?, contact_phone=?, contact_address=?
            WHERE id=?');
        $stmt->bind_param(
            'ssssissssi',
            $dept_name, $dept_short_name, $designation, $station_id, $is_active,
            $contact_name, $contact_email, $contact_phone, $contact_address,
            $deptId
        );
        try {
            if ($stmt->execute()) {
            $_SESSION['dept_name'] = $dept_name;
            $_SESSION['dept_short_name'] = $dept_short_name;
            $flash = 'Department info saved.';
        } else {
            $error = 'Database error saving department info: ' . $stmt->error;
        }
        $stmt->close();

            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1062) {
                    $error = "Another department already uses designation '{$designation}'.";
                } else {
                    $error = 'Database error saving department info: ' . $e->getMessage();
                }
            }

        } // end empty($error)

    }

    // Reload department row
    $stmt = prepare_or_die($conn, 'SELECT id, dept_name, dept_short_name, designation, station_id, is_active, contact_name, contact_email, contact_phone, contact_address
                            FROM department WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $deptId);
    $stmt->execute();
    $res = $stmt->get_result();
    $dept = $res ? $res->fetch_assoc() : $dept;
    $stmt->close();
}

/** Apparatus types for dropdown */
$apparatusTypes = [];
$rt = $conn->query("SELECT ApparatusType FROM apparatus_types WHERE is_active=1 ORDER BY ApparatusType ASC");
if ($rt) {
    while ($r = $rt->fetch_assoc()) {
        $apparatusTypes[] = (string)$r['ApparatusType'];
    }
}




/** Apparatus types (id + name) for mutual aid template dropdowns */
$apparatusTypeOptions = [];
$rt_id = $conn->query("SELECT id, ApparatusType FROM apparatus_types WHERE is_active=1 ORDER BY ApparatusType ASC");
if ($rt_id) {
    while ($r = $rt_id->fetch_assoc()) {
        $apparatusTypeOptions[] = $r;
    }
}

/** Command ranks for dropdown */
$commandRanks = [];
$rt2 = $conn->query("SELECT id, rank_name FROM department_command_rank WHERE is_active=1 ORDER BY sort_order ASC, rank_name ASC");
if ($rt2) {
    while ($r = $rt2->fetch_assoc()) {
        $commandRanks[] = $r;
    }
}



/** Mutual Aid (linked departments + templates) */
$selectedMutualDeptId = isset($_GET['ma_dept_id']) ? (int)$_GET['ma_dept_id'] : 0;
if ($selectedMutualDeptId < 0) { $selectedMutualDeptId = 0; }

$linkedMutualDepts = [];
$stmt = prepare_or_die($conn, "SELECT dma.id AS link_id, dma.mutual_dept_id, dma.is_active, dma.sort_order,
                                     d.department_name AS dept_name, d.designation AS dept_short_name
                              FROM department_mutual_aid dma
                              JOIN mutual_aid_departments d ON d.id = dma.mutual_dept_id
                              WHERE dma.home_dept_id = ?
                              ORDER BY dma.sort_order ASC, d.department_name ASC");
$stmt->bind_param('i', $deptId);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $linkedMutualDepts[] = $row;
    }
}
$stmt->close();

/** Mutual aid command staff template rows for selected mutual dept */
$maOfficers = [];
$maApparatus = [];
if ($selectedMutualDeptId > 0) {
    $stmt = prepare_or_die($conn, "SELECT t.id, t.officer_display, t.rank_id, t.sort_order, t.is_active
                                  FROM department_mutual_aid_command_staff_template t
                                  WHERE t.home_dept_id=? AND t.mutual_dept_id=?
                                  ORDER BY t.sort_order ASC, t.officer_display ASC");
    $stmt->bind_param('ii', $deptId, $selectedMutualDeptId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $maOfficers[] = $row;
        }
    }
    $stmt->close();

    $stmt = prepare_or_die($conn, "SELECT t.id, t.apparatus_label, t.apparatus_type_id, t.sort_order, t.is_active
                                  FROM department_mutual_aid_apparatus_template t
                                  WHERE t.home_dept_id=? AND t.mutual_dept_id=?
                                  ORDER BY t.sort_order ASC, t.apparatus_label ASC");
    $stmt->bind_param('ii', $deptId, $selectedMutualDeptId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $maApparatus[] = $row;
        }
    }
    $stmt->close();
}

/** Apparatus editing state */
$editAppId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editApp = null;
if ($editAppId > 0) {
    $activeTab = 'apparatus';
    $stmt = prepare_or_die($conn, 'SELECT id, apparatus_name, apparatus_type, radio_id, sort_order, is_active
                            FROM department_apparatus WHERE id=? AND dept_id=? LIMIT 1');
    $stmt->bind_param('ii', $editAppId, $deptId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editApp = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}



/** Handle Mutual Aid setup (linked depts, officers, apparatus templates) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Add mutual aid department + link to this home department
    if ($_POST['action'] === 'add_mutual_aid_dept') {
        $activeTab = 'mutual_aid';

        $ma_dept_name = trim((string)($_POST['ma_dept_name'] ?? ''));
        $ma_short = trim((string)($_POST['ma_dept_short'] ?? ''));
        $is_active = isset($_POST['ma_is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['ma_sort_order'] ?? 0);

        if ($ma_dept_name === '' || $ma_short === '') {
            $error = 'Mutual Aid Department Name and Short Name are required.';
        } else {
            // Insert into mutual_aid_departments (master list)
            // NOTE: mutual_aid_departments has a UNIQUE key (uq_ma_dept). If it already exists,
            // we reuse the existing row instead of crashing.
            $station_id = ''; // optional; not captured on this screen

            // 1) Check if this mutual aid department already exists
            $existingId = 0;
            $chk = prepare_or_die($conn, "SELECT id FROM mutual_aid_departments WHERE department_name = ? AND designation = ? AND station_id = ? LIMIT 1");
            $chk->bind_param('sss', $ma_dept_name, $ma_short, $station_id);
            if ($chk->execute()) {
                $res = $chk->get_result();
                if ($row = $res->fetch_assoc()) {
                    $existingId = (int)$row['id'];
                }
            }
            $chk->close();

            // 2) Insert if it doesn't exist
            $newMutualDeptId = $existingId;
            if ($newMutualDeptId <= 0) {
                $stmt = prepare_or_die($conn, "INSERT INTO mutual_aid_departments (department_name, designation, station_id) VALUES (?,?,?)");
                $stmt->bind_param('sss', $ma_dept_name, $ma_short, $station_id);

                if ($stmt->execute()) {
                    $newMutualDeptId = (int)$stmt->insert_id;
                    $stmt->close();
                } else {
                    $error = 'Database error adding mutual aid department: ' . $stmt->error;
                    $stmt->close();
                }
            } else {
                $flash = 'Mutual aid department already exists — linking it to this department.';
            }

            // 3) Link to this home department (safe if already linked)
            if (!$error && $newMutualDeptId > 0) {
                $stmt2 = prepare_or_die($conn, "INSERT INTO department_mutual_aid
                    (home_dept_id, mutual_dept_id, is_active, sort_order)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), sort_order = VALUES(sort_order)");
                $stmt2->bind_param('iiii', $deptId, $newMutualDeptId, $is_active, $sort_order);

                if ($stmt2->execute()) {
                    $flash = $flash ?: 'Mutual aid department linked.';
                    prg_success_redirect('mutual_aid', (int)$newMutualDeptId, $flash);
                    $selectedMutualDeptId = $newMutualDeptId;
                } else {
                    $error = 'Database error linking mutual aid department: ' . $stmt2->error;
                }
                $stmt2->close();
            }
        }
    }

    // Update mutual aid link settings + department name/short
    if ($_POST['action'] === 'update_mutual_aid_dept') {
        $activeTab = 'mutual_aid';

        $link_id = (int)($_POST['ma_link_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);
        $ma_dept_name = trim((string)($_POST['ma_dept_name'] ?? ''));
        $ma_short = trim((string)($_POST['ma_dept_short'] ?? ''));
        $is_active = isset($_POST['ma_is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['ma_sort_order'] ?? 0);

        if ($link_id <= 0 || $mutual_dept_id <= 0) {
            $error = 'Invalid mutual aid record.';
        } elseif ($ma_dept_name === '' || $ma_short === '') {
            $error = 'Mutual Aid Department Name and Short Name are required.';
        } else {
            $stmt = prepare_or_die($conn, "UPDATE mutual_aid_departments SET department_name=?, designation=? WHERE id=?");
            $stmt->bind_param('ssi', $ma_dept_name, $ma_short, $mutual_dept_id);
            $ok1 = $stmt->execute();
            $stmt->close();

            $stmt = prepare_or_die($conn, "UPDATE department_mutual_aid SET is_active=?, sort_order=? WHERE id=? AND home_dept_id=?");
            $stmt->bind_param('iiii', $is_active, $sort_order, $link_id, $deptId);
            $ok2 = $stmt->execute();
            if ($ok1 && $ok2) {
                $flash = 'Mutual aid department updated.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                $selectedMutualDeptId = $mutual_dept_id;
            } else {
                $error = 'Database error updating mutual aid department: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Remove mutual aid link (keeps mutual aid department record for future reuse)
    if ($_POST['action'] === 'delete_mutual_aid_link') {
        $activeTab = 'mutual_aid';
        $link_id = (int)($_POST['ma_link_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);

        if ($link_id > 0) {
            // remove templates first
            $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_command_staff_template WHERE home_dept_id=? AND mutual_dept_id=?");
            $stmt->bind_param('ii', $deptId, $mutual_dept_id);
            $stmt->execute();
            $stmt->close();

            $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_apparatus_template WHERE home_dept_id=? AND mutual_dept_id=?");
            $stmt->bind_param('ii', $deptId, $mutual_dept_id);
            $stmt->execute();
            $stmt->close();

            $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid WHERE id=? AND home_dept_id=?");
            $stmt->bind_param('ii', $link_id, $deptId);
            if ($stmt->execute()) {
                $flash = 'Mutual aid link removed.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                if ($selectedMutualDeptId === $mutual_dept_id) { $selectedMutualDeptId = 0; }
            } else {
                $error = 'Database error removing link: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Save mutual aid officer template (radio_designation stored blank)
    if ($_POST['action'] === 'save_ma_officer') {
        $activeTab = 'mutual_aid';
        $officer_id = (int)($_POST['officer_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);
        $officer_display = trim((string)($_POST['officer_display'] ?? ''));
        $rank_id = (int)($_POST['rank_id'] ?? 0);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($mutual_dept_id <= 0) {
            $error = 'Select a Mutual Aid Department first.';
        } elseif ($officer_display === '') {
            $error = 'Officer identifier is required.';
        } else {
            if ($officer_id > 0) {
                $stmt = prepare_or_die($conn, "UPDATE department_mutual_aid_command_staff_template
                    SET officer_display=?, rank_id=?, radio_designation='', sort_order=?, is_active=?
                    WHERE id=? AND home_dept_id=? AND mutual_dept_id=?");
                $stmt->bind_param('siiiiii', $officer_display, $rank_id, $sort_order, $is_active, $officer_id, $deptId, $mutual_dept_id);
            } else {
                $stmt = prepare_or_die($conn, "INSERT INTO department_mutual_aid_command_staff_template
                    (home_dept_id, mutual_dept_id, officer_display, rank_id, radio_designation, sort_order, is_active)
                    VALUES (?, ?, ?, ?, '', ?, ?)");
                $stmt->bind_param('iisiii', $deptId, $mutual_dept_id, $officer_display, $rank_id, $sort_order, $is_active);
            }
            if ($stmt->execute()) {
                $flash = 'Mutual aid officer saved.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                $selectedMutualDeptId = $mutual_dept_id;
            } else {
                $error = 'Database error saving officer: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Delete mutual aid officer template
    if ($_POST['action'] === 'delete_ma_officer') {
        $activeTab = 'mutual_aid';
        $officer_id = (int)($_POST['officer_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);

        if ($officer_id > 0) {
            $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_command_staff_template
                                          WHERE id=? AND home_dept_id=? AND mutual_dept_id=?");
            $stmt->bind_param('iii', $officer_id, $deptId, $mutual_dept_id);
            if ($stmt->execute()) {
                $flash = 'Mutual aid officer removed.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                $selectedMutualDeptId = $mutual_dept_id;
            } else {
                $error = 'Database error removing officer: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Save mutual aid apparatus template (staffing stored as 0)
    if ($_POST['action'] === 'save_ma_apparatus') {
        $activeTab = 'mutual_aid';
        $app_id = (int)($_POST['app_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);
        $apparatus_label = trim((string)($_POST['apparatus_label'] ?? ''));
        $apparatus_type_id = (int)($_POST['apparatus_type_id'] ?? 0);
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($mutual_dept_id <= 0) {
            $error = 'Select a Mutual Aid Department first.';
        } elseif ($apparatus_label === '' || $apparatus_type_id <= 0) {
            $error = 'Apparatus label and type are required.';
        } else {
            if ($app_id > 0) {
                $stmt = prepare_or_die($conn, "UPDATE department_mutual_aid_apparatus_template
                    SET apparatus_label=?, apparatus_type_id=?, staffing=0, sort_order=?, is_active=?
                    WHERE id=? AND home_dept_id=? AND mutual_dept_id=?");
                $stmt->bind_param('siiiiii', $apparatus_label, $apparatus_type_id, $sort_order, $is_active, $app_id, $deptId, $mutual_dept_id);
            } else {
                $stmt = prepare_or_die($conn, "INSERT INTO department_mutual_aid_apparatus_template
                    (home_dept_id, mutual_dept_id, apparatus_label, apparatus_type_id, staffing, sort_order, is_active)
                    VALUES (?, ?, ?, ?, 0, ?, ?)");
                $stmt->bind_param('iisiii', $deptId, $mutual_dept_id, $apparatus_label, $apparatus_type_id, $sort_order, $is_active);
            }

            if ($stmt->execute()) {
                $flash = 'Mutual aid apparatus saved.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                $selectedMutualDeptId = $mutual_dept_id;
            } else {
                $error = 'Database error saving apparatus: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Delete mutual aid apparatus template
    if ($_POST['action'] === 'delete_ma_apparatus') {
        $activeTab = 'mutual_aid';
        $app_id = (int)($_POST['app_id'] ?? 0);
        $mutual_dept_id = (int)($_POST['ma_mutual_dept_id'] ?? 0);

        if ($app_id > 0) {
            $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_apparatus_template
                                          WHERE id=? AND home_dept_id=? AND mutual_dept_id=?");
            $stmt->bind_param('iii', $app_id, $deptId, $mutual_dept_id);
            if ($stmt->execute()) {
                $flash = 'Mutual aid apparatus removed.';
                 prg_success_redirect('mutual_aid', (int)$mutual_dept_id, $flash);
                $selectedMutualDeptId = $mutual_dept_id;
            } else {
                $error = 'Database error removing apparatus: ' . $stmt->error;
            }
            $stmt->close();
        }
    }



}
/** Handle apparatus add/update/delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_apparatus') {
        $activeTab = 'apparatus';

        $app_id = (int)($_POST['app_id'] ?? 0);
        $apparatus_type = trim((string)($_POST['apparatus_type'] ?? ''));
        $apparatus_name = trim((string)($_POST['apparatus_name'] ?? ''));
        $radio_id = trim((string)($_POST['radio_id'] ?? ''));
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active_app']) ? 1 : 0;

        if ($apparatus_type === '' || $apparatus_name === '') {
            $error = 'Apparatus Type and Apparatus ID/Name are required.';
        } else {
            if ($app_id > 0) {
                $stmt = prepare_or_die($conn, 'UPDATE department_apparatus
                    SET apparatus_name=?, apparatus_type=?, radio_id=?, sort_order=?, is_active=?
                    WHERE id=? AND dept_id=?');
                $stmt->bind_param('sssiiii', $apparatus_name, $apparatus_type, $radio_id, $sort_order, $is_active, $app_id, $deptId);
                if ($stmt->execute()) {
                    $flash = 'Apparatus updated.';
                    $editAppId = 0;
                    $editApp = null;
                } else {
                    $error = 'Database error updating apparatus: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $stmt = prepare_or_die($conn, 'INSERT INTO department_apparatus (dept_id, apparatus_name, apparatus_type, radio_id, sort_order, is_active)
                                        VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isssii', $deptId, $apparatus_name, $apparatus_type, $radio_id, $sort_order, $is_active);
                if ($stmt->execute()) {
                    $flash = 'Apparatus added.';
                } else {
                    $error = 'Database error adding apparatus: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    if ($_POST['action'] === 'delete_apparatus') {
        $activeTab = 'apparatus';
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = prepare_or_die($conn, 'DELETE FROM department_apparatus WHERE id=? AND dept_id=?');
            $stmt->bind_param('ii', $del_id, $deptId);
            if ($stmt->execute()) {
                $flash = 'Apparatus deleted.';
            } else {
                $error = 'Database error deleting apparatus: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($_POST['action'] === 'save_command_staff') {
        $activeTab = 'command_staff';

        $cmd_id = (int)($_POST['cmd_id'] ?? 0);
        $member_name = trim((string)($_POST['member_name'] ?? ''));
        $rank_id = (int)($_POST['rank_id'] ?? 0);
        $radio_designation = trim((string)($_POST['radio_designation'] ?? ''));
        $can_be_ic = isset($_POST['can_be_incident_command']) ? 1 : 0;
        $is_active_cmd = isset($_POST['is_active_cmd']) ? 1 : 0;

        if ($member_name === '' || $rank_id <= 0) {
            $error = 'Rank and Member/Identifier are required.';
        } else {
            if ($cmd_id > 0) {
                $stmt = prepare_or_die($conn, 'UPDATE department_command
                    SET member_name=?, rank_id=?, radio_designation=?, can_be_incident_command=?, is_active=?
                    WHERE id=? AND dept_id=? AND is_mutual_aid=0');
                $stmt->bind_param('sisiiii', $member_name, $rank_id, $radio_designation, $can_be_ic, $is_active_cmd, $cmd_id, $deptId);
                if ($stmt->execute()) {
                    $flash = 'Command staff updated.';
                    prg_success_redirect('command_staff', 0, $flash);
                } else {
                    $error = 'Database error updating command staff: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $stmt = prepare_or_die($conn, 'INSERT INTO department_command
                    (dept_id, member_name, rank_id, radio_designation, can_be_incident_command, is_active, is_mutual_aid)
                    VALUES (?, ?, ?, ?, ?, ?, 0)');
                $stmt->bind_param('isisii', $deptId, $member_name, $rank_id, $radio_designation, $can_be_ic, $is_active_cmd);
                if ($stmt->execute()) {
                    $flash = 'Command staff added.';
                    prg_success_redirect('command_staff', 0, $flash);
                } else {
                    $error = 'Database error adding command staff: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    if ($_POST['action'] === 'delete_command_staff') {
        $activeTab = 'command_staff';
        $del_id = (int)($_POST['del_id'] ?? 0);
        if ($del_id > 0) {
            $stmt = prepare_or_die($conn, 'DELETE FROM department_command WHERE id=? AND dept_id=? AND is_mutual_aid=0');
            $stmt->bind_param('ii', $del_id, $deptId);
            if ($stmt->execute()) {
                $flash = 'Command staff deleted.';
                prg_success_redirect('command_staff', 0, $flash);
            } else {
                $error = 'Database error deleting command staff: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

/** Load apparatus list */
$apparatus = [];
$stmt = prepare_or_die($conn, 'SELECT id, apparatus_name, apparatus_type, radio_id, sort_order, is_active, created_at
                        FROM department_apparatus WHERE dept_id=? ORDER BY sort_order ASC, apparatus_type ASC, apparatus_name ASC');
$stmt->bind_param('i', $deptId);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $apparatus[] = $row;
    }
}
$stmt->close();


/** Load command staff list */
$commandStaff = [];
$stmt = prepare_or_die($conn, 'SELECT dc.id, dc.member_name, dc.rank_id, r.rank_name, dc.radio_designation,
                                      dc.can_be_incident_command, dc.is_active, dc.created_at
                              FROM department_command dc
                              JOIN department_command_rank r ON r.id = dc.rank_id
                              WHERE dc.dept_id=? AND dc.is_mutual_aid=0
                              ORDER BY r.sort_order ASC, r.rank_name ASC, dc.member_name ASC');
$stmt->bind_param('i', $deptId);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $commandStaff[] = $row;
    }
}
$stmt->close();

$welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Department Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .btn-lg { padding: .8rem 1.2rem; font-size: 1.1rem; }
    .card { border-radius: 14px; }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
  <div class="container-fluid">

  <!-- DEBUG/CLARITY: show which department record is being edited -->
<a class="navbar-brand" href="incidents.php?skip_onboard=1">FD Incident Management</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="incidents.php?skip_onboard=1">Back to Incidents</a>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width: 980px;">
  <h2 class="mb-3">Department Settings</h2>

  <?php if ($welcome): ?>
    <div class="alert alert-warning">
      <strong>Setup step:</strong> Please enter your department apparatus before creating incidents.
    </div>
  <?php endif; ?>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?= $activeTab==='info'?'active':'' ?>" href="?tab=info">Department Info</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab==='apparatus'?'active':'' ?>" href="?tab=apparatus">Apparatus</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab==='command_staff'?'active':'' ?>" href="?tab=command_staff">Command Staff</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab==='mutual_aid'?'active':'' ?>" href="?tab=mutual_aid">Mutual Aid</a>
    </li>
  </ul>

  <?php if ($activeTab === 'info'): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="save_dept">

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Department Name *</label>
              <input class="form-control form-control-lg" name="dept_name" value="<?= e((string)($dept['dept_name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Short Name *</label>
              <input class="form-control form-control-lg" name="dept_short_name" value="<?= e((string)($dept['dept_short_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label">Designation (e.g., EVFD)</label>
              <input class="form-control form-control-lg" name="designation" value="<?= e((string)($dept['designation'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Station ID</label>
              <input class="form-control form-control-lg" name="station_id" value="<?= e((string)($dept['station_id'] ?? '')) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= ((int)($dept['is_active'] ?? 1)===1)?'checked':'' ?>>
                <label class="form-check-label" for="is_active">Active</label>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Contact Name</label>
              <input class="form-control" name="contact_name" value="<?= e((string)($dept['contact_name'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Contact Email</label>
              <input class="form-control" type="email" name="contact_email" value="<?= e((string)($dept['contact_email'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Contact Phone</label>
              <input class="form-control" name="contact_phone" value="<?= e((string)($dept['contact_phone'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Contact Address</label>
              <input class="form-control" name="contact_address" value="<?= e((string)($dept['contact_address'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <button class="btn btn-primary btn-lg">Save Department Info</button>
              <a class="btn btn-outline-secondary btn-lg" href="incidents.php?skip_onboard=1">Return</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php elseif ($activeTab === 'apparatus'): ?>
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3"><?= $editApp ? 'Edit Apparatus' : 'Add Apparatus' ?></h5>
            <form method="post">
              <input type="hidden" name="action" value="save_apparatus">
              <input type="hidden" name="app_id" value="<?= (int)($editApp['id'] ?? 0) ?>">

              <div class="mb-3">
                <label class="form-label">Apparatus Type *</label>
                <select class="form-select form-select-lg" name="apparatus_type" required>
                  <option value="">Select type…</option>
                  <?php
                    $curType = (string)($editApp['apparatus_type'] ?? '');
                    foreach ($apparatusTypes as $t):
                      $sel = ($t === $curType) ? 'selected' : '';
                  ?>
                    <option value="<?= e($t) ?>" <?= $sel ?>><?= e($t) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Apparatus ID/Name *</label>
                <input class="form-control form-control-lg" name="apparatus_name" value="<?= e((string)($editApp['apparatus_name'] ?? '')) ?>" placeholder="Eng52, Truck54, Tanker59…" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Radio ID (optional)</label>
                <input class="form-control form-control-lg" name="radio_id" value="<?= e((string)($editApp['radio_id'] ?? '')) ?>" placeholder="E1, L5, T8…">
              </div>

              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">Sort Order</label>
                  <input class="form-control form-control-lg" type="number" name="sort_order" value="<?= e((string)($editApp['sort_order'] ?? '0')) ?>">
                </div>
                <div class="col-6 d-flex align-items-end">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active_app" name="is_active_app" <?= ((int)($editApp['is_active'] ?? 1)===1)?'checked':'' ?>>
                    <label class="form-check-label" for="is_active_app">Active</label>
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-success btn-lg"><?= $editApp ? 'Save Changes' : 'Add Apparatus' ?></button>
                <?php if ($editApp): ?>
                  <a class="btn btn-outline-secondary btn-lg" href="?tab=apparatus">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Current Apparatus</h5>

            <?php if (count($apparatus) === 0): ?>
              <div class="alert alert-secondary mb-0">No apparatus entered yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Type</th>
                      <th>Apparatus</th>
                      <th>Radio</th>
                      <th>Active</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($apparatus as $a): ?>
                      <tr>
                        <td><?= (int)$a['sort_order'] ?></td>
                        <td><?= e((string)$a['apparatus_type']) ?></td>
                        <td><?= e((string)$a['apparatus_name']) ?></td>
                        <td><?= e((string)($a['radio_id'] ?? '')) ?></td>
                        <td><?= ((int)$a['is_active']===1) ? 'Yes' : 'No' ?></td>
                        <td class="text-end">
                          <button type="button" class="btn btn-sm btn-outline-primary btn-edit-apparatus" data-bs-toggle="modal" data-bs-target="#editApparatusModal"
                                  data-app-id="<?= (int)$a['id'] ?>"
                                  data-app-type="<?= e((string)$a['apparatus_type']) ?>"
                                  data-app-name="<?= e((string)$a['apparatus_name']) ?>"
                                  data-app-radio="<?= e((string)($a['radio_id'] ?? '')) ?>"
                                  data-app-sort="<?= (int)$a['sort_order'] ?>"
                                  data-app-active="<?= (int)$a['is_active'] ?>">Edit</button>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this apparatus?');">
                            <input type="hidden" name="action" value="delete_apparatus">
                            <input type="hidden" name="del_id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>
  
  <?php elseif ($activeTab === 'command_staff'): ?>
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Add Command Staff</h5>
            <form method="post" autocomplete="off">
              <input type="hidden" name="action" value="save_command_staff">
              <input type="hidden" name="cmd_id" value="0">

              <div class="mb-3">
                <label class="form-label">Rank *</label>
                <select class="form-select form-select-lg" name="rank_id" required>
                  <option value="">Select rank…</option>
                  <?php foreach ($commandRanks as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= e((string)$r['rank_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Member / Identifier *</label>
                <input class="form-control form-control-lg" name="member_name" placeholder="Chief50, Captain Smith, Engine 52 Alpha" required>
                <div class="form-text">Use whatever your department actually uses on the fireground.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Radio Designation (optional)</label>
                <input class="form-control form-control-lg" name="radio_designation" placeholder="Chief 50, Safety 1, IC, etc.">
              </div>

              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="can_be_ic_add" name="can_be_incident_command">
                    <label class="form-check-label" for="can_be_ic_add">Can be Incident Command</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active_cmd_add" name="is_active_cmd" checked>
                    <label class="form-check-label" for="is_active_cmd_add">Active</label>
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-success btn-lg" type="submit">Add Command Staff</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Current Command Staff</h5>

            <?php if (count($commandStaff) === 0): ?>
              <div class="alert alert-secondary mb-0">No command staff entered yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>Rank</th>
                      <th>Member</th>
                      <th>Radio</th>
                      <th>IC</th>
                      <th>Active</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($commandStaff as $c): ?>
                      <tr>
                        <td><?= e((string)$c['rank_name']) ?></td>
                        <td><?= e((string)$c['member_name']) ?></td>
                        <td><?= e((string)($c['radio_designation'] ?? '')) ?></td>
                        <td><?= ((int)$c['can_be_incident_command']===1) ? 'Yes' : 'No' ?></td>
                        <td><?= ((int)$c['is_active']===1) ? 'Yes' : 'No' ?></td>
                        <td class="text-end">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-primary btn-edit-command"
                            data-bs-toggle="modal"
                            data-bs-target="#editCommandModal"
                            data-cmd-id="<?= (int)$c['id'] ?>"
                            data-cmd-rank-id="<?= (int)$c['rank_id'] ?>"
                            data-cmd-member="<?= e((string)$c['member_name']) ?>"
                            data-cmd-radio="<?= e((string)($c['radio_designation'] ?? '')) ?>"
                            data-cmd-ic="<?= (int)$c['can_be_incident_command'] ?>"
                            data-cmd-active="<?= (int)$c['is_active'] ?>"
                          >Edit</button>

                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this command staff entry?');">
                            <input type="hidden" name="action" value="delete_command_staff">
                            <input type="hidden" name="del_id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

          </div>
        </div>
      </div>
    </div>

    <!-- Edit Command Staff Modal (single instance) -->
    <div class="modal fade" id="editCommandModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content" autocomplete="off">
          <div class="modal-header">
            <h5 class="modal-title">Edit Command Staff</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <input type="hidden" name="action" value="save_command_staff">
            <input type="hidden" name="cmd_id" id="edit_cmd_id" value="0">

            <div class="mb-3">
              <label class="form-label">Rank *</label>
              <select class="form-select form-select-lg" name="rank_id" id="edit_cmd_rank" required>
                <option value="">Select rank…</option>
                <?php foreach ($commandRanks as $r): ?>
                  <option value="<?= (int)$r['id'] ?>"><?= e((string)$r['rank_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Member / Identifier *</label>
              <input class="form-control form-control-lg" name="member_name" id="edit_cmd_member" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Radio Designation (optional)</label>
              <input class="form-control form-control-lg" name="radio_designation" id="edit_cmd_radio">
            </div>

            <div class="row g-2">
              <div class="col-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="edit_cmd_ic" name="can_be_incident_command">
                  <label class="form-check-label" for="edit_cmd_ic">Can be Incident Command</label>
                </div>
              </div>
              <div class="col-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="edit_cmd_active" name="is_active_cmd">
                  <label class="form-check-label" for="edit_cmd_active">Active</label>
                </div>
              </div>
            </div>

            <div class="small text-muted mt-2">
              Tip: If the screen ever looks “stuck dark”, hit ESC to close the modal.
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <script>
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.btn-edit-command');
      if (!btn) return;

      document.getElementById('edit_cmd_id').value = btn.getAttribute('data-cmd-id') || '0';
      document.getElementById('edit_cmd_member').value = btn.getAttribute('data-cmd-member') || '';
      document.getElementById('edit_cmd_radio').value = btn.getAttribute('data-cmd-radio') || '';

      const rankId = btn.getAttribute('data-cmd-rank-id') || '';
      document.getElementById('edit_cmd_rank').value = rankId;

      const ic = btn.getAttribute('data-cmd-ic') || '0';
      const active = btn.getAttribute('data-cmd-active') || '1';
      document.getElementById('edit_cmd_ic').checked = (ic === '1' || ic === 1);
      document.getElementById('edit_cmd_active').checked = (active === '1' || active === 1);
    });
    </script>


  <?php elseif ($activeTab === 'mutual_aid'): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">Mutual Aid Departments</div>
      <div class="card-body">
        <div class="alert alert-secondary small mb-3">
          Add your mutual aid partners here. After you add (and select) a mutual aid department, you can add its command officers and apparatus below.
        </div>

        <form method="post" class="row g-3 align-items-end" id="maDeptForm">
          <input type="hidden" name="action" id="maDeptAction" value="add_mutual_aid_dept">
          <input type="hidden" name="ma_link_id" id="ma_link_id" value="0">
          <input type="hidden" name="ma_mutual_dept_id" id="ma_mutual_dept_id" value="0">

          <div class="col-md-6">
            <label class="form-label">Mutual Aid Department Name *</label>
            <input class="form-control" name="ma_dept_name" id="ma_dept_name" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Short Name *</label>
            <input class="form-control" name="ma_dept_short" id="ma_dept_short" required>
          </div>
          <div class="col-md-1">
            <label class="form-label">Sort</label>
            <input class="form-control" type="number" name="ma_sort_order" id="ma_sort_order" value="0">
          </div>
          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="ma_is_active" id="ma_is_active" checked>
              <label class="form-check-label" for="ma_is_active">Active</label>
            </div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit" id="maDeptSubmit">Add Mutual Aid Department</button>
            <button class="btn btn-outline-secondary" type="button" id="maDeptReset">Clear</button>
          </div>
        </form>

        <hr class="my-3">

        <div class="table-responsive bg-light p-2 rounded">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>Selected</th>
                <th>Department</th>
                <th>Short</th>
                <th class="text-center">Active</th>
                <th class="text-center">Sort</th>
                <th style="width:260px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($linkedMutualDepts)): ?>
                <tr><td colspan="6" class="text-muted">No mutual aid departments linked yet.</td></tr>
              <?php else: ?>
                <?php foreach ($linkedMutualDepts as $row): ?>
                  <tr>
                    <td class="text-center">
                      <?php if ((int)$row['mutual_dept_id'] === (int)$selectedMutualDeptId): ?>
                        <span class="badge bg-success">Selected</span>
                      <?php else: ?>
                        <a class="btn btn-sm btn-outline-success" href="?tab=mutual_aid&ma_dept_id=<?= (int)$row['mutual_dept_id'] ?>">Select</a>
                      <?php endif; ?>
                    </td>
                    <td><?= e((string)$row['dept_name']) ?></td>
                    <td><?= e((string)$row['dept_short_name']) ?></td>
                    <td class="text-center"><?= ((int)$row['is_active']===1) ? 'Yes' : 'No' ?></td>
                    <td class="text-center"><?= (int)$row['sort_order'] ?></td>
                    <td>
                      <button type="button"
                              class="btn btn-sm btn-outline-primary me-1 btnMaEdit"
                              data-link-id="<?= (int)$row['link_id'] ?>"
                              data-mutual-id="<?= (int)$row['mutual_dept_id'] ?>"
                              data-name="<?= e((string)$row['dept_name']) ?>"
                              data-short="<?= e((string)$row['dept_short_name']) ?>"
                              data-active="<?= (int)$row['is_active'] ?>"
                              data-sort="<?= (int)$row['sort_order'] ?>">
                        Edit
                      </button>

                      <form method="post" class="d-inline" onsubmit="return confirm('Remove this mutual aid link? This will also remove its officer/apparatus templates for this home department.');">
                        <input type="hidden" name="action" value="delete_mutual_aid_link">
                        <input type="hidden" name="ma_link_id" value="<?= (int)$row['link_id'] ?>">
                        <input type="hidden" name="ma_mutual_dept_id" value="<?= (int)$row['mutual_dept_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php
      $hasSelectedMA = ($selectedMutualDeptId > 0);
    ?>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-bold">Mutual Aid Command Officers</div>
      <div class="card-body">
        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-6">
            <label class="form-label">Mutual Aid Department</label>
            <select class="form-select" onchange="window.location='?tab=mutual_aid&ma_dept_id='+this.value;">
              <option value="0">-- Select a mutual aid department --</option>
              <?php foreach ($linkedMutualDepts as $row): ?>
                <option value="<?= (int)$row['mutual_dept_id'] ?>" <?= ((int)$row['mutual_dept_id']===(int)$selectedMutualDeptId)?'selected':'' ?>>
                  <?= e((string)$row['dept_name']) ?> (<?= e((string)$row['dept_short_name']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if (!$hasSelectedMA): ?>
          <div class="alert alert-warning">Select a mutual aid department above to add officers.</div>
        <?php else: ?>
          <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="save_ma_officer">
            <input type="hidden" name="ma_mutual_dept_id" value="<?= (int)$selectedMutualDeptId ?>">
            <input type="hidden" name="officer_id" value="0">

            <div class="col-md-6">
              <label class="form-label">Officer Identifier *</label>
              <input class="form-control" name="officer_display" required placeholder="Chief 50, Captain Smith, Engine 52 Alpha">
            </div>

            <div class="col-md-3">
              <label class="form-label">Rank</label>
              <select class="form-select" name="rank_id">
                <option value="0">--</option>
                <?php foreach ($commandRanks as $rk): ?>
                  <option value="<?= (int)$rk['id'] ?>"><?= e((string)$rk['rank_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-1">
              <label class="form-label">Sort</label>
              <input class="form-control" type="number" name="sort_order" value="0">
            </div>

            <div class="col-md-2">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="ma_off_active" checked>
                <label class="form-check-label" for="ma_off_active">Active</label>
              </div>
            </div>

            <div class="col-12">
              <button class="btn btn-primary" type="submit">Add Officer</button>
            </div>
          </form>

          <div class="table-responsive bg-light p-2 rounded mt-3">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>Officer</th>
                  <th>Rank</th>
                  <th class="text-center">Active</th>
                  <th class="text-center">Sort</th>
                  <th style="width:140px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($maOfficers)): ?>
                  <tr><td colspan="5" class="text-muted">No officers entered yet.</td></tr>
                <?php else: ?>
                  <?php
                    $rankMap = [];
                    foreach ($commandRanks as $rk) { $rankMap[(int)$rk['id']] = (string)$rk['rank_name']; }
                  ?>
                  <?php foreach ($maOfficers as $o): ?>
                    <tr>
                      <td><?= e((string)$o['officer_display']) ?></td>
                      <td><?= e($rankMap[(int)$o['rank_id']] ?? '') ?></td>
                      <td class="text-center"><?= ((int)$o['is_active']===1) ? 'Yes' : 'No' ?></td>
                      <td class="text-center"><?= (int)$o['sort_order'] ?></td>
                      <td>
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this officer?');">
                          <input type="hidden" name="action" value="delete_ma_officer">
                          <input type="hidden" name="ma_mutual_dept_id" value="<?= (int)$selectedMutualDeptId ?>">
                          <input type="hidden" name="officer_id" value="<?= (int)$o['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-bold">Mutual Aid Apparatus</div>
      <div class="card-body">
        <?php if (!$hasSelectedMA): ?>
          <div class="alert alert-warning">Select a mutual aid department above to add apparatus.</div>
        <?php else: ?>
          <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="save_ma_apparatus">
            <input type="hidden" name="ma_mutual_dept_id" value="<?= (int)$selectedMutualDeptId ?>">
            <input type="hidden" name="app_id" value="0">

            <div class="col-md-6">
              <label class="form-label">Apparatus Label *</label>
              <input class="form-control" name="apparatus_label" required placeholder="Eng 1, Truck 8, Tanker 52">
            </div>

            <div class="col-md-3">
              <label class="form-label">Apparatus Type *</label>
              <select class="form-select" name="apparatus_type_id" required>
                <option value="0">-- Select --</option>
                <?php foreach ($apparatusTypeOptions as $at): ?>
                  <option value="<?= (int)$at['id'] ?>"><?= e((string)$at['ApparatusType']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-1">
              <label class="form-label">Sort</label>
              <input class="form-control" type="number" name="sort_order" value="0">
            </div>

            <div class="col-md-2">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="is_active" id="ma_app_active" checked>
                <label class="form-check-label" for="ma_app_active">Active</label>
              </div>
            </div>

            <div class="col-12">
              <button class="btn btn-primary" type="submit">Add Apparatus</button>
            </div>
          </form>

          <div class="table-responsive bg-light p-2 rounded mt-3">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Type</th>
                  <th class="text-center">Active</th>
                  <th class="text-center">Sort</th>
                  <th style="width:140px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $typeMap = [];
                  foreach ($apparatusTypeOptions as $at) { $typeMap[(int)$at['id']] = (string)$at['ApparatusType']; }
                ?>
                <?php if (empty($maApparatus)): ?>
                  <tr><td colspan="5" class="text-muted">No apparatus entered yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($maApparatus as $a): ?>
                    <tr>
                      <td><?= e((string)$a['apparatus_label']) ?></td>
                      <td><?= e($typeMap[(int)$a['apparatus_type_id']] ?? '') ?></td>
                      <td class="text-center"><?= ((int)$a['is_active']===1) ? 'Yes' : 'No' ?></td>
                      <td class="text-center"><?= (int)$a['sort_order'] ?></td>
                      <td>
                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this apparatus?');">
                          <input type="hidden" name="action" value="delete_ma_apparatus">
                          <input type="hidden" name="ma_mutual_dept_id" value="<?= (int)$selectedMutualDeptId ?>">
                          <input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      // Edit Mutual Aid Department: populate the top form (no base department dropdown - home dept is already known)
      document.querySelectorAll('.btnMaEdit').forEach(function(btn){
        btn.addEventListener('click', function(){
          document.getElementById('maDeptAction').value = 'update_mutual_aid_dept';
          document.getElementById('maDeptSubmit').textContent = 'Update Mutual Aid Department';
          document.getElementById('ma_link_id').value = btn.getAttribute('data-link-id') || '0';
          document.getElementById('ma_mutual_dept_id').value = btn.getAttribute('data-mutual-id') || '0';
          document.getElementById('ma_dept_name').value = btn.getAttribute('data-name') || '';
          document.getElementById('ma_dept_short').value = btn.getAttribute('data-short') || '';
          document.getElementById('ma_sort_order').value = btn.getAttribute('data-sort') || '0';
          document.getElementById('ma_is_active').checked = (btn.getAttribute('data-active') === '1');
          window.scrollTo({top:0, behavior:'smooth'});
        });
      });

      document.getElementById('maDeptReset')?.addEventListener('click', function(){
        document.getElementById('maDeptAction').value = 'add_mutual_aid_dept';
        document.getElementById('maDeptSubmit').textContent = 'Add Mutual Aid Department';
        document.getElementById('ma_link_id').value = '0';
        document.getElementById('ma_mutual_dept_id').value = '0';
        document.getElementById('ma_dept_name').value = '';
        document.getElementById('ma_dept_short').value = '';
        document.getElementById('ma_sort_order').value = '0';
        document.getElementById('ma_is_active').checked = true;
      });
    </script>


<?php endif; ?>
</div>

<script>
  // Prevent accidental double-submits (can look like a "freeze" on slow machines / networks)
  document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    // If a form is already submitting, block the second submit.
    if (form.dataset.submitting === '1') {
      e.preventDefault();
      return false;
    }
    form.dataset.submitting = '1';

    // Disable all submit buttons in this form.
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(btn){
      btn.disabled = true;
    });
  }, true);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>