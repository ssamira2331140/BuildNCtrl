<?php
session_start();
$required_role = 'client';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Client Chat</title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="../assets/css/style.css">

  <link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="client-page">

<!-- ================= SIDEBAR ================= -->
<div class="client-sidebar">

  <div class="sidebar-logo">
    <i class="fas fa-hard-hat"></i>
    <h2>BuildNCtrl</h2>
  </div>

  <ul class="menu">

    <li>
      <a href="my_projects.php">
        <i class="fas fa-folder"></i>
        My Projects
      </a>
    </li>

    <li>
      <a href="reports.php">
        <i class="fas fa-chart-bar"></i>
        Reports
      </a>
    </li>

    <li class="active">
      <a href="chat.php">
        <i class="fas fa-comments"></i>
        Chat
      </a>
    </li>

    <li class="logout">
      <a href="../auth/logout.php">
        <i class="fas fa-sign-out-alt"></i>
        Logout
      </a>
    </li>

  </ul>

</div>


<!-- ================= MAIN ================= -->
<div class="client-main">

  <!-- TOPBAR -->
  <div class="client-topbar">

    <h2>Client Dashboard</h2>

    <div class="client-user">
      <span class="avatar"><?php echo $sess_initials; ?></span>
      <span class="name"><?php echo $sess_fullname; ?></span>
    </div>

  </div>


  <!-- ================= CHAT LAYOUT ================= -->
  <div class="chat-layout">

    <!-- LEFT PANEL -->
    <div class="chat-list-panel">

      <input type="text"
      placeholder="Search conversations..."
      class="chat-search">

      <div class="conversation-list">

        <!-- ITEM -->
        <div class="conversation-item active">

          <span class="conv-avatar">NI</span>

          <div class="conv-info">

            <div class="conv-name">
              Nazifa Islam
              <span class="role-badge admin">
                Admin
              </span>
            </div>

            <p class="conv-preview">
              Approved already.
            </p>

          </div>

        </div>

        <!-- ITEM -->
        <div class="conversation-item">

          <span class="conv-avatar">MT</span>

          <div class="conv-info">

            <div class="conv-name">
              Maria Tabassum

              <span class="role-badge contractor">
                Contractor
              </span>
            </div>

            <p class="conv-preview">
              Hello!!
            </p>

          </div>

        </div>

      </div>

    </div>


    <!-- RIGHT PANEL -->
    <div class="chat-window-panel">

      <!-- HEADER -->
      <div class="chat-window-header">

        <div class="chat-user-info">

          <span class="conv-avatar">NI</span>

          <div>
            <div class="chat-user-name">
              Nazifa Islam
            </div>

            <span class="role-badge admin">
              Admin
            </span>
          </div>

        </div>

      </div>


      <!-- MESSAGES -->
      <div class="chat-messages">

        <!-- SENT -->
        <div class="message sent">

          <div class="bubble">

            Need an approval for a new project!!

            <span class="msg-time">
              8:47 AM
            </span>

          </div>

        </div>


        <!-- RECEIVED -->
        <div class="message received">

          <div class="bubble">

            Approved already.

            <span class="msg-time">
              8:51 AM
            </span>

          </div>

        </div>

      </div>


      <!-- INPUT -->
      <div class="chat-input-bar">

        <input type="text"
        placeholder="Type a message...">

        <button class="send-btn">
          <i class="fas fa-paper-plane"></i>
        </button>

      </div>

    </div>

  </div>

</div>

</body>
</html>