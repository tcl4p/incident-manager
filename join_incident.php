<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "db_connect.php"; // must define $conn (mysqli)

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$db_error   = null;
$post_error = null;


// ------------------------------------------------------------------
// 1) Validate session department
// ------------------------------------------------------------------
if (!isset($_SESSION['dept_id'])) {
    $db_error = "Department not found in session. Please log in again.";
} else {
    $deptId = (int)$_SESSION['dept_id'];
}

$deptName = $_SESSION['dept_name'] ?? "Unknown Department";

if (!isset($conn) || !($conn instanceof mysqli)) {
    $db_error = "Database connection error.";
} else {
    $conn->set_charset('utf8mb4');
}

// ------------------------------------------------------------------
// 2) Load incident being joined
// ------------------------------------------------------------------
$incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
$incident   = null;

if ($incidentId > 0 && empty($db_error)) {
    $sqlI = "
        SELECT 
            id,
            DeptName,
            IncidentDT,
            Type,
            location
        FROM incidents
        WHERE id = ?
        LIMIT 1
    ";
    $stmtI = $conn->prepare($sqlI);
    if ($stmtI) {
        $stmtI->bind_param("i", $incidentId);
        $stmtI->execute();
        $resI = $stmtI->get_result();
        $incident = $resI->fetch_assoc();
        $stmtI->close();

        if (!$incident) {
            $db_error = "Incident not found.";
        }
    } else {
        $db_error = "Failed to prepare incident query: " . $conn->error;
    }
}

// ------------------------------------------------------------------
// 3) Load existing apparatus already assigned to this incident
// ------------------------------------------------------------------
$existingUnits = [];
if ($incidentId > 0 && empty($db_error)) {
    $sqlEx = "
        SELECT DISTINCT apparatus_id
        FROM apparatus_responding
        WHERE incident_id = ?
    ";
    $stmtEx = $conn->prepare($sqlEx);
    if ($stmtEx) {
        $stmtEx->bind_param("i", $incidentId);
        $stmtEx->execute();
        $resEx = $stmtEx->get_result();
        while ($r = $resEx->fetch_assoc()) {
            $existingUnits[] = $r['apparatus_id'];
        }
        $stmtEx->close();
    }
}

// ------------------------------------------------------------------
// 4) Handle POST → Add apparatus to incident
// ------------------------------------------------------------------
$ffCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($db_error)) {

    $appsRaw = $_POST['apparatus_ids'] ?? '';
    $selectedApps = [];

    foreach (explode(',', $appsRaw) as $id) {
        $id = (int)trim($id);
        if ($id > 0) {
            $selectedApps[] = $id;
        }
    }

    $ffCount = isset($_POST['ff_count']) ? (int)$_POST['ff_count'] : 0;

    if (empty($selectedApps)) {
        $post_error = "Please select at least one apparatus.";
    } elseif ($ffCount <= 0 || $ffCount > 5) {
        $post_error = "Please select the number of firefighters (1–5) per apparatus.";
    } else {
        try {
            $conn->begin_transaction();

            // 1) Load full info for selected apparatus
            $in = implode(',', array_fill(0, count($selectedApps), '?'));
            $typesStr = str_repeat('i', count($selectedApps) + 1);

            $sqlA = "
                SELECT id, apparatus_name, apparatus_type
                FROM department_apparatus
                WHERE dept_id = ?
                  AND id IN ($in)
            ";
            $stmtA = $conn->prepare($sqlA);
            $params = [$deptId];
            foreach ($selectedApps as $a) { $params[] = $a; }
            $stmtA->bind_param($typesStr, ...$params);
            $stmtA->execute();

            $resA = $stmtA->get_result();
            $apparatusList = [];
            while ($r = $resA->fetch_assoc()) {
                $apparatusList[] = $r;
            }
            $stmtA->close();

            if (empty($apparatusList)) {
                throw new Exception("Could not load selected apparatus information.");
            }

            // 2) Lookup apparatus_types.ID
            $stmtT = $conn->prepare("
                SELECT ID
                FROM apparatus_types
                WHERE ApparatusType = ?
                LIMIT 1
            ");

            // 3) INSERT into apparatus_responding
            $stmtAR = $conn->prepare("
                INSERT INTO apparatus_responding
                    (incident_id, apparatus_type, apparatus_id, firefighter_count, dispatch_time, status, notes)
                VALUES
                    (?, ?, ?, ?, NOW(6), 'Responding', '')
            ");

            foreach ($apparatusList as $ap) {
                $label  = $ap['apparatus_name'];
                $tLabel = $ap['apparatus_type'];

                // Skip units already on this incident
                if (in_array($label, $existingUnits, true)) {
                    continue;
                }

                $stmtT->bind_param("s", $tLabel);
                $stmtT->execute();
                $resT = $stmtT->get_result();
                $tRow = $resT->fetch_assoc();
                if (!$tRow) {
                    throw new Exception("No apparatus_types match for type '$tLabel'.");
                }

                $typeId = (int)$tRow['ID'];

                $stmtAR->bind_param("iisi", $incidentId, $typeId, $label, $ffCount);
                $stmtAR->execute();
            }

            $stmtT->close();
            $stmtAR->close();
            $conn->commit();

            header("Location: incidents.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $post_error = $e->getMessage();
        }
    }
}

// ------------------------------------------------------------------
// 5) Load department apparatus for button display
// ------------------------------------------------------------------
$deptApparatus = [];

if (empty($db_error)) {
    $sqlDA = "
        SELECT id, apparatus_name, apparatus_type
        FROM department_apparatus
        WHERE dept_id = ?
          AND is_active = 1
        ORDER BY sort_order, apparatus_name
    ";
    $stmtDA = $conn->prepare($sqlDA);
    $stmtDA->bind_param("i", $deptId);
    $stmtDA->execute();

    $resDA = $stmtDA->get_result();
    while ($r = $resDA->fetch_assoc()) {
        $deptApparatus[] = $r;
    }
    $stmtDA->close();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Join Incident</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background-color: #2a2a2a; color: #fff; }
.section-card { background-color: #1c1c1c; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem; }

.btn-choice { min-width: 140px; margin: 0.3rem; }

/* Apparatus buttons */
.btn-apparatus {
  background-color: #b8e6c4 !important;
  color: #00210e !important;
  border-color: #b8e6c4 !important;
}
.btn-apparatus.active {
  background-color: #198754 !important;
  color: #fff !important;
  border-color: #146c43 !important;
}

.btn-disabled {
  background-color: #6c757d !important;
  color: #fff !important;
  opacity: 0.6;
}

/* FF count buttons */
.ff-btn {
  min-width: 3rem;
  font-weight: 600;
}
.ff-btn.active {
  background-color: #ffc107 !important;
  color: #000 !important;
  border-color: #e0a800 !important;
}
</style>
</head>

<body>
<div class="container mt-3">

  <h2>Join Incident</h2>
  <p class="text-muted">Department: <?= e($deptName) ?></p>

  <?php if ($db_error): ?>
    <div class="alert alert-danger"><?= e($db_error) ?></div>
  <?php endif; ?>

  <?php if ($post_error): ?>
    <div class="alert alert-danger"><?= e($post_error) ?></div>
  <?php endif; ?>

  <?php if ($incident): ?>
    <div class="section-card">
      <strong>Incident #<?= $incidentId ?></strong><br>
      <?= e($incident['IncidentDT']) ?><br>
      <?= e($incident['DeptName']) ?><br>
      Location: <?= e($incident['location']) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="apparatus_ids" id="apparatus_ids">
    <input type="hidden" name="ff_count" id="ff_count" value="<?= (int)$ffCount ?>">

    <!-- Apparatus selection -->
    <div class="section-card">
      <div class="section-header h5">Select Apparatus</div>
      <div class="mb-2">
        <small class="text-muted">
          Tap each unit that is joining this incident. Units already on the incident are grayed out.
        </small>
      </div>
      <div class="d-flex flex-wrap">
        <?php foreach ($deptApparatus as $a): ?>
          <?php
            $aid   = (int)$a['id'];
            $label = $a['apparatus_name'];
            $type  = $a['apparatus_type'];
            $onIncident = in_array($label, $existingUnits, true);
          ?>

          <button type="button"
            class="btn btn-choice <?= $onIncident ? 'btn-disabled' : 'btn-apparatus' ?>"
            data-id="<?= $aid ?>"
            <?= $onIncident ? 'disabled' : '' ?>>
            <div class="fw-bold"><?= e($label) ?></div>
            <div class="small"><?= e($type) ?></div>
            <?php if ($onIncident): ?>
              <div class="small">(On incident)</div>
            <?php endif; ?>
          </button>

        <?php endforeach; ?>
      </div>
      <div class="mt-2 text-muted" id="apparatus_summary">Selected units: None</div>
    </div>

    <!-- Firefighter count -->
    <div class="section-card">
      <div class="section-header h5">Firefighters per Apparatus</div>
      <div class="mb-2">
        <small class="text-muted">
          Select the number of firefighters riding on each unit you are adding (1–5).
        </small>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <button
            type="button"
            class="btn btn-outline-warning ff-btn <?= ($ffCount === $i ? 'active' : '') ?>"
            data-count="<?= $i ?>">
            <?= $i ?>
          </button>
        <?php endfor; ?>
      </div>
      <div class="mt-2 text-muted" id="ff_count_summary">
        <?= $ffCount > 0
            ? 'Firefighters per apparatus: ' . (int)$ffCount
            : 'Firefighters per apparatus: Not set' ?>
      </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <a href="incidents.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
      <button type="submit" class="btn btn-success btn-lg">Accept</button>
    </div>
  </form>

</div>

<script>
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn-apparatus');
  if (!btn) return;

  btn.classList.toggle('active');

  const hidden = document.getElementById('apparatus_ids');
  const summary = document.getElementById('apparatus_summary');
  const ids = [];

  document.querySelectorAll('.btn-apparatus.active').forEach(b => {
    ids.push(b.getAttribute('data-id'));
  });

  hidden.value = ids.join(',');
  if (summary) {
    summary.textContent = ids.length === 0
      ? 'Selected units: None'
      : 'Selected units: ' + ids.length;
  }
});

// FF count buttons
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.ff-btn');
  if (!btn) return;

  const count = parseInt(btn.getAttribute('data-count'), 10);
  if (!count) return;

  document.querySelectorAll('.ff-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const hidden = document.getElementById('ff_count');
  const summary = document.getElementById('ff_count_summary');

  if (hidden) hidden.value = count;
  if (summary) summary.textContent = 'Firefighters per apparatus: ' + count;
});
</script>
</body>
</html>
