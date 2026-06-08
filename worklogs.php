<?php
// ============================================================
// FILE:    contractor/worklogs.php
// UPDATED: Added `title` field throughout:
//          - Collected from POST, validated (required), saved
//          - Added to SELECT queries (contractor + worker logs)
//          - Displayed in card header (.wl-log-title)
//          - Added Title input in modal form
// TABLES USED:
//   work_logs     → log entries
//   projects      → project name; ownership (contractor_id = $sess_id)
//   users         → submitter name (submitted_by) + worker name (worker_id)
//   tasks         → task name (task_id)
//   project_workers → AJAX: workers per project for modal dropdown
// DB PATCH REQUIRED: run add_worklog_columns.sql first.
// NO INLINE CSS. NO INLINE JS.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── AJAX: Workers for a project ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_workers') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $list = [];
    if ($project_id > 0) {
        $stmt = mysqli_prepare($conn,
            "SELECT pw.worker_id,
                    CONCAT(u.first_name,' ',u.last_name) AS worker_name
             FROM   project_workers pw
             JOIN   users u ON u.id = pw.worker_id
             JOIN   projects p ON p.id = pw.project_id
             WHERE  pw.project_id = ? AND p.contractor_id = ?
             ORDER  BY worker_name ASC"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: application/json');
    echo json_encode($list);
    exit();
}

// ── AJAX: Tasks for a project ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_tasks') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $list = [];
    if ($project_id > 0) {
        $stmt = mysqli_prepare($conn,
            "SELECT t.id, t.title
             FROM   tasks t
             JOIN   projects p ON p.id = t.project_id
             WHERE  t.project_id = ? AND p.contractor_id = ?
             AND    t.status NOT IN ('completed','rejected')
             ORDER  BY t.title ASC"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: application/json');
    echo json_encode($list);
    exit();
}

// ── HANDLE: ADD work log ──────────────────────────────────────
$log_error   = '';
$log_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_log') {

    $project_id  = (int)   ($_POST['project_id']  ?? 0);
    $worker_id   = (int)   ($_POST['worker_id']   ?? 0);
    $task_id     = (int)   ($_POST['task_id']      ?? 0);
    $log_date    = trim(   $_POST['log_date']       ?? '');
    $hours       = (float) ($_POST['hours_worked'] ?? 0);
    $title       = trim(   $_POST['title']          ?? '');      // ← NEW
    $description = trim(   $_POST['description']   ?? '');

    $task_val   = $task_id   > 0 ? $task_id   : null;
    $worker_val = $worker_id > 0 ? $worker_id : null;
    $date_val   = !empty($log_date) ? $log_date : date('Y-m-d');
    $hours_val  = $hours > 0 ? $hours : null;
    $title_val  = !empty($title) ? $title : null;                // ← NEW: nullable in DB

    // Validation
    if ($project_id <= 0)        { $log_error = 'Please select a project.'; }
    elseif (empty($title))       { $log_error = 'Please enter a title for this log entry.'; }  // ← NEW
    elseif (empty($description)) { $log_error = 'Please enter a work description.'; }
    else {
        // Ownership check
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM projects WHERE id = ? AND contractor_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res     = mysqli_stmt_get_result($stmt);
        $proj_ok = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);

        if (!$proj_ok) {
            $log_error = 'Invalid project selected.';
        } else {
            // INSERT — 8 bound params (role_type is a SQL literal, not bound)
            // project_id(i), task_id(i), submitted_by(i), worker_id(i),
            // title(s), description(s), log_date(s), hours_worked(d)
            $stmt = mysqli_prepare($conn,
                "INSERT INTO work_logs
                     (project_id, task_id, submitted_by, worker_id,
                      title, role_type, description, log_date, hours_worked)
                 VALUES (?, ?, ?, ?, ?, 'contractor', ?, ?, ?)"
            );
            // 8 bound params (role_type is a literal in SQL)
            // project_id(i), task_id(i), submitted_by(i), worker_id(i),
            // title(s), description(s), log_date(s), hours_worked(d)
            mysqli_stmt_bind_param($stmt, "iiiisssd",
                $project_id, $task_val, $sess_id, $worker_val,
                $title_val, $description, $date_val, $hours_val
            );
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($saved) {
                $log_success = 'Work log "' . htmlspecialchars($title) . '" added.';
            } else {
                $log_error = 'Failed to save. Please try again. (' . mysqli_error($conn) . ')';
            }
        }
    }
}

// ── QUERY A: Contractor's own logs ────────────────────────────
// Added wl.title to SELECT
$stmt = mysqli_prepare($conn,
    "SELECT wl.id, wl.title, wl.description, wl.log_date, wl.hours_worked,
            p.project_name,
            t.title          AS task_name,
            CONCAT(w.first_name,' ',w.last_name) AS worker_name
     FROM   work_logs wl
     JOIN   projects p ON p.id = wl.project_id
     LEFT JOIN tasks t ON t.id = wl.task_id
     LEFT JOIN users w ON w.id = wl.worker_id
     WHERE  wl.submitted_by = ?
     AND    wl.role_type = 'contractor'
     AND    p.contractor_id = ?
     ORDER  BY wl.log_date DESC, wl.submitted_at DESC"
);
mysqli_stmt_bind_param($stmt, "ii", $sess_id, $sess_id);
mysqli_stmt_execute($stmt);
$result          = mysqli_stmt_get_result($stmt);
$contractor_logs = [];
while ($r = mysqli_fetch_assoc($result)) $contractor_logs[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── QUERY B: Worker logs for this contractor's projects ───────
// Added wl.title to SELECT
$stmt = mysqli_prepare($conn,
    "SELECT wl.id, wl.title, wl.description, wl.log_date, wl.hours_worked,
            p.project_name,
            t.title          AS task_name,
            CONCAT(u.first_name,' ',u.last_name) AS worker_name
     FROM   work_logs wl
     JOIN   projects p ON p.id = wl.project_id
     JOIN   users    u ON u.id = wl.submitted_by
     LEFT JOIN tasks t ON t.id = wl.task_id
     WHERE  wl.role_type = 'worker'
     AND    p.contractor_id = ?
     ORDER  BY wl.log_date DESC, wl.submitted_at DESC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result      = mysqli_stmt_get_result($stmt);
$worker_logs = [];
while ($r = mysqli_fetch_assoc($result)) $worker_logs[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── QUERY C: Projects for modal ───────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, project_name FROM projects
     WHERE  contractor_id = ? AND status IN ('active','pending')
     ORDER  BY project_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$projects = [];
while ($r = mysqli_fetch_assoc($result)) $projects[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── HELPERS ───────────────────────────────────────────────────
function fmt_log_date(?string $d): string {
    if (empty($d)) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('F j, Y') : $d;
}
function fmt_log_date_short(?string $d): string {
    if (empty($d)) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d.m.y') : $d;
}
function fmt_hours(?float $h): string {
    if ($h === null || $h <= 0) return '—';
    return number_format($h, 1) . ' hrs';
}

$open_log_on_load = !empty($log_error) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Work Logs</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <!-- ===== SIDEBAR ===== -->
  <aside class="contractor-sidebar">
    <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2>
    </div>
    <ul class="sidebar-menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li class="active"><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="contractor-main">

    <div class="contractor-topbar">
      <div>
        <h2>Contractor Dashboard</h2>
        <p>Manage your projects, workers, and daily tasks</p>
      </div>
      <div class="contractor-user">
        <div class="contractor-avatar"><?php echo $sess_initials; ?></div>
        <span class="contractor-name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <div class="worklogs-wrapper"
         data-open-log="<?php echo $open_log_on_load; ?>">

      <div class="logs-header">
        <h1>Daily Work Logs</h1>
        <button class="add-log-btn" onclick="openLogModal()">
          <i class="fas fa-plus"></i> Add Log Entry
        </button>
      </div>

      <?php if (!empty($log_error)):   ?><div class="feedback-error">⚠️ <?php echo htmlspecialchars($log_error); ?></div><?php endif; ?>
      <?php if (!empty($log_success)): ?><div class="feedback-success">✅ <?php echo htmlspecialchars($log_success); ?></div><?php endif; ?>

      <!-- ── SECTION 1: MY WORK LOGS (cards) ─────────────────── -->
      <div class="log-section">
        <div class="section-title">My Work Logs</div>

        <?php if (empty($contractor_logs)): ?>
          <div class="empty-state">
            <i class="fas fa-clipboard"></i>
            No logs submitted yet. Use <strong>Add Log Entry</strong> to record your first log.
          </div>

        <?php else: ?>
          <?php foreach ($contractor_logs as $log):
            $log_date    = fmt_log_date($log['log_date']);
            $log_title   = !empty($log['title'])
                            ? htmlspecialchars($log['title'], ENT_QUOTES)
                            : 'Untitled Log';
            $proj_name   = htmlspecialchars($log['project_name'], ENT_QUOTES);
            $task_name   = !empty($log['task_name'])
                            ? htmlspecialchars($log['task_name'], ENT_QUOTES)
                            : null;
            $worker_name = !empty($log['worker_name'])
                            ? htmlspecialchars($log['worker_name'], ENT_QUOTES)
                            : null;
            $hours       = fmt_hours(isset($log['hours_worked']) ? (float)$log['hours_worked'] : 0);
          ?>
          <div class="worklog-card">

            <!--
              WORKLOG-TOP — CHANGED:
              Left side: Title (wl-log-title) replaces the bare date h3.
                         Date shown below title as a subtitle.
              Right side: Contractor badge — unchanged.
            -->
            <div class="worklog-top">
              <div>
                <div class="wl-log-title"><?php echo $log_title; ?></div>
                <div class="wl-log-date"><?php echo $log_date; ?></div>
              </div>
              <span class="log-badge contractor-badge">Contractor</span>
            </div>

            <!-- Meta row: project, task, worker, hours — unchanged -->
            <div class="wl-card-meta">
              <span><i class="fas fa-folder"></i> <?php echo $proj_name; ?></span>
              <?php if ($task_name): ?>
                <span><i class="fas fa-list-check"></i> <?php echo $task_name; ?></span>
              <?php endif; ?>
              <?php if ($worker_name): ?>
                <span><i class="fas fa-user"></i> Re: <?php echo $worker_name; ?></span>
              <?php endif; ?>
              <?php if ($hours !== '—'): ?>
                <span><i class="fas fa-clock"></i> <?php echo $hours; ?></span>
              <?php endif; ?>
            </div>

            <!-- Description body — unchanged -->
            <p><?php echo nl2br(htmlspecialchars($log['description'])); ?></p>

            <div class="log-footer">
              Submitted by: <?php echo $sess_firstname; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ── SECTION 2: WORKER LOGS (table) ──────────────────── -->
      <div class="log-section">
        <div class="section-title">Worker's Work Logs</div>

        <?php if (empty($worker_logs)): ?>
          <div class="empty-state">
            <i class="fas fa-users"></i>
            No worker logs found for your projects yet.
          </div>

        <?php else: ?>
          <div class="worker-log-table">
            <!--
              7-column grid now includes Title column.
              .wl-grid-7 defined in additions.css.
            -->
            <!-- 5 columns: Date | Worker | Title | Project | Description -->
            <div class="worker-log-header wl-grid-5">
              <span>Date</span>
              <span>Worker</span>
              <span>Title</span>
              <span>Project</span>
              <span>Description</span>
            </div>

            <?php foreach ($worker_logs as $log):
              $date      = fmt_log_date_short($log['log_date']);
              $worker    = htmlspecialchars($log['worker_name'],  ENT_QUOTES);
              $log_title = !empty($log['title'])
                            ? htmlspecialchars($log['title'], ENT_QUOTES)
                            : '—';
              $proj      = htmlspecialchars($log['project_name'], ENT_QUOTES);
              $desc      = htmlspecialchars($log['description'],  ENT_QUOTES);
            ?>
            <div class="worker-log-row wl-grid-5">
              <span><?php echo $date;      ?></span>
              <span><?php echo $worker;    ?></span>
              <span><?php echo $log_title; ?></span>
              <span><?php echo $proj;      ?></span>
              <span><?php echo $desc;      ?></span>
            </div>
            <?php endforeach; ?>

          </div>
        <?php endif; ?>
      </div>

    </div><!-- end .worklogs-wrapper -->
  </main>


  <!-- ===== ADD LOG MODAL ===== -->
  <div class="add-log-modal" id="logModal">
    <div class="add-log-content">

      <button class="add-log-close" onclick="closeLogModal()">
        <i class="fas fa-times"></i>
      </button>

      <h2>Add Work Log</h2>

      <form method="POST" action="" id="logForm">
        <input type="hidden" name="action" value="add_log">

        <!-- Project -->
        <div class="wl-form-group">
          <label>Project</label>
          <select name="project_id" id="logProject" required>
            <option value="" disabled selected>Select project</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?php echo (int)$pr['id']; ?>">
                <?php echo htmlspecialchars($pr['project_name'], ENT_QUOTES); ?>
              </option>
            <?php endforeach; ?>
            <?php if (empty($projects)): ?>
              <option disabled>No active projects</option>
            <?php endif; ?>
          </select>
        </div>

        <!-- Worker -->
        <div class="wl-form-group">
          <label>Worker (optional)</label>
          <select name="worker_id" id="logWorker">
            <option value="0">Select project first</option>
          </select>
        </div>

        <!-- Task -->
        <div class="wl-form-group">
          <label>Task (optional)</label>
          <select name="task_id" id="logTask">
            <option value="0">Select project first</option>
          </select>
        </div>

        <!-- Date and Hours -->
        <div class="wl-form-row">
          <div class="wl-form-group">
            <label>Work Date</label>
            <input type="date" name="log_date" id="logDate"
                   value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="wl-form-group">
            <label>Hours Worked</label>
            <input type="number" name="hours_worked" id="logHours"
                   min="0.5" max="24" step="0.5" placeholder="e.g. 7.5">
          </div>
        </div>

        <!--
          Title input — NEW: placed above Description.
          Uses existing .add-log-content textarea CSS container
          but is a plain text input, styled by .wl-form-group.
          Required field: PHP validates this before INSERT.
        -->
        <div class="wl-form-group">
          <label>Title</label>
          <input type="text" name="title"
                 placeholder="e.g. Foundation Inspection, Wiring Floor 1"
                 maxlength="200" required>
        </div>

        <!-- Description — wrapped in wl-form-group to show label -->
        <div class="wl-form-group">
          <label>Description</label>
          <textarea name="description"
                    placeholder="Describe the work completed, materials used, any issues encountered..."
                    required></textarea>
        </div>

        <div class="add-log-footer">
          <button type="button" class="cancel-log-btn" onclick="closeLogModal()">Cancel</button>
          <button type="submit" class="submit-log-btn">Add Log</button>
        </div>
      </form>

    </div>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
