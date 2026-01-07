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
// Load all departments for dropdown
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
// Handle CREATE / UPDATE apparatus
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_apparatus'])) {
    $apparatusId   = isset($_POST['apparatus_id']) ? (int)$_POST['apparatus_id'] : 0;
    $deptId        = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $name          = trim($_POST['apparatus_name'] ?? '');
    $type          = trim($_POST['apparatus_type'] ?? '');
    $radioId       = trim($_POST['radio_id'] ?? '');
    $sortOrder     = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $isActive      = isset($_POST['is_active']) ? 1 : 0;

    // Keep the selected dept in view after POST
    $currentDeptId = $deptId;

    if ($deptId <= 0) {
        $message = 'Please select a department.';
        $messageType = 'danger';
    } elseif ($name === '' || $type === '') {
        $message = 'Apparatus Name and Type are required.';
        $messageType = 'danger';
    } else {
        try {
            if ($apparatusId === 0) {
                // INSERT new apparatus
                $sql = "INSERT INTO department_apparatus
                        (dept_id, apparatus_name, apparatus_type, radio_id, sort_order, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("isssii", $deptId, $name, $type, $radioId, $sortOrder, $isActive);
                $stmt->execute();
                $stmt->close();
                $message = 'Apparatus added successfully.';
                $messageType = 'success';
            } else {
                // UPDATE existing apparatus
                $sql = "UPDATE department_apparatus
                        SET apparatus_name = ?, 
                            apparatus_type = ?, 
                            radio_id = ?, 
                            sort_order = ?, 
                            is_active = ?
                        WHERE id = ? AND dept_id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssiiii", $name, $type, $radioId, $sortOrder, $isActive, $apparatusId, $deptId);
                $stmt->execute();
                $stmt->close();
                $message = 'Apparatus updated successfully.';
                $messageType = 'success';
            }

            // Redirect to avoid form resubmission
            header("Location: department_apparatus_manage.php?dept_id=" . $currentDeptId . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;

        } catch (Exception $ex) {
            $message = 'Error saving apparatus: ' . $ex->getMessage();
            $messageType = 'danger';
        }
    }
}

// --------------------------------------------------
// Handle DELETE apparatus
// --------------------------------------------------
if (isset($_GET['delete_id']) && isset($_GET['dept_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deptId   = (int)$_GET['dept_id'];
    $currentDeptId = $deptId;

    if ($deleteId > 0 && $deptId > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM department_apparatus WHERE id = ? AND dept_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ii", $deleteId, $deptId);
            $stmt->execute();
            $stmt->close();

            $message = 'Apparatus deleted successfully.';
            $messageType = 'success';

            header("Location: department_apparatus_manage.php?dept_id=" . $currentDeptId . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;
        } catch (Exception $ex) {
            $message = 'Error deleting apparatus: ' . $ex->getMessage();
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
// Load apparatus for current department
// --------------------------------------------------
$apparatusList = [];
$editApparatus = null;
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

if ($currentDeptId > 0) {
    // Get all apparatus for this dept
    $stmt = $conn->prepare("SELECT * FROM department_apparatus WHERE dept_id = ? ORDER BY sort_order ASC, apparatus_name ASC");
    if ($stmt) {
        $stmt->bind_param("i", $currentDeptId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $apparatusList[] = $row;
        }
        $stmt->close();
    }

    // If editing, load specific apparatus
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM department_apparatus WHERE id = ? AND dept_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $editId, $currentDeptId);
            $stmt->execute();
            $result = $stmt->get_result();
            $editApparatus = $result->fetch_assoc();
            $stmt->close();
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Department Apparatus Management - FD Incident Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">FD Incident Management - Department Apparatus</span>
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
        <form method="get" action="department_apparatus_manage.php" class="row g-2 align-items-end">
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
                <!-- Add / Edit Apparatus Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <?php if ($editApparatus): ?>
                            Edit Apparatus
                        <?php else: ?>
                            Add Apparatus
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="department_apparatus_manage.php?dept_id=<?php echo $currentDeptId; ?>">
                            <input type="hidden" name="apparatus_id"
                                   value="<?php echo $editApparatus ? (int)$editApparatus['id'] : 0; ?>">
                            <input type="hidden" name="dept_id" value="<?php echo $currentDeptId; ?>">

                            <div class="mb-3">
                                <label for="apparatus_name" class="form-label">Apparatus Name</label>
                                <input type="text"
                                       class="form-control"
                                       id="apparatus_name"
                                       name="apparatus_name"
                                       required
                                       value="<?php echo $editApparatus ? e($editApparatus['apparatus_name']) : ''; ?>">
                                <div class="form-text">
                                    Example: "Engine 1", "Truck 5", "Rescue 2".
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="apparatus_type" class="form-label">Apparatus Type</label>
                                <input type="text"
                                       class="form-control"
                                       id="apparatus_type"
                                       name="apparatus_type"
                                       required
                                       value="<?php echo $editApparatus ? e($editApparatus['apparatus_type']) : ''; ?>">
                                <div class="form-text">
                                    Example: "Engine", "Truck", "Rescue", "Tanker", "Brush".
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="radio_id" class="form-label">Radio Designation</label>
                                <input type="text"
                                       class="form-control"
                                       id="radio_id"
                                       name="radio_id"
                                       value="<?php echo $editApparatus ? e($editApparatus['radio_id'] ?? '') : ''; ?>">
                                <div class="form-text">
                                    Optional. Example: "E1", "L5", "Rescue 2".
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="sort_order" class="form-label">Sort Order</label>
                                <input type="number"
                                       class="form-control"
                                       id="sort_order"
                                       name="sort_order"
                                       value="<?php echo $editApparatus ? (int)$editApparatus['sort_order'] : 0; ?>">
                                <div class="form-text">
                                    Lower numbers appear first when listing or making buttons.
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="is_active"
                                       name="is_active"
                                    <?php
                                    $isActiveChecked = true;
                                    if ($editApparatus) {
                                        $isActiveChecked = ((int)$editApparatus['is_active'] === 1);
                                    }
                                    echo $isActiveChecked ? 'checked' : '';
                                    ?>>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>

                            <button type="submit" name="save_apparatus" class="btn btn-primary">
                                <?php echo $editApparatus ? 'Update Apparatus' : 'Add Apparatus'; ?>
                            </button>

                            <?php if ($editApparatus): ?>
                                <a href="department_apparatus_manage.php?dept_id=<?php echo $currentDeptId; ?>"
                                   class="btn btn-secondary ms-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- List of Apparatus -->
                <div class="card">
                    <div class="card-header">
                        Apparatus for Selected Department
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($apparatusList) === 0): ?>
                            <p class="p-3 mb-0">No apparatus found for this department. Add apparatus using the form on the left.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th scope="col">Name</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Radio</th>
                                        <th scope="col">Active</th>
                                        <th scope="col" style="width: 140px;">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($apparatusList as $app): ?>
                                        <tr>
                                            <td><?php echo e($app['apparatus_name']); ?></td>
                                            <td><?php echo e($app['apparatus_type']); ?></td>
                                            <td><?php echo e($app['radio_id']); ?></td>
                                            <td><?php echo ((int)$app['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
                                            <td>
                                                <a href="department_apparatus_manage.php?dept_id=<?php echo $currentDeptId; ?>&edit_id=<?php echo (int)$app['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>
                                                <a href="department_apparatus_manage.php?dept_id=<?php echo $currentDeptId; ?>&delete_id=<?php echo (int)$app['id']; ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this apparatus?');">
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
