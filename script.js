/* ============================================================
   FILE:    assets/js/script.js
   VERSION: Updated for materials.php UI fix.
   CHANGES:
     - deleteMaterial() added: uses fetch() POST instead of <form>
       so the delete button is a plain <button> — no block wrapper.
     - searchProject() and clearSearch() now use the `hidden`
       HTML attribute instead of .visible CSS class, because
       .mat-no-results has display:flex in style.css and the
       CSS override approach was unreliable.
     - Guard added: searchProject() does nothing on empty input.
   ============================================================ */


/* ── 1. GENERIC MODAL HELPERS (my_projects.php) ─────────────── */

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('active');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('active');
}

function downloadDocument(projectId) {
  const link = document.createElement('a');
  link.href     = '../assets/docs/project_' + projectId + '_docs.pdf';
  link.download = 'Project_' + projectId + '_Documents.pdf';
  link.click();
}


/* ── 2. WORKERS.PHP MODALS ──────────────────────────────────── */

function openWorkerModal() {
  const el = document.getElementById('workerModal');
  if (el) el.classList.add('active');
}

function closeWorkerModal() {
  const el = document.getElementById('workerModal');
  if (el) el.classList.remove('active');
}

function openAssignModal(workerId, workerName) {
  const nameEl = document.getElementById('assignWorkerName');
  if (nameEl) nameEl.textContent = workerName;
  const el = document.getElementById('assignModal');
  if (el) el.classList.add('active');
}

function closeAssignModal() {
  const el = document.getElementById('assignModal');
  if (el) el.classList.remove('active');
}

/* Auto-open hire modal after failed submission (workers.php) */
document.addEventListener('DOMContentLoaded', function () {
  const grid = document.querySelector('.worker-grid');
  if (grid && grid.dataset.openModal === 'true') {
    openWorkerModal();
  }
});


/* ── 3. MATERIALS.PHP ───────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', function () {

  /* ── 3a. Search ─────────────────────────────────────────── */

  const searchInput = document.getElementById('projectSearch');
  const clearBtn    = document.getElementById('searchClear');
  const noResults   = document.getElementById('noResults');
  const searchTerm  = document.getElementById('searchTerm');

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      searchProject(this.value);
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', clearSearch);
  }

  function searchProject(val) {
    const query = val.trim().toLowerCase();

    /* Guard: do nothing on empty input — no "not found" on blank */
    if (!query) {
      clearSearch();
      return;
    }

    /* Show clear button */
    if (clearBtn) clearBtn.removeAttribute('hidden');

    let found = 0;
    document.querySelectorAll('.mat-project-block').forEach(function (block) {
      const h3   = block.querySelector('h3');
      const name = h3 ? h3.textContent.toLowerCase() : '';
      const match = name.includes(query);

      /* Use hidden attribute — overrides any CSS display value */
      if (match) {
        block.removeAttribute('hidden');
        found++;
      } else {
        block.setAttribute('hidden', '');
      }
    });

    /* Show/hide no-results message */
    if (noResults) {
      if (found === 0) {
        if (searchTerm) searchTerm.textContent = val.trim();
        noResults.removeAttribute('hidden');
      } else {
        noResults.setAttribute('hidden', '');
      }
    }
  }

  function clearSearch() {
    if (searchInput) searchInput.value = '';

    /* Hide clear button and no-results */
    if (clearBtn)   clearBtn.setAttribute('hidden', '');
    if (noResults)  noResults.setAttribute('hidden', '');

    /* Show first block only, hide rest */
    document.querySelectorAll('.mat-project-block').forEach(function (block, i) {
      if (i === 0) {
        block.removeAttribute('hidden');
      } else {
        block.setAttribute('hidden', '');
      }
    });
  }

  /* ── 3b. Delete material via fetch() ────────────────────── */
  /*
   * deleteMaterial() replaces the <form> wrapper approach.
   * The delete button is now a plain <button class="delete-btn">
   * matching the original HTML exactly — no block-level form
   * stretching it to full cell width.
   *
   * Flow:
   *   1. User clicks delete-btn
   *   2. confirm() dialog shown
   *   3. fetch() POSTs to materials.php with action=delete_material
   *   4. PHP returns JSON {ok: true}
   *   5. JS removes the <tr> from the DOM — no page reload needed
   *   6. If only row in table, swap table for "no materials" message
   */
  window.deleteMaterial = function (btn) {
    const matId   = btn.dataset.matId;
    const matName = btn.dataset.matName || 'this material';

    if (!confirm('Delete ' + matName + '?')) return;

    fetch('materials.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=delete_material&mat_id=' + encodeURIComponent(matId)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (data.ok) {
        /* Remove the table row */
        const row = btn.closest('tr');
        if (row) {
          const tbody = row.closest('tbody');
          row.remove();

          /* If no rows left, replace table with "no materials" message */
          if (tbody && tbody.querySelectorAll('tr').length === 0) {
            const table   = tbody.closest('table');
            const block   = table ? table.closest('.mat-project-block') : null;
            if (table && block) {
              const msg = document.createElement('p');
              msg.className   = 'mat-empty-row';
              msg.textContent = 'No materials recorded for this project yet.';
              table.replaceWith(msg);

              /* Update badge count to 0 */
              const badge = block.querySelector('.mat-project-badge');
              if (badge) badge.textContent = '0 materials';
            }
          } else {
            /* Update badge count */
            const block = btn.closest('.mat-project-block');
            if (block) {
              const remaining = block.querySelectorAll('tbody tr').length;
              const badge     = block.querySelector('.mat-project-badge');
              if (badge) {
                badge.textContent = remaining + ' material' + (remaining !== 1 ? 's' : '');
              }
            }
          }

          /* ── Recalculate project subtotal and overall total ── */
          const block2 = btn.closest('.mat-project-block');
          if (block2) {
            let projSum = 0;
            block2.querySelectorAll('.mat-total-cell').forEach(function (cell) {
              projSum += parseFloat(cell.dataset.rowTotal || 0);
            });
            const footer = block2.querySelector('.mat-proj-total-value');
            if (footer) {
              footer.textContent = '$' + projSum.toLocaleString('en-US',
                { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            block2.dataset.projectTotal = projSum.toFixed(2);
          }

          let grandTotal = 0;
          document.querySelectorAll('.mat-project-block').forEach(function (b) {
            grandTotal += parseFloat(b.dataset.projectTotal || 0);
          });
          const overallEl = document.getElementById('overallCostDisplay');
          if (overallEl) {
            overallEl.textContent = '$' + grandTotal.toLocaleString('en-US',
              { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          }
        }
      } else {
        alert('Delete failed. Please try again.');
      }
    })
    .catch(function () {
      alert('Network error. Please check your connection and try again.');
    });
  };

  /* ── 3c. Add Material Modal ─────────────────────────────── */

  const addModal     = document.getElementById('addMaterialModal');
  const closeAddBtn  = document.getElementById('closeAddModal');
  const cancelAddBtn = document.getElementById('cancelAddModal');

  window.openAddModal = function (btn) {
    const projId = btn ? btn.dataset.projectId : null;
    const select = document.getElementById('addProjectSelect');
    if (select && projId) {
      for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === String(projId)) {
          select.selectedIndex = i;
          break;
        }
      }
    }
    if (addModal) addModal.classList.add('active');
  };

  function closeAddModal() {
    if (addModal) addModal.classList.remove('active');
  }

  if (closeAddBtn)  closeAddBtn.addEventListener('click',  closeAddModal);
  if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAddModal);
  if (addModal) {
    addModal.addEventListener('click', function (e) {
      if (e.target === addModal) closeAddModal();
    });
  }

  /* ── 3d. Edit Material Modal ────────────────────────────── */

  const editModal     = document.getElementById('editMaterialModal');
  const closeEditBtn  = document.getElementById('closeEditModal');
  const cancelEditBtn = document.getElementById('cancelEditModal');

  window.openEditModal = function (btn) {
    if (!btn || !editModal) return;

    const set = function (id, val) {
      const el = document.getElementById(id);
      if (el) el.value = val || '';
    };

    set('editMatId',   btn.dataset.matId);
    set('editMaterial',btn.dataset.matName);
    set('editQty',     btn.dataset.matQty);
    set('editUnit',    btn.dataset.matUnit);
    set('editCost',    btn.dataset.matCost);
    set('editDate',    btn.dataset.matDate);
    set('editProject', btn.dataset.projName);

    const statusSel = document.getElementById('editStatus');
    if (statusSel) statusSel.value = btn.dataset.matStatus || 'in_stock';

    editModal.classList.add('active');
  };

  function closeEditModal() {
    if (editModal) editModal.classList.remove('active');
  }

  if (closeEditBtn)  closeEditBtn.addEventListener('click',  closeEditModal);
  if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);
  if (editModal) {
    editModal.addEventListener('click', function (e) {
      if (e.target === editModal) closeEditModal();
    });
  }

}); /* end DOMContentLoaded */
/* ============================================================ */


/* ── 4. CONTRACTOR / MILESTONE.PHP ─────────────────────────────
   Functions are prefixed "Milestone" to avoid collision with
   workers.php openAssignModal(workerId, workerName).
   ──────────────────────────────────────────────────────────── */

/** Open the Assign New Task modal */
function openMilestoneAssignModal() {
  const el = document.getElementById('assignTaskModal');
  if (el) el.classList.add('active');
}

/** Close the Assign New Task modal */
function closeMilestoneAssignModal() {
  const el = document.getElementById('assignTaskModal');
  if (el) el.classList.remove('active');
}

/** Open the Update Progress modal, pre-populated with task data */
function openMilestoneProgressModal(taskName, status) {
  const nameEl = document.getElementById('progressTaskName');
  if (nameEl) nameEl.textContent = taskName;

  const sel = document.getElementById('progressStatus');
  if (sel) {
    // Match the status value to the correct option
    const map = {
      'pending':     'pending',
      'in_progress': 'in_progress',
      'completed':   'completed',
    };
    sel.value = map[status] || 'pending';
  }

  const el = document.getElementById('progressModal');
  if (el) el.classList.add('active');
}

/** Close the Update Progress modal */
function closeMilestoneProgressModal() {
  const el = document.getElementById('progressModal');
  if (el) el.classList.remove('active');
}

/* Auto-open Assign modal after a failed POST submission.
   PHP sets data-open-assign="true" on .milestones-list. */
document.addEventListener('DOMContentLoaded', function () {
  const list = document.querySelector('.milestones-list');
  if (list && list.dataset.openAssign === 'true') {
    openMilestoneAssignModal();
  }
});
/* ──────────────────────────────────────────────────────────── */


/* ── 5. MILESTONE PROGRESS BAR WIDTH ───────────────────────────
   Reads data-pct attribute from .ms-progress-fill elements and
   sets their CSS width. Using JS (not inline style) keeps the
   PHP file free of style= attributes while still allowing
   per-element dynamic widths that CSS alone cannot express
   (CSS custom properties on individual elements would also work
   but this approach is simpler and more beginner-friendly).
──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.ms-progress-fill').forEach(function (el) {
    const pct = parseInt(el.dataset.pct, 10) || 0;
    el.style.width = pct + '%';
  });
});
/* ──────────────────────────────────────────────────────────── */


/* ── 6. MILESTONE — PROGRESS INPUT VISIBILITY ──────────────────
   The % input field in the update row should only be visible
   when status = in_progress. For pending/completed, PHP forces
   the value so the field would confuse the contractor.
   JS hides/shows it when the status select changes.
──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

  // Set initial visibility for all status selects on page load
  document.querySelectorAll('.ms-status-select').forEach(function (sel) {
    // Only act on selects that are inside a form (the card update forms,
    // not the progressModal select which has no sibling input)
    const input = sel.closest('.ms-update-row')
                    ? sel.closest('.ms-update-row').querySelector('.ms-progress-input')
                    : null;
    if (!input) return;

    toggleProgressInput(sel, input);

    sel.addEventListener('change', function () {
      toggleProgressInput(sel, input);
    });
  });

  function toggleProgressInput(sel, input) {
    if (sel.value === 'in_progress') {
      input.removeAttribute('hidden');
    } else {
      input.setAttribute('hidden', '');
    }
  }
});
/* ──────────────────────────────────────────────────────────── */


/* ── 7. CONTRACTOR / WORKLOGS.PHP ──────────────────────────────
   openLogModal / closeLogModal replace the inline <script> that
   was in the original PHP file.
   Dynamic worker + task dropdowns use fetch() so the page does
   not reload when the project select changes.
──────────────────────────────────────────────────────────────── */

/* Explicitly window-scoped so onclick="" attributes can call them
   from any HTML context without module/strict-mode issues. */
window.openLogModal = function () {
  const el = document.getElementById('logModal');
  if (el) el.classList.add('active');
};

window.closeLogModal = function () {
  const el = document.getElementById('logModal');
  if (el) el.classList.remove('active');
};

document.addEventListener('DOMContentLoaded', function () {

  /* Auto-open modal on page load after a failed POST */
  const wrapper = document.querySelector('.worklogs-wrapper');
  if (wrapper && wrapper.dataset.openLog === 'true') {
    openLogModal();
  }

  /* ── Dynamic worker + task dropdowns ─────────────────────── */
  const projectSel = document.getElementById('logProject');
  const workerSel  = document.getElementById('logWorker');
  const taskSel    = document.getElementById('logTask');

  if (!projectSel) return;

  projectSel.addEventListener('change', function () {
    const projId = this.value;

    /* Reset both dependent selects while loading */
    setSelectLoading(workerSel, 'Loading workers...');
    setSelectLoading(taskSel,   'Loading tasks...');

    /* Fetch workers for selected project */
    postFetch('worklogs.php', { action: 'get_workers', project_id: projId })
      .then(function (workers) {
        workerSel.innerHTML = '<option value="0">— None —</option>';
        workers.forEach(function (w) {
          const opt = document.createElement('option');
          opt.value       = w.worker_id;
          opt.textContent = w.worker_name;
          workerSel.appendChild(opt);
        });
        if (workers.length === 0) {
          workerSel.innerHTML = '<option value="0">No workers on this project</option>';
        }
      });

    /* Fetch tasks for selected project */
    postFetch('worklogs.php', { action: 'get_tasks', project_id: projId })
      .then(function (tasks) {
        taskSel.innerHTML = '<option value="0">— None —</option>';
        tasks.forEach(function (t) {
          const opt = document.createElement('option');
          opt.value       = t.id;
          opt.textContent = t.title;
          taskSel.appendChild(opt);
        });
        if (tasks.length === 0) {
          taskSel.innerHTML = '<option value="0">No open tasks on this project</option>';
        }
      });
  });

  /* Helper: POST key/value pairs as form-encoded, return parsed JSON */
  function postFetch(url, data) {
    const body = Object.entries(data)
      .map(([k, v]) => encodeURIComponent(k) + '=' + encodeURIComponent(v))
      .join('&');

    return fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body
    })
    .then(function (res) { return res.json(); })
    .catch(function ()   { return []; });
  }

  /* Helper: put a disabled placeholder option in a select */
  function setSelectLoading(sel, msg) {
    if (sel) {
      sel.innerHTML = '<option value="0" disabled>' + msg + '</option>';
    }
  }

});
/* ──────────────────────────────────────────────────────────── */


/* ── 8. CONTRACTOR / WORKERS.PHP — ASSIGN TASK MODAL ───────────
   openTaskAssignModal(btn) is called from each worker card's
   "Assign Task" button. The button carries data attributes:
     data-worker-id      → worker's DB user ID
     data-worker-name    → worker's full display name
     data-project-ids    → comma-separated project IDs this worker
                           is assigned to (e.g. "1,3")

   The modal:
   1. Pre-fills the hidden worker_id input
   2. Shows the worker name as a subtitle
   3. Filters the project dropdown to only projects this worker is on
   4. When project changes → fetches milestones via fetch() POST
      (action=get_milestones_for_project)
   5. Populates milestone dropdown from the response

   closeTaskAssignModal() — closes the modal.
   closeAssignModal() — kept for backwards compatibility (workers.php
   may still call it from the old placeholder modal).
──────────────────────────────────────────────────────────────── */

window.openTaskAssignModal = function (btn) {
  const workerId    = btn.dataset.workerId;
  const workerName  = btn.dataset.workerName;
  const projectIds  = (btn.dataset.projectIds || '').split(',').filter(Boolean);

  /* Set hidden worker_id */
  const workerInput = document.getElementById('taskWorkerIdInput');
  if (workerInput) workerInput.value = workerId;

  /* Show worker name under heading */
  const nameEl = document.getElementById('taskAssignWorkerName');
  if (nameEl) nameEl.textContent = workerName;

  /* Filter project dropdown to only this worker's projects */
  const projSelect = document.getElementById('taskProjectSelect');
  if (projSelect) {
    /* Reset */
    projSelect.innerHTML = '<option value="" disabled selected>Select project</option>';
    /* Add only matching options from the full list embedded in all <option>s */
    /* The select was pre-populated by PHP with all contractor projects.
       We clone those options and filter by matching project IDs. */
    const allOptions = Array.from(
      document.querySelectorAll('#taskProjectSelect-source option')
    );
    if (allOptions.length > 0) {
      allOptions.forEach(function (opt) {
        if (projectIds.includes(opt.value)) {
          projSelect.appendChild(opt.cloneNode(true));
        }
      });
    } else {
      /* Fallback: the source list doesn't exist — show all options
         from the modal's own select (they were set by PHP) */
      projectIds.forEach(function (pid) {
        const opt = document.createElement('option');
        opt.value = pid;
        opt.textContent = 'Project #' + pid;
        projSelect.appendChild(opt);
      });
    }
  }

  /* Reset milestone dropdown */
  const msSelect = document.getElementById('taskMilestoneSelect');
  if (msSelect) {
    msSelect.innerHTML = '<option value="0">— Select project first —</option>';
  }

  const modal = document.getElementById('taskAssignModal');
  if (modal) modal.classList.add('active');
};

window.closeTaskAssignModal = function () {
  const modal = document.getElementById('taskAssignModal');
  if (modal) modal.classList.remove('active');
};

/* Milestone AJAX: when project changes in the Assign Task modal */
document.addEventListener('DOMContentLoaded', function () {

  /* ── Auto-open after failed task assignment POST ── */
  const grid = document.querySelector('.worker-grid');
  if (grid && grid.dataset.openTask === 'true') {
    /* Re-open modal if task_error occurred — PHP sets data-open-task */
    const modal = document.getElementById('taskAssignModal');
    if (modal) modal.classList.add('active');
  }

  /* ── Project → Milestone fetch ── */
  const taskProjSel = document.getElementById('taskProjectSelect');
  const taskMsSel   = document.getElementById('taskMilestoneSelect');

  if (taskProjSel && taskMsSel) {

    taskProjSel.addEventListener('change', function () {
      const projId = this.value;
      if (!projId) return;

      taskMsSel.innerHTML = '<option value="0" disabled>Loading…</option>';

      fetch('workers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_milestones_for_project&project_id=' + encodeURIComponent(projId)
      })
      .then(function (res) { return res.json(); })
      .then(function (milestones) {
        taskMsSel.innerHTML = '<option value="0">— No milestone —</option>';
        if (milestones.length === 0) {
          taskMsSel.innerHTML += '<option disabled>No active milestones for this project</option>';
        } else {
          milestones.forEach(function (ms) {
            const opt = document.createElement('option');
            opt.value       = ms.id;
            opt.textContent = ms.title;
            taskMsSel.appendChild(opt);
          });
        }
      })
      .catch(function () {
        taskMsSel.innerHTML = '<option value="0">— Failed to load —</option>';
      });
    });

    /* Close on overlay click */
    const modal = document.getElementById('taskAssignModal');
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) window.closeTaskAssignModal();
      });
    }
  }
});
/* ──────────────────────────────────────────────────────────── */


/* ── 9. CONTRACTOR / MILESTONE.PHP — TASK PANEL + ASSIGN MODAL ─
   Three new window-scoped functions:
     toggleMsTaskPanel(btn)  → show/hide task rows under a card
     openMsAssignModal(btn)  → open Assign Task modal, pre-fill
                               project/milestone, fetch workers
     closeMsAssignModal()    → close the modal

   Also: DOMContentLoaded auto-reopens modal on failed POST.
──────────────────────────────────────────────────────────────── */

/**
 * Toggle the task panel under a milestone card.
 * btn must carry: data-panel-id
 * Removes/adds the HTML `hidden` attribute on the panel div.
 */
window.toggleMsTaskPanel = function (btn) {
  const panelId = btn.dataset.panelId;
  if (!panelId) return;
  const panel = document.getElementById(panelId);
  if (!panel) return;

  if (panel.hasAttribute('hidden')) {
    panel.removeAttribute('hidden');
    btn.innerHTML = btn.innerHTML.replace('View Tasks', 'Hide Tasks');
    btn.querySelector('i').className = 'fas fa-eye-slash';
  } else {
    panel.setAttribute('hidden', '');
    btn.innerHTML = btn.innerHTML.replace('Hide Tasks', 'View Tasks');
    btn.querySelector('i').className = 'fas fa-eye';
  }
};

/**
 * Open the Assign Task modal for a specific milestone.
 * btn must carry:
 *   data-ms-id      → milestone DB id
 *   data-ms-title   → milestone title (display only)
 *   data-proj-id    → project DB id
 *   data-proj-name  → project name (display only)
 *
 * Steps:
 *   1. Set hidden inputs (project_id, milestone_id)
 *   2. Set subtitle text
 *   3. Set form action URL with correct project_id
 *   4. Show modal
 *   5. Fetch workers via AJAX → populate worker select
 */
window.openMsAssignModal = function (btn) {
  const msId    = btn.dataset.msId;
  const msTitle = btn.dataset.msTitle;
  const projId  = btn.dataset.projId;
  const projName= btn.dataset.projName;

  /* Set hidden fields */
  const msInput   = document.getElementById('msAssignMilestoneId');
  const projInput = document.getElementById('msAssignProjectId');
  if (msInput)   msInput.value   = msId;
  if (projInput) projInput.value = projId;

  /* Subtitle: "Milestone: Roofing — Project: House Construction" */
  const subtitle = document.getElementById('msAssignModalSubtitle');
  if (subtitle) {
    subtitle.textContent = 'Milestone: ' + msTitle + ' — Project: ' + projName;
  }

  /* Update form action to preserve project_id in URL */
  const form = document.getElementById('msAssignForm');
  if (form) {
    form.action = 'milestone.php?project_id=' + encodeURIComponent(projId);
  }

  /* Reset worker dropdown while loading */
  const workerSel = document.getElementById('msAssignWorkerSelect');
  if (workerSel) {
    workerSel.innerHTML = '<option value="" disabled selected>Loading workers…</option>';
  }

  /* Show modal */
  const modal = document.getElementById('msAssignModal');
  if (modal) modal.classList.add('active');

  /* Fetch workers for this project */
  fetch('milestone.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    'action=get_workers_for_project&project_id=' + encodeURIComponent(projId)
  })
  .then(function (res) { return res.json(); })
  .then(function (workers) {
    if (!workerSel) return;
    if (workers.length === 0) {
      workerSel.innerHTML = '<option value="" disabled>No workers on this project yet</option>';
    } else {
      workerSel.innerHTML = '<option value="" disabled selected>Select worker</option>';
      workers.forEach(function (w) {
        const opt = document.createElement('option');
        opt.value       = w.id;
        opt.textContent = w.worker_name;
        workerSel.appendChild(opt);
      });
    }
  })
  .catch(function () {
    if (workerSel) {
      workerSel.innerHTML = '<option value="" disabled>Failed to load workers</option>';
    }
  });
};

/** Close the Assign Task modal */
window.closeMsAssignModal = function () {
  const modal = document.getElementById('msAssignModal');
  if (modal) modal.classList.remove('active');
};

/* Auto-reopen modal if assign_task POST failed.
   PHP sets data-open-assign="true" + data-posted-ms-id + data-posted-proj-id
   on .ms-project-filter div. */
document.addEventListener('DOMContentLoaded', function () {
  const filter = document.querySelector('.ms-project-filter');
  if (!filter || filter.dataset.openAssign !== 'true') return;

  /* Find the milestone card whose ms_id matches posted_ms_id */
  const msId   = filter.dataset.postedMsId;
  const projId = filter.dataset.postedProjId;
  if (!msId || msId === '0') return;

  /* Find the Assign Task button for that milestone */
  const btn = document.querySelector(
    '.ms-assign-task-btn[data-ms-id="' + msId + '"]'
  );
  if (btn) {
    window.openMsAssignModal(btn);
  }

  /* Close modal on overlay click */
  const modal = document.getElementById('msAssignModal');
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) window.closeMsAssignModal();
    });
  }
});
/* ──────────────────────────────────────────────────────────── */


/* ── 10. ADMIN / PROJECT_REQUESTS.PHP ──────────────────────────
   openApproveModal, addMilestone, removeMilestone were originally
   inline <script> in the PHP file. Moved here per no-inline-JS rule.
   openApproveModal now also populates the addMilestone container
   with an initial empty milestone row so the form is never blank.
──────────────────────────────────────────────────────────────── */

window.openApproveModal = function (btn) {
  const modal = document.getElementById('approveModal');
  if (!modal) return;

  /* Fill in the hidden request_id and display fields */
  const reqId    = btn.dataset.reqId;
  const projName = btn.dataset.projName;
  const projDesc = btn.dataset.projDesc;
  const budget   = btn.dataset.budget;
  const deadline = btn.dataset.deadline;

  const reqIdEl = document.getElementById('approveReqId');
  if (reqIdEl) reqIdEl.value = reqId;

  const nameEl = document.getElementById('approveProjectName');
  if (nameEl) nameEl.textContent = projName;

  const descEl = document.getElementById('approveProjectDesc');
  if (descEl) descEl.textContent = projDesc;

  const budgetEl = document.getElementById('approveBudget');
  if (budgetEl && budget) budgetEl.value = budget;

  const endDateEl = document.getElementById('approveEndDate');
  if (endDateEl && deadline) endDateEl.value = deadline;

  /* Add one blank milestone row if container is empty */
  const container = document.getElementById('milestones-container');
  if (container && container.children.length === 0) {
    window.addMilestone();
  }

  modal.classList.add('active');
};

window.closeApproveModal = function () {
  const modal = document.getElementById('approveModal');
  if (modal) modal.classList.remove('active');
};

window.addMilestone = function () {
  const container = document.getElementById('milestones-container');
  if (!container) return;

  const div = document.createElement('div');
  div.className = 'milestone-item';

  /* Template includes priority dropdown per requirement */
  div.innerHTML = `
    <div class="field-group">
      <label>Milestone Name</label>
      <input type="text" name="milestone_name[]" placeholder="e.g. Foundation, Roofing" required>
    </div>
    <div class="form-row">
      <div class="field-group">
        <label>Start Date</label>
        <input type="date" name="milestone_start[]">
      </div>
      <div class="field-group">
        <label>End Date (Deadline)</label>
        <input type="date" name="milestone_end[]">
      </div>
    </div>
    <div class="form-row">
      <div class="field-group">
        <label>Budget ($)</label>
        <input type="number" name="milestone_budget[]" placeholder="0.00" min="0" step="0.01">
      </div>
      <div class="field-group">
        <label>Priority</label>
        <select name="milestone_priority[]">
          <option value="medium" selected>Medium</option>
          <option value="high">High</option>
          <option value="low">Low</option>
        </select>
      </div>
    </div>
    <div class="field-group">
      <label>Description</label>
      <textarea name="milestone_desc[]" placeholder="Describe this milestone..." rows="2"></textarea>
    </div>
    <button type="button" class="remove-btn" onclick="window.removeMilestone(this)">
      ✖ Remove
    </button>
  `;

  container.appendChild(div);
};

window.removeMilestone = function (btn) {
  const container = document.getElementById('milestones-container');
  /* Keep at least one milestone row */
  if (container && container.children.length > 1) {
    btn.closest('.milestone-item').remove();
  }
};

/* Wire up admin approve modal close button and cancel button */
document.addEventListener('DOMContentLoaded', function () {
  const closeBtn  = document.getElementById('closeApproveModal');
  const cancelBtn = document.getElementById('cancelApproveModal');
  if (closeBtn)  closeBtn.addEventListener('click',  window.closeApproveModal);
  if (cancelBtn) cancelBtn.addEventListener('click', window.closeApproveModal);

  /* Close on overlay click */
  const modal = document.getElementById('approveModal');
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) window.closeApproveModal();
    });
  }
});
/* ──────────────────────────────────────────────────────────── */


/* ── 11. MILESTONE.PHP — VIEW TASKS MODAL ──────────────────────
   openMsViewTasksModal(btn) reads data-tasks-json from the
   "View Tasks" button, builds an HTML table, and injects it
   into #msViewTasksBody. No AJAX needed.
   closeMsViewTasksModal() closes the overlay.
──────────────────────────────────────────────────────────────── */

window.openMsViewTasksModal = function (btn) {
  const title = btn.dataset.msTitle || 'Tasks';
  const raw   = btn.dataset.tasksJson || '[]';

  let tasks = [];
  try { tasks = JSON.parse(raw); } catch (e) { tasks = []; }

  /* Set modal title */
  const titleEl = document.getElementById('msViewTasksTitle');
  if (titleEl) titleEl.textContent = title;

  const subtitleEl = document.getElementById('msViewTasksSubtitle');
  if (subtitleEl) {
    subtitleEl.textContent = tasks.length === 0
      ? 'No tasks assigned yet.'
      : tasks.length + ' task' + (tasks.length !== 1 ? 's' : '') + ' assigned to this milestone.';
  }

  /* Build content */
  const body = document.getElementById('msViewTasksBody');
  if (!body) return;

  if (tasks.length === 0) {
    body.innerHTML = '<p class="ms-vt-empty"><i class="fas fa-info-circle"></i> No tasks assigned to this milestone yet. Use the <strong>Assign Task</strong> button to add one.</p>';
  } else {
    /* Build a table */
    let html = '<div class="ms-vt-table-wrap"><table class="ms-vt-table">';
    html += '<thead><tr>'
      + '<th>Task</th><th>Worker</th><th>Priority</th>'
      + '<th>Due Date</th><th>Status</th><th>Progress</th>'
      + '</tr></thead><tbody>';

    const priBadge = { high: 'high', medium: 'medium', low: 'low' };
    const statusLabel = {
      pending: 'Pending', accepted: 'Accepted',
      in_progress: 'In Progress', completed: 'Completed', rejected: 'Rejected'
    };
    const statusCss = {
      in_progress: 'inprogress', accepted: 'inprogress',
      completed: 'completed', pending: 'pending', rejected: 'pending'
    };

    tasks.forEach(function (t) {
      const pri    = t.priority || 'medium';
      const pct    = parseInt(t.progress, 10) || 0;
      const stLbl  = statusLabel[t.status]  || t.status;
      const stCss  = statusCss[t.status]    || 'pending';
      html += '<tr>'
        + '<td class="ms-vt-title">' + escHtml(t.title) + '</td>'
        + '<td class="ms-vt-worker"><i class="fas fa-user"></i> ' + escHtml(t.worker_name) + '</td>'
        + '<td><span class="ms-priority ' + pri + '">' + capitalize(pri) + '</span></td>'
        + '<td class="ms-vt-due">'  + escHtml(t.due_date) + '</td>'
        + '<td><span class="ms-badge ' + stCss + '">' + stLbl + '</span></td>'
        + '<td class="ms-vt-pct">'  + pct + '%</td>'
        + '</tr>';
    });

    html += '</tbody></table></div>';
    body.innerHTML = html;
  }

  /* Show modal */
  const modal = document.getElementById('msViewTasksModal');
  if (modal) modal.classList.add('active');
};

window.closeMsViewTasksModal = function () {
  const modal = document.getElementById('msViewTasksModal');
  if (modal) modal.classList.remove('active');
};

/* Helpers used by openMsViewTasksModal */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}
/* ──────────────────────────────────────────────────────────── */


/* ── 12. CONTRACTOR / CHAT.PHP ──────────────────────────────────
   Three behaviours:
   1. Scroll chat messages to bottom on load.
   2. Enter key submits the message form (Shift+Enter = new line).
   3. Contact search filters the contact list client-side.
──────────────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', function () {

  /* ── 1. Auto-scroll chat to bottom ─────────────────────── */
  const chatBox = document.getElementById('chatMessages');
  if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  /* ── 2. Enter to send, Shift+Enter for new line ─────────── */
  const textarea = document.getElementById('chatMessageInput');
  const sendForm = document.getElementById('chatSendForm');

  if (textarea && sendForm) {
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (textarea.value.trim()) {
          sendForm.submit();
        }
      }
    });

    /* Auto-grow textarea as user types */
    textarea.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
  }

  /* ── 3. Client-side contact search filter ───────────────── */
  const searchInput = document.getElementById('chatContactSearch');
  const contactList = document.getElementById('chatContactList');

  if (searchInput && contactList) {
    searchInput.addEventListener('input', function () {
      const query = this.value.trim().toLowerCase();
      const items = contactList.querySelectorAll('a.contractor-conversation-item');

      items.forEach(function (item) {
        const name = item.dataset.name || '';
        item.style.display = (!query || name.includes(query)) ? '' : 'none';
      });
    });
  }

});
/* ──────────────────────────────────────────────────────────── */


/* ── 13. TASK DOCUMENT FILE INPUT PREVIEW ───────────────────────
   When contractor selects a file in the Assign Task modal,
   show the selected filename below the input as a preview.
   No inline JS — wired up via DOMContentLoaded.
──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  const fileInput = document.getElementById('taskDocumentInput');
  const fileNote  = document.getElementById('taskDocumentName');
  if (!fileInput || !fileNote) return;

  fileInput.addEventListener('change', function () {
    if (this.files && this.files.length > 0) {
      const name = this.files[0].name;
      const size = (this.files[0].size / (1024 * 1024)).toFixed(2);
      fileNote.textContent = '📎 ' + name + ' (' + size + ' MB)';
    } else {
      fileNote.textContent = '';
    }
  });
});
/* ──────────────────────────────────────────────────────────── */


/* ── 14. ADMIN / PROJECT_REQUESTS.PHP — VIEW DETAILS + REJECT MODAL ──
   openPrDetailsModal(btn)  → reads data-* attributes, fills the
                              View Details modal, shows/hides
                              rejection reason row and doc link.
   closePrDetailsModal()    → closes the modal.
   openRejectModal(btn)     → fills reject modal subtitle + req id.
   closeRejectModal()       → closes the modal.
   DOMContentLoaded block   → wires up close buttons for all three
                              modals on this page.
──────────────────────────────────────────────────────────────── */

window.openPrDetailsModal = function (btn) {
  /* Fill the detail table cells from data-* attributes */
  var set = function (id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val || '—';
  };

  set('prDetailsTitle', btn.dataset.projName);
  set('prDClient',      btn.dataset.client);
  set('prDEmail',       btn.dataset.email);
  set('prDType',        btn.dataset.type);
  set('prDLocation',    btn.dataset.location);

  /* Format budget */
  var bud = parseFloat(btn.dataset.budget || '0');
  set('prDBudget', '$' + bud.toLocaleString('en-US', {minimumFractionDigits: 0}));

  /* Format dates */
  var fmtDate = function (iso) {
    if (!iso) return '—';
    var d = new Date(iso);
    if (isNaN(d)) return iso;
    return d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
  };
  set('prDDeadline',  fmtDate(btn.dataset.deadline));
  set('prDSubmitted', fmtDate(btn.dataset.submitted));
  set('prDDesc',      btn.dataset.desc);

  /* Status badge */
  var statusEl = document.getElementById('prDStatus');
  if (statusEl) {
    var s   = btn.dataset.status || 'pending';
    var css = s === 'approved' ? 'done' : (s === 'rejected' ? 'pending' : 'inprogress');
    statusEl.innerHTML = '<span class="vr-badge ' + css + '">' +
      s.charAt(0).toUpperCase() + s.slice(1) + '</span>';
  }

  /* Rejection reason row */
  var reasonRow = document.getElementById('prDReasonRow');
  var reasonEl  = document.getElementById('prDReason');
  if (reasonRow && reasonEl) {
    if (btn.dataset.status === 'rejected' && btn.dataset.reason) {
      reasonEl.textContent = btn.dataset.reason;
      reasonRow.removeAttribute('hidden');
    } else {
      reasonRow.setAttribute('hidden', '');
    }
  }

  /* Document link row */
  var docRow  = document.getElementById('prDDocRow');
  var docLink = document.getElementById('prDDocLink');
  if (docRow && docLink) {
    if (btn.dataset.doc) {
      docLink.href = '../' + btn.dataset.doc;
      docRow.removeAttribute('hidden');
    } else {
      docRow.setAttribute('hidden', '');
    }
  }

  /* Show modal */
  var modal = document.getElementById('prDetailsModal');
  if (modal) modal.classList.add('active');
};

window.closePrDetailsModal = function () {
  var modal = document.getElementById('prDetailsModal');
  if (modal) modal.classList.remove('active');
};

window.openRejectModal = function (btn) {
  var reqId   = btn.dataset.reqId;
  var name    = btn.dataset.projName;
  var sub     = document.getElementById('rejectModalSubtitle');
  var idInput = document.getElementById('rejectReqId');
  if (sub)     sub.textContent = 'Rejecting: ' + name;
  if (idInput) idInput.value   = reqId;
  var modal = document.getElementById('rejectModal');
  if (modal) modal.classList.add('active');
};

window.closeRejectModal = function () {
  var modal = document.getElementById('rejectModal');
  if (modal) modal.classList.remove('active');
};

/* Wire up all modal close buttons on this page */
document.addEventListener('DOMContentLoaded', function () {

  /* View Details modal */
  var btnClose = document.getElementById('closePrDetailsModal');
  var btnCancel = document.getElementById('cancelPrDetailsModal');
  if (btnClose)  btnClose.addEventListener('click',  window.closePrDetailsModal);
  if (btnCancel) btnCancel.addEventListener('click', window.closePrDetailsModal);

  var detailsModal = document.getElementById('prDetailsModal');
  if (detailsModal) {
    detailsModal.addEventListener('click', function (e) {
      if (e.target === detailsModal) window.closePrDetailsModal();
    });
  }

  /* Reject modal */
  var rClose  = document.getElementById('closeRejectModal');
  var rCancel = document.getElementById('cancelRejectModal');
  if (rClose)  rClose.addEventListener('click',  window.closeRejectModal);
  if (rCancel) rCancel.addEventListener('click', window.closeRejectModal);

  var rejectModal = document.getElementById('rejectModal');
  if (rejectModal) {
    rejectModal.addEventListener('click', function (e) {
      if (e.target === rejectModal) window.closeRejectModal();
    });
  }

});
/* ─────────────────────────────────────────────────────────── */
