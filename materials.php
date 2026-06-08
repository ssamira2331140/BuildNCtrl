<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Materials</title>
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
        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="projects.php"><i class="fas fa-folder"></i> Projects</a></li>
        <li><a href="project_requests.php"><i class="fas fa-file-alt"></i> Project Requests</a></li>
        <li><a href="assign_contractor.php"><i class="fas fa-user-tie"></i> Contractors</a></li>
        <li class="active"><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
        <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>

  </div>

  <!-- ===== MAIN ===== -->
  <div class="admin-main">

    <!-- TOPBAR -->
    <div class="admin-topbar">
      <h2>Materials</h2>

      <div class="admin-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <p class="chat-subtitle">Manage material categories for construction projects.</p>

    <!-- ===== CARD ===== -->
    <div class="materials-card">

      <div class="materials-header">
        <h3>Material Categories</h3>
        <button class="add-btn" onclick="openModal()">+ Add Category</button>
      </div>

      <input type="text" placeholder="Search categories..." class="search-box">

      <!-- ===== TABLE ===== -->
      <table class="materials-table">

        <tr>
          <th>Category</th>
          <th>Available Items</th>
          <th>Unit Cost</th>
          <th>Total Used</th>
          <th>Total Cost</th>
          <th>Action</th>
        </tr>

        <tr>
          <td>Cement</td>
          <td>8</td>
          <td>$100 / ton</td>
          <td>150 tons</td>
          <td>$15,000</td>
          <td>
            <button class="edit-btn">Edit</button>
            <button class="delete-btn">Delete</button>
          </td>
        </tr>

        <tr>
          <td>Bricks</td>
          <td>12</td>
          <td>$0.50 / unit</td>
          <td>25,000 units</td>
          <td>$12,500</td>
          <td>
            <button class="edit-btn">Edit</button>
            <button class="delete-btn">Delete</button>
          </td>
        </tr>

        <tr>
          <td>Steel</td>
          <td>15</td>
          <td>$2500 / ton</td>
          <td>20 tons</td>
          <td>$50,000</td>
          <td>
            <button class="edit-btn">Edit</button>
            <button class="delete-btn">Delete</button>
          </td>
        </tr>

      </table>

    </div>

  </div>

</div>

<!-- ===== MODAL ===== -->
<div id="materialModal" class="modal">

  <div class="modal-content">

    <div class="modal-header">
      <h3>Add Material Category</h3>
      <span class="close" onclick="closeModal()">&times;</span>
    </div>

    <form>

      <input type="text" placeholder="Material Name" required>
      <input type="text" placeholder="Unit (kg, ton, etc)" required>
      <input type="number" placeholder="Unit Cost ($)" required>

      <div class="modal-actions">
        <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="submit">Add Category</button>
      </div>

    </form>

  </div>

</div>

<script>
function openModal(){
  document.getElementById("materialModal").classList.add("active");
}

function closeModal(){
  document.getElementById("materialModal").classList.remove("active");
}
</script>

</body>
</html>