<?php
// ============================================================
// FILE:    contractor/view_report.php
// CORRECTED ARCHITECTURE:
//   milestones → project phases; progress from milestones.progress
//                (which is auto-updated by worker/mytasks.php recalc_milestone)
//   tasks      → worker jobs; NOT shown directly in report
//                (visible on milestone.php and worker/mytasks.php)
//
// PROGRESS CALCULATION:
//   Overall Progress = AVG(milestones.progress) across all project milestones
//   This is the same formula used in milestone.php effective_progress.
//   milestones.progress is automatically updated whenever a worker
//   saves task progress in worker/mytasks.php.
//
// MATERIAL COST: SUM(quantity × unit_cost) computed in SQL.
// WORKER COUNT: COUNT of project_workers rows for this project.
// WORK LOG COUNT: COUNT(*) from work_logs WHERE project_id = ?
//
// NO INLINE CSS. NO INLINE JS.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

$project_id = (int) ($_GET['id'] ?? 0);
if ($project_id <= 0) { header("Location: my_projects.php"); exit(); }

// ── QUERY 1: Project info ─────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT p.id, p.project_name, p.location, p.project_type,
            p.budget, p.start_date, p.end_date, p.status, p.description,
            CONCAT(u.first_name,' ',u.last_name) AS client_name
     FROM   projects p
     JOIN   users u ON u.id = p.client_id
     WHERE  p.id = ? AND p.contractor_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($result);
mysqli_free_result($result);
mysqli_stmt_close($stmt);
if (!$project) { header("Location: my_projects.php"); exit(); }

// ── QUERY 2: Milestone progress summary ──────────────────────
// Uses milestones.progress (auto-updated by recalc_milestone).
// Overall = AVG(progress). Individual values shown in milestone table.
$stmt = mysqli_prepare($conn,
    "SELECT id, title, description, start_date, end_date,
            budget, status, progress, priority
     FROM   milestones
     WHERE  project_id = ?
     ORDER  BY id ASC"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result     = mysqli_stmt_get_result($stmt);
$milestones = [];
while ($r = mysqli_fetch_assoc($result)) $milestones[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

$total_ms     = count($milestones);
$completed_ms = count(array_filter($milestones, fn($m) => $m['status'] === 'completed'));
$inprog_ms    = count(array_filter($milestones, fn($m) => $m['status'] === 'in_progress'));
$pending_ms   = count(array_filter($milestones, fn($m) => $m['status'] === 'pending'));

// Overall progress = AVG of milestones.progress (task-driven or manual)
$progress_pct = 0;
if ($total_ms > 0) {
    $sum = array_sum(array_column($milestones, 'progress'));
    $progress_pct = (int) round($sum / $total_ms);
}

// ── QUERY 3: Material cost summary ───────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS total_materials,
            COALESCE(SUM(quantity * unit_cost), 0) AS total_cost
     FROM materials WHERE project_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result           = mysqli_stmt_get_result($stmt);
$mat_summary      = mysqli_fetch_assoc($result) ?? [];
mysqli_free_result($result);
mysqli_stmt_close($stmt);
$total_materials  = (int)   ($mat_summary['total_materials'] ?? 0);
$total_mat_cost   = (float) ($mat_summary['total_cost']      ?? 0);

// ── QUERY 4: Materials list ───────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT material_name, quantity, unit, unit_cost,
            (quantity * unit_cost) AS row_cost
     FROM materials WHERE project_id = ? ORDER BY id ASC"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result    = mysqli_stmt_get_result($stmt);
$materials = [];
while ($r = mysqli_fetch_assoc($result)) $materials[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── QUERY 5: Workers ─────────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT CONCAT(u.first_name,' ',u.last_name) AS worker_name,
            COALESCE(u.specialization,'Worker')  AS specialization
     FROM   project_workers pw
     JOIN   users u ON u.id = pw.worker_id
     WHERE  pw.project_id = ? ORDER BY u.first_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$workers = [];
while ($r = mysqli_fetch_assoc($result)) $workers[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);
$total_workers = count($workers);

// ── QUERY 6: Work log count ───────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS total FROM work_logs WHERE project_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result    = mysqli_stmt_get_result($stmt);
$log_count = (int)(mysqli_fetch_assoc($result)['total'] ?? 0);
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── QUERY 7: Latest 5 work logs ──────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT wl.title, wl.description, wl.log_date,
            CONCAT(u.first_name,' ',u.last_name) AS submitted_by_name
     FROM   work_logs wl
     JOIN   users u ON u.id = wl.submitted_by
     WHERE  wl.project_id = ?
     ORDER  BY wl.log_date DESC, wl.submitted_at DESC LIMIT 5"
);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$worklogs = [];
while ($r = mysqli_fetch_assoc($result)) $worklogs[] = $r;
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── HELPERS ───────────────────────────────────────────────────
function vr_date(?string $d): string {
    if (empty($d)) return 'N/A';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('M j, Y') : $d;
}
function vr_cost(float $n): string {
    return '$' . number_format($n, 2, '.', ',');
}
function vr_badge(string $s): string {
    return match($s) { 'completed'=>'done','in_progress'=>'inprogress', default=>'pending' };
}
function vr_ms_label(string $s): string {
    return match($s) { 'completed'=>'Done','in_progress'=>'In Progress', default=>'Pending' };
}
function vr_proj_status(string $s): string {
    return match($s) {
        'active'=>'Active','pending'=>'Pending','completed'=>'Completed','on_hold'=>'On Hold',
        default=>ucfirst($s)
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Project Report — <?php echo htmlspecialchars($project['project_name']); ?></title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <aside class="contractor-sidebar">
    <div class="sidebar-logo"><i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2></div>
    <ul class="sidebar-menu">
      <li class="active"><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

  <main class="contractor-main">

    <div class="contractor-topbar">
      <div class="vr-topbar-left">
        <a href="my_projects.php" class="vd-back-btn"><i class="fas fa-times"></i></a>
        <div>
          <h2>Project Report</h2>
          <p>Detailed overview of <?php echo htmlspecialchars($project['project_name']); ?></p>
        </div>
      </div>
      <div class="contractor-user">
        <div class="contractor-avatar"><?php echo $sess_initials; ?></div>
        <span class="contractor-name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <div class="vr-page">

      <!-- A. PROJECT HEADER -->
      <div class="vr-header-card">
        <h2 class="vr-title"><?php echo htmlspecialchars($project['project_name']); ?></h2>
        <p class="vr-meta">
          <i class="fas fa-map-marker-alt vr-location-icon"></i>
          <?php echo htmlspecialchars($project['location']); ?>
          &nbsp;|&nbsp; Client: <?php echo htmlspecialchars($project['client_name']); ?>
          &nbsp;|&nbsp; Status: <strong><?php echo vr_proj_status($project['status']); ?></strong>
          <?php if (!empty($project['project_type'])): ?>
            &nbsp;|&nbsp; Type: <?php echo htmlspecialchars($project['project_type']); ?>
          <?php endif; ?>
        </p>
        <p class="vr-meta">
          Start: <?php echo vr_date($project['start_date']); ?>
          &nbsp;→&nbsp; Deadline: <?php echo vr_date($project['end_date']); ?>
          &nbsp;|&nbsp; Budget: <strong><?php echo vr_cost((float)$project['budget']); ?></strong>
        </p>
        <?php if (!empty($project['description'])): ?>
          <p class="vr-desc"><?php echo htmlspecialchars($project['description']); ?></p>
        <?php endif; ?>
      </div>

      <!-- B. MILESTONES TABLE with progress per milestone -->
      <div class="vr-card">
        <h3 class="vr-card-title"><i class="fas fa-list-check"></i> Project Milestones</h3>
        <?php if (empty($milestones)): ?>
          <p class="vr-empty-msg">No milestones added for this project yet.</p>
        <?php else: ?>
          <table class="vr-table">
            <thead>
              <tr>
                <th class="vr-th-checkbox"></th>
                <th>Milestone</th>
                <th>Description</th>
                <th>Timeline</th>
                <th>Budget</th>
                <th>Priority</th>
                <th>Progress</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($milestones as $ms):
                $ms_pct = (int)($ms['progress'] ?? 0);
                if ($ms['status'] === 'completed') $ms_pct = 100;
                if ($ms['status'] === 'pending')   $ms_pct = 0;
                $ms_pct = max(0, min(100, $ms_pct));
              ?>
              <tr>
                <td><input type="checkbox" <?php echo $ms['status'] === 'completed' ? 'checked' : ''; ?> disabled></td>
                <td><strong><?php echo htmlspecialchars($ms['title']); ?></strong></td>
                <td><?php echo htmlspecialchars($ms['description'] ?? ''); ?></td>
                <td><?php echo vr_date($ms['start_date']); ?> → <?php echo vr_date($ms['end_date']); ?></td>
                <td><?php echo vr_cost((float)$ms['budget']); ?></td>
                <td>
                  <?php $ms_pri_css = ['high'=>'high','medium'=>'medium','low'=>'low'][$ms['priority'] ?? 'medium'] ?? 'medium'; ?>
                  <span class="ms-priority <?php echo $ms_pri_css; ?>">
                    <?php echo ucfirst($ms['priority'] ?? 'medium'); ?>
                  </span>
                </td>
                <td>
                  <div class="vr-mini-progress-wrap">
                    <div class="vr-progress-track">
                      <div class="vr-progress-fill ms-progress-fill"
                           data-pct="<?php echo $ms_pct; ?>"></div>
                    </div>
                    <span class="vr-mini-pct"><?php echo $ms_pct; ?>%</span>
                  </div>
                </td>
                <td><span class="vr-badge <?php echo vr_badge($ms['status']); ?>"><?php echo vr_ms_label($ms['status']); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- 3-COL: Workers | Material Cost | Progress -->
      <div class="vr-bottom-grid">

        <div class="vr-card">
          <h3 class="vr-card-title"><i class="fas fa-users"></i> Workforce</h3>
          <div class="vr-info-row"><span>Total Workers</span><strong><?php echo $total_workers; ?></strong></div>
          <?php if (!empty($workers)): ?>
            <?php foreach ($workers as $w): ?>
            <div class="vr-info-row">
              <span><?php echo htmlspecialchars($w['worker_name']); ?></span>
              <strong class="vr-worker-spec"><?php echo htmlspecialchars($w['specialization']); ?></strong>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="vr-empty-msg">No workers assigned yet.</p>
          <?php endif; ?>
        </div>

        <div class="vr-card">
          <h3 class="vr-card-title"><i class="fas fa-box"></i> Material Summary</h3>
          <div class="vr-info-row"><span>Total Materials</span><strong><?php echo $total_materials; ?></strong></div>
          <div class="vr-info-row"><span>Total Material Cost</span><strong><?php echo vr_cost($total_mat_cost); ?></strong></div>
          <div class="vr-info-row"><span>Project Budget</span><strong><?php echo vr_cost((float)$project['budget']); ?></strong></div>
          <?php $diff = (float)$project['budget'] - $total_mat_cost; ?>
          <div class="vr-info-row">
            <span>vs Budget</span>
            <strong class="<?php echo $diff >= 0 ? 'vr-under-budget' : 'vr-over-budget'; ?>">
              <?php echo $diff >= 0 ? vr_cost($diff) . ' left' : vr_cost(abs($diff)) . ' over'; ?>
            </strong>
          </div>
        </div>

        <div class="vr-card">
          <h3 class="vr-card-title"><i class="fas fa-chart-pie"></i> Milestone Progress</h3>
          <div class="vr-progress-label">
            <span>Overall</span>
            <span class="vr-progress-pct"><?php echo $progress_pct; ?>%</span>
          </div>
          <div class="vr-progress-track">
            <div class="vr-progress-fill ms-progress-fill"
                 data-pct="<?php echo $progress_pct; ?>"></div>
          </div>
          <div class="vr-spacer-row"></div>
          <div class="vr-info-row"><span>Total Milestones</span><strong><?php echo $total_ms; ?></strong></div>
          <div class="vr-info-row"><span>Completed</span><strong><?php echo $completed_ms; ?></strong></div>
          <div class="vr-info-row"><span>In Progress</span><strong><?php echo $inprog_ms; ?></strong></div>
          <div class="vr-info-row"><span>Pending</span><strong><?php echo $pending_ms; ?></strong></div>
          <div class="vr-info-row">
            <span><small>Progress = AVG(milestone.progress), each updated from tasks</small></span>
          </div>
        </div>

      </div><!-- end vr-bottom-grid -->

      <!-- Materials + Work Logs — 2-col -->
      <div class="vr-bottom-grid-2">

        <div class="vr-card">
          <h3 class="vr-card-title"><i class="fas fa-cubes"></i> Materials Used</h3>
          <?php if (empty($materials)): ?>
            <p class="vr-empty-msg">No materials recorded yet.</p>
          <?php else: ?>
            <table class="vr-table">
              <thead><tr><th>Material</th><th>Qty</th><th>Unit</th><th>Total Cost</th></tr></thead>
              <tbody>
                <?php foreach ($materials as $m): ?>
                <tr>
                  <td><?php echo htmlspecialchars($m['material_name']); ?></td>
                  <td><?php echo rtrim(rtrim(number_format((float)$m['quantity'],2),'0'),'.'); ?></td>
                  <td><?php echo htmlspecialchars($m['unit']); ?></td>
                  <td><?php echo vr_cost((float)$m['row_cost']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="vr-table-footer">Total: <strong><?php echo vr_cost($total_mat_cost); ?></strong></div>
          <?php endif; ?>
        </div>

        <div class="vr-card">
          <h3 class="vr-card-title">
            <i class="fas fa-clipboard-list"></i> Work Logs
            <span class="vr-log-count"><?php echo $log_count; ?> total</span>
          </h3>
          <?php if (empty($worklogs)): ?>
            <p class="vr-empty-msg">No work logs submitted yet.</p>
          <?php else: ?>
            <table class="vr-table">
              <thead><tr><th>Date</th><th>By</th><th>Title</th><th>Description</th></tr></thead>
              <tbody>
                <?php foreach ($worklogs as $wl): ?>
                <tr>
                  <td><?php echo vr_date($wl['log_date']); ?></td>
                  <td><?php echo htmlspecialchars($wl['submitted_by_name']); ?></td>
                  <td><?php echo !empty($wl['title']) ? htmlspecialchars($wl['title']) : '—'; ?></td>
                  <td class="vr-log-desc"><?php echo htmlspecialchars($wl['description']); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </div>

      <!-- REPORT FOOTER -->
      <div class="vr-report-footer">
        <div class="vr-footer-item"><i class="fas fa-calendar-alt"></i> Generated: <?php echo date('F j, Y'); ?></div>
        <div class="vr-footer-item"><i class="fas fa-hard-hat"></i> Contractor: <?php echo $sess_fullname; ?></div>
        <div class="vr-footer-item"><i class="fas fa-folder"></i> Project: <?php echo htmlspecialchars($project['project_name']); ?></div>
      </div>

    </div><!-- end .vr-page -->
  </main>

  <script src="../assets/js/script.js"></script>
</body>
</html>
