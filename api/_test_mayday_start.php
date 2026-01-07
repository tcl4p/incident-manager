<?php
// TEMP TEST PAGE - delete after test
$incident_id = 41;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Test Mayday Start</title></head>
<body>
  <h3>Test Mayday Start (POST)</h3>

  <form method="post" action="mayday_start.php">
    <label>incident_id</label>
    <input type="number" name="incident_id" value="<?php echo (int)$incident_id; ?>" />
    <button type="submit">Send POST</button>
  </form>

</body>
</html>
“Let’s pick up with making sure a firefighter exists for an active Mayday, then wire the Confirm Mayday button.”