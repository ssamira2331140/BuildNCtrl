<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Reports</title>
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
      <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
      <li><a href="projects.php"><i class="fa-solid fa-folder"></i> Projects</a></li>
      <li><a href="project_requests.php"><i class="fa-solid fa-file"></i> Project Requests</a></li>
      <li><a href="assign_contractor.php"><i class="fa-solid fa-user-tie"></i> Contractors</a></li>
      <li><a href="materials.php"><i class="fa-solid fa-box"></i> Materials</a></li>
      <li class="active"><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
      <li><a href="chat.php"><i class="fa-solid fa-comments"></i> Chat</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
  </div>

  <!-- MAIN -->
  <div class="admin-main">

    <!-- TOPBAR -->
    <div class="admin-topbar">
      <h2>Reports</h2>

      <div class="admin-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <p class="subtitle">Generate cost summary and progress reports</p>

    <!-- ===== REPORT FILTER ===== -->
    <div class="card report-filter">

      <div class="filter-group">

        <div>
          <label>Select Project</label>
          <select>
            <option>House Construction</option>
            <option>Padma Bridge</option>
          </select>
        </div>

        <button class="btn-main">Generate Report</button>

      </div>

    </div>

    
    <!-- ===== GENERATED REPORT ===== -->
<div class="report-full card">

  <div class="report-header">
    <h2>Construction Progress Report</h2>
  </div>

  <!-- PROJECT INFO -->
 <div class="report-info">

  <p><strong>Project Name:</strong> House Construction</p>
  <p><strong>Client:</strong> John Doe</p>
  <p><strong>Contractor:</strong> Samira</p>

  <p>
    <strong>Start Date:</strong> Dec 10, 2025 |
    <strong>Deadline:</strong> Sep 19, 2026
  </p>

  <p><strong>Status:</strong> Active</p>

</div>

  <!-- OVERVIEW -->
  <div class="report-section">
    <h3>Project Overview</h3>
    <p>
      Residential building project including foundation, structure,
      electrical and finishing work.
    </p>
  </div>

  <!-- PROGRESS -->
  <div class="report-section">
    <h3>Progress Made</h3>

    <table>
      <tr><td>Foundation</td><td>Completed</td></tr>
      <tr><td>Structure</td><td>Completed</td></tr>
      <tr><td>Electrical</td><td>In Progress</td></tr>
      <tr><td>Finishing</td><td>Not Started</td></tr>
    </table>
  </div>

  <!-- COST -->
  <div class="report-section">
    <h3>Cost Summary</h3>

    <table>
      <tr><td>Material Cost</td><td>$20,000</td></tr>
      <tr><td>Worker Cost</td><td>$10,000</td></tr>
      <tr><td>Contractor Cost</td><td>$15,000</td></tr>
      <tr><td><strong>Total</strong></td><td><strong>$45,000</strong></td></tr>
    </table>
  </div>

  <!-- WORKERS -->
  <div class="report-section">
    <h3>Worker Summary</h3>

    <p>Total Workers: 25</p>
    <p>Active: 18</p>
    <p>On Leave: 7</p>
  </div>

  <!-- MATERIALS -->
  <div class="report-section">
    <h3>Materials Usage</h3>

    <table>
      <tr><th>Material</th><th>Quantity</th></tr>
      <tr><td>Cement</td><td>500 Bags</td></tr>
      <tr><td>Steel</td><td>200 Kg</td></tr>
    </table>
  </div>

  <!-- DOWNLOAD BUTTON -->
  <div class="report-download">
    <button class="btn-main">Download Full Report</button>
  </div>

</div>

  </div>
</div>

</body>
</html>