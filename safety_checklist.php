<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "db_connect.php"; // must define $conn (mysqli)

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Keep long checklist descriptions readable on tablets by showing a shorter label,
// but preserve the full text in a tooltip (title attribute).
function short_label($text, $max = 64) {
  $t = trim((string)$text);
  if ($t === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    return (mb_strlen($t) > $max) ? (mb_substr($t, 0, $max - 1) . '…') : $t;
  }
  return (strlen($t) > $max) ? (substr($t, 0, $max - 1) . '…') : $t;
}

$incidentId = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
if ($incidentId <= 0) {
  http_response_code(400);
  echo "Missing or invalid incident_id.";
  exit;
}

/**
 * POST handlers
 * - toggle: toggle a checklist item (incident_id, checklist_id, is_checked)
 * - save_notes: save top-of-page notes (incident_id, safety_notes)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? (string)$_POST['action'] : 'toggle';
  $postIncidentId = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;

  // Save the top Notes textarea to incidents.safety_checklist_notes
  if ($action === 'save_notes') {
    $notes = isset($_POST['safety_notes']) ? trim((string)$_POST['safety_notes']) : '';
    if ($postIncidentId > 0) {
      $sql = "UPDATE incidents SET safety_checklist_notes = ? WHERE id = ?";
      $stmt = $conn->prepare($sql);
      if ($stmt) {
        $stmt->bind_param("si", $notes, $postIncidentId);
        $stmt->execute();
        $stmt->close();
      }
    }
    header("Location: safety_checklist.php?incident_id=" . (int)$postIncidentId);
    exit;
  }

  // Default: toggle checklist item
  $checklistId = isset($_POST['checklist_id']) ? (int)$_POST['checklist_id'] : 0;
  $isChecked   = isset($_POST['is_checked']) ? (int)$_POST['is_checked'] : 0;

  if ($postIncidentId > 0 && $checklistId > 0) {
    // If row exists -> update, else insert
    $sql = "SELECT id FROM incident_checklist_responses WHERE incident_id = ? AND checklist_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
      $stmt->bind_param("ii", $postIncidentId, $checklistId);
      $stmt->execute();
      $res = $stmt->get_result();
      $existing = $res ? $res->fetch_assoc() : null;
      $stmt->close();

      if ($existing && isset($existing['id'])) {
        $sql = "UPDATE incident_checklist_responses SET is_checked = ?, updated_at = NOW(6) WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
          $id = (int)$existing['id'];
          $stmt->bind_param("ii", $isChecked, $id);
          $stmt->execute();
          $stmt->close();
        }
      } else {
        $sql = "INSERT INTO incident_checklist_responses (incident_id, checklist_id, is_checked, updated_at)
                VALUES (?, ?, ?, NOW(6))";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
          $stmt->bind_param("iii", $postIncidentId, $checklistId, $isChecked);
          $stmt->execute();
          $stmt->close();
        }
      }
    }
  }

  header("Location: safety_checklist.php?incident_id=" . (int)$postIncidentId);
  exit;
}

// -----------------------------
// Load incident header info
// -----------------------------
$incident = null;
$sql = "SELECT id, location, IncidentDT, status,
               incident_commander_display,
               safety_officer_display,
               safety_checklist_notes
        FROM incidents
        WHERE id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  die("DB error preparing incident query.");
}
$stmt->bind_param("i", $incidentId);
$stmt->execute();
$res = $stmt->get_result();
$incident = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$incident) {
  http_response_code(404);
  echo "Incident not found.";
  exit;
}

// -----------------------------
// Load checklist items + responses
// NOTE: response column is is_checked in your schema
// -----------------------------
$items = [];
$sql = "
  SELECT
    ci.id,
    ci.description,
    ci.upon_arrival_only,
    ci.active,
    COALESCE(r.is_checked, 0) AS is_checked
  FROM checklist_items ci
  LEFT JOIN incident_checklist_responses r
    ON r.checklist_id = ci.id
   AND r.incident_id = ?
  WHERE ci.category = 'Safety Officer'
    AND ci.active = 1
  ORDER BY ci.upon_arrival_only DESC, ci.id ASC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  die("DB error preparing checklist query.");
}
$stmt->bind_param("i", $incidentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $items[] = $row;
}
$stmt->close();

$total = count($items);
$done = 0;
foreach ($items as $it) { if ((int)$it['is_checked'] === 1) $done++; }

// Simple date display
$incidentDT = $incident['IncidentDT'] ? date("m/d/Y H:i", strtotime($incident['IncidentDT'])) : "";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Safety Officer Checklist</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6f7; }
    .page-wrap { max-width: 980px; margin: 0 auto; }
    .big-check { transform: scale(1.4); }
    .item-row { border-radius: 14px; }
    .tap-card { border-radius: 16px; }
    .btn-big { padding: .9rem 1.2rem; font-size: 1.05rem; border-radius: 14px; }
    .btn-status { min-width: 132px; }
    /* Clamp long checklist items so they don't dominate the row */
    .desc-trunc {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.2;
      max-width: 34rem;
    }
  </style>
</head>
<body>
<div class="container-fluid p-3">
<div class="page-wrap">

  <!-- Header row: title/incident info on the left, progress + return on the top-right -->
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
    <div class="flex-grow-1">
      <div class="h4 mb-1">Safety Officer Checklist</div>
      <div class="text-muted">
        Incident #<?= (int)$incident['id'] ?> — <?= e($incident['location']) ?>
        <?php if ($incidentDT): ?> · <?= e($incidentDT) ?><?php endif; ?>
        <?php if (!empty($incident['status'])): ?> · Status: <?= e($incident['status']) ?><?php endif; ?>
      </div>
      <div class="mt-2">
        <span class="badge text-bg-dark me-2">IC: <?= e($incident['incident_commander_display'] ?? '—') ?></span>
        <span class="badge text-bg-primary">Safety: <?= e($incident['safety_officer_display'] ?? '—') ?></span>
      </div>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="badge text-bg-success fs-6"><?= (int)$done ?>/<?= (int)$total ?></span>
      <a class="btn btn-secondary btn-big" href="command_board.php?incident_id=<?= (int)$incidentId ?>">Return to Command Board</a>
    </div>
  </div>

  <!-- Notes: full-width under the heading for more writing room -->
  <form method="post" class="mb-3">
    <input type="hidden" name="action" value="save_notes">
    <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
    <label for="safety_notes" class="form-label fw-semibold mb-1">Notes</label>
    <textarea id="safety_notes" name="safety_notes" class="form-control" rows="5" placeholder="Safety Officer notes / hazards / reminders…"><?= e($incident['safety_checklist_notes'] ?? '') ?></textarea>
    <div class="form-text mt-2">Saved to this incident (visible to Safety + IC).</div>
    <div class="d-flex justify-content-end mt-2">
      <button type="submit" class="btn btn-outline-primary">Save Notes</button>
    </div>
  </form>

  <?php if ($total === 0): ?>
    <div class="alert alert-warning">
      No Safety Officer checklist items found. Make sure checklist_items has rows with category = <b>Safety Officer</b>.
    </div>
  <?php else: ?>
    <div class="row g-3">
      <div class="col-12">
        <?php foreach ($items as $it): ?>
          <?php
            $checked = ((int)$it['is_checked'] === 1);
            $cardClass = $checked ? "border-success" : "border-0";
            $bg = $checked ? "bg-success-subtle" : "bg-white";
            $btnClass = $checked ? "btn-success text-white" : "btn-danger text-white";
            $btnText  = $checked ? "DONE" : "OPEN";
            $fullDesc = (string)($it['description'] ?? '');
            $shortDesc = short_label($fullDesc, 64);
          ?>
          <div class="card tap-card shadow-sm <?= $cardClass ?> <?= $bg ?> mb-3">
            <div class="card-body d-flex align-items-center gap-3">
              <form method="post" class="m-0">
                <input type="hidden" name="incident_id" value="<?= (int)$incidentId ?>">
                <input type="hidden" name="checklist_id" value="<?= (int)$it['id'] ?>">
                <input type="hidden" name="is_checked" value="<?= $checked ? 0 : 1 ?>">
                <button type="submit" class="btn <?= $btnClass ?> btn-big btn-status" aria-label="Toggle checklist item">
                  <?= e($btnText) ?>
                </button>
              </form>

              <div class="flex-grow-1">
                <div class="fw-semibold desc-trunc" title="<?= e($fullDesc) ?>">
                  <?= e($shortDesc) ?>
                </div>
                <?php if ((int)$it['upon_arrival_only'] === 1): ?>
                  <div class="text-muted small">Upon arrival</div>
                <?php else: ?>
                  <div class="text-muted small">Ongoing</div>
                <?php endif; ?>
              </div>

              <div class="text-end">
                <?php if ($checked): ?>
                  <span class="badge text-bg-success">Done</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Open</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  <?php endif; ?>

</div><!-- /.page-wrap -->
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
