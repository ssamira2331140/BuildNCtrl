<?php
session_start();
$required_role = 'client';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Track Report</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="client-page">

  <!-- ===== SIDEBAR ===== -->
  <div class="client-sidebar">
    <div class="sidebar-logo">
    <i class="fas fa-hard-hat"></i>
    <h2>BuildNCtrl</h2>
  </div>
    <ul class="menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </div>

  <!-- ===== MAIN ===== -->
  <div class="client-main scrollable">

    <!-- TOP BAR -->
    <div class="client-topbar">
      <div>
        <h2>Client Dashboard</h2>
      </div>
      <div class="client-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <!-- HEADER -->
    <div class="track-header">
      <h2>Active Projects Tracking</h2>
      <p>Track your ongoing projects here</p>
    </div>

    <!-- SEARCH -->
    <div class="track-search">
      <div class="filter-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search projects...">
      </div>
    </div>

    <!-- ===== MAIN CARD ===== -->
    <div class="track-card-wrapper">

      <!-- TOP ROW -->
      <div class="track-top-row">

        <!-- PROJECT INFO -->
        <div class="track-project-box">
          <img src="../assets/images/house.jpg" alt="House Construction">
          <div class="track-project-info">
            <h3>Project: House Construction</h3>
            <p class="card-location"><i class="fas fa-map-marker-alt"></i> Chittagong</p>
            <p>
              Contractor : ABC Builders<br>
              Started : 10th Dec,2025<br>
              Deadline : 19th Sep,2026<br>
              Budget : $150,000
            </p>
            <p class="card-status" style="margin-top:8px">Status : <span class="status-label active">Active</span></p>
          </div>
        </div>

        <!-- MILESTONE PROGRESS -->
        <div class="track-milestone-box">
          <h4>Milestone Progress</h4>
          <div class="pie-wrapper">
            <div class="pie-chart">
              <div class="pie-inner">34%</div>
            </div>
          </div>
          <div class="pie-legend">
            <div class="pie-legend-item">
              <span class="legend-dot done"></span> Work done
            </div>
            <div class="pie-legend-item">
              <span class="legend-dot left"></span> Work left
            </div>
          </div>
        </div>

        <!-- TASK TABLE -->
        <div class="track-task-box">
          <h4>Task Table</h4>
          <div class="task-list">
            <div class="task-item done-task">
              <span class="task-icon done-icon"><i class="fas fa-check"></i></span>
              Foundation
            </div>
            <div class="task-item done-task">
              <span class="task-icon done-icon"><i class="fas fa-check"></i></span>
              Structure
            </div>
            <div class="task-item active-task">
              <span class="task-icon active-icon"><i class="fas fa-spinner"></i></span>
              Roofing
            </div>
            <div class="task-item pending-task">
              <span class="task-icon pending-icon"><i class="fas fa-circle"></i></span>
              Electrical
            </div>
            <div class="task-item pending-task">
              <span class="task-icon pending-icon"><i class="fas fa-circle"></i></span>
              Interior
            </div>
            <!-- GANTT BUTTON -->
            <button class="gantt-open-btn" onclick="openGanttModal()">
              <i class="fas fa-chart-gantt"></i> View Gantt Chart
            </button>
          </div>
        </div>

      </div>

      <!-- BOTTOM ROW -->
      <div class="track-bottom-row">

        <!-- MATERIALS USAGE -->
        <div class="track-materials-box">
          <h4>Materials Usage</h4>
          <table class="materials-table">
            <thead>
              <tr>
                <th>Material</th>
                <th>Quantity</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Cement</td><td>500 Bags</td></tr>
              <tr><td>Steel</td><td>3500 Kg</td></tr>
              <tr><td>Bricks</td><td>8000 Pcs</td></tr>
              <tr><td>Sand</td><td>26 Tons</td></tr>
              <tr><td>Binding Wire</td><td>14 Coils</td></tr>
            </tbody>
          </table>
        </div>

        <!-- WORK LOGS -->
        <div class="track-worklogs-box">
          <h4>Work Logs</h4>
          <table class="worklogs-table">
            <thead>
              <tr>
                <th>Timeline</th>
                <th>Workers</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td></td>
                <td>20</td>
                <td><i class="fas fa-circle" style="font-size:6px;color:#555;margin-right:6px"></i> Execution and foundation preparation</td>
              </tr>
              <tr>
                <td></td>
                <td>60</td>
                <td><i class="fas fa-circle" style="font-size:6px;color:#555;margin-right:6px"></i> Installed steel reinforcement for structural support</td>
              </tr>
              <tr>
                <td></td>
                <td>47</td>
                <td><i class="fas fa-circle" style="font-size:6px;color:#555;margin-right:6px"></i> Wall construction for floor completed</td>
              </tr>
              <tr>
                <td></td>
                <td>32</td>
                <td><i class="fas fa-circle" style="font-size:6px;color:#555;margin-right:6px"></i> Floor leveling completed</td>
              </tr>
              <tr>
                <td></td>
                <td>17</td>
                <td><i class="fas fa-circle" style="font-size:6px;color:#555;margin-right:6px"></i> Roofing framework installed and alignment checked</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- DOWNLOAD BTN -->
      <div class="track-footer">
        <button class="download-btn" onclick="window.print()">
          <i class="fas fa-download"></i> Download PDF
        </button>
      </div>

    </div>

  </div>

<!-- ===== GANTT CHART MODAL ===== -->
<div class="gantt-overlay" id="ganttModal" onclick="if(event.target===this)closeGanttModal()">
  <div class="gantt-box">

    <div class="gantt-header">
      <h3>Milestone Progress — Gantt Chart</h3>
      <button class="gantt-close" onclick="closeGanttModal()">&times;</button>
    </div>

    <div class="gantt-body">

      <!-- LEGEND -->
      <div class="gantt-legend">
        <span class="gantt-legend-item"><span class="gantt-dot done"></span> Completed</span>
        <span class="gantt-legend-item"><span class="gantt-dot active"></span> In Progress</span>
        <span class="gantt-legend-item"><span class="gantt-dot pending"></span> Pending</span>
      </div>

      <!-- CHART -->
      <div class="gantt-chart">

        <!-- HEADER ROW -->
        <div class="gantt-row gantt-row-header">
          <div class="gantt-label-col">Milestone</div>
          <div class="gantt-bar-col">
            <div class="gantt-months">
              <span>Dec</span><span>Jan</span><span>Feb</span><span>Mar</span>
              <span>Apr</span><span>May</span><span>Jun</span>
            </div>
          </div>
        </div>

        <!-- FOUNDATION (Done: Dec 10 - Dec 25) -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot done"></span> Foundation
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar done" style="margin-left:0%; width:10%;"></div>
            </div>
          </div>
        </div>

        <!-- STRUCTURE (Done: Dec 19 - Jan 10) -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot done"></span> Structure
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar done" style="margin-left:6%; width:15%;"></div>
            </div>
          </div>
        </div>

        <!-- ROOFING (Active: Jan 5 - Feb 10) -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot active"></span> Roofing
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar active" style="margin-left:18%; width:20%;"></div>
            </div>
          </div>
        </div>

        <!-- ELECTRICAL (Pending: Feb 15 - Mar 20) -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot pending"></span> Electrical
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar pending" style="margin-left:40%; width:22%;"></div>
            </div>
          </div>
        </div>

        <!-- INTERIOR (Pending: Mar 25 - May 30) -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot pending"></span> Interior
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar pending" style="margin-left:62%; width:30%;"></div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
  function openGanttModal()  { document.getElementById('ganttModal').classList.add('active'); }
  function closeGanttModal() { document.getElementById('ganttModal').classList.remove('active'); }
</script>

</body>
</html>