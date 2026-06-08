<?php
session_start();
$required_role = 'worker';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Worker Dashboard</title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
      <li class="active"><a href="mytasks.php"><i class="fas fa-tasks"></i> My Tasks</a></li>
      <li><a href="worklog.php"><i class="fas fa-clipboard-list"></i> Work Logs</a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>

  </div>

  <!-- MAIN -->
<div class="admin-main">

<div class="admin-topbar">
  <h2>My Tasks</h2>
  <div class="admin-user">
    <span class="avatar"><?php echo $sess_initials; ?></span>
    <span class="name"><?php echo $sess_fullname; ?></span>
  </div>
</div>

<!-- ===== ACTIVE TASKS ===== -->
<h3 class="section-title">Ongoing Tasks</h3>

<div class="task-list">

  <div class="task-card">
    <div class="task-left">
      <h4>Foundation</h4>
      <p>
        <span class="badge progress">In Progress</span>
        <span class="priority high">High Priority</span>
      </p>
      <p>Project: House Construction </p>
      <small>Assigned: Mar 01, 2026 | Deadline: Mar 28, 2026</small>
    </div>

    <div class="task-actions">
      <select>
        <option>Pending</option>
        <option selected>In Progress</option>
        <option>Completed</option>
      </select>
      <button class="update-btn">Update</button>
    </div>
  </div>

  <div class="task-card">
    <div class="task-left">
      <h4>Foundation</h4>
      <p>
        <span class="badge progress">In Progress</span>
        <span class="priority high">High Priority</span>
      </p>
      <p>Project: School Construction </p>
      <small>Assigned: Mar 05, 2026 | Deadline: Apr 05, 2026</small>
    </div>

    <div class="task-actions">
      <select>
        <option>Pending</option>
        <option selected>In Progress</option>
        <option>Completed</option>
      </select>
      <button class="update-btn">Update</button>
    </div>
  </div>

</div>

<!-- ===== REQUESTED TASKS ===== -->
<h3 class="section-title">Task Requests</h3>

<div class="task-list">

  <div class="task-card request">
    <div class="task-left">
      <h4>Structure</h4>
      <p>Project: House Construction </p>
      <p>Requested by: Contractor Samira</p>
      <p class="desc">Complete outer wall painting before inspection.</p>
      <span class="priority medium">Medium Priority</span>
    </div>

    <div class="task-actions">
      <button class="accept-btn">Accept</button>
      <button class="reject-btn">Reject</button>
    </div>
  </div>

  <div class="task-card request">
    <div class="task-left">
      <h4>Inspection</h4>
      <p>Project: School Construction </p>
      <p>Requested by: Contractor Nazifa</p>
      <p class="desc">Check structure safety before next phase.</p>
      <span class="priority high">High Priority</span>
    </div>

    <div class="task-actions">
      <button class="accept-btn">Accept</button>
      <button class="reject-btn">Reject</button>
    </div>
  </div>

</div>

</div>

</div>

</body>
</html>