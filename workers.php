<?php
// ============================================================
// FILE:    contractor/workers.php
// FIX:     "Commands out of sync" — all mysqli_stmt_get_result()
//          calls now stored into variables, fully drained, freed,
//          then closed. Mixed store_result/get_result usage on
//          same connection resolved: get_result() used throughout.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

$hire_error   = '';
$hire_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hire_worker') {

    $project_id   = (int)   ($_POST['project_id']   ?? 0);
    $worker_email = strtolower(trim($_POST['worker_email'] ?? ''));
    $trade        = trim($_POST['trade'] ?? '');

    if ($project_id <= 0) {
        $hire_error = 'Please select a project.';
    } elseif (empty($worker_email)) {
        $hire_error = "Please enter the worker's email address.";
    } elseif (!filter_var($worker_email, FILTER_VALIDATE_EMAIL)) {
        $hire_error = 'Please enter a valid email address.';
    } else {

        // ── Check 1: project belongs to this contractor ───────
        // Use get_result() + free_result() — NO store_result() mix
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM projects WHERE id = ? AND contractor_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res     = mysqli_stmt_get_result($stmt);    // store
        $proj_ok = (mysqli_num_rows($res) > 0);      // check
        mysqli_free_result($res);                    // free
        mysqli_stmt_close($stmt);                    // close

        if (!$proj_ok) {
            $hire_error = 'Invalid project selected.';
        } else {

            // ── Check 2: look up worker by email ──────────────
            $stmt = mysqli_prepare($conn,
                "SELECT id, first_name, last_name FROM users
                 WHERE email = ? AND role = 'worker' LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "s", $worker_email);
            mysqli_stmt_execute($stmt);
            $res    = mysqli_stmt_get_result($stmt); // store
            $worker = mysqli_fetch_assoc($res);      // fetch
            mysqli_free_result($res);                // free
            mysqli_stmt_close($stmt);                // close

            if (!$worker) {
                $hire_error = "No worker account found with that email. Ask the worker to register first.";
            } else {
                $worker_id = (int) $worker['id'];

                // ── Check 3: already hired on this project? ───
                $stmt = mysqli_prepare($conn,
                    "SELECT id FROM project_workers
                     WHERE project_id = ? AND worker_id = ? LIMIT 1"
                );
                mysqli_stmt_bind_param($stmt, "ii", $project_id, $worker_id);
                mysqli_stmt_execute($stmt);
                $res     = mysqli_stmt_get_result($stmt); // store
                $already = (mysqli_num_rows($res) > 0);   // check
                mysqli_free_result($res);                  // free
                mysqli_stmt_close($stmt);                  // close

                if ($already) {
                    $hire_error = htmlspecialchars($worker['first_name'])
                                . ' is already assigned to this project.';
                } else {

                    // ── Insert into project_workers ───────────
                    $stmt = mysqli_prepare($conn,
                        "INSERT INTO project_workers (project_id, worker_id, contractor_id)
                         VALUES (?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt, "iii", $project_id, $worker_id, $sess_id);
                    $saved = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);              // no result set — just close

                    // ── Optionally update trade ────────────────
                    if ($saved && !empty($trade)) {
                        $stmt = mysqli_prepare($conn,
                            "UPDATE users SET specialization = ?
                             WHERE id = ?
                             AND (specialization IS NULL OR specialization = '')"
                        );
                        mysqli_stmt_bind_param($stmt, "si", $trade, $worker_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);          // no result set — just close
                    }

                    if ($saved) {
                        $hire_success = htmlspecialchars(
                            $worker['first_name'] . ' ' . $worker['last_name']
                        ) . ' has been added to the project.';
                    } else {
                        $hire_error = 'Failed to add worker. Please try again.';
                    }
                }
            }
        }
    }
}

// ── QUERY A: Workers hired by this contractor ─────────────────
// 7-step pattern: prepare → bind → execute → store → drain → free → close
$stmt = mysqli_prepare($conn,
    "SELECT u.id, u.first_name, u.last_name, u.email, u.contact,
            u.specialization,
            GROUP_CONCAT(DISTINCT p.project_name
                         ORDER BY p.project_name SEPARATOR ', ') AS project_names
     FROM   project_workers pw
     JOIN   users    u ON u.id = pw.worker_id
     JOIN   projects p ON p.id = pw.project_id
     WHERE  pw.contractor_id = ?
     GROUP  BY u.id
     ORDER  BY u.first_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);    // step 4: store
$workers = [];
while ($w = mysqli_fetch_assoc($result)) {   // step 5: drain
    $workers[] = $w;
}
mysqli_free_result($result);                 // step 6: free
mysqli_stmt_close($stmt);                    // step 7: close

// ── QUERY B: Projects for the modal dropdown ──────────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, project_name FROM projects
     WHERE contractor_id = ? AND status IN ('active','pending')
     ORDER BY project_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result         = mysqli_stmt_get_result($stmt);    // step 4: store
$modal_projects = [];
while ($pr = mysqli_fetch_assoc($result)) {         // step 5: drain
    $modal_projects[] = $pr;
}
mysqli_free_result($result);                        // step 6: free
mysqli_stmt_close($stmt);                           // step 7: close

// Flag for JS: auto-open hire modal after a failed submit
$open_modal_on_load = !empty($hire_error) ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contractor Workers</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <aside class="contractor-sidebar">
    <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2>
    </div>
    <ul class="sidebar-menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li class="active"><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

  <div class="contractor-main">

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

    <div class="workers-page">

      <div class="workers-header">
        <h3>My Workers</h3>
        <button class="add-worker-btn" onclick="openWorkerModal()">
          <i class="fas fa-plus"></i> Add Worker
        </button>
      </div>

      <?php if (!empty($hire_error)): ?>
        <div class="feedback-error">⚠️ <?php echo $hire_error; ?></div>
      <?php endif; ?>
      <?php if (!empty($hire_success)): ?>
        <div class="feedback-success">✅ <?php echo $hire_success; ?></div>
      <?php endif; ?>

      <div class="worker-grid" data-open-modal="<?php echo $open_modal_on_load; ?>">

        <?php if (empty($workers)): ?>
          <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            No workers hired yet. Use the <strong>Add Worker</strong> button.
          </div>

        <?php else: ?>
          <?php foreach ($workers as $w):
            $full_name     = htmlspecialchars($w['first_name'] . ' ' . $w['last_name']);
            $avatar_letter = strtoupper($w['first_name'][0] ?? '?');
            $trade         = htmlspecialchars(!empty($w['specialization']) ? $w['specialization'] : 'Worker');
            $email_raw     = $w['email'] ?? '';
            $email_disp    = strpos($email_raw, '@placeholder.local') !== false
                                ? 'Not provided'
                                : htmlspecialchars($email_raw);
            $contact       = htmlspecialchars(!empty($w['contact']) ? $w['contact'] : 'Not provided');
            $proj_names    = htmlspecialchars($w['project_names'] ?? '');
          ?>
          <div class="worker-card">
            <div class="wc-avatar"><?php echo $avatar_letter; ?></div>
            <div class="wc-name"><?php echo $full_name; ?></div>
            <div class="wc-role"><?php echo $trade; ?></div>
            <div class="wc-contact">
              <span><i class="far fa-envelope"></i> <?php echo $email_disp; ?></span>
              <span><i class="fas fa-phone"></i> <?php echo $contact; ?></span>
            </div>
            <?php if (!empty($proj_names)): ?>
              <span class="wc-project-tag">
                <i class="fas fa-folder"></i> <?php echo $proj_names; ?>
              </span>
            <?php endif; ?>
            <button class="wc-assign-btn"
                    onclick="openAssignModal(<?php echo (int)$w['id']; ?>,'<?php echo addslashes($full_name); ?>')">
              Assign to Task
            </button>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div><!-- end .worker-grid -->
    </div><!-- end .workers-page -->
  </div><!-- end .contractor-main -->

  <!-- HIRE WORKER MODAL -->
  <div class="worker-modal" id="workerModal">
    <div class="worker-modal-content">
      <button class="worker-close" onclick="closeWorkerModal()">&times;</button>
      <h2>Hire New Worker</h2>
      <p>Link an existing worker account to your project.</p>
      <form method="POST" action="">
        <input type="hidden" name="action" value="hire_worker">
        <div class="worker-form-group">
          <label>Project</label>
          <select name="project_id" required>
            <option value="" disabled selected>Select project</option>
            <?php foreach ($modal_projects as $mp): ?>
              <option value="<?php echo (int)$mp['id']; ?>">
                <?php echo htmlspecialchars($mp['project_name'], ENT_QUOTES); ?>
              </option>
            <?php endforeach; ?>
            <?php if (empty($modal_projects)): ?>
              <option disabled>No active projects</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="worker-form-group">
          <label>Worker Email</label>
          <input type="email" name="worker_email"
                 placeholder="Enter worker's registered email" required>
        </div>
        <div class="worker-form-group">
          <label>Trade / Role</label>
          <select name="trade">
            <option value="" disabled selected>Select trade</option>
            <option value="Electrician">Electrician</option>
            <option value="Plumber">Plumber</option>
            <option value="Painter">Painter</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Mason">Mason</option>
            <option value="Carpenter">Carpenter</option>
            <option value="Welder">Welder</option>
            <option value="General Worker">General Worker</option>
          </select>
        </div>
        <div class="worker-modal-footer">
          <button type="button" class="cancel-worker-btn" onclick="closeWorkerModal()">Cancel</button>
          <button type="submit" class="submit-worker-btn">Add Worker</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ASSIGN TASK MODAL (placeholder — full implementation in Step 7) -->
  <div class="worker-modal" id="assignModal">
    <div class="worker-modal-content">
      <button class="worker-close" onclick="closeAssignModal()">&times;</button>
      <h2>Assign to Task</h2>
      <p id="assignWorkerName" class="assign-worker-name"></p>
      <p class="assign-worker-note">
        Full task assignment is available on the
        <a href="milestone.php" class="assign-link">Milestones</a> page.
      </p>
      <div class="worker-modal-footer">
        <button type="button" class="cancel-worker-btn" onclick="closeAssignModal()">Close</button>
      </div>
    </div>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
