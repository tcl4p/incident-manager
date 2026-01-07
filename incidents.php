<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/db_connect.php'; // defines $conn

if (session_status() === PHP_SESSION_NONE) session_start();

$loggedDeptId = isset($_SESSION['dept_id']) ? (int)$_SESSION['dept_id'] : 0;
$loggedDeptName = $_SESSION['dept_name'] ?? '';
$loggedDeptShortName = $_SESSION['dept_short_name'] ?? '';

if ($loggedDeptId <= 0) {
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['dept_id'])) {
    // header("Location: incidents.php");
    // exit;
}

// Session for logged-in department context
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in, go back to keypad login
if ($loggedDeptId <= 0) {
    header('Location: index.php');
    exit;
}

$loggedDeptId = isset($_SESSION['dept_id']) ? (int)$_SESSION['dept_id'] : 0;
$loggedDeptName = isset($_SESSION['dept_name']) ? trim((string)$_SESSION['dept_name']) : '';




// On first login, ensure the department has apparatus set up.
// If none exist yet, send the user to Department Settings → Apparatus.
if (!isset($_GET['skip_onboard'])) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM department_apparatus WHERE dept_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $loggedDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $count = $row ? (int)$row['c'] : 0;
        $stmt->close();
        if ($count <= 0) {
            $needsApparatusSetup = true;
        }
    }
}

// ------------------------------------------------------------
// Auto-cleanup removed: incidents are now closed from the Command Board (no auto-delete on load).
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}




function allowed_apparatus_statuses(): array {
    // Stored values (keep short; UI labels can be nicer)
    return [
        'Responding',
        'On Scene',
        'Cancelled',
        'Rejoined',
        'Returning',
        'In Quarters',
        'Released',
    ];
}

function set_apparatus_status_with_log(mysqli $conn, int $arId, string $newStatus, ?string $note = null): void {
    $allowed = allowed_apparatus_statuses();
    if (!in_array($newStatus, $allowed, true)) {
        throw new Exception("Invalid status selected.");
    }

    // Get incident + current status
    $stmt = $conn->prepare("SELECT `incident_id`, `status` FROM `apparatus_responding` WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Prepare failed (get apparatus status): " . $conn->error);
    }
    $stmt->bind_param("i", $arId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (get apparatus status): " . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        throw new Exception("Apparatus record not found.");
    }
    $incidentId = (int)$row['incident_id'];
    $oldStatus  = (string)$row['status'];

    if ($oldStatus === $newStatus) {
        // No change; do nothing
        return;
    }

    $conn->begin_transaction();

    // Update current status
    $stmt2 = $conn->prepare("UPDATE `apparatus_responding` SET `status` = ? WHERE `id` = ?");
    if (!$stmt2) {
        $conn->rollback();
        throw new Exception("Prepare failed (update apparatus status): " . $conn->error);
    }
    $stmt2->bind_param("si", $newStatus, $arId);
    if (!$stmt2->execute()) {
        $conn->rollback();
        throw new Exception("Update failed (apparatus status): " . $stmt2->error);
    }
    $stmt2->close();

    // Log event
    $stmt3 = $conn->prepare("
        INSERT INTO `apparatus_status_events`
            (`incident_id`, `apparatus_responding_id`, `old_status`, `new_status`, `notes`)
        VALUES
            (?, ?, ?, ?, ?)
    ");
    if (!$stmt3) {
        $conn->rollback();
        throw new Exception("Prepare failed (insert status log): " . $conn->error);
    }
    $notes = $note ?? '';
    $stmt3->bind_param("iisss", $incidentId, $arId, $oldStatus, $newStatus, $notes);
    if (!$stmt3->execute()) {
        $conn->rollback();
        throw new Exception("Insert failed (status log): " . $stmt3->error);
    }
    $stmt3->close();

    $conn->commit();
}

$post_error       = null;
$db_error         = null;
$types_error      = null;
$app_types_error  = null;

// After a successful Add Incident, we optionally prompt for the Pre-Departure Checklist
$post_success_add    = false;
$show_predep_prompt  = false;
$new_incident_id     = 0;

$incidents            = [];
$incidentTypes        = [];
$apparatusTypes       = [];
$appByIncident        = []; // incident_id => [apparatus rows]
$sizeupByIncident     = []; // incident_id => sizeup row
$checklistsByCategory = [];

// Ensure we have a mysqli connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    $db_error = "Database connection \$conn (mysqli) not found. Check db_connect.php.";
} else {
    $conn->set_charset('utf8mb4');
}

// ----------------------
// HANDLE POST
// ----------------------
// ------------------------------------------------------------
// HANDLE POST ACTIONS
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($db_error)) { 

    // 1) ADD INCIDENT + FIRST APPARATUS via modal
    if (isset($_POST['action']) && $_POST['action'] === 'add_incident') {
        $deptName    = isset($_POST['dept_name']) ? trim($_POST['dept_name']) : '';
        $type        = isset($_POST['type']) ? (int)$_POST['type'] : 0; // incident_types.ID (FK)
        $address     = isset($_POST['address']) ? trim($_POST['address']) : '';
        $staging     = isset($_POST["staging_location"]) ? trim($_POST["staging_location"]) : "";

        $appTypeId   = isset($_POST["apparatus_type"]) ? (int)$_POST["apparatus_type"] : 0;
        $apparatusId = isset($_POST["apparatus_id"]) ? trim($_POST["apparatus_id"]) : "";
        $ffCount     = isset($_POST['ff_count']) ? (int)$_POST['ff_count'] : 0;

        if ($loggedDeptId <= 0) {
            $post_error = "Session missing department. Please log in again.";
        } elseif ($deptName === '' || $type <= 0 || $appTypeId <= 0 || $apparatusId === '' || $ffCount <= 0) {
            $post_error = "Department, Incident Type, Apparatus Type, Apparatus ID, and Firefighter count are required.";
        } else {
            try {
                $conn->begin_transaction();

                // Insert incident
                $stmt = $conn->prepare("
                    INSERT INTO `incidents` (`dept_id`, `DeptName`, `IncidentDT`, `status`, `type`, `location`, `Staging_Location`)
                    VALUES (?, ?, NOW(6), 'Active', ?, ?, ?)
                ");
                if (!$stmt) {
                    throw new Exception("Prepare failed (incident insert): " . $conn->error);
                }
                $stmt->bind_param("isiss", $loggedDeptId, $deptName, $type, $address, $staging);
if (!$stmt->execute()) {
                    throw new Exception("Insert incident failed: " . $stmt->error);
                }
                $incidentId = $stmt->insert_id;
                $stmt->close();

                // Insert first responding apparatus
                $stmt2 = $conn->prepare("
                    INSERT INTO `apparatus_responding`
                        (`incident_id`, `apparatus_type`, `apparatus_ID`, `firefighter_count`, `dispatch_time`, `status`, `notes`)
                    VALUES
                        (?, ?, ?, ?, NOW(6), 'Responding', '')
                ");
                if (!$stmt2) {
                    throw new Exception("Prepare failed (apparatus insert): " . $conn->error);
                }
                $stmt2->bind_param("iisi", $incidentId, $appTypeId, $apparatusId, $ffCount);
                if (!$stmt2->execute()) {
                    throw new Exception("Insert apparatus failed: " . $stmt2->error);
                }
                $stmt2->close();

                $conn->commit();

                // Success: show the Pre-Departure Checklist prompt (no data saved yet)
                $post_success_add   = true;
                $show_predep_prompt = true;
                $new_incident_id    = (int)$incidentId;
} catch (Throwable $e) {
                $conn->rollback();
                $post_error = $e->getMessage();
            }
        }

    // 2) JOIN INCIDENT — add apparatus only to an existing incident
    } elseif (isset($_POST['action']) && $_POST['action'] === 'join_incident') {

        $incidentId  = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
        $appTypeId   = isset($_POST['apparatus_type']) ? (int)$_POST['apparatus_type'] : 0;
        $apparatusId = isset($_POST["apparatus_id"]) ? trim($_POST["apparatus_id"]) : "";
        $ffCount     = isset($_POST['ff_count']) ? (int)$_POST['ff_count'] : 0;

        if ($incidentId <= 0 || $appTypeId <= 0 || $apparatusId === '' || $ffCount <= 0) {
            $post_error = "Incident, Apparatus Type, Apparatus ID, and Firefighter count are required.";
        } else {
            try {
                set_apparatus_status_with_log($conn, $arId, 'Cancelled', '');
header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            } catch (Throwable $e) {
                $post_error = $e->getMessage();
            }
        }

    // 3) SAVE SIZE-UP + 360 for a specific incident
    
    // 5) APPARATUS STATUS CHANGE (log + update)
    } elseif (isset($_POST['action']) && $_POST['action'] === 'change_status') {

        $arId      = isset($_POST['apparatus_row_id']) ? (int)$_POST['apparatus_row_id'] : 0;
        $newStatus = isset($_POST['new_status']) ? trim((string)$_POST['new_status']) : '';
        $note      = isset($_POST['status_note']) ? trim((string)$_POST['status_note']) : '';

        if ($arId <= 0 || $newStatus === '') {
            $post_error = "Invalid status change request.";
        } else {
            try {
                set_apparatus_status_with_log($conn, $arId, $newStatus, $note);
                header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            } catch (Throwable $e) {
                $post_error = $e->getMessage();
            }
        }

} elseif (isset($_POST['action']) && $_POST['action'] === 'save_checklists') {

        $incidentId = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;

        if ($incidentId <= 0) {
            $post_error = "Invalid incident ID for checklist save.";
        } else {
            // ------------------------------
            // Core size-up fields (existing)
            // ------------------------------
            $building_type   = $_POST['building_type']   ?? '';
            $occupancy_type  = $_POST['occupancy_type']  ?? '';
            $smoke_side      = $_POST['smoke_side']      ?? '';
            $smoke_floor     = $_POST['smoke_floor']     ?? '';
            $fire_side       = $_POST['fire_side']       ?? '';
            $fire_floor      = $_POST['fire_floor']      ?? '';
            $command_name    = $_POST['command_name']    ?? '';
            $command_officer = $_POST['command_officer'] ?? ''; // can be blank if you hide it in UI
            $iap_mode        = $_POST['iap_mode']        ?? '';
            $walkaround_findings = $_POST['walkaround_findings'] ?? '';

            $building_type   = trim($building_type);
            $occupancy_type  = trim($occupancy_type);
            $smoke_side      = trim($smoke_side);
            $smoke_floor     = trim($smoke_floor);
            $fire_side       = trim($fire_side);
            $fire_floor      = trim($fire_floor);
            $command_name    = trim($command_name);
            $command_officer = trim($command_officer);
            $iap_mode        = trim($iap_mode);
            $walkaround_findings = trim($walkaround_findings);

            // ------------------------------
            // NEW: Size-up / 360 check fields
            // ------------------------------

            // 1) Confirm incident address (checkbox)
            $confirm_address = isset($_POST['confirm_address']) ? '1' : '0';

            // 2) Notify dispatch you have command (checkbox)
            $notify_command = isset($_POST['notify_command']) ? '1' : '0';

            // 3) Life hazard (yes / no / unknown)
            $life_hazard = $_POST['life_hazard'] ?? '';
            $life_hazard = trim($life_hazard);

            // 4) Number of stories (0–10, 11 = >10)
            $num_stories = isset($_POST['num_stories']) ? (int)$_POST['num_stories'] : 0;
            if ($num_stories < 0) {
                $num_stories = 0;
            } elseif ($num_stories > 11) {
                $num_stories = 11;
            }
            $num_stories_str = (string)$num_stories; // bind as string for simplicity

            // 5) Describe water supply (short text)
            $water_supply = $_POST['water_supply'] ?? '';
            $water_supply = trim($water_supply);

            // 6) 360 checklist – all simple yes/no checkboxes
            $walk_look_victims          = isset($_POST['walk_look_victims']) ? '1' : '0';
            $walk_note_fire_location    = isset($_POST['walk_note_fire_location']) ? '1' : '0';
            $walk_check_access_openings = isset($_POST['walk_check_access_openings']) ? '1' : '0';
            $walk_note_basement_access  = isset($_POST['walk_note_basement_access']) ? '1' : '0';
            $walk_note_exposure_risk    = isset($_POST['walk_note_exposure_risk']) ? '1' : '0';
            $walk_note_power_lines      = isset($_POST['walk_note_power_lines']) ? '1' : '0';

            try {
                $stmt = $conn->prepare("
                    INSERT INTO incident_sizeup
                        (
                            incident_id,
                            confirm_address,
                            notify_command,
                            building_type,
                            occupancy_type,
                            smoke_side,
                            smoke_floor,
                            fire_side,
                            fire_floor,
                            command_name,
                            command_officer,
                            iap_mode,
                            life_hazard,
                            num_stories,
                            water_supply,
                            walk_look_victims,
                            walk_note_fire_location,
                            walk_check_access_openings,
                            walk_note_basement_access,
                            walk_note_exposure_risk,
                            walk_note_power_lines,
                            walkaround_findings
                        )
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        confirm_address            = VALUES(confirm_address),
                        notify_command             = VALUES(notify_command),
                        building_type              = VALUES(building_type),
                        occupancy_type             = VALUES(occupancy_type),
                        smoke_side                 = VALUES(smoke_side),
                        smoke_floor                = VALUES(smoke_floor),
                        fire_side                  = VALUES(fire_side),
                        fire_floor                 = VALUES(fire_floor),
                        command_name               = VALUES(command_name),
                        command_officer            = VALUES(command_officer),
                        iap_mode                   = VALUES(iap_mode),
                        life_hazard                = VALUES(life_hazard),
                        num_stories                = VALUES(num_stories),
                        water_supply               = VALUES(water_supply),
                        walk_look_victims          = VALUES(walk_look_victims),
                        walk_note_fire_location    = VALUES(walk_note_fire_location),
                        walk_check_access_openings = VALUES(walk_check_access_openings),
                        walk_note_basement_access  = VALUES(walk_note_basement_access),
                        walk_note_exposure_risk    = VALUES(walk_note_exposure_risk),
                        walk_note_power_lines      = VALUES(walk_note_power_lines),
                        walkaround_findings        = VALUES(walkaround_findings)
                ");
                if (!$stmt) {
                    throw new Exception("Prepare failed (incident_sizeup upsert): " . $conn->error);
                }

                // For simplicity and to avoid type-count mismatches, bind everything as string
                $incidentId_str = (string)$incidentId;

                $stmt->bind_param(
                    "ssssssssssssssssssssss",
                    $incidentId_str,
                    $confirm_address,
                    $notify_command,
                    $building_type,
                    $occupancy_type,
                    $smoke_side,
                    $smoke_floor,
                    $fire_side,
                    $fire_floor,
                    $command_name,
                    $command_officer,
                    $iap_mode,
                    $life_hazard,
                    $num_stories_str,
                    $water_supply,
                    $walk_look_victims,
                    $walk_note_fire_location,
                    $walk_check_access_openings,
                    $walk_note_basement_access,
                    $walk_note_exposure_risk,
                    $walk_note_power_lines,
                    $walkaround_findings
                );

                if (!$stmt->execute()) {
                    throw new Exception("Size-up save failed: " . $stmt->error);
                }
                $stmt->close();

                // Auto: when Size-Up is saved, set initial IC to the first-due apparatus (until relieved)
                // and mark that apparatus as On Scene.
                $primaryAppName = null;
                $incidentCommanderDisplay = null;

                // Get incident primary apparatus (if set) + current IC display (if any)
                if ($stmt2 = $conn->prepare("SELECT primary_apparatus_id, incident_commander_display FROM incidents WHERE id = ? LIMIT 1")) {
                    $stmt2->bind_param("i", $incidentId);
                    $stmt2->execute();
                    $r2 = $stmt2->get_result();
                    if ($r2 && ($row2 = $r2->fetch_assoc())) {
                        $primaryAppId = isset($row2['primary_apparatus_id']) ? (int)$row2['primary_apparatus_id'] : 0;
                        $incidentCommanderDisplay = trim((string)($row2['incident_commander_display'] ?? ''));
                        if ($primaryAppId > 0) {
                            if ($stmt3 = $conn->prepare("SELECT apparatus_name FROM department_apparatus WHERE id = ? LIMIT 1")) {
                                $stmt3->bind_param("i", $primaryAppId);
                                $stmt3->execute();
                                $r3 = $stmt3->get_result();
                                if ($r3 && ($row3 = $r3->fetch_assoc())) {
                                    $primaryAppName = trim((string)$row3['apparatus_name']);
                                }
                                $stmt3->close();
                            }
                        }
                    }
                    $stmt2->close();
                }

                // Fallback: pick the earliest apparatus_responding entry for this incident
                if ($primaryAppName === null || $primaryAppName === '') {
                    if ($stmt4 = $conn->prepare("SELECT id, apparatus_ID FROM apparatus_responding WHERE incident_id = ? ORDER BY dispatch_time ASC, id ASC LIMIT 1")) {
                        $stmt4->bind_param("i", $incidentId);
                        $stmt4->execute();
                        $r4 = $stmt4->get_result();
                        if ($r4 && ($row4 = $r4->fetch_assoc())) {
                            $primaryAppName = trim((string)$row4['apparatus_ID']);
                        }
                        $stmt4->close();
                    }
                }

                if ($primaryAppName !== null && $primaryAppName !== '') {

                    // 1) Set Size-Up "command_officer" to the apparatus name if it was empty in the post
                    $postedOfficer = isset($_POST['command_officer']) ? trim((string)$_POST['command_officer']) : '';
                    if ($postedOfficer === '') {
                        if ($stmt5 = $conn->prepare("UPDATE incident_sizeup SET command_officer = ? WHERE incident_id = ?")) {
                            $stmt5->bind_param("si", $primaryAppName, $incidentId);
                            $stmt5->execute();
                            $stmt5->close();
                        }
                    }

                    // 2) Set incident IC display to the apparatus name if not already set
                    if ($incidentCommanderDisplay === '') {
                        if ($stmt6 = $conn->prepare("UPDATE incidents SET incident_commander_display = ?, incident_commander_source = 'local', incident_commander_officer_id = NULL WHERE id = ?")) {
                            $stmt6->bind_param("si", $primaryAppName, $incidentId);
                            $stmt6->execute();
                            $stmt6->close();
                        }
                    }

                    // 3) Mark primary apparatus as On Scene (with log) if we can locate the apparatus_responding row
                    $primaryArId = 0;
                    if ($stmt7 = $conn->prepare("SELECT id, status FROM apparatus_responding WHERE incident_id = ? AND apparatus_ID = ? ORDER BY id ASC LIMIT 1")) {
                        $stmt7->bind_param("is", $incidentId, $primaryAppName);
                        $stmt7->execute();
                        $r7 = $stmt7->get_result();
                        if ($r7 && ($row7 = $r7->fetch_assoc())) {
                            $primaryArId = (int)$row7['id'];
                            $curStatus = trim((string)($row7['status'] ?? ''));
                            if ($primaryArId > 0 && strcasecmp($curStatus, 'On Scene') !== 0) {
                                set_apparatus_status_with_log($conn, $primaryArId, 'On Scene', 'Auto: Size-Up saved');
                            }
                        }
                        $stmt7->close();
                    }
                }


                header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;

            } catch (Throwable $e) {
                $post_error = $e->getMessage();
            }
        }

    // 4) CANCEL / DELETE ONE INCIDENT (per-card)
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_incident' && !empty($_POST['incident_id'])) {

        $incidentId = (int)$_POST['incident_id'];
        if ($incidentId > 0) {
            try {
                $conn->begin_transaction();

                $stmtA = $conn->prepare("DELETE FROM `apparatus_responding` WHERE `incident_id` = ?");
                if ($stmtA) {
                    $stmtA->bind_param("i", $incidentId);
                    $stmtA->execute();
                    $stmtA->close();
                }

                $stmtS = $conn->prepare("DELETE FROM `incident_sizeup` WHERE `incident_id` = ?");
                if ($stmtS) {
                    $stmtS->bind_param("i", $incidentId);
                    $stmtS->execute();
                    $stmtS->close();
                }

                $stmt = $conn->prepare("DELETE FROM `incidents` WHERE `id` = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed (delete incident): " . $conn->error);
                }
                $stmt->bind_param("i", $incidentId);
                if (!$stmt->execute()) {
                    throw new Exception("Delete failed for ID {$incidentId}: " . $stmt->error);
                }
                $stmt->close();

                $conn->commit();

                header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;

            } catch (Throwable $e) {
                $conn->rollback();
                $post_error = $e->getMessage();
            }
        }

    // 5) UPDATE APPARATUS (full apparatus edit: type, label, FF count, status, notes)
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_apparatus') {

        $arId      = isset($_POST['apparatus_row_id']) ? (int)$_POST['apparatus_row_id'] : 0;
        $appTypeId = isset($_POST['edit_apparatus_type']) ? (int)$_POST['edit_apparatus_type'] : 0;
        $appId     = isset($_POST['edit_apparatus_id']) ? trim((string)$_POST['edit_apparatus_id']) : '';
        $ffCount   = isset($_POST['edit_ff_count']) ? (int)$_POST['edit_ff_count'] : 0;
        $status    = isset($_POST['edit_status']) ? trim((string)$_POST['edit_status']) : '';
        $notes     = isset($_POST['edit_notes']) ? trim((string)$_POST['edit_notes']) : '';

        if ($arId <= 0) {
            $post_error = "Invalid apparatus record selected.";
        } else {
            try {
                if ($appTypeId <= 0) {
                    throw new Exception("Please select an apparatus type.");
                }
                if ($appId === '') {
                    throw new Exception("Please enter an apparatus ID/label.");
                }

                // Fetch current values (needed for status-change logging)
                $stmt0 = $conn->prepare("SELECT `incident_id`, `status` FROM `apparatus_responding` WHERE `id` = ? LIMIT 1");
                if (!$stmt0) {
                    throw new Exception("Prepare failed (get current apparatus): " . $conn->error);
                }
                $stmt0->bind_param("i", $arId);
                if (!$stmt0->execute()) {
                    throw new Exception("Execute failed (get current apparatus): " . $stmt0->error);
                }
                $res0 = $stmt0->get_result();
                $cur = $res0 ? $res0->fetch_assoc() : null;
                $stmt0->close();
                if (!$cur) {
                    throw new Exception("Apparatus record not found.");
                }
                $incidentId = (int)$cur['incident_id'];
                $oldStatus  = (string)$cur['status'];

                // Validate status
                if (!in_array($status, allowed_apparatus_statuses(), true)) {
                    throw new Exception("Invalid status selected.");
                }

                $conn->begin_transaction();

                // Update the apparatus details (type/label/FF/notes)
                $stmt = $conn->prepare("
                    UPDATE `apparatus_responding`
                    SET `apparatus_type` = ?,
                        `apparatus_ID` = ?,
                        `firefighter_count` = ?,
                        `notes` = ?
                    WHERE `id` = ?
                ");
                if (!$stmt) {
                    $conn->rollback();
                    throw new Exception("Prepare failed (update apparatus): " . $conn->error);
                }
                $stmt->bind_param("isisi", $appTypeId, $appId, $ffCount, $notes, $arId);
                if (!$stmt->execute()) {
                    $conn->rollback();
                    throw new Exception("Update apparatus failed: " . $stmt->error);
                }
                $stmt->close();

                // If status changed, update + log
                if ($status !== $oldStatus) {
                    $stmt2 = $conn->prepare("UPDATE `apparatus_responding` SET `status` = ? WHERE `id` = ?");
                    if (!$stmt2) {
                        $conn->rollback();
                        throw new Exception("Prepare failed (update status): " . $conn->error);
                    }
                    $stmt2->bind_param("si", $status, $arId);
                    if (!$stmt2->execute()) {
                        $conn->rollback();
                        throw new Exception("Update failed (status): " . $stmt2->error);
                    }
                    $stmt2->close();

                    $stmt3 = $conn->prepare("
                        INSERT INTO `apparatus_status_events`
                            (`incident_id`, `apparatus_responding_id`, `old_status`, `new_status`, `notes`)
                        VALUES
                            (?, ?, ?, ?, ?)
                    ");
                    if (!$stmt3) {
                        $conn->rollback();
                        throw new Exception("Prepare failed (insert status log): " . $conn->error);
                    }
                    $logNotes = 'Edited via Edit Apparatus';
                    $stmt3->bind_param("iisss", $incidentId, $arId, $oldStatus, $status, $logNotes);
                    if (!$stmt3->execute()) {
                        $conn->rollback();
                        throw new Exception("Insert failed (status log): " . $stmt3->error);
                    }
                    $stmt3->close();
                }

                $conn->commit();

                header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;

            } catch (Throwable $e) {
                // If we started a transaction, roll it back.
                // rollback() is safe even if no transaction is active.
                @$conn->rollback();
                $post_error = $e->getMessage();
            }
        }

    // 6) CANCEL A SINGLE APPARATUS RESPONSE
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_apparatus') {

        $arId = isset($_POST['apparatus_row_id']) ? (int)$_POST['apparatus_row_id'] : 0;

        if ($arId <= 0) {
            $post_error = "Invalid apparatus record selected for cancel.";
        } else {
            try {
                set_apparatus_status_with_log($conn, $arId, 'Cancelled', '');
header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;

            } catch (Throwable $e) {
                $post_error = $e->getMessage();
            }
        }
    }

} // end POST handler



// ----------------------
// GET: Load incidents & related tables
// ----------------------
if (empty($db_error)) {
    try {
        // Incidents + type label
        $sql = "
              SELECT 
                  i.`id` AS id,
                  DATE_FORMAT(i.`IncidentDT`, '%Y-%m-%d %H:%i:%s') AS incident_dt,
                  i.`DeptName` AS dept_name,
                  i.`type`     AS type_id,
                  it.`incidentType` AS type_name,
                  i.`location` AS address
              FROM `incidents` i
              LEFT JOIN `incident_types` it
                ON i.`type` = it.`ID`
              WHERE COALESCE(i.`status`,'Active') = 'Active'
                AND i.`closed_at` IS NULL
                AND (
                      i.`dept_id` = ?
                   OR (i.`dept_id` IS NULL AND (i.`DeptName` = ? OR i.`DeptName` = ?))
                )
              ORDER BY i.`IncidentDT` DESC
          ";

        $stmtInc = $conn->prepare($sql);
        if (!$stmtInc) {
            $db_error = "MySQLi incidents prepare failed: " . $conn->error;
        } else {
            // Bind dept filter
            $stmtInc->bind_param("iss", $loggedDeptId, $loggedDeptName, $loggedDeptShortName);
            if (!$stmtInc->execute()) {
                $db_error = "MySQLi incidents execute failed: " . $stmtInc->error;
            } else {
                $result = $stmtInc->get_result();
                if ($result === false) {
                    $db_error = "MySQLi incidents get_result failed: " . $stmtInc->error;
                } else {
                    while ($row = $result->fetch_assoc()) {
                        $incidents[] = $row;
                    }
                    $result->free();
                }
            }
            $stmtInc->close();
        }

        // Incident types
        $sqlTypes = "
            SELECT 
                `ID` AS id,
                `incidentType` AS name
            FROM `incident_types`
            ORDER BY `incidentType` ASC
        ";
        $result2 = $conn->query($sqlTypes);
        if ($result2 === false) {
            $types_error = "MySQLi incident_types query failed: " . $conn->error;
        } else {
            while ($row = $result2->fetch_assoc()) {
                $incidentTypes[] = $row;
            }
            $result2->free();
        }

        // Apparatus type buttons (scoped to logged-in department)
        // NOTE: We intentionally do NOT join to apparatus_types here.
        // dept apparatus names may use a different collation than apparatus_types and the join can trigger
        // "Illegal mix of collations" on some installs. We only need the distinct names for buttons.
        if ($loggedDeptId > 0) {
            $sqlAppTypes = "
                SELECT
                    MIN(da.`id`) AS id,
                    da.`apparatus_name` AS name
                FROM `department_apparatus` da
                WHERE da.`dept_id` = ?
                  AND da.`is_active` = 1
                GROUP BY da.`apparatus_name`
                ORDER BY MIN(COALESCE(da.`sort_order`, 9999)) ASC, da.`apparatus_name` ASC
            ";
            $stmtAppTypes = $conn->prepare($sqlAppTypes);
            if ($stmtAppTypes) {
                $stmtAppTypes->bind_param("i", $loggedDeptId);
                if ($stmtAppTypes->execute()) {
                    $stmtAppTypes->bind_result($id, $name);
                    while ($stmtAppTypes->fetch()) {
                        $apparatusTypes[] = ['id' => (int)$id, 'name' => (string)$name];
                    }
                } else {
                    $app_types_error = "Failed to load department apparatus types: " . $stmtAppTypes->error;
                }
                $stmtAppTypes->close();
            } else {
                $app_types_error = "Prepare failed (department apparatus types): " . $conn->error;
            }
        } else {
            // No logged-in department context; keep buttons empty (user should log in via index.php)
            $app_types_error = "No department session found. Please return to login.";
        }

// Apparatus responding grouped by incident
        $appByIncident = [];
        $sqlApps = "
            SELECT 
                ar.`id`,
                ar.`incident_id`,
                i.`DeptName`            AS department_name,
                ar.`apparatus_type`     AS apparatus_type_id,
                at.`ApparatusType`      AS apparatus_type_name,
                ar.`apparatus_ID`,
                ar.`firefighter_count`,
                ar.`status`,
                ar.`notes`
            FROM `apparatus_responding` ar
            LEFT JOIN `incidents` i
              ON ar.`incident_id` = i.`id`
            LEFT JOIN `apparatus_types` at
              ON ar.`apparatus_type` = at.`id`
            ORDER BY ar.`incident_id`, ar.`id`
        ";
        $result4 = $conn->query($sqlApps);
        if ($result4 !== false) {
            while ($row = $result4->fetch_assoc()) {
                $iid = (int)$row['incident_id'];
                if (!isset($appByIncident[$iid])) {
                    $appByIncident[$iid] = [];
                }
                $appByIncident[$iid][] = $row;
            }
            $result4->free();
        }

        // Sizeup per incident
        if (!empty($incidents)) {
            $ids = array_column($incidents, 'id');
            $ids = array_filter($ids, fn($v) => (int)$v > 0);
            if (!empty($ids)) {
                $idList = implode(',', array_map('intval', $ids));
                $sqlSize = "SELECT * FROM incident_sizeup WHERE incident_id IN ($idList)";
                $resSize = $conn->query($sqlSize);
                if ($resSize !== false) {
                    while ($row = $resSize->fetch_assoc()) {
                        $sizeupByIncident[(int)$row['incident_id']] = $row;
                    }
                    $resSize->free();
                }
            }
        }

        // Checklists grouped by category
        $sqlChk = "
            SELECT 
                `id`,
                `category`,
                `item_number`,
                `description`
            FROM `firehouse_checklist`
            ORDER BY `category`, `item_number`
        ";
        $resChk = $conn->query($sqlChk);
        if ($resChk !== false) {
            while ($row = $resChk->fetch_assoc()) {
                $cat = $row['category'] ?? 'General';
                if (!isset($checklistsByCategory[$cat])) {
                    $checklistsByCategory[$cat] = [];
                }
                $checklistsByCategory[$cat][] = $row;
            }
            $resChk->free();
        }

    } catch (Throwable $e) {
        $db_error = $e->getMessage();
    }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FD Incident Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
          crossorigin="anonymous">
    <style>
           /* Size-Up status buttons: always bright and readable */
            .sizeup-btn {
                font-weight: 600;
                min-width: 110px;
            }

            /* Not completed yet: keep readable on dark UI */
            .sizeup-btn.sizeup-pending {
                background-color: #6c757d !important;  /* gray (matches btn-secondary) */
                color: #fff !important;                /* white text */
                border: none !important;
            }

            /* Completed: solid green with white text */
            .sizeup-btn.sizeup-complete {
                background-color: #28a745 !important;  /* green */
                color: #fff !important;
                border: none !important;
            }


           /* Make modal content readable on light background */
            .modal .modal-content {
              color: #000;
            }

            .modal .modal-title {
              font-size: 1.2rem;
              font-weight: 700;
              color: #000;
            }

            /* Bigger, finger-friendly checkbox + labels */
            .modal .form-check-input {
              width: 1.25rem;
              height: 1.25rem;
            }

            .modal .form-check-label {
              display: inline-block;
              font-size: 1rem;
              color: #000;
              margin-left: 0.35rem;
            }

            /* Make small buttons a bit larger for touch */
            .modal .btn-group .btn,
            .modal .btn.btn-sm.btn-choice,
            .modal .btn.btn-sm.story-btn {
              font-size: 1rem;
              padding: 0.5rem 0.9rem;
              border-width: 2px;
            }

            /* Highlight selected number-of-stories button */
            .modal .story-btn.active {
              border-color: #000;
              border-width: 3px;
            }
     
      .incident-card-header {
        background: #343a40;
        color: #fff;
      }


      body {
        background-color: #111;
        color: #fff !important;
      }

      .page-title {
        color: #fff;
      }

      .btn-choice {
        min-width: 120px;
      }

      .incident-card {
        border-radius: 0.75rem;
        overflow: hidden;
      }

      .incident-card-header {
        background: #343a40;
        color: #fff;
      }

      .first-due-btn.active {
        background-color: #0dcaf0;
        color: #000;
      }

      .checklist-category-title {
        background: #f0f0f0;
        padding: 4px 8px;
        font-weight: 600;
        border-radius: 4px;
        margin-top: 8px;
      }

      .dispatch-report-textarea {
        font-family: monospace;
        font-size: 0.9rem;
      }

      
        .modal-body {
        max-height: 70vh;
        overflow-y: auto;
        color: #000 !important;
      }



      .ff-btn.active {
        font-weight: bold;
      }

      .checklist-touch-container {
        max-height: 300px;
        overflow-y: auto;
        padding: 0.25rem 0;
      }

      /* FIXED — this is where your black text was coming from */
      .checklist-touch-item {
        padding: 0.5rem 0.75rem;
        border-radius: 0.75rem;
        background: #2a2a2a;
        border: 1px solid #444;
        font-size: 0.95rem;
        color: #fff !important;
      }

      /* Keep checklist description text visible on dark cards (some modal styles force black text) */
      .checklist-touch-item .form-check-label,
      .checklist-touch-item .form-check-label span {
        color: #fff !important;
      }

      
/* CHECKLIST CHECKBOX VISIBILITY (dark checklist cards) */
.checklist-touch-container .form-check-input{
  width: 1.35rem;
  height: 1.35rem;
  background-color: #fff !important;
  border: 2px solid #bbb !important;
  accent-color: #198754; /* modern browsers */
}
.checklist-touch-container .form-check-input:checked{
  background-color: #198754 !important;
  border-color: #198754 !important;
}

.checklist-touch-item + .checklist-touch-item {
        margin-top: 0.35rem;
      }

           /* Make incident cards and list items white on dark background,
         but do NOT override all text globally. */
      /* Make incident cards and list items white on dark background,
   but do NOT override all text globally. */
           /* Make incident cards and list items white on dark background,
   but do NOT override all text globally. */
            .card,
            .card-header,
            .card-body,
            .card-title,
            .card-subtitle,
            .card-text,
            .list-group-item {
                color: #fff !important;
            }

          /* Size-Up button text colors */
          .sizeup-btn.sizeup-pending {
              color: #fff !important;     /* white text until complete */
          }

          .sizeup-btn.sizeup-complete {
              color: #fff !important;     /* white text on green */
          }

/* Remove the ULTRA override completely */

            /* Inside Bootstrap modals, use dark text for form elements,
               but DO NOT override the checklist touch items (they are dark tiles). */
            .modal-content {
                color: #000 !important;
            }

            .modal-content .modal-title,
            .modal-content .form-label,
            .modal-content .form-control,
            .modal-content .form-select,
            .modal-content textarea,
            .modal-content p,
            .modal-content small,
            .modal-content .text-muted {
                color: #000 !important;
            }

            /* Checklist tiles should stay WHITE text on dark background */
            .modal-content .checklist-touch-item {
                color: #fff !important;
            }

            /* Category title is a light pill, so keep it dark */
            .modal-content .checklist-category-title {
                color: #000 !important;
            }


/* Remove the ULTRA global override completely.
   We do NOT want: body, div, p, span, ... { color:#fff !important; } */


      /* NOTE:
         Removed the global "h5, h6, p, span, small" and the ULTRA override.
         This lets the modal use its own (dark) text colors again. */

      /* NOTE:
         Removed the global "h5, h6, p, span, small" and the ULTRA override.
         This lets the modal use its own (dark) text colors again. */

      /* Make all table text white on dark background */
        .table,
        .table td,
        .table th,
        td,
        th {
            color: #fff !important;
        }
        /* Make Size-Up button always readable */
        .btn-sizeup {
            background-color: #0d6efd !important;  /* Bootstrap primary blue */
            color: #fff !important;
            border-color: #0a58ca !important;
            font-weight: 600;
        }

        /* Make Dispatch Report button always readable */
        .btn-dispatch {
            background-color: #6f42c1 !important;  /* nice purple */
            color: #fff !important;
            border-color: #59359c !important;
            font-weight: 600;
        }
        /* === Custom button styles for Incidents page === */

          /* Join Incident (header) */
          .btn-join {
            background-color: #198754 !important;  /* green */
            color: #ffffff !important;
            border-color: #146c43 !important;
            font-weight: 600;
          }

          /* Command Board (header) */
          .btn-command {
            background-color: #fd7e14 !important;  /* orange */
            color: #ffffff !important;
            border-color: #dc6c0d !important;
            font-weight: 600;
          }

          /* Size-Up buttons (in apparatus Tools column) */
          .btn-sizeup {
            background-color: #0dcaf0 !important;  /* teal-ish */
            color: #ffffff !important; /* force readable text */
            border-color: #0aa2c0 !important;
            font-weight: 600;
          }

          /* Dispatch Report buttons (in apparatus Tools column) */
          .btn-dispatch {
            background-color: #6c757d !important;  /* gray */
            color: #ffffff !important;
            border-color: #565e64 !important;
            font-weight: 600;
          }

          /* Cancel Incident (header) */
          .btn-cancel {
            background-color: #dc3545 !important;  /* red */
            color: #ffffff !important;
            border-color: #b02a37 !important;
            font-weight: 600;
          }
          /* Make incident cards use a dark background */
          .incident-card {
              background-color: #1c1c1c;
          }

          /* Ensure the card body is also dark */
          .incident-card .card-body {
              background-color: #1c1c1c;
          }

          /* Make table cells inside incident cards NOT force white backgrounds */
          .incident-card .table > :not(caption) > * > * {
              background-color: transparent !important;
          }
         /* 360 buttons */
                .btn-360 {
              display: inline-block;
              width: 40%;
              margin: 0.35rem auto;      /* vertical spacing + centered */
              text-align: left;
              font-size: 1.1rem;
              padding: 0.65rem 1rem;
              border-radius: 0.75rem;
              border: none;
              background-color: #28a745; /* green */
              color: #fff !important;
              box-shadow: 0 0 4px rgba(0,0,0,0.4);
          }

          .btn-360.active-360 {
              background-color: #dc3545; /* red when checked */
          }

          /* General Size-Up Buttons (green = default, red = active) */
          .btn-su {
              display: inline-block;
              min-width: 120px;
              padding: 0.65rem 1rem;
              margin: 0.25rem;
              font-size: 1.1rem;
              border-radius: 0.75rem;
              border: none;
              background-color: #28a745; /* green */
              color: #fff !important;
          }

          .btn-su.active-su {
              background-color: #dc3545 !important; /* red */
          }
          /* Final safety override: Size-Up button text should always be readable */
          button.sizeup-btn,
          .sizeup-btn {
              color: #fff !important;
          }

    </style>

</head>
  <body>

<?php if (!empty($needsApparatusSetup)): ?>
<div class="alert alert-warning">
  <strong>Setup step:</strong> You haven’t entered any department apparatus yet.
  You can still view incidents, but you’ll want apparatus entered before creating or joining an incident.
  <div class="mt-2 d-flex gap-2 flex-wrap">
    <a class="btn btn-primary" href="department_settings.php?tab=info&welcome=1">Add Apparatus Now</a>
    <a class="btn btn-outline-secondary" href="incidents.php?skip_onboard=1">Dismiss</a>
  </div>
</div>
<?php endif; ?>


<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">FD Incident Management</a>
    <div class="ms-auto d-flex gap-2">
      <a href="department_settings.php?tab=info" class="btn btn-outline-primary btn-sm">Department Settings</a>
      <a href="mutual_aid_settings.php" class="btn btn-outline-warning btn-sm">Mutual Aid</a>
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aboutFdModal">About FD Incident Manager</button>
    </div>
  </div>
</nav>

<div class="container mt-3">
  <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
    <!-- New Incident opens modal -->
   <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addIncidentModal">New Incident</button>

    <!-- Join Incident button removed at top by request -->
  </div>

  <div class="text-center mb-3">
    <?php if (!empty($post_error)): ?>
      <div class="alert alert-danger" role="alert"><?= e($post_error) ?></div>
    <?php endif; ?>

    <?php if (!empty($db_error)): ?>
      <div class="alert alert-danger" role="alert"><?= e($db_error) ?></div>
    <?php else: ?>
      <p class="text-muted mb-0 page-title"><?= count($incidents) ?> incident(s) found.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Add Incident Modal -->
<div class="modal fade" id="addIncidentModal" tabindex="-1" aria-labelledby="addIncidentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="add_incident">
        <input type="hidden" name="type" id="incident_type_input">
        <input type="hidden" name="apparatus_type" id="apparatus_type_input">
        <input type="hidden" name="ff_count" id="ff_count_input">
        <div class="modal-header">
          <h5 class="modal-title" id="addIncidentModalLabel">Add New Incident & First Responding Apparatus <span class="text-muted fs-6">— <?= e($loggedDeptName ?: $loggedDeptShortName) ?></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

  <!-- Incident Type buttons -->
  <div class="mb-3">
    <label class="form-label fw-bold">Incident Type</label>
    <div class="d-flex flex-wrap gap-2">
      <?php if (!empty($incidentTypes)): ?>
        <?php foreach ($incidentTypes as $t): ?>
          <button type="button"
                  class="btn btn-outline-danger btn-choice btn-lg incident-type-btn"
                  data-id="<?= (int)$t['id'] ?>">
            <?= e($t['name']) ?>
          </button>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="text-danger">No incident types configured. Add entries to incident_types table.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Apparatus Type buttons -->
  <div class="mb-3">
    <label class="form-label fw-bold">Apparatus Type</label>
    <div class="d-flex flex-wrap gap-2">
      <?php if (!empty($apparatusTypes)): ?>
        <?php foreach ($apparatusTypes as $a): ?>
          <button type="button"
                  class="btn btn-outline-success btn-choice btn-lg apparatus-type-btn"
                  data-id="<?= (int)$a['id'] ?>">
            <?= e($a['name']) ?>
          </button>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="text-danger">
          No apparatus types configured. Add entries to apparatus_types table.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Apparatus ID and Firefighter Count -->
  <div class="row mb-3">
    <div class="col-md-6 mb-3 mb-md-0">
      <label for="apparatus_id" class="form-label">Apparatus ID (e.g., Eng 52)</label>
      <input type="text"
             class="form-control"
             id="apparatus_id"
             name="apparatus_id"
             required
             autocomplete="off">
    </div>
    <div class="col-md-6">
      <label class="form-label">Firefighters on Board</label>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <input type="text"
               class="form-control"
               id="ff_count_display"
               readonly
               style="max-width:80px;"
               placeholder="# FF">
        <div class="btn-group" role="group" aria-label="Firefighter count">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button"
                    class="btn btn-outline-warning ff-btn"
                    data-count="<?= $i ?>">
              <?= $i ?>
            </button>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Department & Location -->
  <div class="row mb-3">
    <div class="col-md-6 mb-3">
      <label for="dept_name" class="form-label">Department Name</label>
      <input type="text"
             class="form-control"
             id="dept_name"
             name="dept_name"
             required
             value="<?= e($loggedDeptName) ?>"
             <?= ($loggedDeptName !== '' ? 'readonly' : '') ?>
             autocomplete="off">
    </div>
    <div class="col-md-6 mb-3">
      <label for="address" class="form-label">Location</label>
      <input type="text"
             class="form-control"
             id="address"
             name="address"
             placeholder="Address or location description"
             autocomplete="off">
    </div>
  </div>

  <!-- Pre-Departure Checklist (no EnRoute / En Route) -->
  <?php if (!empty($checklistsByCategory)): ?>
    <div id="preDepChecklistInline" class="mb-3" style="display:none;">
      <label class="form-label fw-bold">Pre-Departure Checklist</label>
      <div class="form-text mb-1">Shown only if you choose <strong>Yes</strong> after saving the incident.</div>
      <div class="checklist-touch-container">
        <?php foreach ($checklistsByCategory as $cat => $items): ?>
          <?php
            $catNorm = strtolower(trim($cat));
            if ($catNorm === 'enroute' || $catNorm === 'en route') {
              continue; // skip EnRoute category
            }
          ?>
          <div class="mt-2">
            <div class="checklist-category-title"><?= e($cat) ?></div>
            <div class="mt-1">
              <?php foreach ($items as $item): ?>
                <div class="checklist-touch-item">
                  <label class="form-check-label d-flex align-items-start mb-0">
                    <input type="checkbox" class="form-check-input me-2 mt-1">
                    <span><?= e($item['description']) ?></span>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <small class="text-muted">
        These items are prompts for the responding apparatus to complete before or while responding.
      </small>
    </div>
  <?php endif; ?>

</div>

        <div class="modal-footer">
          <?php if (!empty($post_error)): ?>
            <div class="text-danger me-auto"><?= e($post_error) ?></div>
          <?php endif; ?>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Return</button>
          <button type="submit" class="btn btn-success">Save Incident & Apparatus</button>
        </div>
      </form>
    </div>
  </div>
</div>
<div class="container mt-3 mb-5">
  <?php if (!empty($incidents)): ?>
    <?php foreach ($incidents as $inc): ?>
    <?php
    $iid    = (int)$inc['id'];
    $sizeup = $sizeupByIncident[$iid] ?? [];
    $apps   = $appByIncident[$iid] ?? [];

        $hasSizeup      = !empty($sizeup);
        // Size-Up button should always be readable on dark UI (force white text)
        $sizeupBtnClass = $hasSizeup
            ? 'btn btn-success btn-sm text-white sizeup-btn sizeup-complete'
            : 'btn btn-secondary btn-sm text-white sizeup-btn sizeup-pending';
  ?>
  <?php
        $iid      = (int)$inc['id'];
        $sizeup   = $sizeupByIncident[$iid] ?? [];
        $apps     = $appByIncident[$iid] ?? [];

        $bt       = $sizeup['building_type']        ?? '';
        $occ      = $sizeup['occupancy_type']       ?? '';
        $smSide   = $sizeup['smoke_side']           ?? '';
        $smFloor  = $sizeup['smoke_floor']          ?? '';
        $fSide    = $sizeup['fire_side']            ?? '';
        $fFloor   = $sizeup['fire_floor']           ?? '';
        $iap      = $sizeup['iap_mode']             ?? '';
        $walk360  = $sizeup['walkaround_findings']  ?? '';
        $command_name    = $sizeup['command_name']    ?? '';
        $command_officer = $sizeup['command_officer'] ?? '';
        $confirm_address      = $sizeup['confirm_address']      ?? '0';
        $notify_command       = $sizeup['notify_command']       ?? '0';
        $life_hazard_val      = $sizeup['life_hazard']          ?? '';
        $num_stories_val      = $sizeup['num_stories']          ?? '';
        $water_supply_val     = $sizeup['water_supply']         ?? '';
        $walkaround_findings  = $sizeup['walkaround_findings']  ?? '';

        $hasSizeup      = !empty($sizeup);
        // Pending Size-Up: solid button with white text. Completed: green.
        $sizeupBtnClass = $hasSizeup
            ? 'btn btn-success btn-sm text-white sizeup-btn sizeup-complete'
            : 'btn btn-secondary btn-sm text-white sizeup-btn sizeup-pending';
      ?>
    <div class="card incident-card mb-4">
      <div class="card-header incident-card-header">
        <!-- Header text block -->
        <div class="mb-2">
          <!-- Incident number small -->
          <div style="font-size: 0.95rem; color: #ddd; font-weight: 500;">
            Incident #<?= $iid ?>
          </div>

          <!-- Main incident detail line larger -->
          <div style="font-size: 1.15rem; font-weight: 600; color: #fff; line-height: 1.25;">
            <?= e($inc['incident_dt']) ?>
            &mdash; <?= e($inc['dept_name']) ?>
            <?php if (!empty($inc['address'])): ?>
              &mdash; <?= e($inc['address']) ?>
            <?php endif; ?>
            <?php if (!empty($inc['type_name'])): ?>
              &mdash; <?= e($inc['type_name']) ?>
            <?php endif; ?>
          </div>
      </div>

        <!-- Buttons row BELOW the header text -->
        <div class="d-flex flex-wrap gap-2">
          <a
            href="join_incident.php?incident_id=<?= $iid ?>"
            class="btn btn-primary btn-sm">
            Join Incident
          </a>

          <a
            href="command_board.php?incident_id=<?= $iid ?>"
            class="btn btn-warning btn-sm">
            Command Board
          </a>

          <form method="POST" class="d-inline" onsubmit="return confirmCancelSingle();">
            <input type="hidden" name="action" value="cancel_incident">
            <input type="hidden" name="incident_id" value="<?= $iid ?>">
            <button type="submit" class="btn btn-danger btn-sm" style="font-weight: 600;">
              Cancel Incident
            </button>
          </form>

        </div>
  </div> <!-- end card-header -->

        <!-- Hidden context for dispatch report (used by JS) -->
        <input type="hidden" id="incident_type_name_<?= $iid ?>" value="<?= e($inc['type_name'] ?? '') ?>">
        <input type="hidden" id="incident_address_<?= $iid ?>" value="<?= e($inc['address'] ?? '') ?>">
        <input type="hidden" id="incident_dept_<?= $iid ?>" value="<?= e($inc['dept_name'] ?? '') ?>">
        <input type="hidden" id="has_sizeup_<?= $iid ?>" value="<?= $hasSizeup ? '1' : '0' ?>">

        <div class="card-body p-3">
  <h6 class="fw-bold">Responding Apparatus</h6>
  <?php if (!empty($apps)): ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered mb-0 align-middle">
        <thead class="table-light text-center">
          <tr>
            <th>ID</th>
            <th>Department</th>
            <th>Type</th>
            <th>Apparatus</th>
            <th># FF</th>
            <th>Status</th>
            <th>Tools</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $ar): ?>
            <?php
              $arId        = (int)$ar['id'];
              $arDeptName  = $ar['department_name']       ?? '';
              $arTypeLabel = $ar['apparatus_type_name']   ?? '';
              // Apparatus label is stored as apparatus_ID (e.g., "Engine 1")
              $arLabel     = $ar['apparatus_ID'] ?? '';
              $arFFCount   = isset($ar['firefighter_count']) ? (int)$ar['firefighter_count'] : 0;
              $arStatus    = $ar['status']                ?? 'Responding';
              $arNotes     = $ar['notes']                 ?? '';
            ?>
            <tr>
              <td><?= $arId ?></td>
              <td><?= e($arDeptName) ?></td>
              <td><?= e($arTypeLabel) ?></td>
              <td><?= e($arLabel) ?></td>
              <td class="text-center"><?= $arFFCount ?></td>
              <td><?= e($arStatus) ?></td>
              <td class="text-center">
                <div class="d-grid gap-1">
                  <button
                    type="button"
                    class="<?= $sizeupBtnClass ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#checklistModal_<?= $iid ?>">
                    Size-Up
                  </button>
                  <button
                    type="button"
                    class="btn btn-dispatch btn-sm"
                    onclick="openDispatchReport(<?= $iid ?>);">
                    Dispatch Report
                  </button>
                   <button
                     type="button"
                     class="btn btn-warning btn-sm"
                     data-bs-toggle="modal"
                     data-bs-target="#statusModal_<?= $arId ?>">
                     Status Change
                   </button>
                  <button
                    type="button"
                    class="btn btn-outline-light btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#editAppModal_<?= $arId ?>">
                    Edit
                  </button>
                </div>
              </td>
            </tr>

            
            <!-- Status Change Modal for this apparatus -->
            <div class="modal fade" id="statusModal_<?= $arId ?>" tabindex="-1" aria-labelledby="statusModalLabel_<?= $arId ?>" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="apparatus_row_id" value="<?= $arId ?>">

                    <div class="modal-header">
                      <h5 class="modal-title" id="statusModalLabel_<?= $arId ?>">Apparatus Status Change</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                      <div class="mb-2">
                        <div class="fw-bold"><?= e($arLabel) ?></div>
                        <div class="text-muted small">Current: <span class="fw-semibold"><?= e($arStatus) ?></span></div>
                      </div>

                      <div class="row g-2">
                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="Responding" class="btn btn-outline-primary btn-lg">Responding</button>
                        </div>
                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="On Scene" class="btn btn-outline-success btn-lg">On Scene</button>
                        </div>

                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="Cancelled" class="btn btn-outline-danger btn-lg">Cancel Response</button>
                        </div>
                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="Rejoined" class="btn btn-outline-warning btn-lg">Rejoin Incident</button>
                        </div>

                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="Returning" class="btn btn-outline-secondary btn-lg">Returning to Quarters</button>
                        </div>
                        <div class="col-6 d-grid">
                          <button type="submit" name="new_status" value="In Quarters" class="btn btn-outline-secondary btn-lg">In Quarters</button>
                        </div>

                        <div class="col-12 d-grid">
                          <button type="submit" name="new_status" value="Released" class="btn btn-outline-dark btn-lg">Released</button>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">Close</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

<!-- Edit Apparatus Modal for this row (full edit: type + label + FF + status + notes) -->
            <div class="modal fade" id="editAppModal_<?= $arId ?>" tabindex="-1" aria-labelledby="editAppLabel_<?= $arId ?>" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="update_apparatus">
                    <input type="hidden" name="apparatus_row_id" value="<?= $arId ?>">
                    <input type="hidden" name="edit_apparatus_type" id="edit_app_type_<?= $arId ?>" value="<?= (int)($ar['apparatus_type_id'] ?? 0) ?>">

                    <div class="modal-header">
                      <h5 class="modal-title" id="editAppLabel_<?= $arId ?>">
                        Edit Apparatus — <?= e($arLabel) ?>
                      </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                      <div class="mb-2">
                        <small class="text-muted">Department: <?= e($arDeptName) ?> | Current Status: <?= e($arStatus) ?></small>
                      </div>

                      <!-- Apparatus Type (big buttons) -->
                      <div class="mb-3">
                        <label class="form-label fw-bold">Apparatus Type</label>
                        <div class="d-flex flex-wrap gap-2">
                          <?php if (!empty($apparatusTypes)): ?>
                            <?php foreach ($apparatusTypes as $t): ?>
                              <?php $isActive = ((int)($ar['apparatus_type_id'] ?? 0) === (int)$t['id']); ?>
                              <button
                                type="button"
                                class="btn btn-outline-success btn-lg <?= $isActive ? 'active' : '' ?>"
                                onclick="selectEditAppType(<?= $arId ?>, <?= (int)$t['id'] ?>, '<?= e($t['name']) ?>');">
                                <?= e($t['name']) ?>
                              </button>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="text-danger">No apparatus types available (missing session / department types).</div>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Apparatus ID / Label + Keypad -->
                      <div class="mb-3">
                        <label class="form-label fw-bold">Apparatus ID / Label</label>
                        <input
                          type="text"
                          class="form-control form-control-lg"
                          name="edit_apparatus_id"
                          id="edit_app_id_<?= $arId ?>"
                          value="<?= e($arLabel) ?>"
                          autocomplete="off"
                        >

                        <div class="mt-2">
                          <div class="d-flex flex-wrap gap-2">
                            <?php for ($n = 1; $n <= 9; $n++): ?>
                              <button type="button" class="btn btn-outline-dark btn-lg" style="min-width:64px;" onclick="appendToEditAppId(<?= $arId ?>, '<?= $n ?>');"><?= $n ?></button>
                            <?php endfor; ?>
                            <button type="button" class="btn btn-outline-dark btn-lg" style="min-width:64px;" onclick="appendToEditAppId(<?= $arId ?>, '0');">0</button>
                            <button type="button" class="btn btn-outline-secondary btn-lg" onclick="backspaceEditAppId(<?= $arId ?>);">⌫</button>
                            <button type="button" class="btn btn-outline-danger btn-lg" onclick="clearEditAppId(<?= $arId ?>);">Clear</button>
                          </div>
                          <small class="text-muted">Tip: type with keyboard if easier, or use the keypad.</small>
                        </div>
                      </div>

                      <!-- Firefighters on board -->
                      <div class="mb-3">
                        <label class="form-label fw-bold">Firefighters on Board</label>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                          <input
                            type="text"
                            class="form-control form-control-lg"
                            name="edit_ff_count"
                            id="edit_ff_count_<?= $arId ?>"
                            style="max-width: 90px;"
                            value="<?= $arFFCount > 0 ? $arFFCount : '' ?>"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="#"
                          >
                          <div class="btn-group" role="group" aria-label="FF count quick select">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                              <button type="button" class="btn btn-outline-warning btn-lg" onclick="document.getElementById('edit_ff_count_<?= $arId ?>').value = '<?= $i ?>';"><?= $i ?></button>
                            <?php endfor; ?>
                          </div>
                        </div>
                      </div>

                      <!-- Status (kept here for completeness; changes are logged) -->
                      <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select form-select-lg" name="edit_status">
                          <?php foreach (allowed_apparatus_statuses() as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= ($opt === $arStatus) ? 'selected' : '' ?>><?= e($opt) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <small class="text-muted">If you change status here, it will be logged with a timestamp.</small>
                      </div>

                      <!-- Notes -->
                      <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" name="edit_notes" rows="3" placeholder="Optional notes for this apparatus."><?= e($arNotes) ?></textarea>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Return</button>
                      <button type="submit" class="btn btn-success btn-lg">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="text-muted mb-0">No responding apparatus recorded yet for this incident.</p>
    <?php endif; ?>
  </div> 
</div>

      <!-- On-Arrival Sizeup Modal for this incident (unchanged) -->
   <?php
  // Make this modal self-contained off $sz so we don't depend on earlier variable setup
  $sz  = $sizeupByIncident[$iid] ?? [];

  // Existing fields (for redisplay)
  $bt              = $sz['building_type']        ?? '';
  $occ             = $sz['occupancy_type']       ?? '';
  $smSide          = $sz['smoke_side']           ?? '';
  $smFloor         = $sz['smoke_floor']          ?? '';
  $fSide           = $sz['fire_side']            ?? '';
  $fFloor          = $sz['fire_floor']           ?? '';
  $command_name    = $sz['command_name']         ?? '';
  $command_officer = $sz['command_officer']      ?? '';
  $iap             = $sz['iap_mode']             ?? '';

  // NEW fields
  $confirm_address = !empty($sz['confirm_address']);
  $notify_command  = !empty($sz['notify_command']);
  $life_hazard_val = $sz['life_hazard']          ?? 'unknown';
  $num_stories_val = isset($sz['num_stories']) ? (string)$sz['num_stories'] : '';
  $water_supply_val= $sz['water_supply']         ?? '';

  $walk_look_victims          = !empty($sz['walk_look_victims']);
  $walk_note_fire_location    = !empty($sz['walk_note_fire_location']);
  $walk_check_access_openings = !empty($sz['walk_check_access_openings']);
  $walk_note_basement_access  = !empty($sz['walk_note_basement_access']);
  $walk_note_exposure_risk    = !empty($sz['walk_note_exposure_risk']);
  $walk_note_power_lines      = !empty($sz['walk_note_power_lines']);

  $walkaround_findings        = $sz['walkaround_findings'] ?? '';
?>

<!-- On-Arrival Size-Up & 360 Modal for this incident -->
        <!-- On-Arrival Size-Up & 360 Modal for this incident -->
        <div class="modal fade" id="checklistModal_<?= $iid ?>" tabindex="-1"
             aria-labelledby="checklistModalLabel_<?= $iid ?>" aria-hidden="true">
          <div class="modal-dialog modal-xl">
            <div class="modal-content">
              <form method="POST" autocomplete="off">
                <input type="hidden" name="action" value="save_checklists">
                <input type="hidden" name="incident_id" value="<?= $iid ?>">

                <!-- Hidden fields for button selections (JS updates these) -->
                <input type="hidden" name="building_type"   id="building_type_input_<?= $iid ?>"   value="<?= e($bt) ?>">
                <input type="hidden" name="occupancy_type"  id="occupancy_type_input_<?= $iid ?>"  value="<?= e($occ) ?>">
                <input type="hidden" name="smoke_side"      id="smoke_side_input_<?= $iid ?>"      value="<?= e($smSide) ?>">
                <input type="hidden" name="smoke_floor"     id="smoke_floor_input_<?= $iid ?>"     value="<?= e($smFloor) ?>">
                <input type="hidden" name="fire_side"       id="fire_side_input_<?= $iid ?>"       value="<?= e($fSide) ?>">
                <input type="hidden" name="fire_floor"      id="fire_floor_input_<?= $iid ?>"      value="<?= e($fFloor) ?>">
                <input type="hidden" name="iap_mode"        id="iap_mode_input_<?= $iid ?>"        value="<?= e($iap) ?>">
                <input type="hidden" name="num_stories"     id="num_stories_input_<?= $iid ?>"     value="<?= e($num_stories_val) ?>">
                <input type="hidden" name="water_supply"    id="water_supply_input_<?= $iid ?>"    value="<?= e($water_supply_val) ?>">

                <div class="modal-header">
                  <h5 class="modal-title" id="checklistModalLabel_<?= $iid ?>">
                    Initial Size-Up &amp; 360 — Incident #<?= $iid ?>
                  </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                  <!-- Confirm incident address with dispatch -->
                  <div class="form-check mb-3">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      id="confirm_address_<?= $iid ?>"
                      name="confirm_address"
                      value="1"
                      <?= $confirm_address ? 'checked' : '' ?>
                    >
                    <label class="form-check-label"
                           for="confirm_address_<?= $iid ?>"
                           style="font-size:1.25rem; font-weight:600;">
                      Confirm incident address with dispatch
                    </label>
                  </div>

                  <!-- Life Hazard -->
                  <div class="col-12 mb-3">
                    <!-- Simple heading so it always shows -->
                    <div style="font-size:1.3rem; font-weight:700; margin-bottom:6px;">
                      Life Hazard
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                      <input type="radio"
                            class="btn-check"
                            name="life_hazard"
                            id="lh_yes_<?= $iid ?>"
                            value="yes"
                            <?= ($life_hazard_val === 'yes') ? 'checked' : '' ?>>
                      <label class="btn btn-outline-danger btn-lg"
                            for="lh_yes_<?= $iid ?>"
                            style="min-width:120px;">
                        Yes
                      </label>

                      <input type="radio"
                            class="btn-check"
                            name="life_hazard"
                            id="lh_no_<?= $iid ?>"
                            value="no"
                            <?= ($life_hazard_val === 'no') ? 'checked' : '' ?>>
                      <label class="btn btn-outline-success btn-lg"
                            for="lh_no_<?= $iid ?>"
                            style="min-width:120px;">
                        No
                      </label>

                      <input type="radio"
                            class="btn-check"
                            name="life_hazard"
                            id="lh_unknown_<?= $iid ?>"
                            value="unknown"
                            <?= ($life_hazard_val === 'unknown') ? 'checked' : '' ?>>
                      <label class="btn btn-outline-secondary btn-lg"
                            for="lh_unknown_<?= $iid ?>"
                            style="min-width:120px;">
                        Unknown
                      </label>
                    </div>
                  </div>


                  <!-- Building / Occupancy -->
                  <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Building Type</label>
                      <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php
                          $buildingOptions = [
                            'Wood Frame', 'Masonry', 'Concrete', 'Steel',
                            'Lightweight Truss', 'Ordinary', 'High-Rise', 'Other'
                          ];
                        ?>
                        <?php foreach ($buildingOptions as $opt): ?>
                          <button type="button"
                                  class="btn btn-outline-primary btn-lg btn-choice building-type-btn"
                                  data-target="<?= $iid ?>"
                                  data-value="<?= e($opt) ?>">
                            <?= e($opt) ?>
                          </button>
                        <?php endforeach; ?>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm"
                             id="building_type_display_<?= $iid ?>"
                             value="<?= e($bt) ?>"
                             placeholder="Selected building type"
                             readonly>
                    </div>

                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Occupancy Type</label>
                      <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php
                          $occOptions = [
                            'Single-Family', 'Multi-Family', 'Apartment',
                            'Commercial', 'Industrial', 'Institutional', 'Mixed-Use', 'Vacant'
                          ];
                        ?>
                        <?php foreach ($occOptions as $opt): ?>
                          <button type="button"
                                  class="btn btn-outline-primary btn-lg btn-choice occupancy-type-btn"
                                  data-target="<?= $iid ?>"
                                  data-value="<?= e($opt) ?>">
                            <?= e($opt) ?>
                          </button>
                        <?php endforeach; ?>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm"
                             id="occupancy_type_display_<?= $iid ?>"
                             value="<?= e($occ) ?>"
                             placeholder="Selected occupancy type"
                             readonly>
                    </div>
                  </div>

                  <!-- Number of stories -->
                  <div class="row mb-3">
                    <div class="col-12">
                      <label class="form-label fw-bold">Number of stories</label>
                      <div class="mb-1 small text-muted">
                        Tap the number of stories (&gt;10 for high-rise / larger structures).
                      </div>
                      <div class="d-flex flex-wrap gap-2">
                        <?php for ($s = 1; $s <= 10; $s++): ?>
                          <button type="button"
                                  class="btn btn-outline-secondary btn-lg story-btn"
                                  data-target="<?= $iid ?>"
                                  data-value="<?= $s ?>">
                            <?= $s ?>
                          </button>
                        <?php endfor; ?>
                        <button type="button"
                                class="btn btn-outline-secondary btn-lg story-btn"
                                data-target="<?= $iid ?>"
                                data-value="11">
                          &gt; 10
                        </button>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm mt-2"
                             id="num_stories_display_<?= $iid ?>"
                             value="<?= $num_stories_val !== '' ? ($num_stories_val === '11' ? '> 10' : $num_stories_val) : '' ?>"
                             placeholder="Number of stories (optional)"
                             readonly>
                    </div>
                  </div>

                  <!-- Smoke / Fire Location -->
                  <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Smoke Location</label>
                      <div class="mb-2">
                        <span class="small text-muted">Side (multi-select)</span>
                        <div class="d-flex flex-wrap gap-2">
                          <?php
                            $sides = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Roof', 'Unknown'];
                          ?>
                          <?php foreach ($sides as $s): ?>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-lg smoke-side-btn"
                                    data-target="<?= $iid ?>"
                                    data-value="<?= e($s) ?>">
                              <?= e($s) ?>
                            </button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div>
                        <span class="small text-muted">Floor (multi-select)</span>
                        <div class="d-flex flex-wrap gap-2">
                          <?php
                            $floors = ['Basement', '1st', '2nd', '3rd+', 'Attic','Garage', 'Unknown'];
                          ?>
                          <?php foreach ($floors as $f): ?>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-lg smoke-floor-btn"
                                    data-target="<?= $iid ?>"
                                    data-value="<?= e($f) ?>">
                              <?= e($f) ?>
                            </button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm mt-2"
                             id="smoke_display_<?= $iid ?>"
                             value="<?= e(trim(($smSide ? $smSide . ' / ' : '') . $smFloor)) ?>"
                             placeholder="Smoke side(s) / floor(s)"
                             readonly>
                    </div>

                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Fire Location</label>
                      <div class="mb-2">
                        <span class="small text-muted">Side (multi-select)</span>
                        <div class="d-flex flex-wrap gap-2">
                          <?php foreach ($sides as $s): ?>
                            <button type="button"
                                    class="btn btn-outline-danger btn-lg fire-side-btn"
                                    data-target="<?= $iid ?>"
                                    data-value="<?= e($s) ?>">
                              <?= e($s) ?>
                            </button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <div>
                        <span class="small text-muted">Floor (multi-select)</span>
                        <div class="d-flex flex-wrap gap-2">
                          <?php foreach ($floors as $f): ?>
                            <button type="button"
                                    class="btn btn-outline-danger btn-lg fire-floor-btn"
                                    data-target="<?= $iid ?>"
                                    data-value="<?= e($f) ?>">
                              <?= e($f) ?>
                            </button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm mt-2"
                             id="fire_display_<?= $iid ?>"
                             value="<?= e(trim(($fSide ? $fSide . ' / ' : '') . $fFloor)) ?>"
                             placeholder="Fire side(s) / floor(s)"
                             readonly>
                    </div>
                  </div>

                  <!-- Command / IAP -->
                  <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Command</label>
                      <div class="mb-2">
                        <input type="text"
                               class="form-control mb-2 sizeup-auto"
                               name="command_name"
                               value="<?= e($command_name) ?>"
                               placeholder="Command name (e.g., Oak Street Command)"
                               autocomplete="off"
                               style="font-size:1.3rem; height:3rem; padding:0.5rem 1rem;">

                        <!-- Notify dispatch you have command -->
                        <div class="form-check mb-2" style="padding-left: 2.2rem;">
                          <input
                            class="form-check-input"
                            type="checkbox"
                            id="notify_command_<?= $iid ?>"
                            name="notify_command"
                            value="1"
                            <?= $notify_command ? 'checked' : '' ?>
                          >
                          <label class="form-check-label"
                                 for="notify_command_<?= $iid ?>"
                                 style="font-size:1.1rem; font-weight:600; cursor:pointer;">
                            Notify dispatch you have command
                          </label>
                        </div>

                        <!-- Keep officer field hidden, but still stored -->
                        <input type="hidden"
                               name="command_officer"
                               value="<?= e($command_officer) ?>">
                      </div>
                    </div>

                    <div class="col-md-6 mb-3">
                      <label class="form-label fw-bold">Initial Action Plan (IAP)</label>
                      <div class="small text-muted mb-1">Notify dispatch of initial action:</div>
                      <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php
                          $iapOptions = ['Offensive', 'Defensive', 'Transitional', 'Investigative'];
                        ?>
                        <?php foreach ($iapOptions as $opt): ?>
                          <button type="button"
                                  class="btn btn-outline-warning btn-lg iap-mode-btn"
                                  data-target="<?= $iid ?>"
                                  data-value="<?= e($opt) ?>"
                                  style="min-width:140px; font-size:1.25rem; font-weight:600; padding:0.6rem 1rem;">
                            <?= e($opt) ?>
                          </button>
                        <?php endforeach; ?>
                      </div>
                      <input type="text"
                             class="form-control form-control-sm"
                             id="iap_mode_display_<?= $iid ?>"
                             value="<?= e($iap) ?>"
                             placeholder="Selected IAP mode"
                             readonly>
                    </div>
                  </div>

                  <!-- Water supply buttons -->
                  <div class="mb-3">
                    <label class="form-label fw-bold">Describe water supply</label>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                      <?php
                        $waterOptions = ['Hydrant', 'Tanker Shuttle', 'Static Source', 'Limited', 'Unknown'];
                      ?>
                      <?php foreach ($waterOptions as $wOpt): ?>
                        <?php $isSelected = (strcasecmp($water_supply_val, $wOpt) === 0); ?>
                        <button type="button"
                                class="btn btn-outline-info btn-lg water-supply-btn <?= $isSelected ? 'active' : '' ?>"
                                data-target="<?= $iid ?>"
                                data-value="<?= e($wOpt) ?>"
                                style="min-width:140px;">
                          <?= e($wOpt) ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <input type="text"
                           class="form-control form-control-sm"
                           id="water_supply_display_<?= $iid ?>"
                           value="<?= e($water_supply_val) ?>"
                           placeholder="Selected water supply"
                           readonly>
                  </div>

                  <!-- Dispatch Report Builder (auto-updated) -->
                  <div class="mb-3">
                    <label class="form-label fw-bold">Dispatch / Arrival Report (auto builds from fields above)</label>
                    <textarea
                      class="form-control dispatch-report-textarea"
                      id="dispatch_report_<?= $iid ?>"
                      name="walkaround_findings"
                      rows="5"
                      placeholder="This report will update automatically as you make selections above."><?= e($walkaround_findings) ?></textarea>
                  </div>

                  <!-- Divider between Size-Up and 360 -->
                  <h5 class="fw-bold">360° Checklist</h5>
                  <small class="text-muted d-block mb-2">
                    Complete a 360 around the structure and tap any hazards you find.
                  </small>

                  <!-- 360 checklist as big buttons -->
                  <div class="d-flex flex-column gap-2 mt-2">

                    <!-- Look for victims -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_look_victims_<?= $iid ?>"
                          name="walk_look_victims"
                          value="1"
                          <?= $walk_look_victims ? 'checked' : '' ?>>
                    <label class="btn-360"
                          for="walk_look_victims_<?= $iid ?>">
                      Look for victims
                    </label>

                    <!-- Note fire location / extension -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_note_fire_location_<?= $iid ?>"
                          name="walk_note_fire_location"
                          value="1"
                          <?= $walk_note_fire_location ? 'checked' : '' ?>>
                    <label class="btn-360"
                          for="walk_note_fire_location_<?= $iid ?>">
                      Note fire location / extension
                    </label>

                    <!-- Check access openings / points of entry -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_check_access_openings_<?= $iid ?>"
                          name="walk_check_access_openings"
                          value="1"
                          <?= $walk_check_access_openings ? 'checked' : '' ?>>
                    <label class="btn-360"
                          for="walk_check_access_openings_<?= $iid ?>">
                      Check access openings / points of entry
                    </label>

                    <!-- Note basement access -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_note_basement_access_<?= $iid ?>"
                          name="walk_note_basement_access"
                          value="1"
                          <?= $walk_note_basement_access ? 'checked' : '' ?>>
                    <label class="btn-360"
                          for="walk_note_basement_access_<?= $iid ?>">
                      Note basement access
                    </label>

                    <!-- Note exposure risk -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_note_exposure_risk_<?= $iid ?>"
                          name="walk_note_exposure_risk"
                          value="1"
                          <?= $walk_note_exposure_risk ? 'checked' : '' ?>>
                    <label class="btn-360"
                          for="walk_note_exposure_risk_<?= $iid ?>">
                      Note exposure risk
                    </label>

                    <!-- Note downed power lines / utility hazards -->
                    <input class="btn-check btn-check-360"
                          type="checkbox"
                          id="walk_note_power_lines_<?= $iid ?>"
                          name="walk_note_power_lines"
                          value="1"
                          <?= $walk_note_power_lines ? 'checked' : '' ?>>
                    <label class="btn-360 mb-2"
                          for="walk_note_power_lines_<?= $iid ?>">
                      Note downed power lines / utility hazards
                    </label>

                  </div>

                </div> <!-- /modal-body -->

                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-success">Save Size-Up</button>
                </div>
              </form>
            </div>
          </div>
        </div>


    <?php endforeach; ?>
  <?php else: ?>
    <div class="text-center text-white mt-4">
      No incidents found.
    </div>
  <?php endif; ?>
</div>


<?php include 'footer.php'; ?>


<!-- jQuery must load before any 0 0...) code -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
<script>
function confirmCancelSingle() {
  return confirm("⚠️ Are you sure you want to CANCEL this incident?\n\nThis action cannot be undone.");
}

// -----------------------------
// Edit Apparatus helpers
// -----------------------------
function selectEditAppType(arId, typeId, typeName) {
  const hidden = document.getElementById('edit_app_type_' + arId);
  if (hidden) hidden.value = String(typeId);

  // Toggle active button state inside this specific modal
  const modal = document.getElementById('editAppModal_' + arId);
  if (modal) {
    modal.querySelectorAll('button.btn-outline-success').forEach(btn => btn.classList.remove('active'));
    // Find the button whose onclick includes this typeId (simple + reliable)
    modal.querySelectorAll('button.btn-outline-success').forEach(btn => {
      const oc = btn.getAttribute('onclick') || '';
      if (oc.includes(',' + typeId + ',')) {
        btn.classList.add('active');
      }
    });
  }

  // If apparatus ID box is empty, prefill with type name + space
  const idBox = document.getElementById('edit_app_id_' + arId);
  if (idBox && idBox.value.trim() === '') {
    idBox.value = (typeName || '').trim() + ' ';
    idBox.focus();
  }
}

function appendToEditAppId(arId, ch) {
  const idBox = document.getElementById('edit_app_id_' + arId);
  if (!idBox) return;
  idBox.value = (idBox.value || '') + String(ch);
  idBox.focus();
}

function backspaceEditAppId(arId) {
  const idBox = document.getElementById('edit_app_id_' + arId);
  if (!idBox) return;
  idBox.value = (idBox.value || '').slice(0, -1);
  idBox.focus();
}

function clearEditAppId(arId) {
  const idBox = document.getElementById('edit_app_id_' + arId);
  if (!idBox) return;
  idBox.value = '';
  idBox.focus();
}

// Helper to parse comma-separated lists (for multi-select buttons)
function parseList(str) {
  return str
    .split(',')
    .map(s => s.trim())
    .filter(Boolean);
}

// Restore button states in Size-Up modal from saved DB values
function restoreSizeupUI(incidentId) {
  // ---------- Building Type ----------
  const buildingVal = document.getElementById('building_type_input_' + incidentId)?.value || '';
  document
    .querySelectorAll('.building-type-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (val === buildingVal) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  // ---------- Occupancy Type ----------
  const occVal = document.getElementById('occupancy_type_input_' + incidentId)?.value || '';
  document
    .querySelectorAll('.occupancy-type-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (val === occVal) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  // ---------- Smoke Sides / Floors (multi-select) ----------
  const smokeSideVal  = document.getElementById('smoke_side_input_' + incidentId)?.value  || '';
  const smokeFloorVal = document.getElementById('smoke_floor_input_' + incidentId)?.value || '';
  const smokeSides    = parseList(smokeSideVal);
  const smokeFloors   = parseList(smokeFloorVal);

  document
    .querySelectorAll('.smoke-side-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (smokeSides.includes(val)) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  document
    .querySelectorAll('.smoke-floor-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (smokeFloors.includes(val)) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  updateSmokeDisplay(incidentId);

  // ---------- Fire Sides / Floors (multi-select) ----------
  const fireSideVal  = document.getElementById('fire_side_input_' + incidentId)?.value  || '';
  const fireFloorVal = document.getElementById('fire_floor_input_' + incidentId)?.value || '';
  const fireSides    = parseList(fireSideVal);
  const fireFloors   = parseList(fireFloorVal);

  document
    .querySelectorAll('.fire-side-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (fireSides.includes(val)) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  document
    .querySelectorAll('.fire-floor-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (fireFloors.includes(val)) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

  updateFireDisplay(incidentId);

  // ---------- IAP Mode ----------
  const iapVal = document.getElementById('iap_mode_input_' + incidentId)?.value || '';
  document
    .querySelectorAll('.iap-mode-btn[data-target="' + incidentId + '"]')
    .forEach(btn => {
      const val = btn.getAttribute('data-value') || '';
      if (val === iapVal) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
}

// Incident type buttons (Add Incident modal)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('incident-type-btn')) {
    const id = e.target.getAttribute('data-id');
    document.getElementById('incident_type_input').value = id;

    document.querySelectorAll('.incident-type-btn').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// Apparatus type buttons (Add Incident modal)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('apparatus-type-btn')) {
    const id = e.target.getAttribute('data-id');
    const name = (e.target.textContent || '').trim();

    const typeHidden = document.getElementById('apparatus_type_input');
    if (typeHidden) typeHidden.value = id;

    // Default apparatus name into Apparatus ID field (helps reduce typing)
    const appIdInput = document.getElementById('apparatus_id');
    if (appIdInput && name) {
      const current = (appIdInput.value || '').trim();
      if (current === '') {
        appIdInput.value = name + ' ';
      } else if (!current.toLowerCase().startsWith(name.toLowerCase())) {
        appIdInput.value = name + ' ' + current;
      }
      // Move cursor to end for quick number entry
      appIdInput.focus();
      try { appIdInput.setSelectionRange(appIdInput.value.length, appIdInput.value.length); } catch (err) {}
    }

    document.querySelectorAll('.apparatus-type-btn').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// Firefighter count buttons (Add Incident modal)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('ff-btn')) {
    const count = e.target.getAttribute('data-count');
    const hidden = document.getElementById('ff_count_input');
    const display = document.getElementById('ff_count_display');
    if (hidden && display) {
      hidden.value = count;
      display.value = count;
    }
    document.querySelectorAll('.ff-btn').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// Join Incident button -> open join modal
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('join-incident-btn')) {
    const id    = e.target.getAttribute('data-incident-id');
    const label = e.target.getAttribute('data-incident-label') || ('Incident #' + id);

    document.getElementById('join_incident_id').value = id;
    document.getElementById('join_incident_label').textContent = label;

    // reset selections in join modal
    document.getElementById('join_apparatus_type_input').value = '';
    document.getElementById('join_apparatus_id').value = '';
    document.getElementById('join_ff_count_input').value = '';
    document.getElementById('join_ff_count_display').value = '';

    document.querySelectorAll('.join-apparatus-type-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.join-ff-btn').forEach(btn => btn.classList.remove('active'));

    const joinModal = new bootstrap.Modal(document.getElementById('joinIncidentModal'));
    joinModal.show();
  }
});

// Apparatus type buttons (Join Incident modal)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('join-apparatus-type-btn')) {
    const id = e.target.getAttribute('data-id');
    document.getElementById('join_apparatus_type_input').value = id;

    document.querySelectorAll('.join-apparatus-type-btn').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// Firefighter count buttons (Join Incident modal)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('join-ff-btn')) {
    const count = e.target.getAttribute('data-count');
    document.getElementById('join_ff_count_input').value = count;
    document.getElementById('join_ff_count_display').value = count;

    document.querySelectorAll('.join-ff-btn').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// Building type buttons (per incident)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('building-type-btn')) {
    const target = e.target.getAttribute('data-target');
    const value  = e.target.getAttribute('data-value');
    const hidden = document.getElementById('building_type_input_' + target);
    const display = document.getElementById('building_type_display_' + target);
    if (hidden) hidden.value = value;
    if (display) display.value = value;

    document.querySelectorAll('.building-type-btn[data-target="' + target + '"]').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');

    buildDispatchReport(target);
  }
});

// Occupancy type buttons (per incident)
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('occupancy-type-btn')) {
    const target = e.target.getAttribute('data-target');
    const value  = e.target.getAttribute('data-value');
    const hidden = document.getElementById('occupancy_type_input_' + target);
    const display = document.getElementById('occupancy_type_display_' + target);
    if (hidden) hidden.value = value;
    if (display) display.value = value;

    document.querySelectorAll('.occupancy-type-btn[data-target="' + target + '"]').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');

    buildDispatchReport(target);
  }
});


// IAP mode buttons
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('iap-mode-btn')) {
    const target = e.target.getAttribute('data-target');
    const value  = e.target.getAttribute('data-value');
    const hidden = document.getElementById('iap_mode_input_' + target);
    const display = document.getElementById('iap_mode_display_' + target);
    if (hidden) hidden.value = value;
    if (display) display.value = value;

    document.querySelectorAll('.iap-mode-btn[data-target="' + target + '"]').forEach(btn => btn.classList.remove('active'));
    e.target.classList.add('active');

    buildDispatchReport(target);
  }
});

function updateSmokeDisplay(target) {
  const side  = document.getElementById('smoke_side_input_' + target)?.value || '';
  const floor = document.getElementById('smoke_floor_input_' + target)?.value || '';
  const display = document.getElementById('smoke_display_' + target);
  if (display) {
    display.value = (side ? side : '') + (side && floor ? ' / ' : '') + (floor ? floor : '');
  }
}

function updateFireDisplay(target) {
  const side  = document.getElementById('fire_side_input_' + target)?.value || '';
  const floor = document.getElementById('fire_floor_input_' + target)?.value || '';
  const display = document.getElementById('fire_display_' + target);
  if (display) {
    display.value = (side ? side : '') + (side && floor ? ' / ' : '') + (floor ? floor : '');
  }
}

// Auto-generate report as the user TYPES in key fields
document.addEventListener('input', function (e) {
  if (!e.target.classList.contains('sizeup-auto')) {
    return;
  }
  const modal = e.target.closest('.modal');
  if (!modal || !modal.id) return;

  const match = modal.id.match(/^checklistModal_(\d+)/);
  if (!match) return;

  const incidentId = match[1];
  buildDispatchReport(incidentId);
});

// When a Size-Up modal is opened, restore buttons + rebuild report
document.addEventListener('shown.bs.modal', function (e) {
  const modal = e.target;
  if (!modal.id) return;
  const match = modal.id.match(/^checklistModal_(\d+)/);
  if (!match) return;
  const incidentId = match[1];

  restoreSizeupUI(incidentId);   // restore all buttons from saved data
  buildDispatchReport(incidentId); // rebuild narrative
});

// Build dispatch report text from fields (with Incident Type / Address / Dept)
function buildDispatchReport(incidentId) {
  const reportBox = document.getElementById('dispatch_report_' + incidentId);
  if (!reportBox) return;

  // Incident context for first line
  const incidentType = document.getElementById('incident_type_name_' + incidentId)?.value || '';
  const incidentAddr = document.getElementById('incident_address_' + incidentId)?.value || '';
  const incidentDept = document.getElementById('incident_dept_' + incidentId)?.value || '';

  const building   = document.getElementById('building_type_input_' + incidentId)?.value || '';
  const occupancy  = document.getElementById('occupancy_type_input_' + incidentId)?.value || '';
  const smokeSide  = document.getElementById('smoke_side_input_' + incidentId)?.value || '';
  const smokeFloor = document.getElementById('smoke_floor_input_' + incidentId)?.value || '';
  const fireSide   = document.getElementById('fire_side_input_' + incidentId)?.value || '';
  const fireFloor  = document.getElementById('fire_floor_input_' + incidentId)?.value || '';
  const iap        = document.getElementById('iap_mode_input_' + incidentId)?.value || '';

  const cmdName    = document.querySelector('#checklistModal_' + incidentId + ' input[name="command_name"]')?.value || '';
  const cmdOff     = document.querySelector('#checklistModal_' + incidentId + ' input[name="command_officer"]')?.value || '';
  const walk360    = document.querySelector('#checklistModal_' + incidentId + ' textarea[name="walkaround_findings"]')?.value || '';

  const lines = [];

  // Dispatch-style first line
  if (incidentDept || incidentType || incidentAddr) {
    let firstLine = "Dispatch, ";

    if (incidentDept) {
      firstLine += incidentDept + " on scene of ";
    } else {
      firstLine += "units on scene of ";
    }

    if (incidentType) {
      firstLine += incidentType;
    } else {
      firstLine += "an incident";
    }

    if (incidentAddr) {
      firstLine += " at " + incidentAddr;
    }

    firstLine += ".";
    lines.push(firstLine);
  }

  if (cmdName || cmdOff) {
    lines.push("Command: " + (cmdName || "Command") + (cmdOff ? " (" + cmdOff + ")" : ""));
  }

  if (building || occupancy) {
    lines.push("Building: "
      + (occupancy ? occupancy + ", " : "")
      + (building ? building : ""));
  }

  if (smokeSide || smokeFloor) {
    lines.push("Smoke: " + (smokeSide || "Unknown side") + (smokeFloor ? ", " + smokeFloor : ""));
  }

  if (fireSide || fireFloor) {
    lines.push("Fire: " + (fireSide || "Unknown side") + (fireFloor ? ", " + fireFloor : ""));
  }

  if (walk360) {
    lines.push("360 report: " + walk360);
  }

  if (iap) {
    lines.push("IAP: " + iap + " operations.");
  }

  if (lines.length === 0) {
    lines.push("No size-up information entered yet.");
  }

  reportBox.value = lines.join("\n");
}

// Open the read-only dispatch report modal for an incident
function openDispatchReport(incidentId) {
  // If Size-Up has not been completed, show a clear message
  const hasSizeupEl = document.getElementById('has_sizeup_' + incidentId);
  const hasSizeup = hasSizeupEl ? (hasSizeupEl.value === '1') : false;

  // Ensure the report is up to date (if fields exist)
  buildDispatchReport(incidentId);

  const source = document.getElementById('dispatch_report_' + incidentId);
  const dest   = document.getElementById('dispatch_report_view_global');

  if (dest) {
    if (!hasSizeup) {
      dest.value = 'The Size Up Report has not been completed to show a dispatch report.';
    } else {
      dest.value = (source && source.value) ? source.value : 'The Size Up Report has not been completed to show a dispatch report.';
    }
  }

  const modalEl = document.getElementById('dispatchModalGlobal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    // Fallback if modal markup is missing for some reason
    alert(dest && dest.value ? dest.value : 'The Size Up Report has not been completed to show a dispatch report.');
  }
}

</script>
<script>
  
  function confirmCancelApparatus() {
   
    return confirm('Cancel this apparatus response?');
  }
</script>
<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.story-btn');
  if (!btn) return;

  const targetId = btn.getAttribute('data-target');
  const value = btn.getAttribute('data-value');

  const hidden = document.getElementById('num_stories_input_' + targetId);
  const display = document.getElementById('num_stories_display_' + targetId);

  if (hidden) hidden.value = value;

  if (display) {
    let label;
    if (value === '0') {
      label = '';
    } else if (value === '11') {
      label = '> 10 stories';
    } else {
      label = value + ' stor' + (value === '1' ? 'y' : 'ies');
    }
    display.value = label;
  }

  // Just use .active, keep the base button styles
  document
    .querySelectorAll('.story-btn[data-target="' + targetId + '"]')
    .forEach(b => b.classList.remove('active'));

  btn.classList.add('active');
});
</script>


<!-- ========================================================= -->
<!-- PRE-DEPARTURE CHECKLIST PROMPT (AFTER ADD INCIDENT)        -->
<!-- ========================================================= -->
<div class="modal fade" id="preDepPromptModal" tabindex="-1" aria-labelledby="preDepPromptLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-light text-dark">
      <div class="modal-header">
        <h5 class="modal-title" id="preDepPromptLabel">Complete Pre-Departure Checklist</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="font-size:1.1rem;">
        Complete the Pre-Departure Checklist before leaving the firehouse?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-lg" id="preDepNoBtn">No</button>
        <button type="button" class="btn btn-success btn-lg" id="preDepYesBtn">Yes</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="preDepChecklistModal" tabindex="-1" aria-labelledby="preDepChecklistLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-light text-dark">
      <div class="modal-header">
        <h5 class="modal-title" id="preDepChecklistLabel">Pre-Departure Checklist</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2">Check every item. When all are checked, this dialog will close automatically.</div>
        <div id="preDepChecklistScroll" style="max-height:60vh; overflow-y:auto; padding-right:0.25rem;">
          <?php if (!empty($checklistsByCategory)): ?>
            <?php foreach ($checklistsByCategory as $cat => $items): ?>
              <div class="checklist-category-title"><?= e($cat) ?></div>
              <?php foreach ($items as $it): ?>
                <div class="checklist-touch-item">
                  <div class="form-check">
                    <input class="form-check-input predep-cb" type="checkbox" id="predep_cb_<?= (int)$it['id'] ?>">
                    <label class="form-check-label" for="predep_cb_<?= (int)$it['id'] ?>">
                      <span><?= e((string)$it['item_number']) ?>. <?= e($it['description']) ?></span>
                    </label>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="alert alert-warning mb-0">Checklist items not available.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script>
$(function () {

  // ------------------------------------------------------------
  // Pre-Departure Checklist prompt (after successful Add Incident)
  // ------------------------------------------------------------
  const shouldPromptPreDep = <?= $show_predep_prompt ? 'true' : 'false' ?>;
  const preDepIncidentId = <?= (int)$new_incident_id ?>;
  const preDepKey = preDepIncidentId ? ('predep_done_' + preDepIncidentId) : null;
  const alreadyDonePreDep = preDepKey ? (sessionStorage.getItem(preDepKey) === '1') : false;
  if (shouldPromptPreDep && !alreadyDonePreDep) {
    // Ensure Add Incident modal is closed (if it was open)
    const addEl = document.getElementById('addIncidentModal');
    if (addEl) {
      const addInst = bootstrap.Modal.getInstance(addEl);
      if (addInst) addInst.hide();
    }

    const promptEl = document.getElementById('preDepPromptModal');
    const checklistEl = document.getElementById('preDepChecklistModal');

    if (promptEl) {
      const promptModal = new bootstrap.Modal(promptEl, { backdrop: 'static' });
      promptModal.show();

      const noBtn = document.getElementById('preDepNoBtn');
      const yesBtn = document.getElementById('preDepYesBtn');

      if (noBtn) {
        noBtn.addEventListener('click', function () {
          try { if (preDepKey) sessionStorage.setItem(preDepKey, '1'); } catch(e) {}
          promptModal.hide();
        }, { once: true });
      }

      if (yesBtn) {
        yesBtn.addEventListener('click', function () {
          promptModal.hide();

          if (checklistEl) {
            // Reveal the inline checklist block (if present) and reset the modal checklist each time
            const inlineBlock = document.getElementById('preDepChecklistInline');
            if (inlineBlock) { inlineBlock.style.display = ''; }
            checklistEl.querySelectorAll('input.predep-cb[type="checkbox"]').forEach(cb => cb.checked = false);

            const checklistModal = new bootstrap.Modal(checklistEl, { backdrop: 'static' });
            checklistModal.show();

            const allChecked = () => {
              const cbs = Array.from(checklistEl.querySelectorAll('input.predep-cb[type="checkbox"]'));
              return cbs.length > 0 && cbs.every(cb => cb.checked);
            };

            const maybeClose = () => {
              if (allChecked()) {
                try { if (preDepKey) sessionStorage.setItem(preDepKey, '1'); } catch(e) {}
                checklistModal.hide();
              }
            };

            checklistEl.querySelectorAll('input.predep-cb[type="checkbox"]').forEach(cb => {
              cb.addEventListener('change', maybeClose);
            });
          }
        }, { once: true });
      }
    }
  }

  // Helper: get ID from form
  function getIncidentIdFromElement($el) {
    var $form = $el.closest("form");
    return $form.find('input[name="incident_id"]').val();
  }

  // -----------------------------
  // Initialize button states from hidden inputs
  // -----------------------------
  function initSelectionsForIncident(incidentId) {
    if (!incidentId) return;

    // Building type
    var bVal = $("#building_type_input_" + incidentId).val();
    if (bVal) {
      $('.building-type-btn[data-target="' + incidentId + '"]').each(function () {
        if ($(this).data("value") == bVal) {
          $(this).addClass("active");
        }
      });
    }

    // Occupancy type
    var occVal = $("#occupancy_type_input_" + incidentId).val();
    if (occVal) {
      $('.occupancy-type-btn[data-target="' + incidentId + '"]').each(function () {
        if ($(this).data("value") == occVal) {
          $(this).addClass("active");
        }
      });
    }

    // Stories
    var sVal = $("#num_stories_input_" + incidentId).val();
    if (sVal) {
      $('.story-btn[data-target="' + incidentId + '"]').each(function () {
        if ($(this).data("value").toString() === sVal.toString()) {
          $(this).addClass("active");
        }
      });
      $("#num_stories_display_" + incidentId).val(sVal === "11" ? "> 10" : sVal);
    }

    // Water supply
    var wVal = $("#water_supply_input_" + incidentId).val();
    if (wVal) {
      $('.water-supply-btn[data-target="' + incidentId + '"]').each(function () {
        if ($(this).data("value") == wVal) {
          $(this).addClass("active");
        }
      });
      $("#water_supply_display_" + incidentId).val(wVal);
    }

    // Smoke multi-select
    var smSideStr  = $("#smoke_side_input_" + incidentId).val()  || "";
    var smFloorStr = $("#smoke_floor_input_" + incidentId).val() || "";
    var smSides  = smSideStr.split(",").map(function (v) { return v.trim(); }).filter(Boolean);
    var smFloors = smFloorStr.split(",").map(function (v) { return v.trim(); }).filter(Boolean);

    $('.smoke-side-btn[data-target="' + incidentId + '"]').each(function () {
      if (smSides.indexOf($(this).data("value")) !== -1) {
        $(this).addClass("active");
      }
    });
    $('.smoke-floor-btn[data-target="' + incidentId + '"]').each(function () {
      if (smFloors.indexOf($(this).data("value")) !== -1) {
        $(this).addClass("active");
      }
    });
    updateSmokeDisplay(incidentId);

    // Fire multi-select
    var fSideStr  = $("#fire_side_input_" + incidentId).val()  || "";
    var fFloorStr = $("#fire_floor_input_" + incidentId).val() || "";
    var fSides  = fSideStr.split(",").map(function (v) { return v.trim(); }).filter(Boolean);
    var fFloors = fFloorStr.split(",").map(function (v) { return v.trim(); }).filter(Boolean);

    $('.fire-side-btn[data-target="' + incidentId + '"]').each(function () {
      if (fSides.indexOf($(this).data("value")) !== -1) {
        $(this).addClass("active");
      }
    });
    $('.fire-floor-btn[data-target="' + incidentId + '"]').each(function () {
      if (fFloors.indexOf($(this).data("value")) !== -1) {
        $(this).addClass("active");
      }
    });
    updateFireDisplay(incidentId);
  }

  // -----------------------------
  // Helpers for multi-select display & hidden fields
  // -----------------------------
  function getActiveValues(selector) {
    var vals = [];
    $(selector).each(function () {
      if ($(this).hasClass("active")) {
        vals.push($(this).data("value"));
      }
    });
    return vals;
  }

  function updateSmokeDisplay(incidentId) {
    var sideVals  = getActiveValues('.smoke-side-btn[data-target="' + incidentId + '"]');
    var floorVals = getActiveValues('.smoke-floor-btn[data-target="' + incidentId + '"]');
    var sideStr   = sideVals.join(", ");
    var floorStr  = floorVals.join(", ");

    $("#smoke_side_input_"  + incidentId).val(sideStr);
    $("#smoke_floor_input_" + incidentId).val(floorStr);

    var display = sideStr;
    if (floorStr) {
      display += (display ? " / " : "") + floorStr;
    }
    $("#smoke_display_" + incidentId).val(display);
  }

  function updateFireDisplay(incidentId) {
    var sideVals  = getActiveValues('.fire-side-btn[data-target="' + incidentId + '"]');
    var floorVals = getActiveValues('.fire-floor-btn[data-target="' + incidentId + '"]');
    var sideStr   = sideVals.join(", ");
    var floorStr  = floorVals.join(", ");

    $("#fire_side_input_"  + incidentId).val(sideStr);
    $("#fire_floor_input_" + incidentId).val(floorStr);

    var display = sideStr;
    if (floorStr) {
      display += (display ? " / " : "") + floorStr;
    }
    $("#fire_display_" + incidentId).val(display);
  }

  // -----------------------------
  // Build Dispatch / Arrival Report text automatically
  // -----------------------------
  function buildDispatchReport(incidentId) {
    if (!incidentId) return;
    var $form = $('input[name="incident_id"][value="' + incidentId + '"]').closest("form");
    if (!$form.length) return;

    var cmdName      = $form.find('input[name="command_name"]').val().trim();
    var lifeHazard   = $form.find('input[name="life_hazard"]:checked').val() || "unknown";
    var confirmed    = $form.find("#confirm_address_" + incidentId).is(":checked");

    var building     = $("#building_type_input_"   + incidentId).val();
    var occupancy    = $("#occupancy_type_input_"  + incidentId).val();
    var stories      = $("#num_stories_input_"     + incidentId).val();
    var smokeSide    = $("#smoke_side_input_"      + incidentId).val();
    var smokeFloor   = $("#smoke_floor_input_"     + incidentId).val();
    var fireSide     = $("#fire_side_input_"       + incidentId).val();
    var fireFloor    = $("#fire_floor_input_"      + incidentId).val();
    var waterSupply  = $("#water_supply_input_"    + incidentId).val();
    var iapMode      = $("#iap_mode_input_"        + incidentId).val();

    var parts = [];

    if (cmdName) {
      parts.push(cmdName + " on scene");
    } else {
      parts.push("On scene");
    }

    if (confirmed) {
      parts.push("address confirmed with dispatch");
    }

    if (occupancy || building || stories) {
      var bldgDesc = [];
      if (stories) {
        bldgDesc.push(stories === "11" ? "greater than 10-story" : (stories + "-story"));
      }
      if (building) {
        bldgDesc.push(building);
      }
      if (occupancy) {
        bldgDesc.push(occupancy + " occupancy");
      }
      parts.push(bldgDesc.join(" "));
    }

    if (smokeSide || smokeFloor) {
      var smokeDesc = "smoke showing";
      if (smokeSide)  smokeDesc += " side(s): " + smokeSide;
      if (smokeFloor) smokeDesc += " floor(s): " + smokeFloor;
      parts.push(smokeDesc);
    }

    if (fireSide || fireFloor) {
      var fireDesc = "fire located";
      if (fireSide)  fireDesc += " side(s): " + fireSide;
      if (fireFloor) fireDesc += " floor(s): " + fireFloor;
      parts.push(fireDesc);
    }

    parts.push("life hazard: " + lifeHazard);

    if (waterSupply) {
      parts.push("water supply: " + waterSupply);
    }

    if (iapMode) {
      parts.push("operating in " + iapMode + " mode");
    }

    var reportText = parts.join(". ") + ".";
    $form.find("#dispatch_report_" + incidentId).val(reportText);
  }

  // -----------------------------
  // Button handlers
  // -----------------------------

  // Building type (single select)
  $(document).on("click", ".building-type-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $('.building-type-btn[data-target="' + incidentId + '"]').removeClass("active");
    $btn.addClass("active");

    $("#building_type_input_" + incidentId).val($btn.data("value"));
    $("#building_type_display_" + incidentId).val($btn.text().trim());

    buildDispatchReport(incidentId);
  });

  // Occupancy type (single select)
  $(document).on("click", ".occupancy-type-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $('.occupancy-type-btn[data-target="' + incidentId + '"]').removeClass("active");
    $btn.addClass("active");

    $("#occupancy_type_input_" + incidentId).val($btn.data("value"));
    $("#occupancy_type_display_" + incidentId).val($btn.text().trim());

    buildDispatchReport(incidentId);
  });

  // Stories (single select)
  $(document).on("click", ".story-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $('.story-btn[data-target="' + incidentId + '"]').removeClass("active");
    $btn.addClass("active");

    var val = $btn.data("value").toString();
    $("#num_stories_input_" + incidentId).val(val);
    $("#num_stories_display_" + incidentId).val(val === "11" ? "> 10" : val);

    buildDispatchReport(incidentId);
  });

  // Smoke side/floor (multi-select)
  $(document).on("click", ".smoke-side-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $btn.toggleClass("active");
    
    // Update hidden CSV so values persist + save correctly
    var selected = [];
    $('.smoke-side-btn[data-target="' + incidentId + '"].active').each(function () {
      var v = ($(this).data("value") || "").toString().trim();
      if (v) selected.push(v);
    });
    $('#smoke_side_input_' + incidentId).val(selected.join(", "));

updateSmokeDisplay(incidentId);
    buildDispatchReport(incidentId);
  });
$(document).on("click", ".smoke-floor-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $btn.toggleClass("active");
    
    // Update hidden CSV so values persist + save correctly
    var selected = [];
    $('.smoke-floor-btn[data-target="' + incidentId + '"].active').each(function () {
      var v = ($(this).data("value") || "").toString().trim();
      if (v) selected.push(v);
    });
    $('#smoke_floor_input_' + incidentId).val(selected.join(", "));

updateSmokeDisplay(incidentId);
    buildDispatchReport(incidentId);
  });
// Fire side/floor (multi-select)
  $(document).on("click", ".fire-side-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $btn.toggleClass("active");
    
    // Update hidden CSV so values persist + save correctly
    var selected = [];
    $('.fire-side-btn[data-target="' + incidentId + '"].active').each(function () {
      var v = ($(this).data("value") || "").toString().trim();
      if (v) selected.push(v);
    });
    $('#fire_side_input_' + incidentId).val(selected.join(", "));

updateFireDisplay(incidentId);
    buildDispatchReport(incidentId);
  });
$(document).on("click", ".fire-floor-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $btn.toggleClass("active");
    
    // Update hidden CSV so values persist + save correctly
    var selected = [];
    $('.fire-floor-btn[data-target="' + incidentId + '"].active').each(function () {
      var v = ($(this).data("value") || "").toString().trim();
      if (v) selected.push(v);
    });
    $('#fire_floor_input_' + incidentId).val(selected.join(", "));

updateFireDisplay(incidentId);
    buildDispatchReport(incidentId);
  });
// Water supply (single select)
  $(document).on("click", ".water-supply-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $('.water-supply-btn[data-target="' + incidentId + '"]').removeClass("active");
    $btn.addClass("active");

    var val = $btn.data("value");
    $("#water_supply_input_" + incidentId).val(val);
    $("#water_supply_display_" + incidentId).val(val);

    buildDispatchReport(incidentId);
  });

  // IAP mode (single select)
  $(document).on("click", ".iap-mode-btn", function () {
    var $btn = $(this);
    var incidentId = $btn.data("target");
    $('.iap-mode-btn[data-target="' + incidentId + '"]').removeClass("active");
    $btn.addClass("active");

    var val = $btn.data("value");
    $("#iap_mode_input_" + incidentId).val(val);
    $("#iap_mode_display_" + incidentId).val(val);

    buildDispatchReport(incidentId);
  });

  // Life hazard radio, confirm address checkbox, command name
  $(document).on("change", 'input[name="life_hazard"]', function () {
    var incidentId = getIncidentIdFromElement($(this));
    buildDispatchReport(incidentId);
  });

  $(document).on("change", 'input[id^="confirm_address_"]', function () {
    var incidentId = getIncidentIdFromElement($(this));
    buildDispatchReport(incidentId);
  });

  $(document).on("keyup change", 'input[name="command_name"]', function () {
    var incidentId = getIncidentIdFromElement($(this));
    buildDispatchReport(incidentId);
  });

  // When the modal opens, sync button states + report with current DB values
  $(document).on("shown.bs.modal", ".modal", function () {
    var incidentId = $(this).find('input[name="incident_id"]').val();
    if (!incidentId) return;
    initSelectionsForIncident(incidentId);
    buildDispatchReport(incidentId);
  });
});
</script>


<!-- ========================================================= -->
<!-- DISPATCH REPORT VIEW MODAL (READ-ONLY)                     -->
<!-- ========================================================= -->
<div class="modal fade" id="dispatchModalGlobal" tabindex="-1" aria-labelledby="dispatchModalGlobalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content bg-light text-dark">
      <div class="modal-header">
        <h5 class="modal-title" id="dispatchModalGlobalLabel">Dispatch Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea class="form-control" id="dispatch_report_view_global" rows="10" readonly style="font-size:1.05rem;"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
  // If Add Incident POST failed, reopen the modal so the user can see the error message.
  const reopen = <?= (!empty($post_error) && isset($_POST['action']) && $_POST['action'] === 'add_incident') ? 'true' : 'false' ?>;
  if (reopen) {
    const el = document.getElementById('addIncidentModal');
    if (el && typeof bootstrap !== 'undefined') {
      try {
        new bootstrap.Modal(el).show();
      } catch (e) {
        console.warn('Could not reopen addIncidentModal', e);
      }
    }
  }
});
</script>

</body>
</html>