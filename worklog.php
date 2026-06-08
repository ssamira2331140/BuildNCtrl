<?php
session_start();
$required_role = 'worker';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Work Logs</title>

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="admin-page">

<div class="admin-container">

<!-- ===== SIDEBAR ===== -->
<div class="admin-sidebar">
  <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i>
      <h2>BuildNCtrl</h2>
    </div>

  <ul class="menu">
    <li><a href="mytasks.php"><i class="fas fa-tasks"></i> My Tasks</a></li>
    <li class="active"><a href="worklog.php"><i class="fas fa-clipboard-list"></i> Work Logs</a></li>
    <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
    <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</div>

<!-- ===== MAIN ===== -->
<div class="admin-main">

<div class="admin-topbar">
  <h2>Daily Work Logs</h2>

  <div class="admin-user">
    <span class="avatar"><?php echo $sess_initials; ?></span>
    <span class="name"><?php echo $sess_fullname; ?></span>
  </div>
</div>

<!-- ADD BUTTON -->
<div class="top-actions">
  <button onclick="openLogModal()" class="add-btn">
    <i class="fas fa-plus"></i> Add Log Entry
  </button>
</div>

<!-- ===== LOG LIST ===== -->
<div class="log-list">

  <div class="log-card">
    <div class="log-header">
      <h4>November 24, 2025</h4>
      <span class="status submitted">Submitted</span>
    </div>

    <p>
      Completed electrical installation on Floor 3. All wiring tested and functional.
      Materials used: 250m wire, 20 outlets, 15 switches.
    </p>

    <small>Submitted by: Maria • 5:30 PM</small>
  </div>

  <div class="log-card">
    <div class="log-header">
      <h4>November 23, 2025</h4>
      <span class="status submitted">Submitted</span>
    </div>

    <p>
      Foundation inspection passed. Structural framework progressing as planned.
      Weather conditions favorable.
    </p>

    <small>Submitted by: Maria • 6:15 PM</small>
  </div>

</div>

</div>
</div>

<!-- ===== MODAL ===== -->
<div id="logModal" class="modal">

  <div class="modal-content">

    <span class="close" onclick="closeLogModal()">&times;</span>

    <h3>Add Work Log</h3>

    <form>

      <textarea placeholder="Describe work done, materials used, issues..." required></textarea>

      <div class="modal-actions">
        <button type="button" class="cancel" onclick="closeLogModal()">Cancel</button>
        <button type="submit" class="submit"> Submit </button>
      </div>

    </form>

  </div>

</div>

<script>
function openLogModal(){
  document.getElementById("logModal").style.display = "flex";
}

function closeLogModal(){
  document.getElementById("logModal").style.display = "none";
}
</script>

</body>
</html>