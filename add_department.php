<?php
// add_department.php (FULL PAGE)
// Create a new department record, then log the user in as that department.

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db_connect.php'; // expects $conn (mysqli)

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$errors = [];

$dept_name       = '';
$dept_short_name = '';
$station_id      = '';
$designation     = '';
$contact_name    = '';
$contact_email   = '';
$contact_phone   = '';
$contact_address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $dept_name       = trim((string)($_POST['dept_name'] ?? ''));
  $dept_short_name = trim((string)($_POST['dept_short_name'] ?? ''));
  $station_id      = trim((string)($_POST['station_id'] ?? ''));
  $designation     = trim((string)($_POST['designation'] ?? ''));
  $contact_name    = trim((string)($_POST['contact_name'] ?? ''));
  $contact_email   = trim((string)($_POST['contact_email'] ?? ''));
  $contact_phone   = trim((string)($_POST['contact_phone'] ?? ''));
  $contact_address = trim((string)($_POST['contact_address'] ?? ''));

  $access_code     = trim((string)($_POST['access_code'] ?? ''));
  $access_code2    = trim((string)($_POST['access_code_confirm'] ?? ''));

  if ($dept_name === '') $errors[] = "Department Name is required.";
  if ($dept_short_name === '') $errors[] = "Department Short Name is required.";

  // Access code: EXACTLY 8 digits
  if ($access_code === '' || $access_code2 === '') {
    $errors[] = "Access Code and Confirm Access Code are required.";
  } else {
    if ($access_code !== $access_code2) $errors[] = "Access Codes do not match.";
    if (!preg_match('/^\d{8}$/', $access_code)) $errors[] = "Access Code must be exactly 8 digits.";
  }

  if ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Contact Email is not a valid email address.";
  }

  if (!$errors) {
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $hash = password_hash($access_code, $algo);

    if (!$hash) {
      $errors[] = "Could not hash access code.";
    } else {
      // Optional fields -> NULL when empty
      $station_id  = ($station_id !== '') ? $station_id : null;
      $designation = ($designation !== '') ? $designation : null;

      $contact_name    = ($contact_name !== '') ? $contact_name : null;
      $contact_email   = ($contact_email !== '') ? $contact_email : null;
      $contact_phone   = ($contact_phone !== '') ? $contact_phone : null;
      $contact_address = ($contact_address !== '') ? $contact_address : null;

      $sql = "
        INSERT INTO department
          (dept_name, dept_short_name, access_code_hash, is_active, is_mutual_aid, designation, station_id,
           contact_name, contact_email, contact_phone, contact_address)
        VALUES
          (?, ?, ?, 1, 0, ?, ?, ?, ?, ?, ?)
      ";

      $stmt = $conn->prepare($sql);
      if (!$stmt) {
        $errors[] = "DB prepare failed: " . $conn->error;
      } else {
        // 9 placeholders => 9 bind types
        $stmt->bind_param(
          "sssssssss",
          $dept_name,
          $dept_short_name,
          $hash,
          $designation,
          $station_id,
          $contact_name,
          $contact_email,
          $contact_phone,
          $contact_address
        );

        if (!$stmt->execute()) {
          $errors[] = "DB insert failed: " . $stmt->error;
        } else {
          $_SESSION['dept_id'] = (int)$stmt->insert_id;
          $_SESSION['dept_name'] = $dept_name;
          $_SESSION['dept_short_name'] = $dept_short_name;
          // New department onboarding: add apparatus first
          header("Location: department_settings.php?tab=apparatus&welcome=1");
          exit;
        }
        $stmt->close();
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Department</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container py-4" style="max-width: 900px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Add New Department</h1>
    <a class="btn btn-outline-light" href="index.php">Back</a>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <div class="fw-bold mb-1">Please fix the following:</div>
      <ul class="mb-0">
        <?php foreach ($errors as $er): ?>
          <li><?= e($er) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card bg-secondary text-light shadow-sm">
    <div class="card-body">
      <form method="post" autocomplete="off">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label fw-semibold">Department Name *</label>
            <input class="form-control form-control-lg" name="dept_name" value="<?= e($dept_name) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Short Name *</label>
            <input class="form-control form-control-lg" name="dept_short_name" value="<?= e($dept_short_name) ?>" required>
            <div class="form-text text-light">Example: EVFD</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Station ID</label>
            <input class="form-control form-control-lg" name="station_id" value="<?= e($station_id) ?>">
            <div class="form-text text-light">Example: Station 5</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Online Designation</label>
            <input class="form-control form-control-lg" name="designation" value="<?= e($designation) ?>">
            <div class="form-text text-light">Optional</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Access Code (8 digits) *</label>
            <input class="form-control form-control-lg" name="access_code" inputmode="numeric" pattern="\d{8}" maxlength="8">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Confirm Access Code *</label>
            <input class="form-control form-control-lg" name="access_code_confirm" inputmode="numeric" pattern="\d{8}" maxlength="8">
          </div>

          <hr class="my-2">

          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Name</label>
            <input class="form-control form-control-lg" name="contact_name" value="<?= e($contact_name) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Email</label>
            <input class="form-control form-control-lg" name="contact_email" value="<?= e($contact_email) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Phone</label>
            <input class="form-control form-control-lg" name="contact_phone" value="<?= e($contact_phone) ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Contact Address</label>
            <input class="form-control form-control-lg" name="contact_address" value="<?= e($contact_address) ?>">
          </div>
        </div>

        <div class="mt-4 d-grid">
          <button class="btn btn-danger btn-lg">Create Department</button>
        </div>
      </form>
    </div>
  </div>

  <div class="small text-muted mt-3">
    After creating the department, you’ll be able to add Mutual Aid partners and command staff (next step).
  </div>
</div>
</body>
</html>
