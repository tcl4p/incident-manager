<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * File: command_board.php
 * Version: 2026-1-1 1:15
 */

require_once "db_connect.php"; // must define $conn (mysqli)

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// Color palette for assignment types (fallback)
// --------------------------------------------------
$assignmentColorPalette = [
    "#e53935", // Red
    "#8e24aa", // Purple
    "#3949ab", // Indigo
    "#1e88e5", // Blue
    "#00897b", // Teal
    "#43a047", // Green
    "#fdd835", // Yellow
    "#ffb300", // Amber
    "#fb8c00", // Orange
    "#6d4c41", // Brown
];

// Basic function to get a nice label for TAC usage
function formatTacLabel($row) {
    // Uses new tac_channels schema: ChannelLabel + UsageLabel
    $chan  = isset($row['ChannelLabel']) ? trim($row['ChannelLabel']) : '';
    $usage = isset($row['UsageLabel']) ? trim($row['UsageLabel']) : '';

    if ($chan === '' && $usage === '') {
        return 'No TAC Set';
    }

    $parts = [];
    if ($usage !== '') {
        $parts[] = $usage;
    }
    if ($chan !== '') {
        $parts[] = $chan;
    }
    return implode(' – ', $parts);
}


// Prefer the incident_id from the URL, but also accept it from POST
// --------------------------------------------------
// LOAD INCIDENT + BOARD DATA
// --------------------------------------------------

// Prefer incident_id from the URL, but also accept it from POST
// --------------------------------------------------
// LOAD INCIDENT + BOARD DATA
// --------------------------------------------------

$incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;


// --------------------------------------------------
// CLOSE INCIDENT (Command Board)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_incident'])) {
    $postIncidentId = (int)($_POST['incident_id'] ?? 0);

    if ($postIncidentId > 0) {
        $sqlClose = "UPDATE `incidents`
                     SET `status` = 'Closed',
                         `closed_at` = NOW()
                     WHERE `id` = ?
                     LIMIT 1";
        if ($stmt = $conn->prepare($sqlClose)) {
            $stmt->bind_param("i", $postIncidentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Return to incidents list (closed incident will no longer display there)
    header("Location: incidents.php");
    exit;
}

// =========================================================
// Load available Command Officers for IC selection (buttons)
// =========================================================
$icLocalOfficers = [];
$icMutualAidOfficers = [];


// --------------------------------------------------
// ICS OPERATIONAL ELEMENTS (Divisions / Groups)
// --------------------------------------------------
$icsMessage = null;

// Handle: Add Element
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ics_element'])) {
    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    $elementType = strtoupper(trim($_POST['element_type'] ?? ''));
    $elementName = trim($_POST['element_name'] ?? '');

    $allowedTypes = ['DIVISION','GROUP','BRANCH','OPS'];
    if ($postIncidentId > 0 && in_array($elementType, $allowedTypes, true) && $elementName !== '') {
        $sql = "INSERT INTO incident_elements
                (incident_id, element_type, element_name, parent_element_id,
                 supervisor_source, supervisor_id,
                 status)
                VALUES (?, ?, ?, NULL, 'ic', NULL, 'active')";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iss", $postIncidentId, $elementType, $elementName);
            if (!$stmt->execute()) {
                $icsMessage = "Could not add element: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $icsMessage = "Could not add element (prepare failed).";
        }
    } else {
        $icsMessage = "Please provide an element name.";
    }
}

// Handle: Set Supervisor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_ics_supervisor'])) {
    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    $elementId = (int)($_POST['element_id'] ?? 0);
    $src = trim($_POST['supervisor_source'] ?? '');
    if ($src === 'mutual_aid') { $src = 'mutual'; }
    $supId = (int)($_POST['supervisor_id'] ?? 0);
    $display = trim($_POST['supervisor_display'] ?? ''); // not stored; kept for UI


    if ($postIncidentId > 0 && $elementId > 0 && in_array($src, ['local','mutual'], true) && $supId > 0) {
        $sql = "UPDATE incident_elements
                   SET supervisor_id = ?,
                       supervisor_source = ?
                 WHERE id = ? AND incident_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("isii", $supId, $src, $elementId, $postIncidentId);
            if (!$stmt->execute()) {
                $icsMessage = "Could not set supervisor: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $icsMessage = "Could not set supervisor (prepare failed).";
        }
    } else {
        $icsMessage = "Please select a supervisor.";
    }
}

// Handle: Release Element
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_ics_element'])) {
    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    $elementId = (int)($_POST['element_id'] ?? 0);

    if ($postIncidentId > 0 && $elementId > 0) {
        $sql = "UPDATE incident_elements
                   SET status = 'released',
                       released_at = NOW()
                 WHERE id = ? AND incident_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $elementId, $postIncidentId);
            if (!$stmt->execute()) {
                $icsMessage = "Could not release element: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $icsMessage = "Could not release element (prepare failed).";
        }
    }
}

// Load active elements for display
$icsElements = [];
try {
    $sql = "SELECT id, element_type, element_name, supervisor_source, supervisor_id, status, created_at
            FROM incident_elements
            WHERE incident_id = ? AND status = 'active'
            ORDER BY created_at ASC, id ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $icsElements[] = $row; }
        $stmt->close();
    }
} catch (Throwable $t) {
    // If the table doesn't exist yet, show a friendly note on the UI
    $icsMessage = $icsMessage ?: "ICS elements table is missing. Run the SQL patch to create incident_elements.";
}


// Local Department Command officers
$sql = "SELECT id, member_name, radio_designation
        FROM department_command
        WHERE is_active = 1
        ORDER BY member_name ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $icLocalOfficers[] = $row; }
    $stmt->close();
}

// Mutual Aid command officers for this incident
$sql = "SELECT DISTINCT id, officer_name, radio_designation
        FROM incident_mutual_aid
        WHERE incident_id = ?
          AND is_command_officer = 1
        ORDER BY officer_name ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $icMutualAidOfficers[] = $row; }
    $stmt->close();
}


// --------------------------------------------------
// Build supervisor display labels from officer lists
// --------------------------------------------------
$__localOfficerMap = [];
foreach ($icLocalOfficers as $__o) {
    $label = trim((($__o['radio_designation'] ?? '') . ' ' . ($__o['member_name'] ?? '')));
    if ($label === '') $label = 'Local Officer #' . (int)($__o['id'] ?? 0);
    $__localOfficerMap[(int)($__o['id'] ?? 0)] = $label;
}

$__mutualOfficerMap = [];
foreach ($icMutualAidOfficers as $__o) {
    $label = trim((($__o['radio_designation'] ?? '') . ' ' . ($__o['officer_name'] ?? '')));
    if ($label === '') $label = 'Mutual Aid Officer #' . (int)($__o['id'] ?? 0);
    $__mutualOfficerMap[(int)($__o['id'] ?? 0)] = $label;
}

// Decorate loaded ICS elements with a computed supervisor_display field
if (!empty($icsElements)) {
    foreach ($icsElements as &$__el) {
        $sid = (int)($__el['supervisor_id'] ?? 0);
        $src = (string)($__el['supervisor_source'] ?? '');
        if ($sid > 0) {
            if ($src === 'local') {
                $__el['supervisor_display'] = $__localOfficerMap[$sid] ?? ('Local Officer #' . $sid);
            } elseif ($src === 'mutual') {
                $__el['supervisor_display'] = $__mutualOfficerMap[$sid] ?? ('Mutual Aid Officer #' . $sid);
            } else {
                $__el['supervisor_display'] = 'Officer #' . $sid;
            }
        } else {
            $__el['supervisor_display'] = 'IC covering';
            $__el['supervisor_source']  = 'ic';
        }
    }
    unset($__el);
}

if ($incidentId <= 0) {
    die("No incident_id specified in URL.");
}

$incident           = null;
$assignmentTypes    = [];
$tacChannels        = [];
$commandAssignments = [];
$benchmarkTypes     = [];
$benchmarkEvents    = [];
$parEvents          = [];
$parSummary         = [];
$availableFF        = [];
$totalFFAvailable   = 0;
$db_error           = null;

// Overall incident timer (from size-up)
$overallHasSizeup           = false;
$overallTimerSecondsInitial = 0;
$overallTimerLabel          = 'Awaiting size-up';

try 
{
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Database connection \$conn (mysqli) not found.");
    }
// --------------------------------------------------
// Set Incident Commander (IC) - Manual Display Update (safe)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_ic_manual'])) {

    // Prefer hidden incident_id from the form; fall back to URL
    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    if ($postIncidentId > 0) {
        $incidentId = $postIncidentId;
    }

    $icDisplay = trim($_POST['ic_display'] ?? '');
    $icSource  = trim($_POST['ic_source'] ?? 'local');
    if (!in_array($icSource, ['local','mutual_aid'], true)) {
        $icSource = 'local';
    }

    if ($incidentId > 0 && $icDisplay !== '') {

        $sql = "UPDATE incidents
                SET incident_commander_display = ?,
                    incident_commander_source  = ?,
                    incident_commander_officer_id = NULL
                WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssi", $icDisplay, $icSource, $incidentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: command_board.php?incident_id=" . urlencode((string)$incidentId) . "&ic_updated=1");
    exit;
}

// --------------------------------------------------
// Set Safety Officer - Display + Source (local / mutual aid / manual)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_safety_officer'])) {

    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    if ($postIncidentId > 0) {
        $incidentId = $postIncidentId;
    }

    $soDisplay = trim($_POST['so_display'] ?? '');
    $soSource  = trim($_POST['so_source'] ?? 'manual');
    if (!in_array($soSource, ['local','mutual_aid','manual'], true)) {
        $soSource = 'manual';
    }

    $soLocalId = null;
    $soMaId    = null;

    if ($soSource === 'local') {
        $soLocalId = (int)($_POST['so_local_id'] ?? 0);
        if ($soLocalId <= 0) $soLocalId = null;
    } elseif ($soSource === 'mutual_aid') {
        $soMaId = (int)($_POST['so_mutual_aid_id'] ?? 0);
        if ($soMaId <= 0) $soMaId = null;
    } else {
        // manual
        $soSource  = 'manual';
        $soLocalId = null;
        $soMaId    = null;
    }

    if ($incidentId > 0 && $soDisplay !== '') {

        $sql = "UPDATE incidents
                SET safety_officer_display = ?,
                    safety_officer_source  = ?,
                    safety_officer_officer_id = ?,
                    safety_officer_mutual_aid_id = ?
                WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssiii", $soDisplay, $soSource, $soLocalId, $soMaId, $incidentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: command_board.php?incident_id=" . urlencode((string)$incidentId) . "&safety_updated=1");
    exit;
}

// --------------------------------------------------
// Handle Mutual Aid: apparatus & command officer
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['ma_mode'])) {

    $mode     = $_POST['ma_mode'];
    $deptName = trim($_POST['ma_dept_name'] ?? '');
    $maError = null; // 'dupe'|'save'

    // $incidentId is already set from the URL above


// ----------------------------------------------
// MODE: LOCAL COMMAND STAFF (Incident Dept)
// ----------------------------------------------
if ($mode === 'local_staff') {

    // Use incident department name as the "dept" label for unified command resources
    $deptName = trim($deptName);
    if ($deptName === '' && isset($_POST['incident_dept_fallback'])) {
        $deptName = trim((string)$_POST['incident_dept_fallback']);
    }

    $localCmdId = (int)($_POST['ma_local_command_id'] ?? 0);

    if ($deptName === '' || $localCmdId <= 0) {
        $maError = 'pick';
    } else {

        // Load local staff record
        $sql = "SELECT id, member_name, rank_id, radio_designation
                FROM department_command
                WHERE id = ? AND is_active = 1
                LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $localCmdId);
            $stmt->execute();
            $res = $stmt->get_result();
            $dc  = $res ? $res->fetch_assoc() : null;
            $stmt->close();

            if (!$dc) {
                $maError = 'pick';
            } else {

                $memberName = trim((string)($dc['member_name'] ?? ''));
                $rankId     = isset($dc['rank_id']) ? (int)$dc['rank_id'] : null;
                $radio      = trim((string)($dc['radio_designation'] ?? ''));

                if ($memberName === '') {
                    $maError = 'pick';
                } else {

                    // Prevent duplicate inserts
                    $dupeSql = "SELECT id FROM incident_mutual_aid
                                WHERE incident_id = ?
                                  AND is_command_officer = 1
                                  AND dept_name = ?
                                  AND officer_name = ?
                                LIMIT 1";
                    $alreadyExists = false;
                    if ($dupeStmt = $conn->prepare($dupeSql)) {
                        $dupeStmt->bind_param("iss", $incidentId, $deptName, $memberName);
                        $dupeStmt->execute();
                        $dupeRes = $dupeStmt->get_result();
                        $alreadyExists = ($dupeRes && $dupeRes->num_rows > 0);
                        $dupeStmt->close();
                    }

                    if ($alreadyExists) {
                        $maError = 'dupe';
                    } else {

                        // Store as a unified "command resource" row
                        $cmdIdentity = ($radio !== '') ? $radio : $memberName;

                        $sql = "
                            INSERT INTO incident_mutual_aid
                                (incident_id,
                                 dept_name,
                                 command_identity,
                                 is_command_officer,
                                 officer_name,
                                 rank_id,
                                 radio_designation)
                            VALUES
                                (?, ?, ?, 1, ?, ?, ?)
                        ";
                        if ($stmt = $conn->prepare($sql)) {
                            $rankParam = ($rankId && $rankId > 0) ? $rankId : null;
                            $stmt->bind_param(
                                "isssis",
                                $incidentId,
                                $deptName,
                                $cmdIdentity,
                                $memberName,
                                $rankParam,
                                $cmdIdentity
                            );
                            if (!$stmt->execute()) {
                                $maError = 'save';
                            }
                            $stmt->close();
                        } else {
                            $maError = 'save';
                        }
                    }
                }
            }
        } else {
            $maError = 'save';
        }
    }
}

    // ----------------------------------------------
    // MODE: APPARATUS
    // ----------------------------------------------
    if ($mode === 'apparatus') {

        // Support both old and new field names
        $appTypeId = 0;
        if (isset($_POST['ma_app_type_id'])) {
            $appTypeId = (int)$_POST['ma_app_type_id'];
        } elseif (isset($_POST['ma_apparatus_type_id'])) {
            $appTypeId = (int)$_POST['ma_apparatus_type_id'];
        }

        $appId = '';
        if (!empty($_POST['ma_apparatus_label'])) {
            $appId = trim($_POST['ma_apparatus_label']);
        } elseif (!empty($_POST['ma_apparatus_id'])) {
            $appId = trim($_POST['ma_apparatus_id']);
        }

        $ffCount = 0;
        if (isset($_POST['ma_staffing'])) {
            $ffCount = (int)$_POST['ma_staffing'];
        } elseif (isset($_POST['ma_ff_count'])) {
            $ffCount = (int)$_POST['ma_ff_count'];
        }
        if ($ffCount < 1) $ffCount = 0;

        if ($deptName !== '' && $appTypeId > 0 && $appId !== '' && $ffCount > 0 && preg_match('/\d/', $appId)) {

            // 1) Ensure a *non-officer* incident_mutual_aid row exists for this dept
            $imaId = null;

            $sql = "
                SELECT id
                FROM incident_mutual_aid
                WHERE incident_id = ?
                  AND dept_name   = ?
                  AND is_command_officer = 0
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("is", $incidentId, $deptName);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $imaId = (int)$row['id'];
                }
                $stmt->close();
            } else {
                $maError = 'save';
            }

            // If none, create a new base mutual-aid record for this dept
            if (!$imaId) {
                $sql = "
                    INSERT INTO incident_mutual_aid (incident_id, dept_name, command_identity, is_command_officer)
                    VALUES (?, ?, NULL, 0)
                ";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("is", $incidentId, $deptName);
                    $stmt->execute();
                    $imaId = $stmt->insert_id;
                    $stmt->close();
                }
            }

            // 2) Insert mutual-aid apparatus
            if ($imaId) {
                $sql = "
                    INSERT INTO apparatus_responding
                        (incident_id,
                         Label,
                         Type,
                         ApparatusLabel,
                         apparatus_type,
                         apparatus_ID,
                         firefighter_count,
                         mutual_aid_dept,
                         incident_mutual_aid_id,
                         status,
                         eta_minutes,
                         dispatch_time,
                         notes)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Responding', NULL, NOW(), '')
                ";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $label    = $appId; // show unit ID as label
                    $typeText = trim($_POST['ma_apparatus_type_label'] ?? '');
                    if ($typeText === '') {
                        $typeText = 'Mutual Aid';
                    }

                    $stmt->bind_param(
                        "isssisisi",
                        $incidentId,   // i  incident_id
                        $label,        // s  Label
                        $typeText,     // s  Type
                        $appId,        // s  ApparatusLabel
                        $appTypeId,    // i  apparatus_type (FK)
                        $appId,        // s  apparatus_ID (ENG 9, etc.)
                        $ffCount,      // i  firefighter_count
                        $deptName,     // s  mutual_aid_dept
                        $imaId         // i  incident_mutual_aid_id
                    );

                    $stmt->execute();
                    $stmt->close();
                }

                // 3) Update staffing summary so it shows up in Staffing / FF Available
                if ($appId !== '') {
                    $existingId = null;
                    $sql = "
                        SELECT ID
                        FROM staffing
                        WHERE incident_id = ?
                          AND ApparatusLabel = ?
                        LIMIT 1
                    ";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("is", $incidentId, $appId);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $existingId = (int)$row['ID'];
                        }
                        $stmt->close();
                    }

                    if ($existingId) {
                        $sql = "
                            UPDATE staffing
                            SET NumFirefighters = ?, UpdatedAt = NOW()
                            WHERE ID = ?
                        ";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("ii", $ffCount, $existingId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } else {
                        $sql = "
                            INSERT INTO staffing (incident_id, ApparatusLabel, NumFirefighters)
                            VALUES (?, ?, ?)
                        ";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("isi", $incidentId, $appId, $ffCount);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
            }
        }

        // ----------------------------------------------
        // MODE: COMMAND OFFICER
        // ----------------------------------------------
        } elseif ($mode === 'officer') {

        // Officer ID string built on the UI (e.g., "Chief50")
        $officerId = '';
        if (!empty($_POST['ma_officer_name'])) {
            $officerId = trim($_POST['ma_officer_name']);
        } elseif (!empty($_POST['officer_name'])) {
            // fallback to older name if you still use it
            $officerId = trim($_POST['officer_name']);
        }

        // Rank label from rank buttons (hidden field) + map to department_command_rank.id
        $rankLabel = '';
        $rankId    = null;

        if (isset($_POST['ma_officer_rank']) && $_POST['ma_officer_rank'] !== '') {
            $rankLabel = trim($_POST['ma_officer_rank']);

            $rankMap = [
                'Chief'           => 1,
                'Deputy Chief'    => 2,
                'Assistant Chief' => 3,
                'Captain'         => 4,
                'Lieutenant'      => 5,
                'Firefighter'     => 6,
            ];

            $rankId = $rankMap[$rankLabel] ?? null;
        } elseif (isset($_POST['rank_id']) && $_POST['rank_id'] !== '') {
            $tmpRank = (int)$_POST['rank_id'];
            $rankId  = $tmpRank > 0 ? $tmpRank : null;
        }

        // Enforce: user must pick a Rank AND append at least one digit to the end (via keypad).
        // We normalize and store Officer ID as Rank + digits (no space), e.g., "Chief50".
        $normalizedOfficerId = '';

        if ($deptName !== '' && $officerId !== '') {
            // Normalize to reduce accidental dupes (extra spaces, etc.)
            $deptName  = trim(preg_replace('/\s+/', ' ', $deptName));
            $officerId = trim(preg_replace('/\s+/', ' ', $officerId));

            if ($rankLabel === '') {
                $maError = 'rank';
            } else {
                $re = '/^' . preg_quote($rankLabel, '/') . '\s*(\d+)$/';
                if (preg_match($re, $officerId, $mm)) {
                    $normalizedOfficerId = $rankLabel . $mm[1]; // no space
                } else {
                    $maError = 'digits';
                }
            }
        }

        if ($deptName !== '' && $normalizedOfficerId !== '' && empty($maError)) {

            // Prevent duplicate inserts if the user re-submits the same officer
            $dupeSql = "SELECT id FROM incident_mutual_aid
                        WHERE incident_id = ?
                          AND is_command_officer = 1
                          AND dept_name = ?
                          AND officer_name = ?
                        LIMIT 1";
            if ($dupeStmt = $conn->prepare($dupeSql)) {
                $dupeStmt->bind_param("iss", $incidentId, $deptName, $normalizedOfficerId);
                $dupeStmt->execute();
                $dupeRes = $dupeStmt->get_result();
                $alreadyExists = ($dupeRes && $dupeRes->num_rows > 0);
                $dupeStmt->close();
            } else {
                $alreadyExists = false;
            }

            if ($alreadyExists) {
                $maError = 'dupe';
            } else {
                // Insert one row in incident_mutual_aid for this command officer
            $sql = "
                INSERT INTO incident_mutual_aid
                    (incident_id,
                     dept_name,
                     command_identity,
                     is_command_officer,
                     officer_name,
                     rank_id,
                     radio_designation)
                VALUES
                    (?, ?, ?, 1, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                // For now, treat Officer ID as both command_identity & radio_designation
                $cmdIdentity     = $normalizedOfficerId;
                $officerNameSave = $normalizedOfficerId; // you can separate real name later if you want
                $radio           = $normalizedOfficerId;

                $stmt->bind_param(
                    "isssis",
                    $incidentId,       // i
                    $deptName,         // s
                    $cmdIdentity,      // s
                    $officerNameSave,  // s
                    $rankId,           // i
                    $radio             // s
                );

                if (!$stmt->execute()) {
                    $maError = 'save';
                }
                $stmt->close();
            }
            }
        }
    }
        // Post/Redirect/Get: reload board + reopen Mutual Aid modal
    // ma_success=1 tells JS to show "record saved, add another?" prompt
    // Carry the Mutual Aid Department forward so the JS can repopulate it
    $redirectUrl = "command_board.php?incident_id="
        . urlencode($incidentId)
        . "&open_mutual_aid=1&ma_success=1&ma_mode_saved=" . urlencode($mode);

    if (!empty($deptName)) {
        $redirectUrl .= "&ma_dept=" . urlencode($deptName);
    }

    if (!empty($maError)) {
        $redirectUrl .= "&ma_error=" . urlencode($maError);
    }


    header("Location: " . $redirectUrl);
    exit;
} // <-- end of: if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ma_mode'])) {




// 1) Load the incident record
   $sql = "
    SELECT 
        i.*,
        i.location AS Address,
        it.incidentType AS IncidentType
    FROM incidents i
    LEFT JOIN incident_types it
        ON i.type = it.ID
    WHERE i.ID = ?
    LIMIT 1
";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for incident: " . $conn->error);
    }
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $res      = $stmt->get_result();
    $incident = $res->fetch_assoc();
    $stmt->close();

    if (!$incident) {
        throw new Exception("Incident not found for ID $incidentId");
    }

    // Alarm level (1-5). Default to 1 if not present (older DB).
    $alarmLevel = 1;
    if (isset($incident['alarm_level'])) {
        $alarmLevel = (int)$incident['alarm_level'];
        if ($alarmLevel < 1) $alarmLevel = 1;
        if ($alarmLevel > 5) $alarmLevel = 5;
    }

// 1b) Safety Checklist progress (for header badge)
$safetyTotal = 0;
$safetyDone  = 0;
try {
    // Total active safety items
    $sql = "SELECT COUNT(*) AS cnt FROM checklist_items WHERE category = 'Safety Officer' AND active = 1";
    $res = $conn->query($sql);
    if ($res && ($row = $res->fetch_assoc())) {
        $safetyTotal = (int)$row['cnt'];
    }

    // Completed safety items for this incident
    if ($safetyTotal > 0) {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM incident_checklist_responses r
            INNER JOIN checklist_items ci
                ON ci.id = r.checklist_id
            WHERE r.incident_id = ?
              AND r.is_checked = 1
              AND ci.category = 'Safety Officer'
              AND ci.active = 1
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $incidentId);
            $stmt->execute();
            $rres = $stmt->get_result();
            if ($rres && ($rrow = $rres->fetch_assoc())) {
                $safetyDone = (int)$rrow['cnt'];
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    // Keep the board running even if checklist tables are missing
    $safetyTotal = 0;
    $safetyDone  = 0;
}

// Load latest size-up record for this incident to drive the Incident Time timer
$sql = "
    SELECT created_at
    FROM incident_sizeup
    WHERE incident_id = ?
    ORDER BY created_at DESC
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $sizeupCreatedAt = $row['created_at'];
        $ts = strtotime($sizeupCreatedAt);
        if ($ts !== false) {
            $overallHasSizeup = true;

            // Seconds elapsed from size-up to now
            $elapsed = time() - $ts;
            if ($elapsed < 0) {
                $elapsed = 0; // just in case server clock/timezone oddities
            }

            $overallTimerSecondsInitial = (int)$elapsed;

            $h = floor($elapsed / 3600);
            $m = floor(($elapsed % 3600) / 60);
            $s = $elapsed % 60;

            $overallTimerLabel = sprintf('%02d:%02d:%02d', $h, $m, $s);
        }
    }
    $stmt->close();
}

    // 2) Load assignment types (for the assignment legend row)
    $sql = "
        SELECT 
            id   AS ID,
            name AS Name,
            ColorHex
        FROM assignment_types
        WHERE IsActive = 1
        ORDER BY SortOrder, Name
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $assignmentTypes[] = $row;
        }
        $res->free();
    }

    // 3) Load current TAC channels for the incident
    $tacChannels = [];
    $sql = "
        SELECT
            ID,
            ChannelLabel,
            UsageLabel,
            AssignedUnits
        FROM tac_channels
        WHERE incident_id = ?
        ORDER BY ID ASC
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tacChannels[] = $row;
        }
        $stmt->close();
    }

    // 4) Load the command assignments (main grid)
    $sql = "
        SELECT 
            ca.*,
            at.Name AS AssignmentName,
            at.ColorHex AS AssignmentColor,
            COALESCE(ar.ApparatusLabel, ar.apparatus_ID, ar.Label) AS ApparatusLabel,
            aty.ApparatusType AS ApparatusType
        FROM command_assignments ca
        LEFT JOIN assignment_types at
          ON ca.assignment_type_id = at.id
        LEFT JOIN apparatus_responding ar
          ON ca.apparatus_responding_id = ar.id
        LEFT JOIN apparatus_types aty
          ON ar.apparatus_type = aty.id
        WHERE ca.incident_id = ?
        ORDER BY ca.StartTime ASC, ca.ID ASC
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $commandAssignments[] = $row;
        }
        $stmt->close();
    }

    // 5) Load benchmark types (for the benchmark event buttons)
    $sql = "
        SELECT *
        FROM benchmark_types
        ORDER BY SortOrder, Name
    ";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $benchmarkTypes[] = $row;
        }
        $res->free();
    }

    // 6) Load recent benchmark events for this incident
    $sql = "
        SELECT 
            be.*,
            bt.Name AS benchmark_name
        FROM benchmark_events be
        LEFT JOIN benchmark_types bt
          ON be.benchmark_type_id = bt.ID
        WHERE be.incident_id = ?
        ORDER BY be.EventTime DESC
        LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $benchmarkEvents[] = $row;
        }
        $stmt->close();
    }

    // 7) Load PAR events & summary
    $sql = "
        SELECT *
        FROM par_events
        WHERE incident_id = ?
        ORDER BY EventTime DESC
        LIMIT 50
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $parEvents[] = $row;
        }
        $stmt->close();
    }

    if (!empty($parEvents)) {
        foreach ($parEvents as $row) {
            $app = $row['ApparatusLabel'] ?? '';
            if ($app === '') {
                continue;
            }
            if (!isset($parSummary[$app])) {
                $parSummary[$app] = [
                    'LastStatus' => $row['ParStatus'],
                    'LastTime'   => $row['EventTime'],
                ];
            } else {
                continue;
            }
        }
    }
       
       // 7.5) Sync staffing table from apparatus_responding for this incident
    //      (keeps Staffing / FF Available in line with apparatus_responding.firefighter_count)
    $sql = "
        SELECT
            COALESCE(ar.ApparatusLabel, ar.apparatus_ID, ar.Label) AS AppLabel,
            ar.firefighter_count
        FROM apparatus_responding ar
        WHERE ar.incident_id = ?
          AND ar.firefighter_count > 0
    ";
    if ($stmtApp = $conn->prepare($sql)) {
        $stmtApp->bind_param("i", $incidentId);
        $stmtApp->execute();
        $resApp = $stmtApp->get_result();

        while ($row = $resApp->fetch_assoc()) {
            $appLabel = trim($row['AppLabel'] ?? '');
            $ffCount  = (int)($row['firefighter_count'] ?? 0);
            if ($appLabel === '' || $ffCount <= 0) {
                continue;
            }

            // See if a staffing row already exists for this incident + apparatus
            $existingId = null;
            $sqlCheck = "
                SELECT ID
                FROM staffing
                WHERE incident_id = ?
                  AND ApparatusLabel = ?
                LIMIT 1
            ";
            if ($stmtCheck = $conn->prepare($sqlCheck)) {
                $stmtCheck->bind_param("is", $incidentId, $appLabel);
                $stmtCheck->execute();
                $resCheck = $stmtCheck->get_result();
                if ($r = $resCheck->fetch_assoc()) {
                    $existingId = (int)$r['ID'];
                }
                $stmtCheck->close();
            }

            if ($existingId) {
                // Update existing staffing row
                $sqlUpdate = "
                    UPDATE staffing
                    SET NumFirefighters = ?, UpdatedAt = NOW()
                    WHERE ID = ?
                ";
                if ($stmtUpd = $conn->prepare($sqlUpdate)) {
                    $stmtUpd->bind_param("ii", $ffCount, $existingId);
                    $stmtUpd->execute();
                    $stmtUpd->close();
                }
            } else {
                // Insert new staffing row
                $sqlInsert = "
                    INSERT INTO staffing (incident_id, ApparatusLabel, NumFirefighters)
                    VALUES (?, ?, ?)
                ";
                if ($stmtIns = $conn->prepare($sqlInsert)) {
                    $stmtIns->bind_param("isi", $incidentId, $appLabel, $ffCount);
                    $stmtIns->execute();
                    $stmtIns->close();
                }
            }
        }

        $stmtApp->close();
    }

    // 8) Load staffing summary / FF available
    // 8) Load staffing summary / FF available
$sql = "
    SELECT
        s.ApparatusLabel,
        s.NumFirefighters,
        s.UpdatedAt,
        COALESCE(ar.mutual_aid_dept, i.DeptName) AS DeptName
    FROM staffing s
    JOIN incidents i
        ON s.incident_id = i.id
    LEFT JOIN apparatus_responding ar
        ON ar.incident_id = s.incident_id
       AND ar.ApparatusLabel = s.ApparatusLabel
    WHERE s.incident_id = ?
    ORDER BY s.ApparatusLabel ASC
";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $availableFF[] = $row;
    }
    $stmt->close();
}


    $sql = "
        SELECT COALESCE(SUM(NumFirefighters), 0) AS total_ff
        FROM staffing
        WHERE incident_id = ?
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $totalFFAvailable = (int)$row['total_ff'];
        }
        $stmt->close();
    }

} catch (Exception $ex) {
    $db_error = $ex->getMessage();
}



// --------------------------------------------------
// AJAX: Set Alarm Level (stars on header)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_alarm_level') {
    header('Content-Type: application/json; charset=utf-8');

    $postIncidentId = (int)($_POST['incident_id'] ?? 0);
    $newLevel       = (int)($_POST['alarm_level'] ?? 0);

    if ($postIncidentId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing incident_id']);
        exit;
    }
    if ($newLevel < 1) $newLevel = 1;
    if ($newLevel > 5) $newLevel = 5;

    // Basic authorization: must match the incident currently loaded and the user's dept
    if ($postIncidentId !== $incidentId) {
        echo json_encode(['ok' => false, 'error' => 'Incident mismatch']);
        exit;
    }

    // Read current level from DB (so we only log true upgrades)
    $oldLevel = 1;
    try {
        if ($stmt = $conn->prepare("SELECT COALESCE(alarm_level, 1) AS alarm_level FROM incidents WHERE id = ? LIMIT 1")) {
            $stmt->bind_param("i", $postIncidentId);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && ($row = $r->fetch_assoc())) {
                $oldLevel = (int)$row['alarm_level'];
                if ($oldLevel < 1) $oldLevel = 1;
                if ($oldLevel > 5) $oldLevel = 5;
            }
            $stmt->close();
        }

        // Update current level + timestamp
        if ($stmt = $conn->prepare("UPDATE incidents SET alarm_level = ?, alarm_updated_at = NOW(6) WHERE id = ? LIMIT 1")) {
            $stmt->bind_param("ii", $newLevel, $postIncidentId);
            $stmt->execute();
            $stmt->close();
        }

        // Log ONLY when the alarm is upgraded to a higher level
        if ($newLevel > $oldLevel) {
            // incident_alarm_log is optional; if the table doesn't exist yet, ignore logging.
            try {
                if ($stmt = $conn->prepare("INSERT INTO incident_alarm_log (incident_id, old_level, new_level, changed_at) VALUES (?, ?, ?, NOW(6))")) {
                    $stmt->bind_param("iii", $postIncidentId, $oldLevel, $newLevel);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Throwable $e) {
                // ignore (table may not exist yet)
            }
        }

        echo json_encode(['ok' => true, 'old_level' => $oldLevel, 'new_level' => $newLevel]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Command Board - FD Incident Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
          crossorigin="anonymous">
    <style>
      body {
        background-color: #f2f2f2;
        color: #222;
        font-size: 0.95rem;
      }

      .card {
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.18);
      }

      .card-header {
        font-weight: 600;
        background-color: #b71c1c;
        color: #fff;
        padding: 0.4rem 0.75rem;
      }

      /* =====================================================
         High-contrast "fireground" buttons (solid + white text)
         ===================================================== */
      .btn-ics {
        background-color: #b11226; /* solid fire red */
        color: #ffffff !important;
        border: none;
        font-weight: 700;
      }
      .btn-ics:hover, .btn-ics:focus {
        background-color: #8e0e1d;
        color: #ffffff !important;
      }
      .btn-ics-secondary {
        background-color: #2f2f2f;
        color: #ffffff !important;
        border: none;
        font-weight: 700;
      }
      .btn-ics-secondary:hover, .btn-ics-secondary:focus {
        background-color: #1f1f1f;
        color: #ffffff !important;
      }

      .card-body {
        padding: 0.5rem 0.75rem;
      }

      .legend-row {
        background: #fff;
        border-radius: 0.5rem;
        padding: 0.35rem 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        border-left: 4px solid #b71c1c;
      }

      .legend-pill {
        display: inline-block;
        border-radius: 999px;
        padding: 0.15rem 0.6rem;
        margin-right: 0.25rem;
        margin-bottom: 0.25rem;
        font-size: 0.75rem;
        color: #fff;
        font-weight: 500;
        min-width: 120px;
        text-align: center;
      }

      .board-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.5rem;
      }

      .board-slot {
        background-color: #fff;
        border-radius: 0.5rem;
        border-left: 0.25rem solid #b71c1c;
        padding: 0.4rem 0.45rem;
        min-height: 90px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }

      .board-slot-header {
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.15rem;
      }

      .board-slot-main {
        font-size: 0.9rem;
        font-weight: 600;
      }

      .board-slot-sub {
        font-size: 0.78rem;
        color: #555;
      }

      .board-slot-footer {
        font-size: 0.75rem;
        color: #777;
      }

      .section-label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #555;
      }

      .tac-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
      }

      .tac-label {
        font-weight: 600;
      }

     .par-timer-display {
    font-size: 1.4rem;
    font-weight: 700;
}

/* Main PAR buttons: big, glove-friendly */
      .btn-par {
          min-width: 150px;
          font-weight: 700;
          padding: 0.6rem 1rem;
          font-size: 1rem;
      }

      /* Interval +/- buttons */
      .btn-par-interval {
          min-width: 56px;
          font-weight: 700;
          padding: 0.4rem 0.4rem;
          font-size: 1.1rem;
      }

      /* Interval number input */
      .par-interval-input {
          max-width: 90px;
          text-align: center;
          font-weight: 700;
          font-size: 1rem;
      }

      /* Flash / warn when PAR hits zero */
      .par-alert {
          color: #b71c1c;
      }


      .bench-button-row .btn {
        min-width: 140px;
        color: #fff;
        margin-bottom: 0.35rem;
      }

      .log-list {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 0.5rem;
        padding: 0.35rem 0.5rem;
        background-color: #fff;
      }
      /* Staffing list: show about 5 apparatus rows, then scroll */
          .log-list-staffing {
            max-height: 8rem;  /* tweak if you want a bit more or less */
        }
      .log-item-time {
        font-size: 0.75rem;
        color: #888;
        margin-right: 0.35rem;
      }

            /* TAC numeric controls */
      .tac-number-input {
        max-width: 110px;
        text-align: center;
        font-weight: 700;
        font-size: 1.1rem;
      }

      .btn-tac-inc {
        min-width: 56px;
        font-weight: 700;
        padding: 0.45rem 0.6rem;
        font-size: 1.3rem; /* big +/- for fingers */
      }

      .tac-caption {
        font-size: 0.8rem;
        color: #666;
      }

      /* MAYDAY checklist: big touch-friendly buttons in a grid */
      #maydayChecklist{
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.6rem;
      }
      @media (max-width: 576px){
        #maydayChecklist{ grid-template-columns: 1fr; }
      }
      #maydayChecklist .mayday-check-item{
        text-align: left;
        padding: 0.85rem 0.9rem;
        font-size: 1.05rem;
        line-height: 1.2;
        border-width: 2px;
      }


    
      /* =====================================================
         Tabs: force-hide inactive panes even if markup nesting changes
         ===================================================== */
      #fdMainTabsContent .tab-pane { display: none; }
      #fdMainTabsContent .tab-pane.active { display: block; }

/* Command Resources touch styling */
#commandResourcesModal .btn.cr-solid:hover,
#commandResourcesModal .btn.cr-solid:focus,
#commandResourcesModal .btn.cr-solid:active {
  filter: none !important;
  opacity: 1 !important;
  box-shadow: none !important;
}
#commandResourcesModal .btn.cr-solid {
  transition: none !important;
}

    /* Alarm stars in header (touch-friendly) */
    .alarm-star{
      background: transparent !important;
      border: 0 !important;
      padding: .45rem .55rem !important;      /* big hit target */
      line-height: 1 !important;
      font-size: 2.4rem !important;          /* BIG star */
      cursor: pointer;
      user-select: none;
      touch-action: manipulation;
    }
    .alarm-star.on{ color: #f0b429 !important; } /* gold-ish */
    .alarm-star.off{ color: #c7c7c7 !important; }
    .alarm-star:focus{ outline: 3px solid rgba(13,110,253,.45); outline-offset: 3px; border-radius: .5rem; }

    /* Mayday button (glove-friendly) */
    .btn-mayday{
      font-size: 1.5rem !important;
      font-weight: 800;
      padding: .75rem 1.5rem !important;
      border-radius: .75rem !important;
      letter-spacing: .03em;
    }
</style>
  </head>
  <body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="incidents.php">FD Incident Management</a>
    <div class="ms-auto d-flex gap-2">
      <a href="incidents.php" class="btn btn-secondary btn-sm me-4">← Back to Incidents</a>
    </div>
      <button type="button" class="btn btn-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalCloseIncident">
        Close Incident
      </button>
  </div>
</nav>

<div class="container-fluid mt-2 mb-4">
  <?php if (!empty($db_error)): ?>
    <div class="alert alert-danger"><?= e($db_error) ?></div>
  <?php else: ?>
    <!-- Incident Header -->
   <!-- Incident Header (minimal, to save vertical space) -->
  <div class="mb-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
      <div class="me-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <h3 class="mb-0">Command Board</h3>

          <div class="d-inline-flex align-items-center gap-1" id="alarmStars" data-current-level="<?= (int)$alarmLevel ?>" aria-label="Alarm level">
            <span class="text-muted me-2" style="font-size:1.05rem; font-weight:700;">Alarms</span>
            <?php for ($i=1; $i<=5; $i++): ?>
              <button type="button"
                      class="alarm-star <?= ($i <= (int)$alarmLevel) ? 'on' : 'off' ?>"
                      data-level="<?= $i ?>"
                      aria-label="Set alarm level to <?= $i ?>"
                      title="Set alarm level to <?= $i ?>">★</button>
            <?php endfor; ?>
          </div>

          <button type="button" class="btn btn-danger btn-mayday" id="btnMayday">Mayday</button>
        </div>
            <div class="text-muted" style="font-size:.9rem;">
                <?= e(!empty($incident['Address']) ? $incident['Address'] : 'No Address Entered') ?>
            </div>
      </div>
      
      <div class="mt-2 d-flex flex-wrap align-items-center gap-3">
        <div>
          <span class="fw-bold fs-3 text-primary">IC: </span>
          <span class="fw-bold fs-3 text-primary">
            <?= e(!empty($incident['incident_commander_display']) ? $incident['incident_commander_display'] : 'Not set') ?>
          </span>
        </div>

        <div>
          <span class="fw-bold fs-3 text-danger">Safety: </span>
          <span class="fw-bold fs-3 text-danger">
            <?= e(!empty($incident['safety_officer_display']) ? $incident['safety_officer_display'] : 'Not set') ?>
          </span>
        </div>

        <button type="button"
                class="btn btn-primary cr-solid btn-lg px-3 py-2"
                data-bs-toggle="modal"
                data-bs-target="#modalChangeIC">
          Change IC
        </button>

        <button type="button"
                <button type="button"
                class="btn btn-danger cr-solid btn-lg px-3 py-2"
                data-bs-toggle="modal"
                data-bs-target="#modalSetSafetyOfficer">
          Set Safety
        </button>

        <a href="safety_checklist.php?incident_id=<?= (int)$incidentId ?>"
           class="btn btn-dark btn-lg px-3 py-2">
          Safety Checklist
          <?php if (!empty($safetyTotal)): ?>
            <span class="badge bg-light text-dark ms-2"><?= (int)$safetyDone ?>/<?= (int)$safetyTotal ?></span>
          <?php endif; ?>
        </a>



      <div class="text-end">
          <div class="section-label mb-0">Incident Time</div>
          <div id="overallIncidentTimer"
            class="fw-bold"
            style="font-size:1.1rem;"
            data-has-sizeup="<?= $overallHasSizeup ? '1' : '0' ?>"
            data-initial-seconds="<?= (int)$overallTimerSecondsInitial ?>">
          <?= e($overallTimerLabel) ?>
        </div>
      </div>
    </div>
  </div>


    <!-- Color Legend Strip -->
    <div class="legend-row mb-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="me-1 text-muted" style="font-size:0.8rem;">Assignments Legend:</span>

        <?php if (!empty($assignmentTypes)): ?>
          <?php $paletteCount = count($assignmentColorPalette); ?>

          <?php foreach ($assignmentTypes as $t): ?>
            <?php
              $name  = $t['Name'] ?? 'Assignment';
              $id    = isset($t['ID']) ? (int)$t['ID'] : 0;
              $index = ($paletteCount > 0) ? ($id % $paletteCount) : 0;
              $color = $assignmentColorPalette[$index];
            ?>
            <span class="legend-pill" style="background: <?= e($color) ?>;">
              <?= e($name) ?>
            </span>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="text-warning" style="font-size:0.8rem;">No assignment types configured.</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- PAR + TAC + Staffing row -->
    <div class="row mb-2 g-2">
      <!-- PAR Panel -->
      <div class="col-12 col-md-4 col-lg-3">
  <div class="card">
    <div class="card-header">
      PAR Check
    </div>
    <div class="card-body">

      <!-- PAR Interval Controls -->
      <div class="section-label mb-1">PAR Interval (minutes)</div>
      <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
        <button class="btn btn-secondary cr-solid btn-par-interval" id="btnParMinus">
          &minus;
        </button>

        <input type="number"
               id="parIntervalMinutes"
               class="form-control form-control-lg par-interval-input"
               value="20"
               min="1"
               max="60">

        <button class="btn btn-secondary cr-solid btn-par-interval" id="btnParPlus">
          +
        </button>
      </div>
      <div class="text-muted" style="font-size:0.8rem;">
        Default is 20 minutes. Adjust as needed (e.g., after a Mayday).
      </div>

      <hr class="my-2">

      <!-- PAR Timer Display + Controls -->
      <!-- PAR Timer Display + Controls -->
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="section-label mb-0">PAR Timer</div>
        <div class="par-timer-display" id="parTimerDisplay">20:00</div>
      </div>

      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-danger cr-solid btn-par" id="btnParStart">
          Start PAR
        </button>
        <button class="btn btn-secondary cr-solid btn-par" id="btnParReset">
          Reset
        </button>
      </div>


      <hr class="my-2">

      <!-- Last PAR Results -->
      <div class="section-label mb-1">Last PAR Results</div>
      <div class="log-list">
        <?php if (!empty($parSummary)): ?>
          <?php foreach ($parSummary as $app => $info): ?>
            <div class="mb-1">
              <span class="log-item-time"><?= e($info['LastTime']) ?></span>
              <strong><?= e($app) ?>:</strong>
              <span><?= e($info['LastStatus']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted" style="font-size:0.85rem;">
            No PAR events recorded yet.
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>


      <!-- TAC Channels Panel -->
      <!-- TAC Channels Panel -->
<div class="col-12 col-md-4 col-lg-3">
  <div class="card">
    <div class="card-header">
      TAC Channels
    </div>
    <div class="card-body">

      <!-- Command TAC -->
      <div class="section-label mb-1">CMD TAC</div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <button class="btn btn-secondary cr-solid btn-tac-inc"
                type="button"
                data-target="cmdTacInput"
                data-dir="-1">
          &minus;
        </button>

        <input type="number"
               id="cmdTacInput"
               class="form-control form-control-lg tac-number-input"
               value="3"
               min="1"
               max="30">

        <button class="btn btn-secondary cr-solid btn-tac-inc"
                type="button"
                data-target="cmdTacInput"
                data-dir="1">
          +
        </button>
      </div>
      <div class="tac-caption mb-2">
        Example: TAC 3 &mdash; Command channel.
      </div>

      <hr class="my-2">

      <!-- Water TAC -->
      <div class="section-label mb-1">Water TAC</div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <button class="btn btn-secondary cr-solid btn-tac-inc"
                type="button"
                data-target="waterTacInput"
                data-dir="-1">
          &minus;
        </button>

        <input type="number"
               id="waterTacInput"
               class="form-control form-control-lg tac-number-input"
               value="4"
               min="1"
               max="30">

        <button class="btn btn-secondary cr-solid btn-tac-inc"
                type="button"
                data-target="waterTacInput"
                data-dir="1">
          +
        </button>
      </div>
      <div class="tac-caption">
        Example: TAC 4 &mdash; Water supply channel.
      </div>

    </div>
  </div>
</div>

      <!-- Staffing / FF Available -->
      <div class="col-12 col-md-4 col-lg-3">
        <div class="card">
          <div class="card-header">
            Staffing / FF Available
          </div>
                    <div class="card-body">
            <div class="section-label mb-1">Current Staffing</div>
           <div class="log-list log-list-staffing mb-2">
  <?php if (!empty($availableFF)): ?>
    <?php foreach ($availableFF as $row): ?>
      <div class="d-flex justify-content-between mb-1">
      <!-- Apparatus -->
        <span class="flex-grow-1">
          <?= e($row['ApparatusLabel']) ?>
        </span>

        <!-- Department Name -->
        <span class="text-center flex-grow-1" style="white-space:nowrap;">
          <?= e($row['DeptName'] ?? '') ?>
        </span>

        <!-- FF count -->
        <span style="min-width:3rem; text-align:right;">
          <?= (int)$row['NumFirefighters'] ?> FF
        </span>

      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>


            <div class="d-flex justify-content-between">
              <span class="section-label mb-0">Total FF</span>
              <span style="font-weight:700; font-size:1.1rem;">
                <?= (int)$totalFFAvailable ?>
              </span>
            </div>

            <hr class="my-2">

              <!-- Mutual Aid: open modal to add a mutual aid apparatus or officer -->
                <button type="button"
                        id="btnCommandResources"
                        class="btn btn-danger btn-lg w-100 mt-1">
                  Command Resources
                </button>

          </div>

        </div>
      </div>
    </div>

    
    <!-- Main Command Board -->

    <!-- ========================================================= -->
    <!-- MAIN TABBED LAYOUT: Assignments | Command | Benchmarks     -->
    <!-- ========================================================= -->
    <ul class="nav nav-tabs mb-2" id="fdMainTabs" role="tablist" style="font-size:1.05rem;">
      <li class="nav-item" role="presentation">
        <button class="nav-link active py-3 px-3" id="tab-assignments-tab" data-bs-toggle="tab" data-bs-target="#tab-assignments" type="button" role="tab" aria-controls="tab-assignments" aria-selected="true">
          Assignments
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link py-3 px-3" id="tab-command-tab" data-bs-toggle="tab" data-bs-target="#tab-command" type="button" role="tab" aria-controls="tab-command" aria-selected="false">
          Command
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link py-3 px-3" id="tab-benchmarks-tab" data-bs-toggle="tab" data-bs-target="#tab-benchmarks" type="button" role="tab" aria-controls="tab-benchmarks" aria-selected="false">
          Benchmarks
        </button>
      </li>
    </ul>

    <div class="tab-content" id="fdMainTabsContent">
      <!-- ===================== -->
      <!-- Assignments TAB       -->
      <!-- ===================== -->
      <div class="tab-pane fade show active" id="tab-assignments" role="tabpanel" aria-labelledby="tab-assignments-tab" tabindex="0">
        <div class="row g-2">
<!-- Left: Command Assignment Board -->
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
  <div>Command Assignment Board</div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-ics" onclick="openAddIcs('DIVISION')">+ Division</button>
    <button type="button" class="btn btn-sm btn-ics-secondary" onclick="openAddIcs('GROUP')">+ Group</button>
  </div>
</div>
          <div class="card-body">
            <?php if (!empty($commandAssignments)): ?>
              <div class="board-grid">
                <?php foreach ($commandAssignments as $slot): ?>
                  <?php
                    $assignName  = $slot['AssignmentName'] ?? 'Unassigned';
                    $assignColor = trim($slot['AssignmentColor'] ?? '');
                    if ($assignColor === '') {
                        $assignColor = '#b71c1c';
                    }
                    $apparatus   = $slot['ApparatusLabel'] ?? '';
                    $apptype     = $slot['ApparatusType'] ?? '';
                    $notes       = $slot['Notes'] ?? '';
                  ?>
                  <div class="board-slot">
                    <div>
                      <div class="board-slot-header" style="color: <?= e($assignColor) ?>;">
                        <?= e($assignName) ?>
                      </div>
                      <div class="board-slot-main">
                        <?php if ($apparatus !== ''): ?>
                          <?= e($apparatus) ?>
                          <?php if ($apptype !== ''): ?>
                            <span class="text-muted" style="font-size:0.75rem;">(<?= e($apptype) ?>)</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="text-muted">No apparatus assigned</span>
                        <?php endif; ?>
                      </div>
                      <?php if ($notes !== ''): ?>
                        <div class="board-slot-sub">
                          <?= e($notes) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="board-slot-footer d-flex justify-content-between align-items-center mt-1">
                      <span style="font-size:0.75rem;">
                        Row <?= isset($slot['RowPos']) ? (int)$slot['RowPos'] : 0 ?>,
                        Col <?= isset($slot['ColumnPos']) ? (int)$slot['ColumnPos'] : 0 ?>
                      </span>
                      <span style="font-size:0.75rem;">
                        <?= e($slot['Status'] ?? 'Active') ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-muted" style="font-size:0.9rem;">
                No command assignments have been created for this incident yet.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      
        </div>
      </div>

      <!-- ===================== -->
      <!-- Command TAB           -->
      <!-- ===================== -->
      <div class="tab-pane fade" id="tab-command" role="tabpanel" aria-labelledby="tab-command-tab" tabindex="0">
        <div class="row g-2">
          <div class="col-12">
<!-- Operations Structure (ICS Divisions / Groups) -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>Operations Structure</div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-ics" onclick="openAddIcs('DIVISION')">+ Add Division</button>
              <button type="button" class="btn btn-sm btn-ics-secondary" onclick="openAddIcs('GROUP')">+ Add Group</button>
            </div>
          </div>
          <div class="card-body">
            <?php if (!empty($icsMessage)): ?>
              <div class="alert alert-warning py-2 mb-3"><?= e($icsMessage) ?></div>
            <?php endif; ?>

            <?php if (empty($icsElements)): ?>
              <div class="text-muted" style="font-size:0.95rem;">
                No active Divisions / Groups yet. For small incidents, you can run IC-direct.
              </div>
            <?php else: ?>
              <div class="d-flex flex-column gap-2">
                <?php foreach ($icsElements as $el): ?>
                  <div class="border rounded p-2">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                      <div>
                        <div class="fw-bold">
                          <span class="badge bg-dark me-2"><?= e($el['element_type']) ?></span>
                          <?= e($el['element_name']) ?>
                        </div>
                        <div class="small text-muted mt-1">
                          Supervisor:
                          <?php if (!empty($el['supervisor_display'])): ?>
                            <span class="fw-semibold"><?= e($el['supervisor_display']) ?></span>
                            <span class="badge bg-light text-dark ms-1"><?= e($el['supervisor_source']) ?></span>
                          <?php else: ?>
                            <span class="fw-semibold">IC covering</span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="d-flex gap-2">
                        <button type="button"
                                class="btn btn-sm btn-primary cr-solid"
                                onclick="openSetSupervisor(<?= (int)$el['id'] ?>)">
                          Set Supervisor
                        </button>

                        <form method="post" class="m-0">
                          <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">
                          <input type="hidden" name="element_id" value="<?= (int)$el['id'] ?>">
                          <button type="submit" name="release_ics_element" value="1" class="btn btn-sm btn-danger cr-solid"
                                  onclick="return confirm('Release <?= e($el['element_type']) ?> <?= e($el['element_name']) ?>?');">
                            Release
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Add ICS Element Modal -->
        <div class="modal fade" id="modalAddIcs" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="post">
                <div class="modal-header">
                  <h5 class="modal-title">Add <span id="ics_modal_title_kind">Division</span></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">
                  <input type="hidden" name="element_type" id="ics_element_type" value="DIVISION">

                  <div class="mb-2 fw-bold">Type</div>
                  <div class="mb-3">
                    <span class="badge bg-dark" id="ics_type_badge">DIVISION</span>
                  </div>

                  <label class="form-label fw-bold" for="ics_element_name">Name</label>
                  <input type="text"
                         class="form-control form-control-lg"
                         id="ics_element_name"
                         name="element_name"
                         placeholder="e.g., Division 2, Alpha, Vent Group"
                         required>

                  <div class="mt-2 d-flex flex-wrap gap-2" id="ics_quick_picks"></div>
                  <div class="form-text">
                    Tip: Keep it short and clear. You can create as few or as many as you need.
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" name="add_ics_element" value="1" class="btn btn-primary btn-lg">Add</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Set Supervisor Modal -->
        <div class="modal fade" id="modalSetSupervisor" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <form method="post">
                <div class="modal-header">
                  <h5 class="modal-title">Set Division/Group Supervisor</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">
                  <input type="hidden" name="element_id" id="sup_element_id" value="0">
                  <input type="hidden" name="supervisor_source" id="sup_source" value="">
                  <input type="hidden" name="supervisor_id" id="sup_id" value="0">
                  <input type="hidden" name="supervisor_display" id="sup_display" value="">

                  <div class="alert alert-info py-2">
                    Select a supervisor below, then press <strong>Save</strong>.
                  </div>

                  <div class="mb-3">
                    <div class="fw-bold fs-5 mb-2">Local Command Officers</div>
                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($icLocalOfficers as $o): ?>
                        <?php
                          $label = trim(($o['radio_designation'] ?? '') . ' ' . ($o['member_name'] ?? ''));
                          if ($label === '') $label = 'Officer #' . (int)$o['id'];
                        ?>
                        <button type="button"
                                class="btn btn-lg btn-outline-primary px-3 py-2 sup-pick"
                                onclick="pickSupervisor('<?= e($label) ?>','local',<?= (int)$o['id'] ?>, this)">
                          <?= e($label) ?>
                        </button>
                      <?php endforeach; ?>
                      <?php if (empty($icLocalOfficers)): ?>
                        <div class="text-muted">No local command staff on file.</div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="mb-3">
                    <div class="fw-bold fs-5 mb-2">Mutual Aid Command Officers</div>
                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($icMutualAidOfficers as $o): ?>
                        <?php
                          $label = trim(($o['radio_designation'] ?? '') . ' ' . (($o['display_name'] ?? '') ?: ($o['officer_name'] ?? '') ?: ($o['command_staff_display'] ?? '')));
                          if ($label === '') $label = 'Mutual Aid Officer #' . (int)($o['id'] ?? 0);
                        ?>
                        <button type="button"
                                class="btn btn-lg btn-outline-secondary px-3 py-2 sup-pick"
                                onclick="pickSupervisor('<?= e($label) ?>','mutual_aid',<?= (int)($o['id'] ?? 0) ?>, this)">
                          <?= e($label) ?>
                        </button>
                      <?php endforeach; ?>
                      <?php if (empty($icMutualAidOfficers)): ?>
                        <div class="text-muted">No mutual aid command staff for this incident.</div>
                      <?php endif; ?>
                    </div>
                  </div>

                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" name="set_ics_supervisor" value="1" class="btn btn-primary btn-lg">Save</button>
                </div>
              </form>
            </div>
          </div>
        </div>

<!-- ========================================================= -->
<!-- MUTUAL AID MODAL (FULL + CORRECT + UPDATED UI)            -->
<!-- ========================================================= -->
<div class="modal fade" id="modalMutualAid" tabindex="-1" aria-labelledby="mutualAidLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-light text-dark">
    <form method="post" action="command_board.php?incident_id=<?= (int)$incidentId ?>" id="mutualAidForm">
        <input type="hidden" name="ma_mode" id="ma_mode" value="apparatus">
        <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">

        <div class="modal-header border-0">
          <h5 class="modal-title" id="mutualAidLabel">Add Command Resources</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

            <!-- MUTUAL AID DEPARTMENT -->
            <div class="mb-3">
              <label class="form-label">Department</label>
              <input type="text" name="ma_dept_name" id="ma_dept_name" class="form-control form-control-lg" required data-incident-dept="<?= e($incident['DeptName'] ?? '') ?>">
            </div>

          <!-- =================================================== -->
          <!-- MODE SELECT BUTTONS                                 -->
          <!-- =================================================== -->
          <div class="d-flex flex-wrap gap-2 mb-3">
  <button type="button" class="btn btn-secondary cr-solid btn-lg ma-mode-btn" data-mode="local_staff" onclick="window.setMutualAidMode && window.setMutualAidMode('local_staff');">
    Add Local Command Staff
  </button>
  <button type="button" class="btn btn-primary btn-lg ma-mode-btn active" data-mode="apparatus" onclick="window.setMutualAidMode && window.setMutualAidMode('apparatus');">
    Add Apparatus
  </button>
  <button type="button" class="btn btn-secondary cr-solid btn-lg ma-mode-btn" data-mode="officer" onclick="window.setMutualAidMode && window.setMutualAidMode('officer');">
    Add Command Officer
  </button>
</div>

          <!-- =================================================== -->
          <!-- APPARATUS SECTION (UPDATED UI)                      -->
          <!-- =================================================== -->
          <div class="ma-section ma-section-apparatus">

            <?php
            $appTypes = [];
            $sql = "SELECT id, ApparatusType FROM apparatus_types
                    WHERE is_active = 1 AND ApparatusType <> 'Brush' ORDER BY ApparatusType ASC";
            if ($res = $conn->query($sql)) {
              while ($row = $res->fetch_assoc()) $appTypes[] = $row;
              $res->free();
            }
            ?>

            <!-- APPARATUS TYPE -->
            <div class="mb-3">
              <label class="form-label">Apparatus Type</label>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($appTypes as $t): ?>
                  <button type="button"
                          class="btn btn-primary cr-solid btn-lg ma-app-type-btn"
                          style="min-width: 7rem;"
                          data-app-type-id="<?= (int)$t['id'] ?>"
                          data-app-type-name="<?= e($t['ApparatusType']) ?>">
                      <?= e($t['ApparatusType']) ?>
                  </button>
                <?php endforeach; ?>

              </div>
              <input type="hidden" name="ma_app_type_id" id="ma_app_type_id">
            </div>

            <!-- APPARATUS LABEL + DIGIT KEYPAD -->
            <div class="mb-3">
              <label class="form-label">Apparatus ID / Label</label>
              <input type="text"
                     name="ma_apparatus_label"
                     id="ma_apparatus_label"
                     class="form-control form-control-lg"
                     placeholder="e.g. Engine 50" required pattern="^[A-Za-z ]*\d+[A-Za-z0-9 ]*$" title="Enter a unit label that includes at least one number (example: Engine 50).">

              <div class="mt-2 d-flex flex-wrap gap-2">
                <?php for ($d = 0; $d <= 9; $d++): ?>
                  <button type="button"
                          class="btn btn-secondary cr-solid btn-lg ma-label-key-btn"
                          style="width: 3.2rem;"
                          data-digit="<?= $d ?>">
                      <?= $d ?>
                  </button>
                <?php endfor; ?>
              </div>
            </div>

            <!-- NUMBER OF FIREFIGHTERS + BUTTONS 1–5 -->
            <div class="mb-3">
              <label class="form-label">Number of firefighters</label>
              <input type="number"
                     min="1"
                     name="ma_staffing"
                     id="ma_staffing"
                     value=""
                     class="form-control form-control-lg"
                     required>

             <div class="mt-2 d-flex flex-wrap gap-2">
                <?php for ($n = 1; $n <= 5; $n++): ?>
                  <button type="button"
                          class="btn btn-secondary cr-solid btn-lg ma-ff-key-btn"
                          style="width: 3.2rem;"
                          data-ff="<?= $n ?>">
                      <?= $n ?>
                  </button>
                <?php endfor; ?>
              </div>
            </div>

          </div> <!-- END APPARATUS SECTION -->

          <!-- =================================================== -->
          <!-- COMMAND OFFICER SECTION (UNCHANGED)                 -->
          <!-- =================================================== -->

          <!-- COMMAND OFFICER SECTION -->
          <div class="ma-section ma-section-officer d-none">
            <!-- Officer Rank FIRST (buttons) -->
            <div class="mb-3">
              <label class="form-label">Officer Rank</label>
              <div class="d-flex flex-wrap gap-2">
                <button type="button"
                        class="btn btn-primary cr-solid btn-lg ma-officer-rank-btn"
                        data-rank="Chief">
                  Chief
                </button>
                <button type="button"
                        class="btn btn-primary cr-solid btn-lg ma-officer-rank-btn"
                        data-rank="Deputy Chief">
                  Deputy Chief
                </button>
                <button type="button"
                        class="btn btn-primary cr-solid btn-lg ma-officer-rank-btn"
                        data-rank="Assistant Chief">
                  Assistant Chief
                </button>
                <button type="button"
                        class="btn btn-primary cr-solid btn-lg ma-officer-rank-btn"
                        data-rank="Captain">
                  Captain
                </button>
                <button type="button"
                        class="btn btn-primary cr-solid btn-lg ma-officer-rank-btn"
                        data-rank="Lieutenant">
                  Lieutenant
                </button>
              </div>
              <!-- hidden field actually submitted to PHP -->
              <input type="hidden" name="ma_officer_rank" id="ma_officer_rank">
            </div>

            <!-- Officer ID with keypad -->
            <div class="mb-3">
              <label class="form-label">Officer ID</label>
              <input type="text"
                    name="ma_officer_name"
                    id="ma_officer_id"
                    class="form-control form-control-lg"
                    placeholder="e.g. Chief50"
                    readonly
                    inputmode="none"
                    autocomplete="off">

              <div class="mt-2 d-flex flex-wrap gap-2">
                <?php for ($d = 0; $d <= 9; $d++): ?>
                  <button type="button"
                          class="btn btn-secondary cr-solid btn-lg ma-officer-id-key-btn"
                          style="width: 3.2rem;"
                          data-digit="<?= $d ?>">
                    <?= $d ?>
                  </button>
                <?php endfor; ?>
              </div>
            </div>
          </div>


        </div> <!-- END MODAL BODY -->

        <!-- ===================================================== -->
        <!-- SAVE / CANCEL BUTTONS                                 -->
        <!-- ===================================================== -->
        <div class="modal-footer border-0">
          <button type="submit" name="save_mutual_aid" class="btn btn-success btn-lg">Save</button>
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>


<!-- Simple PAR timer script (front-end only) -->
    

          </div>
        </div>
        <div class="text-muted small mt-2">
          Tip: For small incidents you can run IC-direct. As complexity increases, add Divisions/Groups and assign supervisors to manage span of control.
        </div>
      </div>

      <!-- ===================== -->
      <!-- Benchmarks TAB        -->
      <!-- ===================== -->
      <div class="tab-pane fade" id="tab-benchmarks" role="tabpanel" aria-labelledby="tab-benchmarks-tab" tabindex="0">
        <div class="row g-2">
          <div class="col-12">
<!-- Benchmark Buttons -->
        <div class="card mb-2">
          <div class="card-header">
            Benchmarks
          </div>
          <div class="card-body">
            <div class="bench-button-row">
              <?php if (!empty($benchmarkTypes)): ?>
                <?php foreach ($benchmarkTypes as $b): ?>
                  <button class="btn btn-sm btn-danger mb-1" disabled>
                    <?= e($b['Name']) ?>
                  </button>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted" style="font-size:0.85rem;">
                  No benchmark types configured.
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        
        
          

<!-- Benchmark Log -->
        <div class="card">
          <div class="card-header">
            Benchmark / Event Log
          </div>
          <div class="card-body">
            <div class="section-label mb-1">Recent Benchmarks</div>
            <div class="log-list">
              <?php if (!empty($benchmarkEvents)): ?>
                <?php foreach ($benchmarkEvents as $b): ?>
                  <div class="mb-1">
                    <span class="log-item-time"><?= e($b['EventTime']) ?></span>
                    <span><?= e($b['benchmark_name'] ?? 'Benchmark') ?></span>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-muted" style="font-size:0.85rem;">
                  No benchmark events recorded yet.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  <?php endif; ?>
</div>
<?php
// Pre-fill last mutual-aid department and command identity for this incident (if any)
$lastMADepartment = '';
$lastMACommand    = '';

if (isset($conn) && $incidentId > 0) {
    $sql = "
        SELECT dept_name, command_identity
        FROM incident_mutual_aid
        WHERE incident_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $incidentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $lastMADepartment = $row['dept_name'] ?? '';
            $lastMACommand    = $row['command_identity'] ?? '';
        }
        $stmt->close();
    }
}


?>
</div>
        </div>
      </div>
    </div>

<script>
  (function() {
    var parTimer           = null;
    var parIntervalMinutes = 20;   // default
    var parRemainingSecs   = parIntervalMinutes * 60;

    var display        = document.getElementById('parTimerDisplay');
    var btnParStart    = document.getElementById('btnParStart');
    var btnParReset    = document.getElementById('btnParReset');
    var inputMinutes   = document.getElementById('parIntervalMinutes');
    var btnParMinus    = document.getElementById('btnParMinus');
    var btnParPlus     = document.getElementById('btnParPlus');
    var lastCheckDisplay = document.getElementById('parLastCheck');

    if (!display || !btnParStart || !btnParReset || !inputMinutes) {
      return;
    }
    // --------------------------------------------------
    // Overall Incident Timer (from size-up completion)
    // --------------------------------------------------
    (function() {
      var timerEl = document.getElementById('overallIncidentTimer');
      if (!timerEl) return;

      var hasSizeup = timerEl.getAttribute('data-has-sizeup') === '1';
      if (!hasSizeup) {
        // No size-up yet; keep "Awaiting size-up" static
        return;
      }

      var secs = parseInt(timerEl.getAttribute('data-initial-seconds'), 10);
      if (isNaN(secs) || secs < 0) secs = 0;

      function renderOverall() {
        var h  = Math.floor(secs / 3600);
        var m  = Math.floor((secs % 3600) / 60);
        var s  = secs % 60;

        var hh = (h < 10 ? '0' : '') + h;
        var mm = (m < 10 ? '0' : '') + m;
        var ss = (s < 10 ? '0' : '') + s;

        timerEl.textContent = hh + ':' + mm + ':' + ss;
      }

      // Render initial value from PHP
      renderOverall();

      // Increment every second
      setInterval(function() {
        secs++;
        renderOverall();
      }, 1000);
    })();

    // --- Utility: update display from remaining seconds ---
    function updateParDisplay() {
      var m  = Math.floor(parRemainingSecs / 60);
      var s  = parRemainingSecs % 60;
      var mm = (m < 10 ? '0' : '') + m;
      var ss = (s < 10 ? '0' : '') + s;
      display.textContent = mm + ':' + ss;

      // Visual alert when at 0
      if (parRemainingSecs <= 0) {
        display.classList.add('par-alert');
      } else {
        display.classList.remove('par-alert');
      }
    }

    // --- Utility: reset remaining seconds from minutes field ---
    function resetFromInterval() {
      var val = parseInt(inputMinutes.value, 10);
      if (isNaN(val) || val < 1)  val = 1;
      if (val > 60)               val = 60;

      parIntervalMinutes = val;
      parRemainingSecs   = parIntervalMinutes * 60;
      updateParDisplay();
    }

    // --- Simple beep using Web Audio API when PAR hits zero ---
    function playParBeep() {
      var AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) {
        return; // older browser, no beep
      }

      var ctx = new AudioCtx();

      function singleBeep(offsetSeconds) {
        var osc  = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.value = 880; // A5-ish tone

        osc.connect(gain);
        gain.connect(ctx.destination);

        var startTime = ctx.currentTime + offsetSeconds;

        gain.gain.setValueAtTime(0.001, startTime);
        gain.gain.exponentialRampToValueAtTime(1.0, startTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + 0.4);

        osc.start(startTime);
        osc.stop(startTime + 0.5);
      }

      // Three short beeps spaced 0.6s apart
      singleBeep(0);
      singleBeep(0.6);
      singleBeep(1.2);
    }

    // --- Timer tick (countdown) ---
    function tickPar() {
      if (parRemainingSecs > 0) {
        parRemainingSecs--;
        updateParDisplay();
        if (parRemainingSecs <= 0) {
          // Hit zero: stop and beep
          clearInterval(parTimer);
          parTimer = null;
          updateParDisplay();
          playParBeep();
          btnParStart.textContent = 'Start PAR';
          btnParStart.classList.remove('btn-danger');
          btnParStart.classList.add('btn-outline-danger');
        }
      }
    }

    // --- Button / input wiring ---

    // Start / Pause
    btnParStart.addEventListener('click', function() {
      if (parTimer === null) {
        // If already at or below zero, reset from interval first
        if (parRemainingSecs <= 0) {
          resetFromInterval();
        }
        parTimer = setInterval(tickPar, 1000);
        btnParStart.textContent = 'Pause PAR';
        btnParStart.classList.remove('btn-outline-danger');
        btnParStart.classList.add('btn-danger');
      } else {
        clearInterval(parTimer);
        parTimer = null;
        btnParStart.textContent = 'Resume PAR';
        btnParStart.classList.remove('btn-danger');
        btnParStart.classList.add('btn-outline-danger');
      }
    });

    // --- Record "Last PAR check at" as local time in 24-hour format ---
        function updateLastParCheck() {
          if (!lastCheckDisplay) return;

          var now = new Date();

          function pad(n) {
            return n < 10 ? '0' + n : '' + n;
          }

          var hh = pad(now.getHours());   // 00-23
          var mm = pad(now.getMinutes()); // 00-59
          var ss = pad(now.getSeconds()); // 00-59

          lastCheckDisplay.textContent = hh + ':' + mm + ':' + ss;
        }

   // Reset button
btnParReset.addEventListener('click', function() {
        if (parTimer !== null) {
          clearInterval(parTimer);
          parTimer = null;
        }

        // Treat Reset as "PAR just completed"
        resetFromInterval();
        updateLastParCheck();

        btnParStart.textContent = 'Start PAR';
        btnParStart.classList.remove('btn-danger');
        btnParStart.classList.add('btn-outline-danger');
      });


    // Interval minus / plus
    if (btnParMinus) {
      btnParMinus.addEventListener('click', function() {
        var val = parseInt(inputMinutes.value, 10) || parIntervalMinutes;
        val = Math.max(1, val - 1);
        inputMinutes.value = val;
        if (parTimer === null) {
          resetFromInterval();
        }
      });
    }

    if (btnParPlus) {
      btnParPlus.addEventListener('click', function() {
        var val = parseInt(inputMinutes.value, 10) || parIntervalMinutes;
        val = Math.min(60, val + 1);
        inputMinutes.value = val;
        if (parTimer === null) {
          resetFromInterval();
        }
      });
    }

    // Also reset from interval when user types a value and leaves the field
    inputMinutes.addEventListener('change', function() {
      if (parTimer === null) {
        resetFromInterval();
      }
    });

    // Initialize display at load
    resetFromInterval();
  })();
</script>
<script>
  (function() {
    var buttons = document.querySelectorAll('.btn-tac-inc');

    function clamp(val, min, max) {
      if (val < min) return min;
      if (val > max) return max;
      return val;
    }

    buttons.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var targetId = btn.getAttribute('data-target');
        var dir      = parseInt(btn.getAttribute('data-dir'), 10) || 0;
        var input    = document.getElementById(targetId);
        if (!input) return;

        var current = parseInt(input.value, 10);
        if (isNaN(current)) current = 0;

        var min = parseInt(input.getAttribute('min'), 10) || 1;
        var max = parseInt(input.getAttribute('max'), 10) || 30;

        var next = clamp(current + dir, min, max);
        input.value = next;
      });
    });
  })();
</script>
 <!-- Bootstrap 5 JS bundle (needed for modals to work) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous">
    </script>

<script>
/* Mutual Aid: resilient mode switch helper.
   Defined BEFORE DOMContentLoaded so inline onclick works even if later JS errors. */
window.setMutualAidMode = function(mode){
  try {
    var form = document.getElementById('mutualAidForm');
    if (!form) return;

    // normalize mode
    mode = (mode === 'officer' || mode === 'local_staff') ? mode : 'apparatus';

    // store mode in hidden input
    var modeInput = document.getElementById('ma_mode');
    if (modeInput) modeInput.value = mode;

    // required fields by mode
    var appLabel  = document.getElementById('ma_apparatus_label');
    var ffInput   = document.getElementById('ma_staffing');
    var officerId = document.getElementById('ma_officer_id');

    if (mode === 'apparatus') {
      if (appLabel)  appLabel.required = true;
      if (ffInput)   ffInput.required  = true;
      if (officerId) officerId.required = false;
    } else if (mode === 'officer') {
      if (appLabel)  appLabel.required = false;
      if (ffInput)   ffInput.required  = false;
      if (officerId) officerId.required = true;
    } else {
      // local_staff
      if (appLabel)  appLabel.required = false;
      if (ffInput)   ffInput.required  = false;
      if (officerId) officerId.required = true; // selecting local staff sets officer id
    }

    // Dept field behavior
    var deptField = document.getElementById('ma_dept_name');
    if (deptField) {
      if (mode === 'local_staff') {
        var incidentDept = deptField.getAttribute('data-incident-dept') || '';
        if (incidentDept) deptField.value = incidentDept;
        deptField.readOnly = true;
      } else {
        deptField.readOnly = false;
      }
    }

    // button styles
    document.querySelectorAll('.ma-mode-btn').forEach(function(b){
      b.classList.remove('active','btn-primary');
      b.classList.add('btn-outline-secondary');
      if ((b.getAttribute('data-mode')||'apparatus') === mode) {
        b.classList.add('active','btn-primary');
        b.classList.remove('btn-outline-secondary');
      }
    });

    // sections
    document.querySelectorAll('.ma-section').forEach(function(sec){ sec.classList.add('d-none'); });
    var sel = (mode === 'apparatus')
      ? '.ma-section-apparatus'
      : (mode === 'officer')
        ? '.ma-section-officer'
        : '.ma-section-localstaff';
    var show = document.querySelector(sel);
    if (show) show.classList.remove('d-none');

  } catch (e) {
    console && console.error && console.error('setMutualAidMode error', e);
  }
};

document.addEventListener('DOMContentLoaded', function () {
  // =====================================================
  // 1) Auto-open Mutual Aid modal + "add another?" prompt
  // =====================================================
  var maModal = null;
  var focusDeptField = function () {
  var form = document.getElementById('mutualAidForm');
  if (!form) return;

  var deptField = form.querySelector('input[name="ma_dept_name"]');
  if (!deptField) return;

  // If the field is disabled/readonly/hidden, don't try to focus it
  if (deptField.disabled || deptField.readOnly || deptField.offsetParent === null) return;

  // Let Bootstrap finish rendering before focusing (prevents "freeze" on some browsers)
  setTimeout(function () {
    try {
      deptField.focus({ preventScroll: true });

      // Only attempt selection if it's a text-like input and supported
      var type = (deptField.getAttribute('type') || 'text').toLowerCase();
      var isTextLike = (type === 'text' || type === 'search' || type === 'tel' || type === 'url' || type === 'email');

      if (isTextLike && typeof deptField.setSelectionRange === 'function') {
        var len = (deptField.value || '').length;
        deptField.setSelectionRange(len, len);
      }
    } catch (e) {
      // Don't let focus logic break the modal
      console.warn('focusDeptField failed:', e);
    }
  }, 50);
};



  // Clear previous entry values each time the modal is opened.
  // Kept simple and defensive to avoid "dark backdrop freeze" if anything is missing.
  window.resetMutualAidFields = function(keepDept) {
    try {
      var form = document.getElementById('mutualAidForm');
      if (!form) return;

      var deptInput = form.querySelector('input[name="ma_dept_name"]');
      var deptVal = deptInput ? deptInput.value : "";

      // Reset form controls back to defaults
      form.reset();

      // Restore dept (optional)
      if (keepDept && deptInput) deptInput.value = deptVal;

      // Clear hidden selections
      var appTypeId = document.getElementById('ma_app_type_id');
      if (appTypeId) appTypeId.value = '';

      var localId = document.getElementById('ma_local_command_id');
      if (localId) localId.value = '';

      // Clear visible fields that can sometimes survive form.reset() depending on browser
      var appLabel = document.getElementById('ma_apparatus_label');
      if (appLabel) appLabel.value = '';

      var ffInput = document.getElementById('ma_staffing');
      if (ffInput) ffInput.value = '';

      var offId = document.getElementById('ma_officer_id');
      if (offId) {
        offId.value = '';
        if (offId.dataset) {
          offId.dataset.baseRank = '';
          offId.dataset.digits   = '';
        }
      }
      var hiddenRank = document.getElementById('ma_officer_rank');
      if (hiddenRank) hiddenRank.value = '';

      // Remove highlights
      document.querySelectorAll('.ma-app-type-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.ma-local-staff-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.ma-officer-rank-btn').forEach(function (b) {
        b.classList.remove('active', 'btn-primary');
        b.classList.add('btn-outline-primary');
      });

      // Always start in apparatus mode on open
      if (window.setMutualAidMode) window.setMutualAidMode('apparatus');
    } catch (e) {
      console && console.error && console.error('resetMutualAidFields error', e);
    }
  };
// Local command staff buttons (tap-to-select)
document.querySelectorAll('.ma-local-staff-btn').forEach(function(btn){
  btn.addEventListener('click', function(){
    var id = btn.getAttribute('data-local-id') || '';
    var hid = document.getElementById('ma_local_command_id');
    if (hid) hid.value = id;

    // highlight selection
    document.querySelectorAll('.ma-local-staff-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
  });
});

  // Always focus the Mutual Aid Department field and start with a clean entry when opening the modal
  (function initMutualAidOpenBehavior() {
    var maModalEl = document.getElementById('modalMutualAid');
    if (!maModalEl) return;

    // Prevent double-binding if this script runs twice for any reason
    if (maModalEl.getAttribute('data-ma-initialized') === '1') return;
    maModalEl.setAttribute('data-ma-initialized', '1');

    maModalEl.addEventListener('show.bs.modal', function () {
      // Fresh open: clear old values
      resetMutualAidFields(false);
    });

    maModalEl.addEventListener('shown.bs.modal', function () {
      // On-screen: focus dept field
      focusDeptField();
    });
  })();


  try {
    var params = new URLSearchParams(window.location.search);
    if (params.get('open_mutual_aid') === '1') {
      var maModalEl = document.getElementById('modalMutualAid');
      if (maModalEl && window.bootstrap && bootstrap.Modal) {
        maModal = new bootstrap.Modal(maModalEl);
        maModal.show();

        // When the Mutual Aid modal is shown, focus the Department field
        maModalEl.addEventListener('shown.bs.modal', function () {
          focusDeptField();
        });

        // Pre-populate the Mutual Aid Department field from the query string, if present
        try {
          var deptFromParam = params.get('ma_dept');
          if (deptFromParam) {
            var formForDept = document.getElementById('mutualAidForm');
            if (formForDept) {
              var deptInputField = formForDept.querySelector('input[name="ma_dept_name"]');
              if (deptInputField && !deptInputField.value) {
                deptInputField.value = deptFromParam;
              }
            }
          }
        } catch (e) {
          // ignore prefill errors
        }


        // If we got an error back (duplicate or save failure), show it once
        if (params.get('ma_error')) {
          var err = params.get('ma_error');
          if (err === 'dupe') {
            window.alert("This Command Officer already exists for this incident.");
          } else if (err === 'rank') {
            window.alert("Please select an Officer Rank.");
          } else if (err === 'pick') {
            window.alert("Please select a local command staff member.");
          } else if (err === 'digits') {
            window.alert("Please append at least one digit to the Rank using the number buttons (example: Chief50).");
          } else {
            window.alert("Mutual Aid record could not be saved. Please try again.");
          }
          // Keep modal open for correction
          try {
            params.delete('ma_error');
            var newUrlErr = window.location.pathname + (params.toString() ? "?" + params.toString() : "");
            window.history.replaceState({}, "", newUrlErr);
          } catch(e) {}
        }

        // If we just successfully saved a record, ask whether to add another
        if (params.get('ma_success') === '1') {
          setTimeout(function () {
            var addAnother = window.confirm(
              "Command resource has been saved.\n\nClick OK to add another, or Cancel to return to the Command Board."
            );

            // Determine which mode was just saved (apparatus or officer),
            // defaulting to apparatus if we don't know.
            var savedMode = params.get('ma_mode_saved') || 'apparatus';

            if (addAnother) {
              // Record is already saved; clear form for a fresh entry
              var form = document.getElementById('mutualAidForm');
              if (form) {
                // Preserve department name, since they'll often add multiple units/officers
                var deptInput = form.querySelector('input[name="ma_dept_name"]');
                var savedDept = deptInput ? deptInput.value : "";

                form.reset();

                if (deptInput && savedDept !== "") {
                  deptInput.value = savedDept;
                }
              }

              // Restore the mode that was just used (apparatus or officer)
              var modeInput = document.getElementById('ma_mode');
              if (modeInput) {
                modeInput.value = savedMode;
              }
              setMutualAidRequiredForMode(savedMode);

              // Button visual state based on savedMode
              var modeButtons = document.querySelectorAll('.ma-mode-btn');
              modeButtons.forEach(function (btn) {
                var mode = btn.getAttribute('data-mode');
                btn.classList.remove('active', 'btn-primary');
                btn.classList.add('btn-outline-secondary');
                if (mode === savedMode) {
                  btn.classList.add('active', 'btn-primary');
                  btn.classList.remove('btn-outline-secondary');
                }
              });

              // Show the correct section (apparatus vs officer) based on savedMode
              var sections = document.querySelectorAll('.ma-section');
              sections.forEach(function (sec) {
                if (savedMode === 'apparatus') {
                  if (sec.classList.contains('ma-section-apparatus')) {
                    sec.classList.remove('d-none');
                  } else {
                    sec.classList.add('d-none');
                  }
                } else {
                  if (sec.classList.contains('ma-section-officer')) {
                    sec.classList.remove('d-none');
                  } else {
                    sec.classList.add('d-none');
                  }
                }
              });

              // After resetting for "add another", always focus the Mutual Aid Department field
              focusDeptField();

              // Modal stays open so they can add another

              // Clean the URL so this doesn't repeat on refresh
              try {
                params.delete('open_mutual_aid');
                params.delete('ma_success');
                params.delete('ma_dept');
                params.delete('ma_mode_saved');
                var newUrl =
                  window.location.pathname +
                  (params.toString() ? "?" + params.toString() : "");
                window.history.replaceState({}, "", newUrl);
              } catch (e) {
                // ignore history errors
              }
            } else {
              // User is done; record is saved, close the modal and go back to the Command Board
              try {
                var modalInstance = bootstrap.Modal.getOrCreateInstance(maModalEl);
                modalInstance.hide();
              } catch (e) {
                // As a fallback, try the older reference if it exists
                if (maModal && typeof maModal.hide === 'function') {
                  maModal.hide();
                }
              }

              try {
                var incidentIdFromUrl = params.get('incident_id');
                if (incidentIdFromUrl) {
                  // Go straight back to this incident's Command Board
                  window.location.href =
                    "command_board.php?incident_id=" + encodeURIComponent(incidentIdFromUrl);
                } else {
                  // Fallback: build a clean URL without the Mutual Aid flags
                  params.delete('open_mutual_aid');
                  params.delete('ma_success');
                  params.delete('ma_dept');
                  params.delete('ma_mode_saved');
                  var cleanUrl =
                    window.location.pathname +
                    (params.toString() ? "?" + params.toString() : "");
                  window.location.href = cleanUrl;
                }
              } catch (e) {
                // If navigation fails, we at least tried; do nothing further
              }
            }
          }, 300);
        }
      }
    }
  } catch (e) {
    // Fail silently if URLSearchParams or bootstrap not available
  }

  // =====================================================
// 2) Mode toggle (Apparatus vs Command Officer)
  // =====================================================
  var modeInput = document.getElementById('ma_mode');
  var modeBtns  = document.querySelectorAll('.ma-mode-btn');
  var sections  = document.querySelectorAll('.ma-section');

  modeBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var mode = this.getAttribute('data-mode') || 'apparatus';
      if (modeInput) {
        modeInput.value = mode;
      }
      setMutualAidRequiredForMode(mode);

      // Button visual state
      modeBtns.forEach(function (b) {
        b.classList.remove('active');
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline-secondary');
      });
      this.classList.add('active');
      this.classList.add('btn-primary');
      this.classList.remove('btn-outline-secondary');

      // Show/hide sections
      sections.forEach(function (sec) {
        sec.classList.add('d-none');
      });
      if (mode === 'apparatus') {
        var appSec = document.querySelector('.ma-section-apparatus');
        if (appSec) appSec.classList.remove('d-none');
      } else {
        var offSec = document.querySelector('.ma-section-officer');
        if (offSec) offSec.classList.remove('d-none');
      }

      // Whenever user switches modes, keep focus on the Mutual Aid Department field
      focusDeptField();
    });
  });

  // =====================================================
  // 3) Apparatus TYPE buttons (Engine, Truck, etc.)
  // =====================================================
  var appTypeButtons = document.querySelectorAll('.ma-app-type-btn');
  appTypeButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var appTypeId   = this.getAttribute('data-app-type-id') || '';
      var appTypeName = this.getAttribute('data-app-type-name') || '';

      var hiddenField = document.getElementById('ma_app_type_id');
      if (hiddenField) {
        hiddenField.value = appTypeId;
      }

      // Default Apparatus Label to something like "Engine "
      var labelInput = document.getElementById('ma_apparatus_label');
      if (labelInput && appTypeName) {
        labelInput.value = appTypeName + ' ';
        if (labelInput.setSelectionRange) {
          var len = labelInput.value.length;
          labelInput.setSelectionRange(len, len);
        }
        labelInput.focus();
      }

      // Visual selection
      appTypeButtons.forEach(function (b) {
        b.classList.remove('active');
      });
      this.classList.add('active');
    });
  });

  // =====================================================
  // 4) Apparatus ID keypad (0–9) for Apparatus Label
  // =====================================================
  (function () {
    var labelInput = document.getElementById('ma_apparatus_label');
    if (!labelInput) return;

    document.querySelectorAll('.ma-label-key-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var digit = this.getAttribute('data-digit') || '';
        if (!digit) return;

        labelInput.value = (labelInput.value || '') + digit;
        labelInput.focus();
        if (labelInput.setSelectionRange) {
          var len = labelInput.value.length;
          labelInput.setSelectionRange(len, len);
        }
      });
    });
  })();

  // =====================================================
  // 5) Firefighter count keypad (1–5) for ma_staffing
  // =====================================================
  (function () {
    var ffInput = document.getElementById('ma_staffing');
    if (!ffInput) return;

    document.querySelectorAll('.ma-ff-key-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var ff = parseInt(this.getAttribute('data-ff'), 10);
        if (isNaN(ff)) return;

        ffInput.value = ff;
        ffInput.focus();
        if (ffInput.setSelectionRange) {
          var len = ffInput.value.toString().length;
          ffInput.setSelectionRange(len, len);
        }
      });
    });
  })();

  // =====================================================
  // 6) Command Officer rank buttons + Officer ID keypad (Rank + digits)
  // =====================================================
  (function () {
    var hiddenRank     = document.getElementById('ma_officer_rank');
    var officerIdInput = document.getElementById('ma_officer_id');
    var rankButtons    = document.querySelectorAll('.ma-officer-rank-btn');
    var idButtons      = document.querySelectorAll('.ma-officer-id-key-btn');

    if (!officerIdInput) return;

    // Track base rank + digits so we never double-append or drift
    officerIdInput.dataset.baseRank = '';
    officerIdInput.dataset.digits   = '';

    function renderOfficerId() {
      var base = officerIdInput.dataset.baseRank || '';
      var digs = officerIdInput.dataset.digits   || '';
      officerIdInput.value = base + digs; // e.g., "Chief50" (no space)
      if (officerIdInput.setSelectionRange) {
        var len = officerIdInput.value.length;
        officerIdInput.setSelectionRange(len, len);
      }
    }

    // Rank selection
    if (hiddenRank && rankButtons.length) {
      rankButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();

          var rank = this.getAttribute('data-rank') || '';
          hiddenRank.value = rank;

          // Reset digits when rank changes
          officerIdInput.dataset.baseRank = rank;
          officerIdInput.dataset.digits   = '';
          renderOfficerId();
          officerIdInput.focus();

          // Visual selection
          rankButtons.forEach(function (b) {
            b.classList.remove('active', 'btn-primary');
            b.classList.add('btn-outline-primary');
          });

          this.classList.remove('btn-outline-primary');
          this.classList.add('btn-primary', 'active');
        });
      });
    }

    // Digit keypad
    if (idButtons.length) {
      idButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();

          var digit = this.getAttribute('data-digit') || '';
          if (!digit) return;

          if (!officerIdInput.dataset.baseRank) {
            alert('Please select an Officer Rank first.');
            return;
          }

          officerIdInput.dataset.digits = (officerIdInput.dataset.digits || '') + digit;
          renderOfficerId();
          officerIdInput.focus();
        });
      });
    }
  })();

  // =====================================================
  // 7) Final safety net: basic Mutual Aid form validation
  // =====================================================
  var maForm = document.getElementById('mutualAidForm');
  if (maForm) {
    maForm.addEventListener('submit', function (event) {
      var modeInput = document.getElementById('ma_mode');
      var mode = modeInput ? modeInput.value : 'apparatus';

      if (mode === 'apparatus') {
        var appTypeIdField = document.getElementById('ma_app_type_id');
        var appTypeId = appTypeIdField ? appTypeIdField.value : '';
        if (!appTypeId) {
          alert('Please select an Apparatus Type for the mutual aid unit.');
          event.preventDefault();
          return false;
        }
      } else if (mode === 'officer') {
        var hiddenRank = document.getElementById('ma_officer_rank');
        var officerId  = document.getElementById('ma_officer_id');

        if (!hiddenRank || !hiddenRank.value) {
          alert('Please select an Officer Rank.');
          event.preventDefault();
          return false;
        }

        // Require at least one digit appended (Officer ID must end in a number)
        var v = officerId ? (officerId.value || '').trim() : '';
        if (!/\d$/.test(v)) {
          alert('Please use the number buttons to append at least one digit to the Rank (example: Chief50).');
          event.preventDefault();
          return false;
        }
      }
      // Other required fields are enforced by HTML5 "required" attributes
    });
  }

  // Also ensure that whenever the Mutual Aid modal is opened manually
  // (e.g., by clicking the "Mutual Aid" button), we focus the Department field.
  (function () {
    var modalEl = document.getElementById('modalMutualAid');
    if (!modalEl) return;
    modalEl.addEventListener('shown.bs.modal', function(){
      // Always start with UI clean slate (server reload will repopulate if active)
      lastChecklistItems = [];
      renderChecklist([]);
      maydayDirty = false;

        focusDeptField();
    });
  })();

});
</script>
<!-- ========================================================= -->
<!-- CHANGE IC MODAL                                           -->
<!-- ========================================================= -->

<!-- ========================================================= -->
<!-- CHANGE IC MODAL                                           -->
<!-- ========================================================= -->
<div class="modal fade" id="modalChangeIC" tabindex="-1" aria-labelledby="changeICLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-light text-dark">
      <form method="post" action="command_board.php?incident_id=<?= (int)$incidentId ?>" id="formSetIC">
        <input type="hidden" name="set_ic_manual" value="1">
        <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">
        <input type="hidden" name="ic_display" id="ic_display" value="">
        <input type="hidden" name="ic_source" id="ic_source" value="local">

        <div class="modal-header">
          <h5 class="modal-title" id="changeICLabel">Set Incident Commander</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <div class="fw-bold fs-6">Current IC</div>
            <div class="fs-5">
              <?= e(!empty($incident['incident_commander_display']) ? $incident['incident_commander_display'] : 'Not set') ?>
            </div>
          </div>

          <div class="mb-3">
            <div class="fw-bold fs-6">Selected IC</div>
            <div class="fs-5" id="icSelectedText">None</div>
          </div>

          <div class="mb-3">
            <div class="fw-bold fs-5 mb-2">Local Command Officers</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($icLocalOfficers as $o): ?>
                <?php
                  $label = trim(($o['radio_designation'] ?? '') . ' ' . ($o['member_name'] ?? ''));
                  if ($label === '') $label = 'Officer #' . (int)$o['id'];
                ?>
                <button type="button"
                        class="btn btn-lg btn-outline-primary px-3 py-2 ic-pick"
                        onclick="setIC('<?= e($label) ?>','local', this)">
                  <?= e($label) ?>
                </button>
              <?php endforeach; ?>
              <?php if (empty($icLocalOfficers)): ?>
                <div class="text-muted">No local command staff on file.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <div class="fw-bold fs-5 mb-2">Mutual Aid Command Officers</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($icMutualAidOfficers as $o): ?>
                <?php
                  // Expected fields: display_name or officer_name; fall back to id
                  $label = trim(($o['display_name'] ?? '') ?: ($o['officer_name'] ?? '') ?: ($o['command_staff_display'] ?? ''));
                  if ($label === '') $label = 'Mutual Aid Officer #' . (int)($o['id'] ?? 0);
                ?>
                <button type="button"
                        class="btn btn-lg btn-outline-secondary px-3 py-2 ic-pick"
                        onclick="setIC('<?= e($label) ?>','mutual_aid', this)">
                  <?= e($label) ?>
                </button>
              <?php endforeach; ?>
              <?php if (empty($icMutualAidOfficers)): ?>
                <div class="text-muted">No mutual aid command staff for this incident.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="alert alert-info mb-0">
            Tip: Select an officer above, then press <strong>Save IC</strong>.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-lg" id="btnSaveIC" disabled>Save IC</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================= -->
<!-- SET SAFETY OFFICER MODAL                                  -->
<!-- ========================================================= -->
<div class="modal fade" id="modalSetSafetyOfficer" tabindex="-1" aria-labelledby="setSafetyLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content bg-light text-dark">
      <form method="post" action="command_board.php?incident_id=<?= (int)$incidentId ?>" id="formSetSafety">
        <input type="hidden" name="set_safety_officer" value="1">
        <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">
        <input type="hidden" name="so_display" id="so_display" value="">
        <input type="hidden" name="so_source" id="so_source" value="manual">
        <input type="hidden" name="so_local_id" id="so_local_id" value="">
        <input type="hidden" name="so_mutual_aid_id" id="so_mutual_aid_id" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="setSafetyLabel">Set Safety Officer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <div class="fw-bold fs-6">Current Safety Officer</div>
            <div class="fs-5">
              <?= e(!empty($incident['safety_officer_display']) ? $incident['safety_officer_display'] : 'Not set') ?>
            </div>
          </div>

          <div class="mb-3">
            <div class="fw-bold fs-6">Selected Safety Officer</div>
            <div class="fs-5" id="soSelectedText">None</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold fs-6" for="so_manual">Or type a name (not in the list)</label>
            <div class="d-flex gap-2">
              <input type="text" class="form-control form-control-lg" id="so_manual" placeholder="e.g., Capt. Smith">
              <button type="button" class="btn btn-danger cr-solid btn-lg" onclick="useSafetyTyped()">Use Typed Name</button>
            </div>
            <div class="form-text">Typing is optional — buttons are fastest on a tablet.</div>
          </div>

          <hr>

          <div class="mb-3">
            <div class="fw-bold fs-5 mb-2">Local Command Officers</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($icLocalOfficers as $o): ?>
                <?php
                  $label = trim(($o['radio_designation'] ?? '') . ' ' . ($o['member_name'] ?? ''));
                  if ($label === '') $label = 'Officer #' . (int)$o['id'];
                ?>
                <button type="button"
                        class="btn btn-lg btn-outline-danger px-3 py-2 so-pick"
                        onclick="setSafety('<?= e($label) ?>','local',<?= (int)$o['id'] ?>,0, this)">
                  <?= e($label) ?>
                </button>
              <?php endforeach; ?>
              <?php if (empty($icLocalOfficers)): ?>
                <div class="text-muted">No local command staff on file.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="mb-3">
            <div class="fw-bold fs-5 mb-2">Mutual Aid Command Officers</div>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($icMutualAidOfficers as $o): ?>
                <?php
                  $label = trim(($o['display_name'] ?? '') ?: ($o['officer_name'] ?? '') ?: ($o['command_staff_display'] ?? ''));
                  $oid = (int)($o['id'] ?? 0);
                  if ($label === '') $label = 'Mutual Aid Officer #' . $oid;
                ?>
                <button type="button"
                        class="btn btn-lg btn-outline-secondary px-3 py-2 so-pick"
                        onclick="setSafety('<?= e($label) ?>','mutual_aid',0,<?= $oid ?>, this)">
                  <?= e($label) ?>
                </button>
              <?php endforeach; ?>
              <?php if (empty($icMutualAidOfficers)): ?>
                <div class="text-muted">No mutual aid command staff for this incident.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="alert alert-info mb-0">
            Tip: Choose a Safety Officer (or type one) and press <strong>Save Safety</strong>.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-lg" id="btnSaveSafety" disabled>Save Safety</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function ensureModalOnBody(modalId){
    var el = document.getElementById(modalId);
    if (!el) return null;
    // If modal lives inside a tab-pane/container with overflow rules, it can be visually clipped.
    // Appending to <body> ensures Bootstrap's z-index/positioning behaves as expected.
    if (el.parentElement !== document.body) {
      document.body.appendChild(el);
    }
    return el;
  }


  // Fix: ensure key modals are appended to <body> to avoid backdrop-only "freeze" when opened inside tab panes
  ensureModalOnBody('modalMutualAid');
  ensureModalOnBody('modalAddIcs');
  ensureModalOnBody('modalSetSupervisor');
  ensureModalOnBody('modalChangeIC');
  ensureModalOnBody('modalSetSafetyOfficer');

(function(){
  function clearActive(selector){
    document.querySelectorAll(selector).forEach(function(btn){
      btn.classList.remove('active');
    });
  }

  window.setIC = function(label, source, btn){
    document.getElementById('ic_display').value = label || '';
    document.getElementById('ic_source').value  = source || 'local';
    document.getElementById('icSelectedText').textContent = label || 'None';
    document.getElementById('btnSaveIC').disabled = !(label && label.trim().length);
    clearActive('.ic-pick');
    if (btn) btn.classList.add('active');
  };

  window.setSafety = function(label, source, localId, maId, btn){
    document.getElementById('so_display').value = label || '';
    document.getElementById('so_source').value  = source || 'manual';
    document.getElementById('so_local_id').value = (source === 'local') ? (localId || '') : '';
    document.getElementById('so_mutual_aid_id').value = (source === 'mutual_aid') ? (maId || '') : '';
    document.getElementById('soSelectedText').textContent = label || 'None';
    document.getElementById('btnSaveSafety').disabled = !(label && label.trim().length);
    clearActive('.so-pick');
    if (btn) btn.classList.add('active');
    // clear manual field to reduce confusion
    var m = document.getElementById('so_manual');
    if (m) m.value = '';
  };

  window.useSafetyTyped = function(){
    var m = document.getElementById('so_manual');
    var val = (m && m.value) ? m.value.trim() : '';
    if (!val) return;
    clearActive('.so-pick');
    window.setSafety(val, 'manual', 0, 0, null);
  };

  // When modals open, clear selections (prevents stale clicks)
  var icModal = document.getElementById('modalChangeIC');
  if (icModal) {
    icModal.addEventListener('shown.bs.modal', function(){
      document.getElementById('ic_display').value = '';
      document.getElementById('ic_source').value = 'local';
      document.getElementById('icSelectedText').textContent = 'None';
      document.getElementById('btnSaveIC').disabled = true;
      clearActive('.ic-pick');
    });
  }

  var soModal = document.getElementById('modalSetSafetyOfficer');
  if (soModal) {
    soModal.addEventListener('shown.bs.modal', function(){
      document.getElementById('so_display').value = '';
      document.getElementById('so_source').value = 'manual';
      document.getElementById('so_local_id').value = '';
      document.getElementById('so_mutual_aid_id').value = '';
      document.getElementById('soSelectedText').textContent = 'None';
      document.getElementById('btnSaveSafety').disabled = true;
      clearActive('.so-pick');
      var m = document.getElementById('so_manual');
      if (m) m.value = '';
    });
  }


  // ------------------------------
  // ICS Elements UI helpers
  // ------------------------------
  window.openAddIcs = function(type){
    ensureModalOnBody('modalAddIcs');
    var t = (type || 'DIVISION').toUpperCase();
    document.getElementById('ics_element_type').value = t;
    document.getElementById('ics_type_badge').textContent = t;

    // Friendly defaults based on type
    var titleKind = (t === 'GROUP') ? 'Group' : 'Division';
    var titleEl = document.getElementById('ics_modal_title_kind');
    if (titleEl) titleEl.textContent = titleKind;

    var nameEl = document.getElementById('ics_element_name');
    if (nameEl){
      nameEl.value = '';
      nameEl.placeholder = (t === 'GROUP')
        ? 'e.g., Vent Group, RIT Group, Water Supply Group'
        : 'e.g., Division 2, Division Alpha, Roof Division';

    // Quick-pick buttons (minimize typing on tablet)
    var qp = document.getElementById('ics_quick_picks');
    if (qp){
      qp.innerHTML = '';
      var picks = (t === 'GROUP')
        ? ['Vent', 'RIT', 'Search', 'Water Supply', 'Medical', 'Exposure']
        : ['Div 1', 'Div 2', 'Div 3', 'Basement', 'Roof', 'Alpha', 'Charlie'];

      picks.forEach(function(label){
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-secondary cr-solid btn-sm';
        b.textContent = label;
        b.onclick = function(){
          var el = document.getElementById('ics_element_name');
          if (el){ el.value = label; el.focus(); }
        };
        qp.appendChild(b);
      });
    }

    }
    var m = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddIcs'));
    m.show();
    setTimeout(function(){ document.getElementById('ics_element_name').focus(); }, 250);
  };

  window.openSetSupervisor = function(elementId){
    ensureModalOnBody('modalSetSupervisor');
    document.getElementById('sup_element_id').value = elementId || 0;
    document.getElementById('sup_source').value = '';
    document.getElementById('sup_id').value = 0;
    document.getElementById('sup_display').value = '';
    clearActive('.sup-pick');
    var m = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSetSupervisor'));
    m.show();
  };

  window.pickSupervisor = function(label, source, id, btn){
    document.getElementById('sup_display').value = label || '';
    document.getElementById('sup_source').value  = source || '';
    document.getElementById('sup_id').value      = id || 0;
    clearActive('.sup-pick');
    if (btn) btn.classList.add('active');
  };

})();
</script>

<?php
// -------------------------------
// Command Resources: template data
// -------------------------------
$homeDeptId = isset($incident['dept_id']) ? (int)$incident['dept_id'] : 0;

// Home department name/designation for display (if available)
$homeDeptName = '';
$homeDeptDesignation = '';
$homeDeptStation = '';
if ($homeDeptId > 0) {
   if ($stmt = $conn->prepare("SELECT dept_name, designation, station_id FROM department WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $homeDeptId);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r && ($row = $r->fetch_assoc())) {
        $homeDeptName = (string)$row['dept_name'];
        $homeDeptDesignation = (string)($row['designation'] ?? '');
        $homeDeptStation = (string)($row['station_id'] ?? '');
    }
    $stmt->close();
}

}

// Local command staff templates
$crLocalStaff = [];
if ($homeDeptId > 0) {
    if ($stmt = $conn->prepare("SELECT id, role, rank_designation, person_name FROM dept_command_staff_template WHERE dept_id = ? AND is_active = 1 ORDER BY sort_order, id")) {
        $stmt->bind_param("i", $homeDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rank = trim((string)$row['rank_designation']);
            $name = trim((string)($row['person_name'] ?? ''));
            $display = trim($rank . ($name !== '' ? (' ' . $name) : ''));
            $crLocalStaff[] = [
                'role' => (string)$row['role'],
                'officer_display' => $display !== '' ? $display : $rank,
            ];
        }
        $stmt->close();
    }
}

// Mutual Aid departments linked to home dept
$crMaDepts = [];
if ($homeDeptId > 0) {
    $sql = "SELECT mad.mutual_dept_id, mad.sort_order,
                   m.department_name, m.designation, m.station_id
            FROM department_mutual_aid mad
            INNER JOIN mutual_aid_departments m ON m.id = mad.mutual_dept_id
            WHERE mad.home_dept_id = ? AND mad.is_active = 1
            ORDER BY mad.sort_order, m.department_name";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $homeDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $crMaDepts[] = [
                'mutual_dept_id' => (int)$row['mutual_dept_id'],
                'department_name' => (string)$row['department_name'],
                'designation' => (string)($row['designation'] ?? ''),
                'station_id' => (string)($row['station_id'] ?? ''),
            ];
        }
        $stmt->close();
    }
}

// Mutual Aid command staff templates (grouped by mutual_dept_id)
$crMaStaff = [];
if ($homeDeptId > 0) {
    $sql = "SELECT mutual_dept_id, officer_display
            FROM department_mutual_aid_command_staff_template
            WHERE home_dept_id = ? AND is_active = 1
            ORDER BY mutual_dept_id, sort_order, officer_display";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $homeDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $mid = (int)$row['mutual_dept_id'];
            if (!isset($crMaStaff[$mid])) $crMaStaff[$mid] = [];
            $crMaStaff[$mid][] = (string)$row['officer_display'];
        }
        $stmt->close();
    }
}

// Mutual Aid apparatus templates (grouped by mutual_dept_id)
$crMaApparatus = [];
if ($homeDeptId > 0) {
    $sql = "SELECT mutual_dept_id, apparatus_label, apparatus_type_id, staffing
            FROM department_mutual_aid_apparatus_template
            WHERE home_dept_id = ? AND is_active = 1
            ORDER BY mutual_dept_id, sort_order, apparatus_label";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $homeDeptId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $mid = (int)$row['mutual_dept_id'];
            if (!isset($crMaApparatus[$mid])) $crMaApparatus[$mid] = [];
            $crMaApparatus[$mid][] = [
                'label' => (string)$row['apparatus_label'],
                'apparatus_type_id' => (int)$row['apparatus_type_id'],
                'staffing' => (int)$row['staffing'],
            ];
        }
        $stmt->close();
    }
}
?>
<script>
window.CR_TEMPLATE_DATA = <?php echo json_encode([
  'home' => [
    'dept_id' => $homeDeptId,
    'department_name' => $homeDeptName,
    'designation' => $homeDeptDesignation,
    'station_id' => $homeDeptStation
  ],
  'local_staff' => $crLocalStaff,
  'ma_depts' => $crMaDepts,
  'ma_staff' => $crMaStaff,
  'ma_apparatus' => $crMaApparatus
], JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  // Bind ONCE
  if (window.__crBound) return;
  window.__crBound = true;

  const btn = document.getElementById('btnCommandResources');
  const modalEl = document.getElementById('commandResourcesModal');
  if (!btn || !modalEl) return;

  const alertEl = document.getElementById('crAlert');
  const listEl  = document.getElementById('crList');
  const saveBtn = document.getElementById('btnCrSave');
  const form    = document.getElementById('commandResourcesForm');

  // Incident id for API calls
  const incidentId = document.getElementById('cr_incident_id')?.value || '';

  // Open modal from the Command Board button
  btn.addEventListener('click', function () {
    try {
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
      if (alertEl) { alertEl.classList.add('d-none'); alertEl.textContent = ''; }
      // Always refresh current resources when opening
      loadList();
    } catch (e) {
      console.error('Command Resources open failed:', e);
    }
  });



  // -------------------------------
  // Template-driven button pickers
  // -------------------------------
  const tpl = window.CR_TEMPLATE_DATA || {};
  const home = tpl.home || {};
  let selectedMaDeptId = 0;
  let selectedMaAppTypeId = 0;
  let lastAutoAppKey = '';

  const elLocal = document.getElementById('crLocalStaffButtons');
  const elMaDept = document.getElementById('crMaDeptButtons');
  const elMaStaff = document.getElementById('crMaStaffButtons');
  const elMaDeptApp = document.getElementById('crMaDeptButtonsApp');
  const elMaApp = document.getElementById('crMaAppButtons');
  const staffWrap = document.getElementById('crStaffManualWrap');

  // Hide/show manual staff form based on main tab
  const tabStaffBtn = document.getElementById('crTabStaffBtn');
  const tabAppBtn = document.getElementById('crTabAppBtn');
  function syncManualVisibility() {
    if (!staffWrap) return;
    const appActive = tabAppBtn && tabAppBtn.classList.contains('active');
    staffWrap.style.display = appActive ? 'none' : '';
  }
  if (tabStaffBtn) tabStaffBtn.addEventListener('shown.bs.tab', syncManualVisibility);
  if (tabAppBtn) tabAppBtn.addEventListener('shown.bs.tab', syncManualVisibility);

  function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function setFormFields({deptName='', designation='', stationId='', officerDisplay='', role=''}) {
    if (!form) return;
    const f = (name) => form.querySelector(`[name="${name}"]`);
    if (f('ma_dept_name')) f('ma_dept_name').value = deptName;
    if (f('ma_designation')) f('ma_designation').value = designation;
if (f('officer_display')) f('officer_display').value = officerDisplay;
    if (role && f('role')) f('role').value = role;
  }

  async function saveCommandResourceQuick(payload) {
    clearError();
    try {
      await postJson('api/command_resources.php', payload);
      await loadList();
    } catch (err) {
      console.error(err);
      showError(err.message || 'Save failed');
    }
  }

  function renderLocalStaff() {
    if (!elLocal) return;
    const items = Array.isArray(tpl.local_staff) ? tpl.local_staff : [];
    if (!items.length) {
      elLocal.innerHTML = '<div class="small text-muted">No local command staff templates found.</div>';
      return;
    }
    elLocal.innerHTML = items.map((it, idx) => {
      const label = escapeHtml(it.officer_display || ('Local #' + (idx+1)));
      return `<button type="button" class="btn btn-primary cr-solid btn-lg cr-local" data-role="${escapeHtml(it.role||'Command')}" data-officer="${escapeHtml(it.officer_display||'')}">${label}</button>`;
    }).join('');
    elLocal.addEventListener('click', function(e){
      const b = e.target.closest('.cr-local'); if (!b) return;
      const officer = b.getAttribute('data-officer') || '';
      const role = b.getAttribute('data-role') || 'Command';
      // Save as text; dept fields are informational
      setFormFields({deptName: home.department_name || '', designation: home.designation || '', stationId: home.station_id || '', officerDisplay: officer, role: role});
      // NOTE: Do not auto-save on selection; allow user to edit then press Save.
      // (User will click the Save button below.)
      });
  }

  function renderMaDepts(targetEl, onPick) {
    if (!targetEl) return;
    const depts = Array.isArray(tpl.ma_depts) ? tpl.ma_depts : [];
    if (!depts.length) {
      targetEl.innerHTML = '<div class="small text-muted">No mutual aid departments linked yet.</div>';
      return;
    }
    targetEl.innerHTML = depts.map(d => {
      const label = escapeHtml(d.designation || d.department_name);
      return `<button type="button" class="btn btn-danger cr-solid btn-lg cr-madept" data-id="${d.mutual_dept_id}">${label}</button>`;
    }).join('');
    targetEl.addEventListener('click', function(e){
      const b = e.target.closest('.cr-madept'); if (!b) return;
      selectedMaDeptId = parseInt(b.getAttribute('data-id')||'0',10) || 0;
      // visual active
      targetEl.querySelectorAll('.cr-madept').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      if (typeof onPick === 'function') onPick(selectedMaDeptId);
    });
  }

  function getMaDeptInfo(mid){
    const depts = Array.isArray(tpl.ma_depts) ? tpl.ma_depts : [];
    return depts.find(d => parseInt(d.mutual_dept_id,10) === parseInt(mid,10)) || null;
  }

  function renderMaStaff(mid) {
    if (!elMaStaff) return;
    const staffMap = tpl.ma_staff || {};
    const arr = staffMap && mid && staffMap[mid] ? staffMap[mid] : [];
    if (!arr || !arr.length) {
      elMaStaff.innerHTML = '<div class="small text-muted">No officers found for that mutual aid department.</div>';
      return;
    }
    elMaStaff.innerHTML = arr.map(off => {
      const label = escapeHtml(off);
      return `<button type="button" class="btn btn-danger btn-lg cr-mastaff" data-officer="${label}">${label}</button>`;
    }).join('');
  }

  if (elMaStaff) {
    elMaStaff.addEventListener('click', function(e){
      const b = e.target.closest('.cr-mastaff'); if (!b) return;
      const officer = b.getAttribute('data-officer') || '';
      const info = getMaDeptInfo(selectedMaDeptId) || {};
            const roleSel = '';
setFormFields({deptName: info.department_name||'', designation: info.designation||'', stationId: info.station_id||'', officerDisplay: officer, role: ''});
      // NOTE: Do not auto-save on selection; allow user to edit then press Save.
      // (User will click the Save button below.)
      });
  }

  function renderMaApparatus(mid) {
    if (!elMaApp) return;
    const appMap = tpl.ma_apparatus || {};
    const arr = (appMap && mid && appMap[mid]) ? appMap[mid] : [];
    if (!Array.isArray(arr) || !arr.length) {
      elMaApp.innerHTML = '<div class="small text-muted">No apparatus templates found for that mutual aid department.</div>';
      return;
    }

    elMaApp.innerHTML = arr.map(function(a){
      const label = escapeHtml(a.label || a.apparatus_label || a.apparatus_id || '');
      const staff = parseInt(a.staffing || a.firefighter_count || 0, 10) || 0;
      const typeId = parseInt(a.apparatus_type_id || 0, 10) || 0;

      return '<button type="button" class="btn btn-success btn-lg cr-maapp me-2 mb-2"'
        + ' data-label="' + label.replace(/"/g,'&quot;') + '"'
        + ' data-type="' + String(typeId) + '"'
        + ' data-staff="' + String(staff) + '">'
        + label
        + (staff ? ' <span class="badge bg-dark ms-1">' + String(staff) + '</span>' : '')
        + '</button>';
    }).join('');
  }

  
  if (elMaApp) {
    elMaApp.addEventListener('click', function(e){
      const b = e.target.closest('.cr-maapp'); if (!b) return;
      clearError();

      const info  = getMaDeptInfo(selectedMaDeptId) || {};
      const label = b.getAttribute('data-label') || '';
      const staff = parseInt(b.getAttribute('data-staff') || '0', 10) || 0;
      const typeId = parseInt(b.getAttribute('data-type') || '0', 10) || 0;

      selectedMaAppTypeId = typeId;

      const deptEl = document.getElementById('cr_app_ma_dept');
      const unitEl = document.getElementById('cr_app_unit');
      const staffEl = document.getElementById('cr_app_staff');

      // Populate manual fields as confirmation
      if (deptEl) deptEl.value = (info.designation || info.department_name || '');
      if (unitEl) unitEl.value = label;
      if (staffEl) staffEl.value = String(staff > 0 ? staff : 4);

      // Selection only: populate fields, allow edits, then press Add Mutual Aid Apparatus.
      return;

});
  }

  const btnAddAppManual = document.getElementById('btnCrAddAppManual');
  if (btnAddAppManual) {
    btnAddAppManual.addEventListener('click', async function(){
      clearError();
      const dept = document.getElementById('cr_app_ma_dept')?.value || '';
      const unit = document.getElementById('cr_app_unit')?.value || '';
      const staff = parseInt(document.getElementById('cr_app_staff')?.value || '0', 10) || 0;
      if (!dept.trim() || !unit.trim()) {
        showError('Enter Mutual Aid Department and Unit ID.');
        return;
      }

      // Guard against fast double-click
      if (btnAddAppManual.dataset.busy === '1') return;
      btnAddAppManual.dataset.busy = '1';
      btnAddAppManual.disabled = true;
      try {
        const info = getMaDeptInfo(selectedMaDeptId) || {};
        const desig = String(info.designation || '').trim();
        const staffCount = (staff > 0 ? staff : 4);
        const officerDisplay = unit.trim() + ' (' + String(staffCount) + ' FF)';
        await postJson('api/command_resources.php', {
          action: 'add',
          incident_id: String(incidentId || ''),
          ma_dept_name: dept.trim(),
          dept_designation: desig,
          station_id: String(staffCount),
          officer_display: officerDisplay,
          role: 'APPARATUS'
        });

        // Clear fields after add to prevent duplicate on repeated taps
        const deptEl  = document.getElementById('cr_app_ma_dept');
        const unitEl  = document.getElementById('cr_app_unit');
        const staffEl = document.getElementById('cr_app_staff');
        if (deptEl)  deptEl.value = '';
        if (unitEl)  unitEl.value = '';
        if (staffEl) staffEl.value = '4';
        selectedMaAppTypeId = 0;

        await loadList();
      } catch (err) {
        console.error(err);
        showError(err.message || 'Add apparatus failed');
      } finally {
        btnAddAppManual.dataset.busy = '0';
        btnAddAppManual.disabled = false;
      }
    });
  }

  // Initial render
  renderLocalStaff();
  renderMaDepts(elMaDept, (mid)=>{ renderMaStaff(mid); });
  renderMaDepts(elMaDeptApp, (mid)=>{
    selectedMaAppTypeId = 0; // reset when dept changes

    // clear the manual fields so you don't save stale values
    const deptEl  = document.getElementById('cr_app_ma_dept');
    const unitEl  = document.getElementById('cr_app_unit');
    const staffEl = document.getElementById('cr_app_staff');
    if (deptEl)  deptEl.value = '';
    if (unitEl)  unitEl.value = '';
    if (staffEl) staffEl.value = '';

    renderMaApparatus(mid);
  });



  function showError(msg) {
    if (!alertEl) return;
    alertEl.textContent = msg;
    alertEl.classList.remove('d-none');
  }
  function clearError() {
    if (!alertEl) return;
    alertEl.textContent = '';
    alertEl.classList.add('d-none');
  }

  async function postJson(url, dataObj) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(dataObj)
    });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); }
    catch (e) { throw new Error('Server returned non-JSON: ' + text.slice(0, 200)); }
    if (!res.ok || json.ok === false) {
      throw new Error(json.error || ('HTTP ' + res.status));
    }
    return json;
  }

  async function loadList() {
    if (!listEl) return;
    const incidentId = document.getElementById('cr_incident_id')?.value || '';
    if (!incidentId) return;

    try {
      const res = await fetch('api/command_resources.php?incident_id=' + encodeURIComponent(incidentId));
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); } catch (e) { throw new Error('Non-JSON list response: ' + text.slice(0, 200)); }

      if (json.ok === false) throw new Error(json.error || 'Failed to load list');

      // Expect json.items = [{id, officer_display, dept_label}]
      const items = Array.isArray(json.items) ? json.items : [];
      if (!items.length) {
        listEl.innerHTML = '<div class="text-muted small">No command resources yet.</div>';
        return;
      }
      listEl.innerHTML = items.map(function (it) {
        const label = (it.officer_display || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const dept  = (it.dept_label || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        return '<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">' +
                 '<div><div class="fw-bold">' + label + '</div>' +
                 (dept ? '<div class="small text-muted">' + dept + '</div>' : '') +
                 '</div>' +
                 (it.id ? '<button type="button" class="btn btn-sm btn-light cr-solid text-danger cr-remove" data-id="' + it.id + '">Remove</button>' : '') +
               '</div>';
      }).join('');
    } catch (err) {
      console.error(err);
      showError(err.message || 'Failed to load command resources');
    }
  }

  // Delegate remove buttons if endpoint supports it
  document.addEventListener('click', async function (e) {
    const btnRm = e.target.closest('.cr-remove');
    if (!btnRm) return;
    e.preventDefault();
    const id = btnRm.getAttribute('data-id');
    if (!id) return;
    clearError();
    try {
      await postJson('api/command_resources.php', { action: 'delete', id: id });
      await loadList();
    } catch (err) {
      console.error(err);
      showError(err.message || 'Remove failed');
    }
  });

  function openModal() {
    if (typeof bootstrap === 'undefined') {
      showError('Bootstrap JS not loaded.');
      return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, keyboard: true });
    modal.show();
  }

  btn.addEventListener('click', function (e) {
    e.preventDefault();
    clearError();
    openModal();
  });

  modalEl.addEventListener('shown.bs.modal', function () {
    loadList();
    // focus first visible input (if any)
    if (!form) return;
    const first = form.querySelector('input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])');
    if (first && first.focus) setTimeout(function () { try { first.focus(); } catch(e){} }, 50);
  });

  if (saveBtn) {
    saveBtn.addEventListener('click', async function () {
      clearError();
      if (!form) { showError('Form not found.'); return; }

      const incidentId = document.getElementById('cr_incident_id')?.value || '';
      if (!incidentId) { showError('Missing incident id.'); return; }

      const data = Object.fromEntries(new FormData(form).entries());
      data.incident_id = incidentId;
      if (!('action' in data)) data.action = 'add';
      // defaults: Command Resources stages availability only; assignment details set later
      if (!('role' in data)) data.role = '';
      if (!('ma_station_id' in data)) data.ma_station_id = '';
      if (!('assignment_name' in data)) data.assignment_name = '';


      try {
        await postJson('api/command_resources.php', data);
        // clear manual fields after save
        const manual = form.querySelector('input[name="officer_display"]');
        if (manual) manual.value = '';
        await loadList();
      } catch (err) {
        console.error(err);
        showError(err.message || 'Save failed');
      }
    });
  }
});
</script>

<script>
(function(){
  const starsWrap = document.getElementById('alarmStars');
  const btnMayday = document.getElementById('btnMayday');
  const incidentIdEl = document.getElementById('cr_incident_id') || document.querySelector('input[name="incident_id"]') || document.querySelector('input#incident_id') || document.querySelector('input[name="incidentId"]');
  const incidentId = incidentIdEl ? (incidentIdEl.value || incidentIdEl.getAttribute('value') || '') : '';

  function paint(level){
    if (!starsWrap) return;
    starsWrap.querySelectorAll('.alarm-star').forEach(btn => {
      const n = parseInt(btn.getAttribute('data-level')||'0', 10);
      btn.classList.toggle('on', n <= level);
      btn.classList.toggle('off', n > level);
    });
    starsWrap.setAttribute('data-current-level', String(level));
  }

  async function setAlarm(level){
    if (!incidentId) return;
    const fd = new FormData();
    fd.append('action', 'set_alarm_level');
    fd.append('incident_id', incidentId);
    fd.append('alarm_level', String(level));

    const res = await fetch(window.location.href, { method: 'POST', body: fd });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch(e){ throw new Error('Non-JSON response: ' + text.slice(0,200)); }
    if (!json.ok) throw new Error(json.error || 'Alarm update failed');
    paint(parseInt(json.new_level,10) || level);
  }

  if (starsWrap){
    starsWrap.addEventListener('click', async (e) => {
      const btn = e.target.closest('.alarm-star');
      if (!btn) return;
      const level = parseInt(btn.getAttribute('data-level')||'0', 10);
      if (!level) return;

      // Optimistic UI
      paint(level);
      try {
        await setAlarm(level);
      } catch(err){
        console.error(err);
        alert('Could not update Alarm level. ' + err.message);
        // Revert to previous displayed level in DOM attribute
        const cur = parseInt(starsWrap.getAttribute('data-current-level')||'1', 10) || 1;
        paint(cur);
      }
    });
  }

 // if (btnMayday){
 //   btnMayday.addEventListener('click', () => {
//      alert('Mayday checklist coming next.'); // placeholder
//    });
//  }
})();
</script>



<div class="modal fade" id="modalCloseIncident" tabindex="-1" aria-labelledby="closeIncidentLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content bg-light text-dark">
      <form method="post" action="command_board.php?incident_id=<?= (int)$incidentId ?>">
        <input type="hidden" name="close_incident" value="1">
        <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
        <input type="hidden" name="incident_dept_fallback" value="<?= e($incident['DeptName'] ?? '') ?>">

        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="closeIncidentLabel">Close Incident</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="mb-2">
            This will mark the incident as <strong>Closed</strong> and remove it from the Active Incidents list.
          </p>
          <div class="alert alert-warning mb-0">
            Only close when command has confirmed the incident is terminated and accountability is complete.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">No</button>
          <button type="submit" class="btn btn-lg btn-danger">Yes, Close Incident</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="commandResourcesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Command Resources<?php if (!empty($homeDeptName)) { echo " - " . htmlspecialchars($homeDeptName); } ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div id="crAlert" class="alert alert-danger d-none"></div>

        <div class="mb-3">
          <ul class="nav nav-pills flex-wrap gap-2 gap-2" id="crMainTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active btn btn-outline-primary text-decoration-none" id="crTabStaffBtn" data-bs-toggle="pill" data-bs-target="#crTabStaff" type="button" role="tab">Command Staff</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link btn btn-outline-primary text-decoration-none" id="crTabAppBtn" data-bs-toggle="pill" data-bs-target="#crTabApp" type="button" role="tab">Mutual Aid Apparatus</button>
            </li>
          </ul>
          <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="crTabStaff" role="tabpanel" aria-labelledby="crTabStaffBtn">
              <div class="small text-muted mb-2">Tap a button to load into the fields, make any edits you want, then press Save.</div>

              <div class="mb-2">
                <div class="fw-bold mb-1">Local Department Command Staff</div>
                <div id="crLocalStaffButtons" class="d-flex flex-wrap gap-2"></div>
              </div>

              <hr class="my-3">

              <div class="mb-2">
                <div class="fw-bold mb-1">Mutual Aid Departments</div>
                <div id="crMaDeptButtons" class="d-flex flex-wrap gap-2"></div>
              </div>

              <div class="mb-2">
                <div class="fw-bold mb-1">Mutual Aid Command Staff</div>
                <div id="crMaStaffButtons" class="d-flex flex-wrap gap-2"></div>
                <div id="crMaStaffHint" class="small text-muted mt-1">Select a Mutual Aid Department above to load its officers.</div>
              </div>

              <hr class="my-3">
              <div class="fw-bold mb-2">Manual Add (Command Staff)</div>
            </div>

            <div class="tab-pane fade" id="crTabApp" role="tabpanel" aria-labelledby="crTabAppBtn">
              <div class="small text-muted mb-2">Tap a unit to add it to this incident as Mutual Aid. Manual entry is below if needed.</div>

              <div class="mb-2">
                <div class="fw-bold mb-1">Mutual Aid Departments</div>
                <div id="crMaDeptButtonsApp" class="d-flex flex-wrap gap-2"></div>
              </div>

              <div class="mb-2">
                <div class="fw-bold mb-1">Mutual Aid Apparatus</div>
                <div id="crMaAppButtons" class="d-flex flex-wrap gap-2"></div>
                <div id="crMaAppHint" class="small text-muted mt-1">Select a Mutual Aid Department above to load its units.</div>
              </div>

              <hr class="my-3">
              <div class="fw-bold mb-2">Manual Add (Additional Mutual Aid)</div>
              <div class="row g-2 align-items-end">
                <div class="col-md-6">
                  <label class="form-label">Mutual Aid Department</label>
                  <input type="text" class="form-control" id="cr_app_ma_dept" placeholder="Crozet VFD">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Unit ID</label>
                  <input type="text" class="form-control" id="cr_app_unit" placeholder="Eng 56">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Staffing</label>
                  <input type="number" min="0" class="form-control" id="cr_app_staff" value="4">
                </div>
                <div class="col-12">
                  <button type="button" class="btn btn-success w-100" id="btnCrAddAppManual">Add Mutual Aid Apparatus</button>
                </div>
              </div>

              <div class="small text-muted mt-2">Note: your own department apparatus are added on the Add Incident screen.</div>
            </div>
          </div>
        </div>



        <div id="crStaffManualWrap">
        <form id="commandResourcesForm">
          <input type="hidden" name="incident_id" id="cr_incident_id" value="<?php echo htmlspecialchars($incidentId ?? ''); ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Mutual Aid Department Name</label>
              <input type="text" class="form-control" name="ma_dept_name" placeholder="Elmont Volunteer Fire Department">
            </div>
            <div class="col-md-3">
              <label class="form-label">Designation</label>
              <input type="text" class="form-control" name="ma_designation" placeholder="EVFD">
            </div>
<div class="col-md-6">
              <label class="form-label">Officer Display</label>
              <input type="text" class="form-control" name="officer_display" placeholder="Engine 52 Alpha or Captain Smith">
              <div class="form-text">Allow either unit-based or name-based identifiers.</div>
            </div>
</div>

          <hr class="my-3">

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" id="btnCrSave">Save</button>
            <button type="button" class="btn btn-secondary cr-solid" data-bs-dismiss="modal">Close</button>
          </div>
        </form>
        </div>

        <hr class="my-3">

        <h6 class="mb-2">Current Command Resources</h6>
        <div id="crList" class="small text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>

  
<?php
// --------------------------------------------------
// MAYDAY helper data (touch-friendly commander buttons)
// --------------------------------------------------
$maydayCommandStaff = [];
if (!empty($incidentId)) {
  if ($stmt = $conn->prepare("
      SELECT role, officer_display
      FROM incident_command_resources
      WHERE incident_id = ?
        AND (role IS NULL OR role <> 'APPARATUS')
        AND officer_display IS NOT NULL
        AND officer_display <> ''
      ORDER BY
        CASE role
          WHEN 'IC' THEN 1
          WHEN 'Operations' THEN 2
          WHEN 'Safety' THEN 3
          WHEN 'Accountability' THEN 4
          WHEN 'PIO' THEN 5
          WHEN 'Liaison' THEN 6
          ELSE 99
        END,
        officer_display
  ")) {
    $stmt->bind_param("i", $incidentId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($r && ($row = $r->fetch_assoc())) {
      $maydayCommandStaff[] = $row;
    }
    $stmt->close();
  }
}
?>

<!-- =========================
     MAYDAY Modal
     ========================= -->
<div class="modal fade" id="maydayModal" tabindex="-1" aria-labelledby="maydayModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <div class="d-flex flex-column">
          <h5 class="modal-title" id="maydayModalLabel">MAYDAY</h5>
          <div class="small">
            <span id="maydayStatusBadge" class="badge bg-light text-danger">INACTIVE</span>
            <span class="ms-2">Elapsed: <strong id="maydayElapsed">00:00</strong></span>
            <span class="ms-2">TAC: <strong id="maydayTacLabel">—</strong></span>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="maydayIncidentId" value="<?= (int)$incidentId ?>">

        <!-- Commander -->
        <div class="section-label mb-2">MAYDAY Commander</div>
        <div class="row g-2 mb-2" id="maydayCommanderButtons">
          <?php if (!empty($maydayCommandStaff)): ?>
            <?php foreach ($maydayCommandStaff as $cs): ?>
              <div class="col-6 col-lg-4">
                <button type="button"
                        class="btn btn-outline-dark w-100 py-3 mayday-commander-btn"
                        data-display="<?= e($cs['officer_display']) ?>">
                  <div class="fw-bold"><?= e($cs['officer_display']) ?></div>
                  <?php if (!empty($cs['role'])): ?>
                    <div style="font-size:.85rem; opacity:.8;"><?= e($cs['role']) ?></div>
                  <?php endif; ?>
                </button>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-muted small">No incident command staff found. You can still type a name below.</div>
            </div>
          <?php endif; ?>
        </div>

        <input type="text" class="form-control form-control-lg mb-3"
               id="maydayCommanderDisplay"
               placeholder="Commander (tap above or type, e.g., Captain Smith)">

        <hr class="my-2">

        <!-- TAC selection (match existing +/- TAC UI) -->
        <div class="section-label mb-1">MAYDAY TAC</div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <button class="btn btn-secondary cr-solid btn-tac-inc"
                  type="button"
                  data-target="maydayTacInput"
                  data-dir="-1">&minus;</button>

          <input type="number"
                 id="maydayTacInput"
                 class="form-control form-control-lg tac-number-input"
                 value="0"
                 min="0"
                 max="30">

          <button class="btn btn-secondary cr-solid btn-tac-inc"
                  type="button"
                  data-target="maydayTacInput"
                  data-dir="1">+</button>

          <button type="button" class="btn btn-primary btn-lg ms-2" id="btnMaydayApplyTac">
            Set TAC
          </button>
        </div>
        <div class="tac-caption mb-3">
          Tip: Set the TAC number used for the MAYDAY channel (e.g., TAC 8). This links to your TAC list for the incident.
        </div>

        <!-- Hidden select populated from API (kept for compatibility + saving tac_channel_id) -->
        <select id="maydayTacSelect" class="form-select d-none" aria-hidden="true"></select>

        <hr class="my-2">

        <!-- WHO/WHAT/WHERE/AIR/NEEDS -->
       <hr class="my-2">

<!-- FIREFIGHTERS (event-specific list, NOT a department roster) -->
<div class="section-label mb-2 d-flex align-items-center justify-content-between">
  <span>Firefighters (this MAYDAY)</span>
  <button type="button" class="btn btn-outline-primary btn-sm" id="btnMaydayAddFF">+ Add Firefighter</button>
</div>

<input type="hidden" id="maydaySelectedFfId" value="">

<div id="maydayFirefighterChips" class="d-flex flex-wrap gap-2 mb-3">
  <div class="text-muted">Loading…</div>
</div>

<!-- SELECTED FIREFIGHTER -->
<div class="section-label mb-2">Selected Firefighter</div>

<div class="row g-3 mb-2">
  <div class="col-md-6">
    <label class="form-label fw-bold">NAME</label>
    <input type="text" class="form-control" id="maydayFfName" placeholder="Type FF name (e.g., Smith, E52 Alpha)">
  </div>
  <div class="col-md-6">
    <label class="form-label fw-bold">STATUS</label>
    <div id="maydayStatusButtons" class="d-flex flex-wrap gap-2">
      <div class="text-muted">Loading…</div>
    </div>
  </div>
</div>

<!-- WHO/WHAT/WHERE/AIR/NEEDS (per firefighter) -->
            <div class="section-label mb-2">WHO / WHAT / WHERE / AIR / NEEDS</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">WHO</label>
                <input type="text" class="form-control" id="maydayWho" placeholder="Who is in trouble? (unit/name)">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">WHAT</label>
                <input type="text" class="form-control" id="maydayWhat" placeholder="What happened? (lost, trapped, collapse, etc.)">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">WHERE</label>
                <input type="text" class="form-control" id="maydayWhere" placeholder="Where are they? Floor/side/landmark">
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">AIR</label>
                <input type="text" class="form-control" id="maydayAir" placeholder="Air / PASS / ESK">
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">NEEDS</label>
                <input type="text" class="form-control" id="maydayNeeds" placeholder="What do they need?">
              </div>
            </div>

            <div class="mt-3">
              <button type="button" class="btn btn-success" id="btnMaydaySaveFf">Save Firefighter</button>
            </div>
        <hr class="my-3">

        <!-- Checklist -->
        <div class="section-label mb-2">Checklist</div>
        <div id="maydayChecklist" class="mayday-checklist">
          <div class="text-muted">Loading…</div>
        </div>

        <hr class="my-3">
        <hr class="my-3">

        <div class="section-label mb-2">Add Note</div>
        <textarea class="form-control" id="maydayNoteText" rows="3" placeholder="Short note / update"></textarea>

      </div>

      <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-success btn-lg" id="btnMaydayStart">Start / Activate</button>
          <button type="button" class="btn btn-danger btn-lg fw-bold" id="btnMaydayClear" style="font-size:1.15rem;">CLEAR MAYDAY</button>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary btn-lg" id="btnMaydaySaveNote">Save Note</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const btnOpen = document.getElementById('btnMayday');
  const modalEl = document.getElementById('maydayModal');
  if (!btnOpen || !modalEl) return;

  const maydayModal = bootstrap.Modal.getOrCreateInstance(modalEl, {
    backdrop: 'static',
    keyboard: false,
    focus: true
  });

 // const incidentId = document.getElementById('maydayIncidentId')?.value || '';
 function getIncidentId(){
  // 1) hidden input in modal
  const fromHidden = parseInt(document.getElementById('maydayIncidentId')?.value || '0', 10);
  if (fromHidden > 0) return fromHidden;

  // 2) URL query string (command_board.php?incident_id=41)
  const url = new URL(window.location.href);
  const fromUrl = parseInt(url.searchParams.get('incident_id') || '0', 10);
  if (fromUrl > 0) return fromUrl;

  return 0;
}

const incidentId = getIncidentId();


  // Header bits
  const statusBadge = document.getElementById('maydayStatusBadge');
  const elapsedEl = document.getElementById('maydayElapsed');
  const tacLabelEl = document.getElementById('maydayTacLabel');

  // Firefighter UI
  const chipsEl = document.getElementById('maydayFirefighterChips');
  const selectedFfIdEl = document.getElementById('maydaySelectedFfId');
  const ffNameEl = document.getElementById('maydayFfName');
  const statusBtnsEl = document.getElementById('maydayStatusButtons');

  const checklistEl = document.getElementById('maydayChecklist');

  // Per-FF fields (reusing your existing IDs)
  const whoEl = document.getElementById('maydayWho');
  const whatEl = document.getElementById('maydayWhat');
  const whereEl = document.getElementById('maydayWhere');
  const airEl = document.getElementById('maydayAir');
  const needsEl = document.getElementById('maydayNeeds');

  // Buttons
  const btnAddFF = document.getElementById('btnMaydayAddFF');
  const btnSaveFf = document.getElementById('btnMaydaySaveFf');

  // Existing buttons (already in your modal)
  const btnStart = document.getElementById('btnMaydayStart');
  const btnClear = document.getElementById('btnMaydayClear');

  // Extra Mayday controls (Commander / TAC / Notes)
  const commanderInputEl = document.getElementById('maydayCommanderDisplay');
  const commanderButtonsEl = document.getElementById('maydayCommanderButtons');
  const tacInputEl = document.getElementById('maydayTacInput');
  const btnApplyTac = document.getElementById('btnMaydayApplyTac');
  const noteTextEl = document.getElementById('maydayNoteText');
  const btnSaveNote = document.getElementById('btnMaydaySaveNote');

  function pickTacIdFromNumber(n){
    const tacs = (lastData && lastData.tac_channels) ? lastData.tac_channels : [];
    const nn = String(n|0);
    for (const t of tacs){
      const id = t.id ?? t.ID ?? null;
      const label = String(t.label ?? t.ChannelLabel ?? '');
      // match "TAC 8" / "Tac8" / "8"
      const nums = label.match(/\d+/g) || [];
      if (nums.includes(nn)) return id;
    }
    return null;
  }

  async function ensureMaydayActive(){
    if (lastData && lastData.mayday) return lastData.mayday;
    // Auto-start Mayday if user tries to set IC/TAC/Note before starting
    const res = await apiPost('api/mayday_start.php', { incident_id: incidentId });
    if (!res || !res.ok){
      alert((res && res.error) ? res.error : 'Unable to start Mayday.');
      return null;
    }
    await loadMayday(true);
    return (lastData && lastData.mayday) ? lastData.mayday : (res.mayday ?? null);
  }

  async function updateMaydayMeta(patch){
    const mayday = await ensureMaydayActive();
    if (!mayday) return false;
    const payload = Object.assign({ incident_id: incidentId }, patch || {});
    const res = await apiPost('api/mayday_update.php', payload);
    if (!res || !res.ok){
      alert((res && res.error) ? res.error : 'Update failed.');
      return false;
    }
    await loadMayday(true);
    return true;
  }

  async function addMaydayNote(message){
    const mayday = await ensureMaydayActive();
    if (!mayday) return false;
    const res = await apiPost('api/mayday_log_add.php', {
      incident_id: incidentId,
      event_type: 'NOTE',
      message: message
    });
    if (!res || !res.ok){
      alert((res && res.error) ? res.error : 'Unable to save note.');
      return false;
    }
    await loadMayday(true);
    return true;
  }


  // State
  let lastData = null;

  function pad2(n){ return (n<10?'0':'')+n; }
  function formatElapsed(seconds){
    seconds = Math.max(0, seconds|0);
    const mm = Math.floor(seconds/60);
    const ss = seconds%60;
    return pad2(mm)+":"+pad2(ss);
  }

  async function apiGet(url){
    const r = await fetch(url, { credentials:'same-origin' });
    return await r.json();
  }
  async function apiPost(url, bodyObj){
    const fd = new FormData();
    Object.entries(bodyObj).forEach(([k,v]) => fd.append(k, v));
    const r = await fetch(url, { method:'POST', body: fd, credentials:'same-origin' });
    return await r.json();
  }

  function setSelectedFf(id){
    selectedFfIdEl.value = id ? String(id) : '';
    renderSelected();
  }

  function renderHeader(){
    if (!lastData) return;
    const mayday = lastData.mayday || null;
    if (!mayday){
      statusBadge.textContent = 'INACTIVE';
      statusBadge.className = 'badge bg-light text-danger';
      elapsedEl.textContent = '00:00';
      tacLabelEl.textContent = '—';
      return;
    }
    statusBadge.textContent = (mayday.status || 'ACTIVE').toUpperCase();
    statusBadge.className = 'badge bg-light text-danger';

    // elapsed (client-side)
    const started = Date.parse((mayday.started_at || '').replace(' ', 'T'));
    if (!isNaN(started)){
      const now = Date.now();
      const sec = Math.floor((now - started)/1000);
      elapsedEl.textContent = formatElapsed(sec);
    } else {
      elapsedEl.textContent = '—';
    }

    // Commander + TAC (derived from mayday + tac_channels list)
    try{
      if (commanderInputEl){
        const cmd = (mayday.mayday_commander_display ?? mayday.mayday_commander ?? mayday.mayday_commander_name ?? '');
        commanderInputEl.value = String(cmd || '');
      }
      const tacId = (mayday.tac_channel_id ?? mayday.tacChannelId ?? null);
      const tacs = (lastData && lastData.tac_channels) ? lastData.tac_channels : [];
      let tacLabel = '—';
      let tacNum = 0;
      if (tacId){
        const found = tacs.find(t => String(t.id ?? t.ID ?? '') === String(tacId));
        const lbl = String((found && (found.label ?? found.ChannelLabel)) || '');
        tacLabel = lbl || ('TAC #' + tacId);
        const m = tacLabel.match(/\d+/);
        if (m) tacNum = parseInt(m[0], 10) || 0;
      }
      if (tacLabelEl) tacLabelEl.textContent = tacLabel;
      if (tacInputEl && tacNum) tacInputEl.value = String(tacNum);
    } catch(e){ /* ignore UI derivation errors */ }

  }

  function renderChips(){
    const ffs = (lastData && lastData.firefighters) ? lastData.firefighters : [];
    if (!chipsEl) return;

    if (!ffs.length){
      chipsEl.innerHTML = `<div class="text-muted">No firefighters yet.</div>`;
      return;
    }

    const selected = parseInt(selectedFfIdEl.value || '0', 10);

    chipsEl.innerHTML = ffs.map(ff => {
      const isSel = (ff.id === selected);
      const cls = ff.status_color_class ? ff.status_color_class : 'btn-secondary';
      const base = isSel ? 'btn' : 'btn btn-outline-secondary';
      // Use status color for the *selected* chip; outline for others
      const chipClass = isSel ? `btn ${cls}` : `btn btn-outline-secondary`;
      const label = (ff.name && ff.name.trim()) ? ff.name : 'Unknown';
      const status = ff.status_label ? ff.status_label : '';
      return `
        <button type="button"
          class="${chipClass}"
          data-ff-id="${ff.id}"
          style="min-width: 140px; text-align:left;">
          <div class="fw-bold">${escapeHtml(label)}</div>
          <div class="small">${escapeHtml(status)}</div>
        </button>
      `;
    }).join('');

    // click handlers
    chipsEl.querySelectorAll('button[data-ff-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        setSelectedFf(parseInt(btn.getAttribute('data-ff-id'), 10));
      });
    });
  }

  function renderStatusButtons(){
    const statuses = (lastData && lastData.statuses) ? lastData.statuses : [];
    const selectedId = parseInt(selectedFfIdEl.value || '0', 10);
    const ffs = (lastData && lastData.firefighters) ? lastData.firefighters : [];
    const ff = ffs.find(x => x.id === selectedId);

    if (!statusBtnsEl) return;
    if (!selectedId || !ff){
      statusBtnsEl.innerHTML = `<div class="text-muted">Select a firefighter.</div>`;
      return;
    }

    statusBtnsEl.innerHTML = statuses.map(st => {
      const cls = st.color_class || 'btn-secondary';
      const isActive = (parseInt(ff.status_id, 10) === parseInt(st.id, 10));
      const btnClass = isActive ? `btn ${cls}` : `btn btn-outline-secondary`;
      return `<button type="button" class="${btnClass}" data-status-id="${st.id}">${escapeHtml(st.label)}</button>`;
    }).join('');

    statusBtnsEl.querySelectorAll('button[data-status-id]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const statusId = parseInt(btn.getAttribute('data-status-id'), 10);
        const res = await apiPost('api/mayday_firefighter_set_status.php', {
          incident_id: incidentId,
          firefighter_id: selectedId,
          status_id: statusId
        });
        if (res && res.ok) await loadMayday(true);
        else alert(res && res.error ? res.error : 'Status update failed');
      });
    });
  }

  function renderSelected(){
    const selectedId = parseInt(selectedFfIdEl.value || '0', 10);
    const ffs = (lastData && lastData.firefighters) ? lastData.firefighters : [];
    const ff = ffs.find(x => x.id === selectedId);

    if (!ff){
      ffNameEl.value = '';
      whoEl.value = '';
      whatEl.value = '';
      whereEl.value = '';
      airEl.value = '';
      needsEl.value = '';
      renderStatusButtons();
      return;
    }

    ffNameEl.value = ff.name || '';
    whoEl.value = ff.who_text || '';
    whatEl.value = ff.what_text || '';
    whereEl.value = ff.where_text || '';
    airEl.value = ff.air_text || '';
    needsEl.value = ff.needs_text || '';

    renderStatusButtons();
  }

  function escapeHtml(s){
    return String(s ?? '')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function renderChecklist(){
    if (!checklistEl) return;
   // const items = (lastData && lastData.checklist) ? lastData.checklist : [];
      const items = (lastData && lastData.checklist_items) ? lastData.checklist_items : [];

    if (!items.length){
      checklistEl.innerHTML = `<div class="text-muted">No checklist items.</div>`;
      return;
    }
    checklistEl.innerHTML = items.map(it => {
      const label = escapeHtml(it.label || '');
      const done = it.done ? '✅ ' : '';
      return `<div class="mayday-check-item">${done}${label}</div>`;
    }).join('');
  }

  //async function loadMayday(force){
  // if (!incidentId){
   //     chipsEl.innerHTML = `<div class="text-danger">No incident_id found — Mayday cannot load.</div>`;
   //     statusBtnsEl.innerHTML = `<div class="text-danger">No incident_id found — Mayday cannot load.</div>`;
  //      return;
 //   }
async function loadMayday(force){
  try {
    const data = await apiGet(`api/mayday_get.php?incident_id=${encodeURIComponent(incidentId)}`);
    // ... existing logic ...
  } catch (err) {
    console.error('Mayday load failed:', err);
    chipsEl.innerHTML = `<div class="text-danger">Mayday failed to load.</div>`;
    statusBtnsEl.innerHTML = `<div class="text-danger">Mayday failed to load.</div>`;
  }
}


    const data = await apiGet(`api/mayday_get.php?incident_id=${encodeURIComponent(incidentId)}`);
    lastData = data;

    renderHeader();
    renderChecklist();

    // Choose a default selected FF if none set
    const ffs = (data && data.firefighters) ? data.firefighters : [];
    if (!selectedFfIdEl.value && ffs.length){
      selectedFfIdEl.value = String(ffs[0].id);
    }

    renderChips();
    renderSelected();
  }

  // Open modal + load
  btnOpen.addEventListener('click', (e) => {
    e.preventDefault();
    maydayModal.show();
    loadMayday(true);
  })
// Always load when the modal is actually shown (bulletproof)
modalEl.addEventListener('shown.bs.modal', () => {
  loadMayday(true);
});

  // --------------------------------------------------
  // Mayday modal event delegation (survives re-renders)
  // --------------------------------------------------
  modalEl.addEventListener('click', async (e) => {
    // Commander quick-pick buttons
    const cmdBtn = e.target.closest('.mayday-commander-btn');
    if (cmdBtn){
      const display = cmdBtn.getAttribute('data-display') || cmdBtn.textContent.trim();
      if (commanderInputEl) commanderInputEl.value = display;
      await updateMaydayMeta({ mayday_commander_display: display });
      return;
    }

    // TAC +/- buttons
    const incBtn = e.target.closest('.btn-tac-inc');
    if (incBtn){
      const targetId = incBtn.getAttribute('data-target');
      const dir = parseInt(incBtn.getAttribute('data-dir') || '0', 10) || 0;
      const input = document.getElementById(targetId);
      if (input){
        const cur = parseInt(input.value || '0', 10) || 0;
        let next = cur + dir;
        if (typeof input.min !== 'undefined' && input.min !== '') next = Math.max(next, parseInt(input.min, 10) || next);
        if (typeof input.max !== 'undefined' && input.max !== '') next = Math.min(next, parseInt(input.max, 10) || next);
        input.value = String(next);
      }
      return;
    }

    // Apply TAC
    const tacBtn = e.target.closest('#btnMaydayApplyTac');
    if (tacBtn){
      const n = parseInt((tacInputEl && tacInputEl.value) ? tacInputEl.value : '0', 10) || 0;
      const tacId = pickTacIdFromNumber(n);
      if (!tacId){
        alert('No TAC channel matched that number. Check your TAC list for this incident.');
        return;
      }
      await updateMaydayMeta({ tac_channel_id: tacId });
      return;
    }

    // Start Mayday
    const startBtn = e.target.closest('#btnMaydayStart');
    if (startBtn){
      const res = await apiPost('api/mayday_start.php', { incident_id: incidentId });
      if (!res || !res.ok){
        alert((res && res.error) ? res.error : 'Unable to start Mayday.');
      } else {
        await loadMayday(true);
      }
      return;
    }

    // Clear Mayday
    const clearBtn = e.target.closest('#btnMaydayClear');
    if (clearBtn){
      const ok = confirm('Clear/End the active Mayday?');
      if (!ok) return;
      const res = await apiPost('api/mayday_clear.php', { incident_id: incidentId });
      if (!res || !res.ok){
        alert((res && res.error) ? res.error : 'Unable to clear Mayday.');
      } else {
        await loadMayday(true);
      }
      return;
    }

    // Save Note
    const noteBtn = e.target.closest('#btnMaydaySaveNote');
    if (noteBtn){
      const msg = (noteTextEl && noteTextEl.value) ? noteTextEl.value.trim() : '';
      if (!msg){
        alert('Enter a note first.');
        return;
      }
      const ok = await addMaydayNote(msg);
      if (ok && noteTextEl) noteTextEl.value = '';
      return;
    }
  });

;

  // Add firefighter
  if (btnAddFF){
    btnAddFF.addEventListener('click', async () => {
      const res = await apiPost('api/mayday_firefighter_add.php', { incident_id: incidentId });
      if (res && res.ok){
        selectedFfIdEl.value = String(res.firefighter_id || '');
        await loadMayday(true);
      } else {
        alert(res && res.error ? res.error : 'Add firefighter failed');
      }
    });
  }

  // Save firefighter
  if (btnSaveFf){
    btnSaveFf.addEventListener('click', async () => {
      const selectedId = parseInt(selectedFfIdEl.value || '0', 10);
      if (!selectedId){
        alert('Select a firefighter first.');
        return;
      }
      const res = await apiPost('api/mayday_firefighter_update.php', {
        incident_id: incidentId,
        firefighter_id: selectedId,
        name: ffNameEl.value || '',
        who_text: whoEl.value || '',
        what_text: whatEl.value || '',
        where_text: whereEl.value || '',
        air_text: airEl.value || '',
        needs_text: needsEl.value || ''
      });
      if (res && res.ok) await loadMayday(true);
      else alert(res && res.error ? res.error : 'Save failed');
    });
  }

  // Optional: refresh elapsed timer every second while modal is open
  setInterval(() => {
    renderHeader();
  }, 1000);

})();
</script>


</body>
</html>