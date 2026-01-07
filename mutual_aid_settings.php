<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/db_connect.php'; // expects $conn (mysqli)

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$homeDeptId = (int)($_SESSION['dept_id'] ?? 0);
if ($homeDeptId <= 0) {
  http_response_code(401);
  ?>
  <!doctype html>
  <html lang="en"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mutual Aid Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light">
    <div class="container py-4" style="max-width: 900px;">
      <div class="alert alert-warning">
        <div class="fw-bold mb-1">Mutual Aid Settings needs your Department session</div>
        <div>Missing <code>$_SESSION['dept_id']</code>. Go back to Incidents and open this page from the buttons there.</div>
      </div>
      <a class="btn btn-primary" href="incidents.php">Back to Incidents</a>
      <a class="btn btn-outline-secondary ms-2" href="index.php">Back to Index</a>
      <hr>
      <div class="small text-muted">Debug: session_id=<?= e(session_id()) ?>, session_name=<?= e(session_name()) ?></div>
    </div>
  </body></html>
  <?php
  exit;
}

function prepare_or_die(mysqli $conn, string $sql): mysqli_stmt {
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    echo "<pre style='white-space:pre-wrap'>DB PREPARE FAILED\nError: {$conn->error}\n\nSQL:\n{$sql}\n</pre>";
    exit;
  }
  return $stmt;
}

function redirect_self(array $params = []): void {
  $base = basename($_SERVER['PHP_SELF']);
  $qs = $params ? ('?' . http_build_query($params)) : '';
  header("Location: {$base}{$qs}");
  exit;
}

$flash = null;
$error = null;

$selectedMutualId = isset($_GET['mutual_dept_id']) ? (int)$_GET['mutual_dept_id'] : 0;

/* ---------- Ensure template tables exist (won't overwrite existing) ---------- */
$conn->query("
CREATE TABLE IF NOT EXISTS department_mutual_aid_command_staff_template (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  home_dept_id INT UNSIGNED NOT NULL,
  mutual_dept_id INT NOT NULL,
  officer_display VARCHAR(100) NOT NULL,
  rank_id INT UNSIGNED NOT NULL DEFAULT 0,
  radio_designation VARCHAR(100) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_home (home_dept_id),
  KEY idx_mutual (mutual_dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
CREATE TABLE IF NOT EXISTS department_mutual_aid_apparatus_template (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  home_dept_id INT UNSIGNED NOT NULL,
  mutual_dept_id INT NOT NULL,
  apparatus_label VARCHAR(100) NOT NULL,
  apparatus_type_id INT UNSIGNED NOT NULL DEFAULT 0,
  staffing INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_home (home_dept_id),
  KEY idx_mutual (mutual_dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ---------- Actions ---------- */
if (isset($_GET['unlink']) && (int)$_GET['unlink'] > 0) {
  $mutualId = (int)$_GET['unlink'];
  $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid WHERE home_dept_id=? AND mutual_dept_id=?");
  $stmt->bind_param("ii", $homeDeptId, $mutualId);
  $stmt->execute();
  $stmt->close();
  redirect_self();
}

if (isset($_GET['del_staff']) && (int)$_GET['del_staff'] > 0) {
  $id = (int)$_GET['del_staff'];
  $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_command_staff_template WHERE id=? AND home_dept_id=?");
  $stmt->bind_param("ii", $id, $homeDeptId);
  $stmt->execute();
  $stmt->close();
  redirect_self(['mutual_dept_id' => $selectedMutualId]);
}

if (isset($_GET['del_app']) && (int)$_GET['del_app'] > 0) {
  $id = (int)$_GET['del_app'];
  $stmt = prepare_or_die($conn, "DELETE FROM department_mutual_aid_apparatus_template WHERE id=? AND home_dept_id=?");
  $stmt->bind_param("ii", $id, $homeDeptId);
  $stmt->execute();
  $stmt->close();
  redirect_self(['mutual_dept_id' => $selectedMutualId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create_master_dept') {
    $name = trim((string)($_POST['department_name'] ?? ''));
    $designation = trim((string)($_POST['designation'] ?? ''));
    $station = trim((string)($_POST['station_id'] ?? ''));

    if ($name === '') {
      $error = "Department name is required.";
    } else {
      $res = $conn->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM mutual_aid_departments");
      $nextId = (int)($res ? ($res->fetch_assoc()['next_id'] ?? 1) : 1);

      $stmt = prepare_or_die($conn, "INSERT INTO mutual_aid_departments (id, department_name, designation, station_id) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("isss", $nextId, $name, $designation, $station);
      $stmt->execute();
      $stmt->close();
      $flash = "Created mutual aid department: {$name}";
    }
  }

  if ($action === 'link_partner') {
    $mutualId = (int)($_POST['mutual_dept_id'] ?? 0);
    if ($mutualId <= 0) {
      $error = "Pick a mutual aid department to link.";
    } else {
      $stmt = prepare_or_die($conn, "SELECT id FROM department_mutual_aid WHERE home_dept_id=? AND mutual_dept_id=? LIMIT 1");
      $stmt->bind_param("ii", $homeDeptId, $mutualId);
      $stmt->execute();
      $exists = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if ($exists) {
        $flash = "That department is already linked.";
      } else {
        $res = $conn->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM department_mutual_aid");
        $nextId = (int)($res ? ($res->fetch_assoc()['next_id'] ?? 1) : 1);

        $stmt = prepare_or_die($conn, "INSERT INTO department_mutual_aid (id, home_dept_id, mutual_dept_id, is_active, sort_order) VALUES (?, ?, ?, 1, 0)");
        $stmt->bind_param("iii", $nextId, $homeDeptId, $mutualId);
        $stmt->execute();
        $stmt->close();
        $flash = "Linked mutual aid department.";
        $selectedMutualId = $mutualId;
      }
    }
  }

  if ($action === 'add_staff') {
    $mutualId = (int)($_POST['mutual_dept_id'] ?? 0);
    $officer = trim((string)($_POST['officer_display'] ?? ''));
    $rankId = (int)($_POST['rank_id'] ?? 0);
    $radio = trim((string)($_POST['radio_designation'] ?? ''));

    if ($mutualId <= 0 || $officer === '') {
      $error = "Pick a partner and enter an officer display (e.g., Chief 50).";
    } else {
      $stmt = prepare_or_die($conn, "INSERT INTO department_mutual_aid_command_staff_template (home_dept_id, mutual_dept_id, officer_display, rank_id, radio_designation, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 0, 1)");
      $stmt->bind_param("iisis", $homeDeptId, $mutualId, $officer, $rankId, $radio);
      $stmt->execute();
      $stmt->close();
      $flash = "Added command staff.";
      $selectedMutualId = $mutualId;
    }
  }

  if ($action === 'add_app') {
    $mutualId = (int)($_POST['mutual_dept_id'] ?? 0);
    $label = trim((string)($_POST['apparatus_label'] ?? ''));
    $typeId = (int)($_POST['apparatus_type_id'] ?? 0);
    $staff = (int)($_POST['staffing'] ?? 0);

    if ($mutualId <= 0 || $label === '') {
      $error = "Pick a partner and enter an apparatus label (e.g., Eng 56).";
    } else {
      $stmt = prepare_or_die($conn, "INSERT INTO department_mutual_aid_apparatus_template (home_dept_id, mutual_dept_id, apparatus_label, apparatus_type_id, staffing, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 0, 1)");
      $stmt->bind_param("iisii", $homeDeptId, $mutualId, $label, $typeId, $staff);
      $stmt->execute();
      $stmt->close();
      $flash = "Added apparatus template.";
      $selectedMutualId = $mutualId;
    }
  }
}

/* ---------- Load data ---------- */
// Linked partners
$stmt = prepare_or_die($conn, "
  SELECT dma.mutual_dept_id,
         mad.department_name,
         mad.designation,
         mad.station_id
    FROM department_mutual_aid dma
    JOIN mutual_aid_departments mad ON mad.id = dma.mutual_dept_id
   WHERE dma.home_dept_id = ?
   ORDER BY mad.department_name
");
$stmt->bind_param("i", $homeDeptId);
$stmt->execute();
$partners = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Master list for dropdown
$master = [];
$res = $conn->query("SELECT id, department_name, designation, station_id FROM mutual_aid_departments ORDER BY department_name");
if ($res) {
  $master = $res->fetch_all(MYSQLI_ASSOC);
}

// Ranks for select
$ranks = [];
$res = $conn->query("SELECT id, rank_name FROM department_command_rank ORDER BY sort_order, rank_name");
if ($res) $ranks = $res->fetch_all(MYSQLI_ASSOC);

// Apparatus types
$appTypes = [];
$res = $conn->query("SELECT id, type_name FROM apparatus_types ORDER BY sort_order, type_name");
if ($res) $appTypes = $res->fetch_all(MYSQLI_ASSOC);

// Selected partner details (staff + apparatus)
$selectedPartner = null;
foreach ($partners as $p) {
  if ((int)$p['mutual_dept_id'] === $selectedMutualId) { $selectedPartner = $p; break; }
}
$staffRows = [];
$appRows = [];

if ($selectedMutualId > 0) {
  $stmt = prepare_or_die($conn, "
    SELECT id, officer_display, rank_id, radio_designation
      FROM department_mutual_aid_command_staff_template
     WHERE home_dept_id=? AND mutual_dept_id=?
     ORDER BY sort_order, officer_display
  ");
  $stmt->bind_param("ii", $homeDeptId, $selectedMutualId);
  $stmt->execute();
  $staffRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $stmt = prepare_or_die($conn, "
    SELECT t.id, t.apparatus_label, t.apparatus_type_id, t.staffing, at.type_name
      FROM department_mutual_aid_apparatus_template t
      LEFT JOIN apparatus_types at ON at.id = t.apparatus_type_id
     WHERE t.home_dept_id=? AND t.mutual_dept_id=?
     ORDER BY t.sort_order, t.apparatus_label
  ");
  $stmt->bind_param("ii", $homeDeptId, $selectedMutualId);
  $stmt->execute();
  $appRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mutual Aid Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 1100px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Mutual Aid Settings</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="incidents.php">Back to Incidents</a>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateDept">Add Mutual Aid Department</button>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-success"><?= e($flash) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3">Linked Partners</h5>

          <?php if (!$partners): ?>
            <div class="text-muted">No mutual aid partners linked yet.</div>
          <?php else: ?>
            <div class="list-group mb-3">
              <?php foreach ($partners as $p): ?>
                <?php
                  $active = ((int)$p['mutual_dept_id'] === $selectedMutualId);
                  $title = $p['department_name'] ?? '';
                  $sub = trim(($p['designation'] ?? '') . ' ' . ($p['station_id'] ?? ''));
                ?>
                <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
                   href="?mutual_dept_id=<?= (int)$p['mutual_dept_id'] ?>">
                  <div class="fw-bold"><?= e($title) ?></div>
                  <?php if ($sub !== ''): ?><div class="small opacity-75"><?= e($sub) ?></div><?php endif; ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" class="border-top pt-3">
            <input type="hidden" name="action" value="link_partner">
            <label class="form-label fw-bold">Link an existing Mutual Aid Department</label>
            <select name="mutual_dept_id" class="form-select mb-2">
              <option value="0">-- Select Department --</option>
              <?php foreach ($master as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= e($m['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-danger w-100">Link Partner</button>
          </form>

          <?php if ($selectedMutualId > 0): ?>
            <div class="mt-3">
              <a class="btn btn-outline-danger w-100"
                 href="?unlink=<?= (int)$selectedMutualId ?>"
                 onclick="return confirm('Unlink this partner from your department?');">
                Unlink Selected Partner
              </a>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <?php if ($selectedMutualId <= 0 || !$selectedPartner): ?>
            <div class="text-muted">Select a linked partner on the left to manage their Command Staff and Apparatus templates.</div>
          <?php else: ?>
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="mb-1"><?= e($selectedPartner['department_name'] ?? '') ?></h5>
                <div class="small text-muted">
                  <?= e(trim(($selectedPartner['designation'] ?? '') . ' ' . ($selectedPartner['station_id'] ?? ''))) ?>
                </div>
              </div>
              <span class="badge bg-secondary">Home Dept ID: <?= (int)$homeDeptId ?></span>
            </div>

            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabStaff" type="button" role="tab">Command Staff</button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApp" type="button" role="tab">Apparatus</button>
              </li>
            </ul>

            <div class="tab-content pt-3">

              <!-- Staff Tab -->
              <div class="tab-pane fade show active" id="tabStaff" role="tabpanel">
                <form method="post" class="row g-2 align-items-end mb-3">
                  <input type="hidden" name="action" value="add_staff">
                  <input type="hidden" name="mutual_dept_id" value="<?= (int)$selectedMutualId ?>">
                  <div class="col-12 col-md-5">
                    <label class="form-label">Officer Display</label>
                    <input class="form-control" name="officer_display" placeholder="Chief 50" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Rank</label>
                    <select class="form-select" name="rank_id">
                      <option value="0">--</option>
                      <?php foreach ($ranks as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= e($r['rank_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label">Radio Designation</label>
                    <input class="form-control" name="radio_designation" placeholder="CVFD Chief 50">
                  </div>
                  <div class="col-12">
                    <button class="btn btn-danger btn-lg w-100">Add Command Staff</button>
                  </div>
                </form>

                <?php if (!$staffRows): ?>
                  <div class="text-muted">No command staff templates yet.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle">
                      <thead>
                        <tr>
                          <th>Officer</th>
                          <th>Rank ID</th>
                          <th>Radio</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($staffRows as $s): ?>
                          <tr>
                            <td class="fw-bold"><?= e($s['officer_display']) ?></td>
                            <td><?= (int)$s['rank_id'] ?></td>
                            <td><?= e($s['radio_designation'] ?? '') ?></td>
                            <td class="text-end">
                              <a class="btn btn-outline-danger btn-sm"
                                 href="?mutual_dept_id=<?= (int)$selectedMutualId ?>&del_staff=<?= (int)$s['id'] ?>"
                                 onclick="return confirm('Delete this command staff template?');">
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

              <!-- Apparatus Tab -->
              <div class="tab-pane fade" id="tabApp" role="tabpanel">
                <form method="post" class="row g-2 align-items-end mb-3">
                  <input type="hidden" name="action" value="add_app">
                  <input type="hidden" name="mutual_dept_id" value="<?= (int)$selectedMutualId ?>">
                  <div class="col-12 col-md-5">
                    <label class="form-label">Apparatus Label</label>
                    <input class="form-control" name="apparatus_label" placeholder="Eng 56" required>
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="apparatus_type_id">
                      <option value="0">--</option>
                      <?php foreach ($appTypes as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= e($t['type_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-2">
                    <label class="form-label">Staffing</label>
                    <input type="number" class="form-control" name="staffing" value="0" min="0" max="20">
                  </div>
                  <div class="col-12 col-md-2">
                    <button class="btn btn-danger btn-lg w-100">Add</button>
                  </div>
                </form>

                <?php if (!$appRows): ?>
                  <div class="text-muted">No apparatus templates yet.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle">
                      <thead>
                        <tr>
                          <th>Apparatus</th>
                          <th>Type</th>
                          <th class="text-center">FF</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($appRows as $a): ?>
                          <tr>
                            <td class="fw-bold"><?= e($a['apparatus_label']) ?></td>
                            <td><?= e($a['type_name'] ?? '') ?></td>
                            <td class="text-center"><?= (int)$a['staffing'] ?></td>
                            <td class="text-end">
                              <a class="btn btn-outline-danger btn-sm"
                                 href="?mutual_dept_id=<?= (int)$selectedMutualId ?>&del_app=<?= (int)$a['id'] ?>"
                                 onclick="return confirm('Delete this apparatus template?');">
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Master Dept Modal -->
<div class="modal fade" id="modalCreateDept" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create_master_dept">
        <div class="modal-header">
          <h5 class="modal-title">Add Mutual Aid Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">Department Name</label>
              <input class="form-control" name="department_name" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Designation</label>
              <input class="form-control" name="designation" placeholder="CVFD">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Station ID</label>
              <input class="form-control" name="station_id" placeholder="Station 5">
            </div>
          </div>
          <div class="small text-muted mt-2">After creating, use “Link Partner” on the left to attach it to your department.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
