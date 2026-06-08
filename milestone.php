<?php
// ============================================================
// FILE:    contractor/milestone.php
// CHANGES IN THIS VERSION:
//   1. "View Tasks" now opens a modal (not expand-in-card).
//      Expandable task panel inside cards removed.
//   2. milestones.priority column read and shown as badge.
//      Priority belongs to milestones, NOT tasks.
//   3. update_milestone form includes a priority dropdown.
//   4. assign_task handler still creates tasks under milestones.
//   5. Admin approve flow is handled in project_requests.php.
//
// REQUIRES: add_milestone_priority_column.sql to be run first.
// NO INLINE CSS. NO INLINE JS.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── AJAX: Workers for a project (Assign Task modal) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'get_workers_for_project') {
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $list = [];
    if ($project_id > 0) {
        $stmt = mysqli_prepare($conn,
            "SELECT pw.worker_id AS id,
                    CONCAT(u.first_name,' ',u.last_name) AS worker_name
             FROM   project_workers pw
             JOIN   users u    ON u.id = pw.worker_id
             JOIN   projects p ON p.id = pw.project_id
             WHERE  pw.project_id = ? AND p.contractor_id = ?
             ORDER  BY u.first_name ASC"
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

// ── HANDLE: Assign task under a milestone ────────────────────
$assign_error = $assign_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'assign_task') {

    $project_id   = (int) ($_POST['project_id']   ?? 0);
    $milestone_id = (int) ($_POST['milestone_id'] ?? 0);
    $worker_id    = (int) ($_POST['worker_id']    ?? 0);
    $title        = trim( $_POST['title']          ?? '');
    $priority     = trim( $_POST['priority']       ?? 'medium');
    $due_date     = trim( $_POST['due_date']        ?? '');
    $due_val      = !empty($due_date) ? $due_date : null;
    $allowed_pri  = ['low','medium','high'];

    if ($project_id <= 0)                          { $assign_error = 'Invalid project.'; }
    elseif ($milestone_id <= 0)                    { $assign_error = 'Invalid milestone.'; }
    elseif ($worker_id <= 0)                       { $assign_error = 'Please select a worker.'; }
    elseif (empty($title))                         { $assign_error = 'Task title is required.'; }
    elseif (!in_array($priority, $allowed_pri))    { $assign_error = 'Invalid priority.'; }
    else {
        // Validate: contractor owns project
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM projects
             WHERE id = ? AND contractor_id = ? AND status IN ('active','pending') LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res     = mysqli_stmt_get_result($stmt);
        $proj_ok = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);

        // Validate: milestone belongs to project
        if ($proj_ok) {
            $stmt = mysqli_prepare($conn,
                "SELECT id FROM milestones WHERE id = ? AND project_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "ii", $milestone_id, $project_id);
            mysqli_stmt_execute($stmt);
            $res   = mysqli_stmt_get_result($stmt);
            $ms_ok = mysqli_num_rows($res) > 0;
            mysqli_free_result($res);
            mysqli_stmt_close($stmt);
        } else { $ms_ok = false; }

        // Validate: worker is on this project under this contractor
        if ($proj_ok && $ms_ok) {
            $stmt = mysqli_prepare($conn,
                "SELECT id FROM project_workers
                 WHERE project_id = ? AND worker_id = ? AND contractor_id = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "iii", $project_id, $worker_id, $sess_id);
            mysqli_stmt_execute($stmt);
            $res       = mysqli_stmt_get_result($stmt);
            $worker_ok = mysqli_num_rows($res) > 0;
            mysqli_free_result($res);
            mysqli_stmt_close($stmt);
        } else { $worker_ok = false; }

        if (!$proj_ok)       { $assign_error = 'You do not own this project or it is not active.'; }
        elseif (!$ms_ok)     { $assign_error = 'Milestone does not belong to this project.'; }
        elseif (!$worker_ok) { $assign_error = 'Worker is not assigned to this project.'; }
        else {
            // ── File upload (optional) ────────────────────────
            // Validation runs BEFORE INSERT so no partial saves occur.
            $attach_path = null;
            $file_error  = '';

            if (!empty($_FILES['task_document']['name'])) {
                $file      = $_FILES['task_document'];
                $orig_name = $file['name'];
                $tmp_path  = $file['tmp_name'];
                $file_size = $file['size'];
                $file_err  = $file['error'];

                // Allowed extensions — no executables ever
                $allowed_ext = ['pdf','doc','docx','jpg','jpeg','png'];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                if ($file_err !== UPLOAD_ERR_OK) {
                    $file_error = 'File upload error. Please try again.';
                } elseif ($file_size > 5 * 1024 * 1024) {
                    $file_error = 'File too large. Maximum size is 5 MB.';
                } elseif (!in_array($ext, $allowed_ext)) {
                    $file_error = 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.';
                } else {
                    // Safe filename: timestamp + sanitized title + random id + ext
                    // Never use the original filename — prevents path traversal and injection.
                    $safe_title = preg_replace('/[^a-z0-9_]/', '_', strtolower($title));
                    $safe_title = substr($safe_title, 0, 30);
                    $unique_id  = bin2hex(random_bytes(6));
                    $safe_name  = time() . '_' . $safe_title . '_' . $unique_id . '.' . $ext;

                    // Upload directory — relative to project root (one level above contractor/)
                    $upload_dir = dirname(__DIR__) . '/uploads/task_documents/';

                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $dest = $upload_dir . $safe_name;

                    if (move_uploaded_file($tmp_path, $dest)) {
                        // Store as relative path from project root
                        $attach_path = 'uploads/task_documents/' . $safe_name;
                    } else {
                        $file_error = 'Could not save file. Check server folder permissions.';
                    }
                }

                if (!empty($file_error)) {
                    $assign_error = $file_error;
                    // Skip INSERT — validation failed
                    goto assign_done;
                }
            }

            // ── INSERT task with attachment_path ──────────────
            $stmt = mysqli_prepare($conn,
                "INSERT INTO tasks
                     (project_id, milestone_id, worker_id, assigned_by,
                      title, priority, due_date, status, progress, attachment_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?)"
            );
            mysqli_stmt_bind_param($stmt, "iiiissss",
                $project_id, $milestone_id, $worker_id, $sess_id,
                $title, $priority, $due_val, $attach_path
            );
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($saved) {
                recalc_ms($conn, $milestone_id);
                $assign_success = 'Task "' . htmlspecialchars($title) . '" assigned.';
            } else {
                // If INSERT failed and we already moved a file, delete it to avoid orphans
                if ($attach_path && file_exists(dirname(__DIR__) . '/' . $attach_path)) {
                    unlink(dirname(__DIR__) . '/' . $attach_path);
                }
                $assign_error = 'Failed to create task. Please try again.';
            }

            assign_done: // goto target — only reached on file validation failure
            ;
        }
    }
}

// ── HELPER: Recalculate milestone progress from tasks ─────────
function recalc_ms(mysqli $conn, int $mid): void {
    $stmt = mysqli_prepare($conn,
        "SELECT COUNT(*) AS total,
                COALESCE(AVG(progress),0) AS avg_prog,
                SUM(CASE WHEN status='completed'   THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) AS inprog
         FROM tasks WHERE milestone_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $mid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
    if (!$row || (int)$row['total'] === 0) return;

    $total  = (int)   $row['total'];
    $done   = (int)   $row['done'];
    $inprog = (int)   $row['inprog'];
    $pct    = (int)   round((float)$row['avg_prog']);

    if ($done === $total)         { $st = 'completed';   $pct = 100; }
    elseif ($inprog + $done > 0) { $st = 'in_progress'; }
    else                          { $st = 'pending';     $pct = 0; }

    $stmt = mysqli_prepare($conn,
        "UPDATE milestones SET progress = ?, status = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "isi", $pct, $st, $mid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ── QUERY: Contractor's projects ──────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, project_name FROM projects
     WHERE contractor_id = ? AND status IN ('active','pending','on_hold')
     ORDER BY project_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$res      = mysqli_stmt_get_result($stmt);
$projects = [];
while ($r = mysqli_fetch_assoc($res)) $projects[] = $r;
mysqli_free_result($res);
mysqli_stmt_close($stmt);

$selected_project_id = (int) ($_GET['project_id'] ?? 0);
if ($selected_project_id <= 0 && !empty($projects)) {
    $selected_project_id = (int) $projects[0]['id'];
}
$selected_project_name = '';
foreach ($projects as $p) {
    if ((int)$p['id'] === $selected_project_id) {
        $selected_project_name = $p['project_name'];
        break;
    }
}

// ── QUERY A: Milestones with priority + task-based progress ───
$milestones = [];
$total_ms   = 0;
$overall_pct = 0;

if ($selected_project_id > 0) {
    $stmt = mysqli_prepare($conn,
        "SELECT m.id, m.title, m.description, m.start_date, m.end_date,
                m.budget, m.status, m.progress AS manual_progress,
                m.priority,
                COUNT(t.id)                       AS task_count,
                COALESCE(AVG(t.progress), -1)     AS avg_task_progress,
                SUM(CASE WHEN t.status='completed'   THEN 1 ELSE 0 END) AS tasks_done,
                SUM(CASE WHEN t.status='in_progress' THEN 1 ELSE 0 END) AS tasks_inprog
         FROM   milestones m
         LEFT JOIN tasks t ON t.milestone_id = m.id
         WHERE  m.project_id = ?
         GROUP  BY m.id
         ORDER  BY FIELD(m.status,'in_progress','pending','completed'), m.end_date ASC"
    );
    mysqli_stmt_bind_param($stmt, "i", $selected_project_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $milestones[] = $row;
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

// Compute effective_progress per milestone
foreach ($milestones as &$ms) {
    $tc = (int) $ms['task_count'];
    if ($tc > 0) {
        $avg    = (float) $ms['avg_task_progress'];
        $done   = (int)   $ms['tasks_done'];
        $inprog = (int)   $ms['tasks_inprog'];
        $ms['effective_progress'] = (int) round($avg);
        if ($done === $tc)            { $ms['effective_status'] = 'completed';   $ms['effective_progress'] = 100; }
        elseif ($inprog + $done > 0) { $ms['effective_status'] = 'in_progress'; }
        else                          { $ms['effective_status'] = 'pending';     $ms['effective_progress'] = 0; }
        $ms['progress_source'] = 'tasks';
    } else {
        $ms['effective_progress'] = (int) $ms['manual_progress'];
        $ms['effective_status']   = $ms['status'];
        $ms['progress_source']    = 'manual';
    }
}
unset($ms);

// ── QUERY B: Tasks per milestone (for View Tasks modal) ───────
$milestone_ids      = array_column($milestones, 'id');
$tasks_by_milestone = [];

if (!empty($milestone_ids)) {
    $ph    = implode(',', array_fill(0, count($milestone_ids), '?'));
    $types = str_repeat('i', count($milestone_ids));
    $stmt  = mysqli_prepare($conn,
        "SELECT t.id, t.title, t.status, t.progress, t.due_date,
                t.milestone_id, t.priority,
                CONCAT(u.first_name,' ',u.last_name) AS worker_name
         FROM   tasks t
         JOIN   users u ON u.id = t.worker_id
         WHERE  t.milestone_id IN ($ph)
         ORDER  BY t.id ASC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...array_values($milestone_ids));
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($t = mysqli_fetch_assoc($res)) {
        $tasks_by_milestone[$t['milestone_id']][] = $t;
    }
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

$total_ms = count($milestones);
if ($total_ms > 0) {
    $sum = 0;
    foreach ($milestones as $m) $sum += $m['effective_progress'];
    $overall_pct = (int) round($sum / $total_ms);
}

// ── HELPERS ───────────────────────────────────────────────────
function ms_badge(string $s): string {
    return match($s) { 'in_progress'=>'inprogress','completed'=>'completed',default=>'pending' };
}
function ms_label(string $s): string {
    return match($s) { 'in_progress'=>'In Progress','completed'=>'Completed',default=>'Pending' };
}
function ms_date(?string $d): string {
    if (empty($d)) return 'Not set';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('M j, Y') : $d;
}
function t_badge(string $s): string {
    return match($s) {
        'in_progress','accepted'=>'inprogress','completed'=>'completed',default=>'pending',
    };
}
function t_label(string $s): string {
    return match($s) {
        'in_progress'=>'In Progress','completed'=>'Completed',
        'accepted'=>'Accepted','pending'=>'Pending','rejected'=>'Rejected',default=>ucfirst($s),
    };
}

$open_assign_on_load = !empty($assign_error) ? 'true' : 'false';
$posted_ms_id        = (int) ($_POST['milestone_id'] ?? 0);
$posted_proj_id      = (int) ($_POST['project_id']   ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Milestones</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <aside class="contractor-sidebar">
    <div class="sidebar-logo"><i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2></div>
    <ul class="sidebar-menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li class="active"><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

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

    <div class="milestones-topbar">
      <h2>Milestone Tab</h2>
    </div>

    <?php if (!empty($assign_error)):   ?><div class="feedback-error">⚠️ <?php echo htmlspecialchars($assign_error); ?></div><?php endif; ?>
    <?php if (!empty($assign_success)): ?><div class="feedback-success">✅ <?php echo htmlspecialchars($assign_success); ?></div><?php endif; ?>


    <!-- PROJECT FILTER -->
    <div class="ms-project-filter"
         data-open-assign="<?php echo $open_assign_on_load; ?>"
         data-posted-ms-id="<?php echo $posted_ms_id; ?>"
         data-posted-proj-id="<?php echo $posted_proj_id; ?>">
      <form method="GET" action="" id="msProjectFilterForm">
        <div class="ms-filter-row">
          <label class="ms-filter-label"><i class="fas fa-folder"></i> Project</label>
          <select name="project_id" id="msProjectSelect" class="ms-filter-select"
                  onchange="document.getElementById('msProjectFilterForm').submit()">
            <?php if (empty($projects)): ?>
              <option value="">No active projects</option>
            <?php else: ?>
              <?php foreach ($projects as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>"
                  <?php echo ((int)$p['id'] === $selected_project_id) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($p['project_name'], ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <?php if ($total_ms > 0): ?>
            <span class="ms-filter-count">
              <?php echo $total_ms; ?> milestone<?php echo $total_ms !== 1 ? 's' : ''; ?>
            </span>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- OVERALL PROGRESS -->
    <?php if ($total_ms > 0): ?>
    <div class="ms-progress-row">
      <span class="ms-progress-label">
        <?php echo htmlspecialchars($selected_project_name); ?> — <?php echo $overall_pct; ?>%
      </span>
      <div class="progress-track">
        <div class="progress-fill ms-progress-fill" data-pct="<?php echo $overall_pct; ?>"></div>
      </div>
      <span class="progress-pct"><?php echo $overall_pct; ?>%</span>
    </div>
    <?php endif; ?>

    <!-- MILESTONE CARDS -->
    <div class="milestones-list">

      <?php if (empty($projects)): ?>
        <div class="empty-state"><i class="fas fa-folder-open"></i> No active projects yet.</div>

      <?php elseif (empty($milestones)): ?>
        <div class="empty-state">
          <i class="fas fa-list-check"></i>
          No milestones for this project. Admin creates them when approving the project.
        </div>

      <?php else: ?>

        <?php foreach ($milestones as $ms):
          $ms_id      = (int) $ms['id'];
          $ms_title   = htmlspecialchars($ms['title'],          ENT_QUOTES);
          $ms_desc    = htmlspecialchars($ms['description'] ?? '', ENT_QUOTES);
          $ms_start   = ms_date($ms['start_date']);
          $ms_end     = ms_date($ms['end_date']);
          $ms_budget  = '$' . number_format((float)$ms['budget'], 0, '.', ',');
          $eff_status = $ms['effective_status'];
          $eff_pct    = $ms['effective_progress'];
          $badge_css  = ms_badge($eff_status);
          $badge_lbl  = ms_label($eff_status);
          $from_tasks = ($ms['progress_source'] === 'tasks');
          $task_count = (int) $ms['task_count'];
          // Priority — belongs to milestone
          $ms_pri     = $ms['priority'] ?? 'medium';
          $ms_tasks   = $tasks_by_milestone[$ms_id] ?? [];


          // Priority is read-only for contractor — no sel_pri array needed.
        ?>

        <div class="milestone-card">

          <!-- TOP: title | badges (status + priority) | update form -->
          <div class="milestone-card-top">
            <h4><?php echo $ms_title; ?></h4>
            <div class="ms-card-right">
              <div class="milestone-badges">
                <!-- Status badge -->
                <span class="ms-badge <?php echo $badge_css; ?>"><?php echo $badge_lbl; ?></span>
                <!-- Priority badge — from milestones.priority -->
                <span class="ms-priority <?php echo $ms_pri; ?>">
                  <?php echo ucfirst($ms_pri); ?> priority
                </span>

              </div>

            </div>
          </div>

          <!-- META -->
          <div class="milestone-card-meta">
            <?php if (!empty($ms_desc)): ?>
              <span><i class="fas fa-align-left"></i> <?php echo $ms_desc; ?></span>
            <?php endif; ?>
            <span><i class="fas fa-calendar-day"></i> <?php echo $ms_start; ?> → <?php echo $ms_end; ?></span>
            <span><i class="fas fa-wallet"></i> <?php echo $ms_budget; ?></span>
          </div>

          <!-- PROGRESS BAR -->
          <div class="ms-card-progress">
            <div class="ms-card-progress-top">
              <span class="ms-card-progress-label">
                Progress

              </span>
              <span class="ms-card-progress-pct"><?php echo $eff_pct; ?>%</span>
            </div>
            <div class="progress-track">
              <div class="progress-fill ms-progress-fill" data-pct="<?php echo $eff_pct; ?>"></div>
            </div>
          </div>

          <!-- CARD ACTIONS: View Tasks (opens modal) + Assign Task -->
          <div class="ms-card-actions">
            <!--
              View Tasks: passes all task data via data-tasks-json.
              JS reads it and populates the modal — no fetch() needed.
              data-ms-title feeds the modal heading.
            -->
            <button class="ms-view-tasks-btn"
                    data-ms-title="<?php echo $ms_title; ?>"
                    data-tasks-json="<?php
                      // Build JSON of tasks for this milestone
                      $modal_tasks = [];
                      foreach ($ms_tasks as $t) {
                          $t_pct = (int)($t['progress'] ?? 0);
                          if ($t['status'] === 'completed') $t_pct = 100;
                          if ($t['status'] === 'pending')   $t_pct = 0;
                          $modal_tasks[] = [
                              'title'       => $t['title'],
                              'worker_name' => $t['worker_name'],
                              'priority'    => $t['priority'],
                              'due_date'    => !empty($t['due_date'])
                                  ? (DateTime::createFromFormat('Y-m-d',$t['due_date'])?->format('M j, Y') ?? $t['due_date'])
                                  : '—',
                              'status'      => $t['status'],
                              'status_label'=> t_label($t['status']),
                              'status_css'  => t_badge($t['status']),
                              'progress'    => $t_pct,
                          ];
                      }
                      echo htmlspecialchars(json_encode($modal_tasks), ENT_QUOTES);
                    ?>"
                    onclick="openMsViewTasksModal(this)">
              <i class="fas fa-eye"></i>
              View Tasks
              <?php if ($task_count > 0): ?>
                <span class="ms-task-count-badge"><?php echo $task_count; ?></span>
              <?php endif; ?>
            </button>

            <button class="ms-assign-task-btn"
                    data-ms-id="<?php echo $ms_id; ?>"
                    data-ms-title="<?php echo $ms_title; ?>"
                    data-proj-id="<?php echo $selected_project_id; ?>"
                    data-proj-name="<?php echo htmlspecialchars($selected_project_name, ENT_QUOTES); ?>"
                    onclick="openMsAssignModal(this)">
              <i class="fas fa-plus"></i> Assign Task
            </button>
          </div>

        </div><!-- end .milestone-card -->

        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- end .milestones-list -->

  </main>


  <!-- ===== VIEW TASKS MODAL ===== -->
  <!--
    Title and task rows populated by JS from data-tasks-json attribute.
    No AJAX needed — PHP embedded the JSON in the button's data attribute.
    Uses .details-modal-overlay + .details-modal-box (existing CSS).
  -->
  <div class="details-modal-overlay" id="msViewTasksModal"
       onclick="if(event.target===this) closeMsViewTasksModal()">
    <div class="details-modal-box ms-view-tasks-modal-box">
      <button class="modal-close-btn" onclick="closeMsViewTasksModal()">&times;</button>
      <h3 class="modal-title" id="msViewTasksTitle">Tasks</h3>
      <p class="modal-subtitle-text" id="msViewTasksSubtitle"></p>

      <!-- Populated by JS -->
      <div id="msViewTasksBody"></div>
    </div>
  </div>


  <!-- ===== ASSIGN TASK MODAL ===== -->
  <div class="worker-modal" id="msAssignModal">
    <div class="worker-modal-content">
      <button class="worker-close" onclick="closeMsAssignModal()">&times;</button>
      <h2>Assign Task</h2>
      <p id="msAssignModalSubtitle" class="assign-worker-name"></p>

      <form method="POST"
            action="milestone.php?project_id=<?php echo $selected_project_id; ?>"
            id="msAssignForm"
            enctype="multipart/form-data">
        <input type="hidden" name="action"       value="assign_task">
        <input type="hidden" name="project_id"   id="msAssignProjectId">
        <input type="hidden" name="milestone_id" id="msAssignMilestoneId">

        <div class="worker-form-group">
          <label>Assign To</label>
          <select name="worker_id" id="msAssignWorkerSelect" required>
            <option value="" disabled selected>Loading workers…</option>
          </select>
        </div>
        <div class="worker-form-group">
          <label>Task Title</label>
          <input type="text" name="title"
                 placeholder="e.g. Install ceiling fans, Lay floor tiles" required>
        </div>
        <div class="worker-form-group">
          <label>Priority</label>
          <select name="priority" required>
            <option value="" disabled selected>Select priority</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div class="worker-form-group">
          <label>Due Date</label>
          <input type="date" name="due_date">
        </div>
        <!--
          FILE UPLOAD — optional field.
          Accepted: pdf, doc, docx, jpg, png. Max 5 MB.
          Uses .task-file-group instead of .worker-form-group because
          style.css sets height:54px on .worker-form-group input which
          forces the native file picker into an unusable fixed height.
          .task-file-group allows natural height for type="file".
        -->
        <div class="task-file-group">
          <label class="task-file-label">
            <i class="fas fa-paperclip"></i> Task Document
            <span class="task-file-hint">(optional · PDF, DOC, DOCX, JPG, PNG · max 5 MB)</span>
          </label>
          <input type="file"
                 name="task_document"
                 id="taskDocumentInput"
                 class="task-file-input"
                 accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
          <p class="task-file-note" id="taskDocumentName"></p>
        </div>

        <div class="worker-modal-footer">
          <button type="button" class="cancel-worker-btn"
                  onclick="closeMsAssignModal()">Cancel</button>
          <button type="submit" class="submit-worker-btn">Assign Task</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
