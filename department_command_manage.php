<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db_connect.php"; // must define $conn (mysqli)
session_start();

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'success';

// --------------------------------------------------
// Load all active departments for dropdown
// --------------------------------------------------
$departments = [];
$result = $conn->query("SELECT id, dept_name, dept_short_name FROM department WHERE is_active = 1 ORDER BY dept_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    $result->free();
}

// Determine current department selection
$currentDeptId = 0;
if (isset($_GET['dept_id'])) {
    $currentDeptId = (int)$_GET['dept_id'];
} elseif (!empty($departments)) {
    $currentDeptId = (int)$departments[0]['id']; // default to first active dept
}

// --------------------------------------------------
// Load ranks for dropdown
// --------------------------------------------------
$ranks = [];
$result = $conn->query("SELECT id, rank_name FROM department_command_rank WHERE is_active = 1 ORDER BY sort_order ASC, rank_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ranks[] = $row;
    }
    $result->free();
}

// --------------------------------------------------
// Handle CREATE / UPDATE command staff
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff'])) {
    $staffId          = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    $deptId           = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $memberName       = trim($_POST['member_name'] ?? '');
    $rankId           = isset($_POST['rank_id']) ? (int)$_POST['rank_id'] : 0;
    $radioDesignation = trim($_POST['radio_designation'] ?? '');
    $isActive         = isset($_POST['is_active']) ? 1 : 0;

    // Keep the selected dept in view after POST
    $currentDeptId = $deptId;

    if ($deptId <= 0) {
        $message = 'Please select a department.';
        $messageType = 'danger';
    } elseif ($memberName === '') {
        $message = 'Member name is required.';
        $messageType = 'danger';
    } elseif ($rankId <= 0) {
        $message = 'Please select a rank.';
        $messageType = 'danger';
    } else {
        try {
            // For now we are NOT using can_be_incident_command in the UI
            $canBeIC = 0;

            if ($staffId === 0) {
                // INSERT new staff
                $sql = "INSERT INTO department_command
                        (dept_id, member_name, rank_id, radio_designation, can_be_incident_command, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param(
                    "isissi",
                    $deptId,
                    $memberName,
                    $rankId,
                    $radioDesignation,
                    $canBeIC,
                    $isActive
                );
                $stmt->execute();
                $stmt->close();
                $message = 'Command staff member added successfully.';
                $messageType = 'success';
            } else {
                // UPDATE existing staff
                $sql = "UPDATE department_command
                        SET member_name = ?, 
                            rank_id = ?, 
                            radio_designation = ?, 
                            is_active = ?
                        WHERE id = ? AND dept_id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param(
                    "sisiii",
                    $memberName,
                    $rankId,
                    $radioDesignation,
                    $isActive,
                    $staffId,
                    $deptId
                );
                $stmt->execute();
                $stmt->close();
                $message = 'Command staff member updated successfully.';
                $messageType = 'success';
            }

            // Redirect to avoid form resubmission
            header("Location: department_command_manage.php?dept_id=" . $currentDeptId . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;

        } catch (Exception $ex) {
            $message = 'Error saving command staff member: ' . $ex->getMessage();
            $messageType = 'danger';
        }
    }
}

// --------------------------------------------------
// Handle DELETE staff
// --------------------------------------------------
if (isset($_GET['delete_id']) && isset($_GET['dept_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deptId   = (int)$_GET['dept_id'];
    $currentDeptId = $deptId;

    if ($deleteId > 0 && $deptId > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM department_command WHERE id = ? AND dept_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ii", $deleteId, $deptId);
            $stmt->execute();
            $stmt->close();

            $message = 'Command staff member deleted successfully.';
            $messageType = 'success';

            header("Location: department_command_manage.php?dept_id=" . $currentDeptId . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;
        } catch (Exception $ex) {
            $message = 'Error deleting command staff member: ' . $ex->getMessage();
            $messageType = 'danger';
        }
    }
}

// --------------------------------------------------
// Load message from redirect (if any)
// --------------------------------------------------
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// --------------------------------------------------
// Load staff for current department
// --------------------------------------------------
$staffList = [];
$editStaff = null;
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

if ($currentDeptId > 0) {
    // Get all staff for this dept
    $sql = "SELECT dc.*, r.rank_name
            FROM department_command dc
            JOIN department_command_rank r ON dc.rank_id = r.id
            WHERE dc.dept_id = ?
            ORDER BY r.sort_order ASC, r.rank_name ASC, dc.member_name ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $currentDeptId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $staffList[] = $row;
        }
        $stmt->close();
    }

    // If editing, load specific staff
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM department_command WHERE id = ? AND dept_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $editId, $currentDeptId);
            $stmt->execute();
            $result = $stmt->get_result();
            $editStaff = $result->fetch_assoc();
            $stmt->close();
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Department Command Staff - FD Incident Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">FD Incident Management - Command Staff</span>
    </div>
</nav>

<div class="container mb-5">

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo e($messageType); ?> alert-dismissible fade show" role="alert">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <form method="get" action="department_command_manage.php" class="row g-2 align-items-end">
            <div class="col-md-6 col-lg-4">
                <label for="dept_id" class="form-label">Department</label>
                <select class="form-select" id="dept_id" name="dept_id" onchange="this.form.submit()">
                    <?php if (empty($departments)): ?>
                        <option value="0">No active departments found</option>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo (int)$dept['id']; ?>"
                                <?php echo ((int)$dept['id'] === $currentDeptId) ? 'selected' : ''; ?>>
                                <?php echo e($dept['dept_name']); ?> (<?php echo e($dept['dept_short_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($currentDeptId <= 0 || empty($departments)): ?>
        <div class="alert alert-warning">
            Please add an active department first on the Department Management page, then return here.
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-6">
                <!-- Add / Edit Staff Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <?php if ($editStaff): ?>
                            Edit Command Staff Member
                        <?php else: ?>
                            Add Command Staff Member
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="department_command_manage.php?dept_id=<?php echo $currentDeptId; ?>">
                            <input type="hidden" name="staff_id"
                                   value="<?php echo $editStaff ? (int)$editStaff['id'] : 0; ?>">
                            <input type="hidden" name="dept_id" value="<?php echo $currentDeptId; ?>">

                            <div class="mb-3">
                                <label for="member_name" class="form-label">Member Name</label>
                                <input type="text"
                                       class="form-control"
                                       id="member_name"
                                       name="member_name"
                                       required
                                       value="<?php echo $editStaff ? e($editStaff['member_name']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="rank_id" class="form-label">Rank</label>
                                <select class="form-select"
                                        id="rank_id"
                                        name="rank_id"
                                        required>
                                    <option value="">-- Select Rank --</option>
                                    <?php foreach ($ranks as $rank): ?>
                                        <option value="<?php echo (int)$rank['id']; ?>"
                                            <?php
                                            if ($editStaff && (int)$editStaff['rank_id'] === (int)$rank['id']) {
                                                echo 'selected';
                                            }
                                            ?>>
                                            <?php echo e($rank['rank_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="radio_designation" class="form-label">Radio Designation</label>
                                <input type="text"
                                       class="form-control"
                                       id="radio_designation"
                                       name="radio_designation"
                                       value="<?php echo $editStaff ? e($editStaff['radio_designation'] ?? '') : ''; ?>">
                                <div class="form-text">
                                    Optional. Example: "Car 1", "Command 1".
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                    <?php
                                    $isActiveChecked = true;
                                    if ($editStaff) {
                                        $isActiveChecked = ((int)$editStaff['is_active'] === 1);
                                    }
                                    echo $isActiveChecked ? 'checked' : '';
                                    ?>>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>

                            <button type="submit" name="save_staff" class="btn btn-primary">
                                <?php echo $editStaff ? 'Update Member' : 'Add Member'; ?>
                            </button>

                            <?php if ($editStaff): ?>
                                <a href="department_command_manage.php?dept_id=<?php echo $currentDeptId; ?>"
                                   class="btn btn-secondary ms-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- List of Staff -->
                <div class="card">
                    <div class="card-header">
                        Command Staff for Selected Department
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($staffList) === 0): ?>
                            <p class="p-3 mb-0">No command staff found for this department. Add members using the form on the left.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Rank</th>
                                        <th scope="col">Radio</th>
                                        <th scope="col">Active</th>
                                        <th scope="col" style="width: 140px;">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td><?php echo e($staff['member_name']); ?></td>
                                            <td><?php echo e($staff['rank_name']); ?></td>
                                            <td><?php echo e($staff['radio_designation']); ?></td>
                                            <td><?php echo ((int)$staff['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <a href="department_command_manage.php?dept_id=<?php echo $currentDeptId; ?>&edit_id=<?php echo (int)$staff['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>
                                                <a href="department_command_manage.php?dept_id=<?php echo $currentDeptId; ?>&delete_id=<?php echo (int)$staff['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this member?');">
                                                    Delete
                                                </a>
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
    <?php endif; ?>

    <hr class="my-4">

    <footer class="text-center text-muted small">
        Fire Department Incident Management System<br>
        With development assistance from ChatGPT (OpenAI)
    </footer>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
