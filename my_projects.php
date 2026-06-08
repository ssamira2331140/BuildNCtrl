<?php
// ============================================================
// FILE:    contractor/my_projects.php
// FIX:     "Commands out of sync" — every mysqli_stmt_get_result()
//          now stored into a variable, fully drained with
//          mysqli_free_result(), THEN mysqli_stmt_close().
//          No chained/inline get_result() calls remain.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── HELPER: run a single-row COUNT query safely ───────────────
// Encapsulates the 7-step pattern so COUNT queries are DRY.
// Returns the integer value of the first column named 'total'.
function count_query(mysqli $conn, string $sql, string $types, ...$params): int {
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);   // step 4: store result
    $row    = mysqli_fetch_assoc($result);      // step 5: fetch one row
    mysqli_free_result($result);               // step 6: free result set
    mysqli_stmt_close($stmt);                  // step 7: close statement
    return (int) ($row['total'] ?? 0);
}

// ── QUERY 1: My Projects count ────────────────────────────────
$stat_projects = count_query(
    $conn,
    "SELECT COUNT(*) AS total FROM projects
     WHERE contractor_id = ? AND status != 'completed'",
    "i", $sess_id
);

// ── QUERY 2: Total Workers count ──────────────────────────────
$stat_workers = count_query(
    $conn,
    "SELECT COUNT(DISTINCT worker_id) AS total FROM project_workers
     WHERE contractor_id = ?",
    "i", $sess_id
);

// ── QUERY 3: Pending Tasks count ──────────────────────────────
$stat_pending = count_query(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks
     WHERE assigned_by = ? AND status IN ('pending','accepted','in_progress')",
    "i", $sess_id
);

// ── QUERY 4: Completed Tasks count ───────────────────────────
$stat_completed = count_query(
    $conn,
    "SELECT COUNT(*) AS total FROM tasks
     WHERE assigned_by = ? AND status = 'completed'",
    "i", $sess_id
);

// ── QUERY 5: Project list ─────────────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT p.id, p.project_name, p.location, p.budget, p.start_date,
            p.end_date, p.status, p.progress,
            CONCAT(u.first_name,' ',u.last_name) AS client_name,
            COALESCE(SUM(m.quantity * m.unit_cost), 0) AS total_spent
     FROM   projects p
     JOIN   users u ON u.id = p.client_id
     LEFT JOIN materials m ON m.project_id = p.id
     WHERE  p.contractor_id = ?
     GROUP  BY p.id
     ORDER  BY FIELD(p.status,'active','pending','on_hold','completed'),
               p.end_date ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);     // step 4: store
$projects = [];
while ($r = mysqli_fetch_assoc($result)) {     // step 5: drain fully
    $projects[] = $r;
}
mysqli_free_result($result);                   // step 6: free
mysqli_stmt_close($stmt);                      // step 7: close

// ── QUERY 6: All milestones in one query (avoids N+1) ─────────
$milestones_by_project = [];
$project_ids = array_column($projects, 'id');

if (!empty($project_ids)) {
    $ph    = implode(',', array_fill(0, count($project_ids), '?'));
    $types = str_repeat('i', count($project_ids));
    $stmt  = mysqli_prepare($conn,
        "SELECT project_id, title FROM milestones
         WHERE project_id IN ($ph) ORDER BY id ASC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...array_values($project_ids));
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);   // step 4: store
    while ($m = mysqli_fetch_assoc($result)) { // step 5: drain
        $milestones_by_project[$m['project_id']][] = $m;
    }
    mysqli_free_result($result);               // step 6: free
    mysqli_stmt_close($stmt);                  // step 7: close
}

// ── HELPERS ───────────────────────────────────────────────────
function status_css_class(string $s): string {
    return match($s) {
        'active','completed' => 'active',
        default              => 'pending',
    };
}
function status_label(string $s): string {
    return match($s) {
        'active'  => 'Active',   'pending'   => 'Pending',
        'on_hold' => 'On Hold',  'completed' => 'Completed',
        default   => ucfirst($s),
    };
}
function fmt_date(?string $d): string {
    if (empty($d)) return 'N/A';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d/m/Y') : $d;
}
function fmt_money(float $n): string {
    return '$' . number_format($n, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contractor Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <aside class="contractor-sidebar">
    <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2>
    </div>
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
      <div>
        <h2>Contractor Dashboard</h2>
        <p>Manage your projects, workers, and daily tasks</p>
      </div>
      <div class="contractor-user">
        <div class="contractor-avatar"><?php echo $sess_initials; ?></div>
        <span class="contractor-name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <!-- STATS -->
    <div class="contractor-stats-grid">
      <div class="contractor-stat-card">
        <div class="contractor-stat-top">
          <div class="contractor-stat-icon"><i class="fas fa-folder"></i></div>
          <span>My Projects</span>
        </div>
        <h3><?php echo $stat_projects; ?></h3>
      </div>
      <div class="contractor-stat-card">
        <div class="contractor-stat-top">
          <div class="contractor-stat-icon"><i class="fas fa-users"></i></div>
          <span>Total Workers</span>
        </div>
        <h3><?php echo $stat_workers; ?></h3>
      </div>
      <div class="contractor-stat-card">
        <div class="contractor-stat-top">
          <div class="contractor-stat-icon"><i class="fas fa-clock"></i></div>
          <span>Pending Tasks</span>
        </div>
        <h3><?php echo $stat_pending; ?></h3>
      </div>
      <div class="contractor-stat-card">
        <div class="contractor-stat-top">
          <div class="contractor-stat-icon"><i class="fas fa-circle-check"></i></div>
          <span>Completed Tasks</span>
        </div>
        <h3><?php echo $stat_completed; ?></h3>
      </div>
    </div>

    <!-- PROJECT TABLE -->
    <section class="assigned-projects-section">
      <h3>Assigned Projects</h3>
      <div class="assigned-projects-header">
        <span>Project Name</span>
        <span>Status</span>
        <span>Details</span>
      </div>

      <?php if (empty($projects)): ?>
        <div class="empty-state">
          <i class="fas fa-folder-open"></i>
          No projects assigned to you yet.
        </div>

      <?php else: ?>
        <?php foreach ($projects as $p):
          $proj_id     = (int) $p['id'];
          $proj_name   = htmlspecialchars($p['project_name'], ENT_QUOTES);
          $proj_loc    = htmlspecialchars($p['location'],     ENT_QUOTES);
          $proj_client = htmlspecialchars($p['client_name'],  ENT_QUOTES);
          $proj_budget = fmt_money((float) $p['budget']);
          $proj_spent  = fmt_money((float) $p['total_spent']);
          $proj_start  = fmt_date($p['start_date']);
          $proj_end    = fmt_date($p['end_date']);
          $proj_status = $p['status'];
          $css_class   = status_css_class($proj_status);
          $status_text = status_label($proj_status);
          $is_pending  = ($proj_status === 'pending');
          $modal_id    = 'modal_' . $proj_id;
          $proj_miles  = $milestones_by_project[$proj_id] ?? [];
        ?>

        <div class="assigned-project-row">
          <div class="project-name-col">
            <div class="project-dot"></div>
            <span><?php echo $proj_name; ?></span>
            <?php if ($is_pending): ?>
              <span class="new-project-badge">New</span>
            <?php endif; ?>
          </div>
          <div>
            <span class="project-status <?php echo $css_class; ?>">
              <?php echo $status_text; ?>
            </span>
          </div>
          <div>
            <?php if ($is_pending): ?>
              <button class="project-action-btn"
                      onclick="openModal('<?php echo $modal_id; ?>')">
                View Details
              </button>
            <?php else: ?>
              <a href="view_report.php?id=<?php echo $proj_id; ?>"
                 class="view-report-btn">View Report</a>
            <?php endif; ?>
          </div>
        </div>

        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </main>

  <!-- MODALS — one per pending project -->
  <?php foreach ($projects as $p):
    if ($p['status'] !== 'pending') continue;
    $proj_id    = (int) $p['id'];
    $proj_name  = htmlspecialchars($p['project_name'], ENT_QUOTES);
    $proj_loc   = htmlspecialchars($p['location'],     ENT_QUOTES);
    $proj_client= htmlspecialchars($p['client_name'],  ENT_QUOTES);
    $proj_budget= fmt_money((float) $p['budget']);
    $proj_spent = fmt_money((float) $p['total_spent']);
    $proj_start = fmt_date($p['start_date']);
    $proj_end   = fmt_date($p['end_date']);
    $modal_id   = 'modal_' . $proj_id;
    $proj_miles = $milestones_by_project[$proj_id] ?? [];
  ?>

  <div class="proj-details-overlay"
       id="<?php echo $modal_id; ?>"
       onclick="if(event.target===this) closeModal('<?php echo $modal_id; ?>')">
    <div class="proj-details-box">
      <button class="proj-details-close"
              onclick="closeModal('<?php echo $modal_id; ?>')">&times;</button>
      <img src="../assets/images/building.jpg" alt="Project Image" class="proj-details-img">
      <div class="proj-details-body">
        <h2 class="proj-details-title"><?php echo $proj_name; ?></h2>
        <p class="proj-details-location">
          <i class="fas fa-location-dot"></i> <?php echo $proj_loc; ?>
        </p>
        <div class="proj-details-info">
          <span>Client: <?php echo $proj_client; ?></span>
          <span class="proj-details-divider">|</span>
          <span>Budget: <?php echo $proj_budget; ?></span>
          <span class="proj-details-divider">|</span>
          <span>Spent: <?php echo $proj_spent; ?></span>
        </div>
        <div class="proj-details-info">
          <span>Starting Date: <?php echo $proj_start; ?></span>
        </div>
        <div class="proj-details-info">
          <span>Deadline: <?php echo $proj_end; ?></span>
        </div>
        <div class="proj-milestones-box">
          <div class="proj-milestones-top">
            <p class="proj-milestones-label">Milestones:</p>
            <button class="proj-docs-btn"
                    onclick="downloadDocument(<?php echo $proj_id; ?>)">
              <i class="fas fa-file-arrow-down"></i> Documents
            </button>
          </div>
          <ol class="proj-milestones-list">
            <?php if (empty($proj_miles)): ?>
              <li class="no-miles-msg">No milestones yet</li>
            <?php else: ?>
              <?php foreach ($proj_miles as $m): ?>
                <li><?php echo htmlspecialchars($m['title'], ENT_QUOTES); ?></li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ol>
        </div>
        <div class="proj-details-actions">
          <form method="POST" action="handle_project_response.php" class="inline-form">
            <input type="hidden" name="project_id" value="<?php echo $proj_id; ?>">
            <input type="hidden" name="action"     value="accept">
            <button type="submit" class="proj-accept-btn">Accept</button>
          </form>
          <form method="POST" action="handle_project_response.php" class="inline-form">
            <input type="hidden" name="project_id" value="<?php echo $proj_id; ?>">
            <input type="hidden" name="action"     value="reject">
            <button type="submit" class="proj-reject-btn">Reject</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php endforeach; ?>

  <script src="../assets/js/script.js"></script>
</body>
</html>
