<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Contractors</title>
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
      <li class="active"><a href="assign_contractor.php"><i class="fas fa-user-tie"></i> Contractors</a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
      <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>

  </div>

  <!-- ===== MAIN ===== -->
  <div class="admin-main">

    <!-- TOPBAR -->
    <div class="admin-topbar">
      <h2>Contractors</h2>

      <div class="admin-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <p class="chat-subtitle">Manage and assign contractors to projects.</p>

    <!-- HEADER -->
    <div class="contractor-header">
      <input type="text" placeholder="Search contractors..." class="search-box">
      <button class="add-btn" onclick="openModal()">Add New Contractor</button>
    </div>

    <!-- ===== CARDS ===== -->
    <div class="contractor-list">

      <!-- CARD 1 -->
      <div class="contractor-card">
        <div class="card-top">
          <div class="avatar">S</div>
          <div>
            <h4>Samira</h4>
            <span>Commercial Construction</span>
          </div>
        </div>

        <p>📧 samira123@gmail.com</p>
        <p>📞 ********</p>

        <button class="assign-btn">Assign to Project</button>
      </div>

      <!-- CARD 2 -->
      <div class="contractor-card">
        <div class="card-top">
          <div class="avatar">M</div>
          <div>
            <h4>Maria</h4>
            <span>Residential Construction</span>
          </div>
        </div>

        <p>📧 maria@gmail.com</p>
        <p>📞 ********</p>

        <button class="assign-btn">Assign to Project</button>
      </div>

      <!-- CARD 3 -->
      <div class="contractor-card">
        <div class="card-top">
          <div class="avatar">N</div>
          <div>
            <h4>Nazifa</h4>
            <span>Renovation & Remodeling</span>
          </div>
        </div>

        <p>📧 nazifa@gmail.com</p>
        <p>📞 ********</p>

        <button class="assign-btn">Assign to Project</button>
      </div>

    </div>

  </div>

</div>

<!-- ===== MODAL ===== -->
<div id="contractorModal" class="modal">

  <div class="modal-content">

    <div class="modal-header">
      <h3>Add New Contractor</h3>
      <span class="close" onclick="closeModal()">&times;</span>
    </div>

    <form>

      <input type="text" placeholder="Contractor Name" required>
      <input type="email" placeholder="Email" required>
      <input type="text" placeholder="Phone Number" required>
      <input type="text" placeholder="Type (e.g. Residential)" required>

      <div class="modal-actions">
        <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="submit">Add Contractor</button>
      </div>

    </form>

  </div>

</div>

<script>
function openModal(){
  document.getElementById("contractorModal").classList.add("active");
}

function closeModal(){
  document.getElementById("contractorModal").classList.remove("active");
}
</script>

</body>
</html>