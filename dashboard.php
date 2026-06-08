<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
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
      <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="projects.php"><i class="fas fa-folder"></i> Projects</a></li>
      <li><a href="project_requests.php"><i class="fas fa-file-alt"></i> Project Requests</a></li>
      <li><a href="assign_contractor.php"><i class="fas fa-user-tie"></i> Contractors</a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
      <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>

  </div>

  <!-- ===== MAIN ===== -->
  <div class="admin-main">

    <!-- TOP BAR -->
    <div class="admin-topbar">
      <h2>Admin Dashboard</h2>

      <div class="admin-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <!-- CARDS -->
    <div class="admin-cards">

      <div class="card">
        <h3>Total Projects</h3>
        <h1>12</h1>
      </div>

      <div class="card">
        <h3>Active Projects</h3>
        <h1>06</h1>
      </div>

      <div class="card">
        <h3>Contractors</h3>
        <h1>08</h1>
      </div>

      <div class="card">
        <h3>Workers</h3>
        <h1>54</h1>
      </div>

    </div>


    <!-- TABLE -->
    <div class="admin-table">

      <h3>Upcoming Deadlines</h3>

      <table>
        <tr>
          <th>SR</th>
          <th>Project</th>
          <th>Name</th>
          <th>Location</th>
          <th>Cost</th>
          <th>Start</th>
          <th>Deadline</th>
          <th>Contractor</th>
          <th>Status</th>
        </tr>

        <tr>
          <td>1</td>
          <td>Bridge</td>
          <td>Abcd Bridge</td>
          <td>Dhaka</td>
          <td>100000</td>
          <td>12-2-2025</td>
          <td>25-3-2026</td>
          <td>Mr XYZ</td>
          <td>Ongoing</td>
        </tr>

        <tr>
          <td>2</td>
          <td>House</td>
          <td>Abcd House</td>
          <td>Dhaka</td>
          <td>100000</td>
          <td>12-2-2025</td>
          <td>25-3-2026</td>
          <td>Mr XYZ</td>
          <td>Ongoing</td>
        </tr>

      </table>

    </div>

  </div>

</div>

</body>
</html>