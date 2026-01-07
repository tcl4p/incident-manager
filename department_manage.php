<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db_connect.php"; // must define $conn (mysqli)
session_start();

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function isValidAccessCode(string $code): bool {
    // Exactly 6 digits, no letters, no spaces
    return (bool)preg_match('/^\d{6}$/', $code);
}

$message = '';
$messageType = 'success';

// --------------------------------------------------
// Handle CREATE / UPDATE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) {
    $deptId         = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : 0;
    $deptName       = trim($_POST['dept_name'] ?? '');
    $deptShortName  = trim($_POST['dept_short_name'] ?? '');
    $accessCode     = trim($_POST['access_code'] ?? '');
    $isActive       = isset($_POST['is_active']) ? 1 : 0;

    $contactName    = trim($_POST['contact_name'] ?? '');
    $contactEmail   = trim($_POST['contact_email'] ?? '');
    $contactPhone   = trim($_POST['contact_phone'] ?? '');
    $contactAddress = trim($_POST['contact_address'] ?? '');

    if ($deptName === '' || $deptShortName === '') {
        $message = 'Department Name and Short Name are required.';
        $messageType = 'danger';
    } elseif ($deptId === 0 && $accessCode === '') {
        // For new departments, access code is required
        $message = 'Access Code is required when creating a new department.';
        $messageType = 'danger';
    } elseif ($deptId === 0 && !isValidAccessCode($accessCode)) {
        $message = 'Access Code must be exactly 6 numeric digits (0–9).';
        $messageType = 'danger';
    } elseif ($deptId > 0 && $accessCode !== '' && !isValidAccessCode($accessCode)) {
        // Editing and user is trying to change the code
        $message = 'Access Code must be exactly 6 numeric digits (0–9).';
        $messageType = 'danger';
    } else {
        try {
            // For new or changed codes, hash the access code
            $accessCodeHash = null;
            if ($accessCode !== '') {
                if (defined('PASSWORD_ARGON2ID')) {
                    $accessCodeHash = password_hash($accessCode, PASSWORD_ARGON2ID);
                } else {
                    $accessCodeHash = password_hash($accessCode, PASSWORD_DEFAULT);
                }
            }

            if ($deptId === 0) {
                // INSERT new department
                $sql = "INSERT INTO department 
                            (dept_name, dept_short_name, access_code_hash, is_active,
                             contact_name, contact_email, contact_phone, contact_address)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param(
                    "sssissss",
                    $deptName,
                    $deptShortName,
                    $accessCodeHash,
                    $isActive,
                    $contactName,
                    $contactEmail,
                    $contactPhone,
                    $contactAddress
                );
                $stmt->execute();
                $stmt->close();
                $message = 'Department created successfully.';
                $messageType = 'success';
            } else {
                // UPDATE existing department
                if ($accessCodeHash !== null) {
                    // Update including new access code
                    $sql = "UPDATE department
                            SET dept_name = ?, 
                                dept_short_name = ?, 
                                access_code_hash = ?, 
                                is_active = ?, 
                                contact_name = ?, 
                                contact_email = ?, 
                                contact_phone = ?, 
                                contact_address = ?, 
                                updated_at = NOW()
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param(
                        "sssissssi",
                        $deptName,
                        $deptShortName,
                        $accessCodeHash,
                        $isActive,
                        $contactName,
                        $contactEmail,
                        $contactPhone,
                        $contactAddress,
                        $deptId
                    );
                } else {
                    // Update without touching access code
                    $sql = "UPDATE department
                            SET dept_name = ?, 
                                dept_short_name = ?, 
                                is_active = ?, 
                                contact_name = ?, 
                                contact_email = ?, 
                                contact_phone = ?, 
                                contact_address = ?, 
                                updated_at = NOW()
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param(
                        "ssissssi",
                        $deptName,
                        $deptShortName,
                        $isActive,
                        $contactName,
                        $contactEmail,
                        $contactPhone,
                        $contactAddress,
                        $deptId
                    );
                }

                $stmt->execute();
                $stmt->close();
                $message = 'Department updated successfully.';
                $messageType = 'success';
            }

            // Post/Redirect/Get pattern to avoid resubmission on refresh
            header("Location: department_manage.php?msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;

        } catch (Exception $ex) {
            $message = 'Error saving department: ' . $ex->getMessage();
            $messageType = 'danger';
        }
    }
}

// --------------------------------------------------
// Handle DELETE
// --------------------------------------------------
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    if ($deleteId > 0) {
        try {
            // ON DELETE CASCADE will remove apparatus and command entries for this dept
            $stmt = $conn->prepare("DELETE FROM department WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $deleteId);
            $stmt->execute();
            $stmt->close();

            $message = 'Department deleted successfully.';
            $messageType = 'success';

            header("Location: department_manage.php?msg=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;
        } catch (Exception $ex) {
            $message = 'Error deleting department: ' . $ex->getMessage();
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
// Load data for editing (if edit_id is present)
// --------------------------------------------------
$editDeptId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editDept = null;

if ($editDeptId > 0) {
    $stmt = $conn->prepare("SELECT * FROM department WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $editDeptId);
        $stmt->execute();
        $result = $stmt->get_result();
        $editDept = $result->fetch_assoc();
        $stmt->close();
    }
}

// --------------------------------------------------
// Load all departments for the listing table
// --------------------------------------------------
$departments = [];
$result = $conn->query("SELECT * FROM department ORDER BY dept_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    $result->free();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Department Management - FD Incident Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">FD Incident Management - Department Setup</span>
    </div>
</nav>

<div class="container mb-5">

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo e($messageType); ?> alert-dismissible fade show" role="alert">
            <?php echo e($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <!-- Add / Edit Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <?php if ($editDept): ?>
                        Edit Department
                    <?php else: ?>
                        Add New Department
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="department_manage.php">
                        <input type="hidden" name="dept_id" value="<?php echo $editDept ? (int)$editDept['id'] : 0; ?>">

                        <div class="mb-3">
                            <label for="dept_name" class="form-label">Department Name</label>
                            <input type="text"
                                   class="form-control"
                                   id="dept_name"
                                   name="dept_name"
                                   required
                                   value="<?php echo $editDept ? e($editDept['dept_name']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="dept_short_name" class="form-label">Short Name</label>
                            <input type="text"
                                   class="form-control"
                                   id="dept_short_name"
                                   name="dept_short_name"
                                   required
                                   value="<?php echo $editDept ? e($editDept['dept_short_name']) : ''; ?>">
                            <div class="form-text">
                                Example: "Station 8", "Township FD" – used in headers and compact displays.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="access_code" class="form-label">
                                Access Code
                                <?php if ($editDept): ?>
                                    <small class="text-muted">(leave blank to keep existing code)</small>
                                <?php endif; ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="access_code"
                                   name="access_code"
                                   <?php echo $editDept ? '' : 'required'; ?>
                                   autocomplete="off"
                                   pattern="\d{6}"
                                   maxlength="6"
                                   inputmode="numeric"
                                   title="Six-digit numeric code (0–9)">
                            <div class="form-text">
                                Exactly 6 digits (0–9). This is the code the department will enter on the home screen
                                to access their incidents.
                            </div>
                        </div>

                        <hr>

                        <h6>Contact Person</h6>

                        <div class="mb-3">
                            <label for="contact_name" class="form-label">Contact Name</label>
                            <input type="text"
                                   class="form-control"
                                   id="contact_name"
                                   name="contact_name"
                                   value="<?php echo $editDept ? e($editDept['contact_name'] ?? '') : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_email" class="form-label">Contact Email</label>
                            <input type="email"
                                   class="form-control"
                                   id="contact_email"
                                   name="contact_email"
                                   value="<?php echo $editDept ? e($editDept['contact_email'] ?? '') : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_phone" class="form-label">Contact Phone</label>
                            <input type="text"
                                   class="form-control"
                                   id="contact_phone"
                                   name="contact_phone"
                                   value="<?php echo $editDept ? e($editDept['contact_phone'] ?? '') : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="contact_address" class="form-label">Contact Address</label>
                            <textarea
                                   class="form-control"
                                   id="contact_address"
                                   name="contact_address"
                                   rows="2"><?php echo $editDept ? e($editDept['contact_address'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="is_active"
                                   name="is_active"
                                   <?php
                                   $isActiveChecked = true;
                                   if ($editDept) {
                                       $isActiveChecked = ((int)$editDept['is_active'] === 1);
                                   }
                                   echo $isActiveChecked ? 'checked' : '';
                                   ?>>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>

                        <button type="submit" name="save_department" class="btn btn-primary">
                            <?php echo $editDept ? 'Update Department' : 'Create Department'; ?>
                        </button>

                        <?php if ($editDept): ?>
                            <a href="department_manage.php" class="btn btn-secondary ms-2">Cancel Edit</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- List of Departments -->
            <div class="card">
                <div class="card-header">
                    Existing Departments
                </div>
                <div class="card-body p-0">
                    <?php if (count($departments) === 0): ?>
                        <p class="p-3 mb-0">No departments found. Add a new department using the form on the left.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Short</th>
                                    <th scope="col">Active</th>
                                    <th scope="col" style="width: 120px;">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo e($dept['dept_name']); ?></td>
                                        <td><?php echo e($dept['dept_short_name']); ?></td>
                                        <td><?php echo ((int)$dept['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
                                        <td>
                                            <a href="department_manage.php?edit_id=<?php echo (int)$dept['id']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                Edit
                                            </a>
                                            <a href="department_manage.php?delete_id=<?php echo (int)$dept['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this department? This will also remove its apparatus and command staff.');">
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

    <hr class="my-4">

    <footer class="text-center text-muted small">
        Fire Department Incident Management System<br>
        With development assistance from ChatGPT (OpenAI)
    </footer>
</div>

<!-- Bootstrap 5 JS (for alerts + navbar toggles) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
