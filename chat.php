<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Chat</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="admin-page chat-page">

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
      <li><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
      <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      <li class="active"><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>

  </div>

  <!-- ===== MAIN ===== -->
  <div class="admin-main">

    <!-- TOPBAR -->
    <div class="admin-topbar">
      <h2>Admin Chat</h2>

      <div class="admin-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <p class="chat-subtitle">Communicate with contractors and clients</p>

    <!-- ===== CHAT CONTAINER ===== -->
    <div class="chat-container">

      <!-- LEFT: USERS -->
      <div class="chat-users">

        <input type="text" placeholder="Search conversations..." class="chat-search">

        <div class="user-item active">
          <div class="avatar">SS</div>
          <div>
            <p class="name">Sumsun Nahar Samira</p>
            <span class="role">Client</span>
          </div>
        </div>

        <!-- more users later -->

      </div>

      <!-- RIGHT: CHAT AREA -->
      <div class="chat-box">

        <!-- HEADER -->
        <div class="chat-header">

  <div class="chat-user-info">

    <div class="avatar">SS</div>

    <div class="user-text">
      <h4>Sumsun Nahar Samira</h4>
      <span class="role-badge">Client</span>
    </div>

  </div>

</div>

        <!-- MESSAGES -->
        <div class="chat-messages">

          <div class="message received">
            <div class="bubble">
               <p>Hello!!</p>
               <span class="time">10:30 AM</span>
            </div>
          </div>

          <div class="message sent">
             <div class="bubble">
                <p>Hi! How can I help?</p>
                <span class="time">10:32 AM</span>
              </div>
          </div>

        </div>

        <!-- INPUT -->
        <div class="chat-input">
          <input type="text" placeholder="Type a message...">
          <button>➤</button>
        </div>

      </div>

    </div>

  </div>

</div>

</body>
</html>