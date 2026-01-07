<?php
// mayday_test.php - isolated Mayday harness
session_start();
$incident_id = isset($_GET['incident_id']) ? (int)$_GET['incident_id'] : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mayday Test Harness</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">

  <h3 class="mb-3">Mayday Test Harness</h3>

  <div class="mb-2">
    <label class="form-label">Incident ID</label>
    <input class="form-control" id="incidentId" value="<?= htmlspecialchars((string)$incident_id) ?>">
  </div>

  <button class="btn btn-danger" id="btnMayday">Mayday</button>

  <!-- Mayday Modal -->
  <div class="modal fade" id="maydayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">MAYDAY</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="small text-muted mb-2" id="mdState"></div>

          <div class="row g-2 mb-2">
            <div class="col-md-4">
              <label class="form-label">Who</label>
              <input class="form-control" id="mdWho">
            </div>
            <div class="col-md-4">
              <label class="form-label">Where</label>
              <input class="form-control" id="mdWhere">
            </div>
            <div class="col-md-4">
              <label class="form-label">What</label>
              <input class="form-control" id="mdWhat">
            </div>
          </div>

          <hr>
          <h6>Checklist</h6>
          <div id="mdChecklist" class="d-flex flex-wrap gap-2"></div>

          <hr>
          <button class="btn btn-outline-secondary" id="btnEnd">End Mayday</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function(){
      const btn = document.getElementById('btnMayday');
      const modalEl = document.getElementById('maydayModal');
      const mdState = document.getElementById('mdState');
      const checklistEl = document.getElementById('mdChecklist');
      const endBtn = document.getElementById('btnEnd');

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop:'static', keyboard:false });
      let activeMaydayId = null;

      function qsIncidentId(){ return (document.getElementById('incidentId').value || '').trim(); }

      function renderChecklist(items){
        checklistEl.innerHTML = '';
        items.forEach(it => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'btn btn-sm btn-outline-danger';
          b.textContent = it.label;
          b.dataset.itemId = it.id;
          b.addEventListener('click', ()=> b.classList.toggle('active'));
          checklistEl.appendChild(b);
        });
      }

      async function getState(){
        const incidentId = qsIncidentId();
        const r = await fetch('mayday_get.php?incident_id=' + encodeURIComponent(incidentId), {cache:'no-store'});
        return await r.json();
      }

      async function startMayday(){
        const incidentId = qsIncidentId();
        const r = await fetch('mayday_start.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({incident_id: incidentId})
        });
        return await r.json();
      }

      async function clearMayday(){
        if (!activeMaydayId) return {ok:true};
        const r = await fetch('mayday_clear.php', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({mayday_id: activeMaydayId})
        });
        return await r.json();
      }

      btn.addEventListener('click', async ()=>{
        try{
          const st = await getState();
          let m = st.mayday || null;
          if (!m || String(m.status||'').toUpperCase() !== 'ACTIVE'){
            const started = await startMayday();
            if (!started.ok) throw new Error('start failed');
          }
          const st2 = await getState();
          const m2 = st2.mayday || null;
          activeMaydayId = m2 && m2.id ? String(m2.id) : null;

          mdState.textContent = 'status=' + (m2?.status || 'NONE') + ' mayday_id=' + (activeMaydayId || 'null');
          renderChecklist(st2.checklist_items || []);
          modal.show();
        } catch(e){
          console.error(e);
          alert('Mayday failed. Check Console.');
        }
      });

      endBtn.addEventListener('click', async ()=>{
        if (!confirm('End Mayday and reset?')) return;
        const res = await clearMayday();
        if (!res.ok){ alert('End failed.'); return; }
        activeMaydayId = null;
        checklistEl.innerHTML = '';
        mdState.textContent = 'INACTIVE';
      });
    })();
  </script>
</body>
</html>
