<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>View Details</title>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body class="admin-page">

<div class="admin-container">

<!-- SIDEBAR -->
<div class="admin-sidebar">
<div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i>
      <h2>BuildNCtrl</h2>
    </div>

<ul class="menu">
<li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
<li class="active"><a href="projects.php"><i class="fas fa-folder"></i> Projects</a></li>
<li><a href="project_requests.php"><i class="fas fa-file-alt"></i> Project Requests</a></li>
<li><a href="assign_contractor.php"><i class="fas fa-user-tie"></i> Contractors</a></li>
<li><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
<li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
<li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
<li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>

<!-- MAIN -->
<div class="admin-main">

<!-- TOPBAR -->
<div class="admin-topbar">
  <div class="admin-user" style="gap:14px;">
    <a href="projects.php" class="vd-back-btn"><i class="fas fa-times"></i></a>
    <span style="font-size:18px;font-weight:700;color:#222;">Project Details</span>
  </div>
  <div class="admin-user">
    <span class="avatar"><?php echo $sess_initials; ?></span>
    <span><?php echo $sess_fullname; ?></span>
  </div>
</div>

<!-- ===== PROJECT HEADER ===== -->
<div class="project-header card">

  <h2>House Construction</h2>

  <p class="meta">
    📍 Chittagong |
    Client: John Doe |
    Contractor: Samira
  </p>

  <p class="meta">
    Starting Date: Dec 10,2025 → Deadline: Sep 19,2026
  </p>

  <p class="description">
    Residential building construction including foundation, structure, electrical and finishing work.
  </p>

</div>


<!-- ===== MILESTONES (FULL WIDTH) ===== -->
<div class="card milestone-card">

  <h3>Project Milestones</h3>

  <div class="milestone-table">

    <!-- HEADER -->
    <div class="milestone-header">
      <span></span>
      <span>Milestone</span>
      <span>Description</span>
      <span>Timeline</span>
      <span>Budget</span>
      <span>Status</span>
    </div>

    <!-- ROW -->
    <div class="milestone-row">
      <input type="checkbox" checked>

      <span class="title">Foundation</span>
      <span class="desc">Initial base structure completed</span>

      <span class="timeline">Sep 1 → Sep 10</span>
      <span class="budget">$10,000</span>

      <span class="status done">Done</span>
    </div>

    <div class="milestone-row">
      <input type="checkbox">

      <span class="title">Structure</span>
      <span class="desc">Building frame completed</span>

      <span class="timeline">Sep 11 → Sep 25</span>
      <span class="budget">$25,000</span>

      <span class="status progress">In Progress</span>
    </div>

    <div class="milestone-row">
      <input type="checkbox">

      <span class="title">Electrical</span>
      <span class="desc">Wiring setup</span>

      <span class="timeline">Oct 1 → Oct 15</span>
      <span class="budget">$8,000</span>

      <span class="status pending">Pending</span>
    </div>

  </div>

</div>


<!-- ===== MAIN 2 COLUMN SECTION ===== -->
<div class="details-grid">

  <!-- LEFT SIDE -->
  <div class="left-side">

    <!-- WORKERS -->
    <div class="card">
      <h3>Workers</h3>

      <p>Total Workers: <strong>25</strong></p>
      <p>Active Today: <strong>18</strong></p>
      <p>On Leave: <strong>7</strong></p>
    </div>

    <!-- MATERIALS -->
    <div class="card">
      <h3>Materials Used</h3>

      <table>
        <tr><th>Material</th><th>Quantity</th></tr>
        <tr><td>Cement</td><td>500 Bags</td></tr>
        <tr><td>Steel</td><td>200 Kg</td></tr>
      </table>
    </div>

  </div>


  <!-- RIGHT SIDE -->
  <div class="right-side">

    <!-- BUDGET -->
    <div class="card">
      <h3>Budget Summary</h3>

      <p>Total Budget: <strong>$150,000</strong></p>
      <p>Spent: <strong>$60,000</strong></p>
      <p>Remaining: <strong>$90,000</strong></p>
    </div>


  </div>

</div>


<!-- ===== WORK LOGS (FULL WIDTH) ===== -->
<div class="card full">

  <h3>Work Logs</h3>

  <table>
    <tr>
      <th>Date</th>
      <th>Contructor</th>
      <th>Description</th>
    </tr>

    <tr>
      <td>Mar 10</td>
      <td>Samira</td>
      <td>Foundation work completed</td>
    </tr>

  </table>

</div>

</div>

</div>

</div>
</div>

</body>
</html>