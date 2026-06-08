<?php
session_start();
$required_role = 'client';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Reports</title>
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
      <div><h2>Client Dashboard</h2></div>
      <div class="client-user">
        <span class="avatar"><?php echo $sess_initials; ?></span>
        <span class="name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>
  
    <!-- REPORTS HEADER -->
    <div class="reports-header">
      <h2>Reports</h2>
      <p>Take a look on your projects here</p>
    </div>

    <!-- SEARCH -->
    <div class="reports-search">
      <div class="filter-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search projects...">
      </div>
    </div>

    <!-- TABLE HEADER -->
    <div class="reports-table-header">
      <span>Project Name</span>
      <span>Company Name</span>
      <span>Timeline</span>
      <span>Report</span>
    </div>

    <!-- ROW 1 -->
    <div class="report-row">
      <div class="report-project">
        <img src="../assets/images/house.jpg" alt="House Construction">
        <div class="report-project-info">
          <h4>House Construction</h4>
          <p class="card-location"><i class="fas fa-map-marker-alt"></i> Chittagong</p>
        </div>
      </div>
      <ul class="report-companies">
        <li>ABC Builders</li>
        <li>X Architect</li>
      </ul>
      <div class="report-timeline">10th December,2025 -<br>19th September,2026</div>
      <button class="track-btn" onclick="openTrackModal()">Track Report</button>
    </div>

    <!-- ROW 2 -->
    <div class="report-row">
      <div class="report-project">
        <img src="../assets/images/school.jpg" alt="School Building">
        <div class="report-project-info">
          <h4>School Building</h4>
          <p class="card-location"><i class="fas fa-map-marker-alt"></i> Dhaka</p>
        </div>
      </div>
      <ul class="report-companies">
        <li>CBA Construction</li>
        <li>Y Limited</li>
      </ul>
      <div class="report-timeline">30th October,2025 -<br>3rd July,2026</div>
      <button class="track-btn" onclick="openTrackModal()">Track Report</button>
    </div>

    <!-- ROW 3 -->
    <div class="report-row">
      <div class="report-project">
        <img src="../assets/images/bridge.jpg" alt="Padma Bridge">
        <div class="report-project-info">
          <h4>Padma Bridge</h4>
          <p class="card-location"><i class="fas fa-map-marker-alt"></i> Munshiganj</p>
        </div>
      </div>
      <ul class="report-companies">
        <li>China Major Bridge Engineering Company Limited (MBEC).</li>
        <li>Abdul Monem Limited(AML), Bangladesh.</li>
      </ul>
      <div class="report-timeline">26 November,2014 -<br>23 June,2022</div>
      <button class="view-btn-report" onclick="openViewModal()">View Report</button>
    </div>

  </div>

  <!-- ===== TRACK REPORT MODAL ===== -->
  <div class="track-modal-overlay" id="trackModal" onclick="closeTrackOutside(event)">
    <div class="track-modal-box">

      <button class="track-modal-close" onclick="closeTrackModal()">&times;</button>

      <!-- TOP ROW -->
      <div class="tm-top-row">

        <!-- PROJECT INFO -->
        <div class="tm-project-box">
          <img src="../assets/images/house.jpg" alt="House Construction">
          <div class="tm-project-info">
            <h3>Project: House Construction</h3>
            <p class="card-location"><i class="fas fa-map-marker-alt"></i> Chittagong</p>
            <p>
              Contractor : ABC Builders<br>
              Started : 10th Dec,2025<br>
              Deadline : 19th Sep,2026<br>
              Budget : $150,000
            </p>
            <p class="card-status" style="margin-top:6px">Status : <span class="status-label active">Active</span></p>
          </div>
        </div>

        <!-- TASK TABLE -->
        <div class="tm-task-box">
          <h4>Task Table</h4>
          <div class="tm-task-list">
            <div class="tm-task-item done">
              <span class="tm-task-icon done"><i class="fas fa-check"></i></span> Foundation
            </div>
            <div class="tm-task-item done">
              <span class="tm-task-icon done"><i class="fas fa-check"></i></span> Structure
            </div>
            <div class="tm-task-item inprogress">
              <span class="tm-task-icon inprogress"><i class="fas fa-spinner"></i></span> Roofing
            </div>
            <div class="tm-task-item pending">
              <span class="tm-task-icon pending"><i class="fas fa-circle"></i></span> Electrical
            </div>
            <div class="tm-task-item pending">
              <span class="tm-task-icon pending"><i class="fas fa-circle"></i></span> Interior
            </div>
            <button class="gantt-open-btn" onclick="openGanttModal()">
              <i class="fas fa-chart-gantt"></i> View Gantt Chart
            </button>
          </div>
        </div>

      </div>

      <!-- BOTTOM ROW -->
      <div class="tm-bottom-row">

        <!-- MATERIALS -->
        <div class="tm-materials-box">
          <h4>Materials Usage</h4>
          <table class="tm-materials-table">
            <thead>
              <tr><th>Material</th><th>Quantity</th></tr>
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
        <div class="tm-worklogs-box">
          <h4>Work Logs</h4>
          <table class="tm-worklogs-table">
            <thead>
              <tr><th>Timeline</th><th>Workers</th><th>Description</th></tr>
            </thead>
            <tbody>
              <tr>
                <td>10th Dec - 14th Dec</td>
                <td>20</td>
                <td><i class="fas fa-circle" style="font-size:5px;color:#555;margin-right:5px"></i>Execution and foundation preparation</td>
              </tr>
              <tr>
                <td>19th Dec - 27th Dec</td>
                <td>60</td>
                <td><i class="fas fa-circle" style="font-size:5px;color:#555;margin-right:5px"></i>Installed steel reinforcement for structural support</td>
              </tr>
              <tr>
                <td>29st Dec - 2nd Jan</td>
                <td>47</td>
                <td><i class="fas fa-circle" style="font-size:5px;color:#555;margin-right:5px"></i>Wall construction for floor completed</td>
              </tr>
              <tr>
                <td>5th Jan - 9th Jan</td>
                <td>32</td>
                <td><i class="fas fa-circle" style="font-size:5px;color:#555;margin-right:5px"></i>Floor leveling completed</td>
              </tr>
              <tr>
                <td>11th Jan - 18th Jan</td>
                <td>17</td>
                <td><i class="fas fa-circle" style="font-size:5px;color:#555;margin-right:5px"></i>Roofing framework installed and alignment checked</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="tm-footer">
        <button class="tm-download-btn" onclick="window.print()">
          <i class="fas fa-download"></i> Download PDF
        </button>
      </div>

    </div>
  </div>

<script>
  function openTrackModal() {
    document.getElementById('trackModal').classList.add('active');
  }
  function closeTrackModal() {
    document.getElementById('trackModal').classList.remove('active');
  }
  function closeTrackOutside(e) {
    if (e.target === document.getElementById('trackModal')) closeTrackModal();
  }
  function openViewModal() {
    document.getElementById('viewModal').classList.add('active');
  }
  function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
  }
  function closeViewOutside(e) {
    if (e.target === document.getElementById('viewModal')) closeViewModal();
  }
</script>

<!-- ===== VIEW REPORT MODAL ===== -->
<div class="view-modal-overlay" id="viewModal" onclick="closeViewOutside(event)">
  <div class="view-modal-box">

    <button class="view-modal-close" onclick="closeViewModal()">&times;</button>

    <!-- TITLE -->
    <div class="vr-title">COMPLETED PROJECT REPORT</div>

    <!-- META -->
    <div class="vr-meta">
      <strong>Project Name:</strong> Padma Bridge
    </div>
    <div class="vr-meta">
      <strong>Client:</strong> Government of Bangladesh
    </div>
    <div class="vr-meta">
      <strong>Contractor:</strong> China Major Bridge Engineering Co., Ltd.
    </div>
    <div class="vr-meta-row">
      <span><strong>Start Date:</strong> Jul 26, 2014</span>
      <span>|</span>
      <span><strong>Completion Date:</strong> Jun 25, 2022</span>
      <span>|</span>
      <span><strong>Status:</strong> <span class="vr-status-completed">Completed</span></span>
    </div>

    <!-- PROJECT OVERVIEW -->
    <div class="vr-section">
      <div class="vr-section-header">PROJECT OVERVIEW</div>
      <div class="vr-section-body">
        Construction of a two-level road-rail bridge over the Padma River, connecting south-west Bangladesh to the rest of the country.
      </div>
    </div>

    <!-- KEY MILESTONES -->
    <div class="vr-section">
      <div class="vr-section-header">KEY MILESTONES</div>
      <table class="vr-table">
        <thead>
          <tr><th>Milestone</th><th>Completed On</th></tr>
        </thead>
        <tbody>
          <tr><td>Project Approval</td><td>Nov 23, 2011</td></tr>
          <tr><td>Main Construction Start</td><td>Jul 26, 2014</td></tr>
          <tr><td>Bridge Structure Completion</td><td>Dec 10, 2020</td></tr>
          <tr><td>Road & Rail Link Completion</td><td>Mar 15, 2022</td></tr>
          <tr><td>Project Completion</td><td>Jun 25, 2022</td></tr>
        </tbody>
      </table>
    </div>

    <!-- SUCCESS BANNER -->
    <div class="vr-success-banner">
      <span class="vr-success-icon"><i class="fas fa-check"></i></span>
      Project Completed Successfully on Jun 25, 2022
    </div>

    <!-- WORKER SUMMARY -->
    <div class="vr-section">
      <div class="vr-section-header">WORKER SUMMARY</div>
      <div class="vr-worker-row">
        <div class="vr-worker-col">
          <div class="label">Total Workers</div>
          <div class="value">12,500</div>
        </div>
        <div class="vr-worker-col">
          <div class="label">Active Workers (Peak)</div>
          <div class="value">7,850</div>
        </div>
        <div class="vr-worker-col">
          <div class="label">On Leave (Avg.)</div>
          <div class="value">1,150</div>
        </div>
      </div>
    </div>

    <!-- MATERIALS USAGE -->
    <div class="vr-section">
      <div class="vr-section-header">MATERIALS USAGE</div>
      <table class="vr-table">
        <thead>
          <tr><th>Material</th><th>Total Used</th></tr>
        </thead>
        <tbody>
          <tr><td>Concrete</td><td>2.6 Million m³</td></tr>
          <tr><td>Steel</td><td>127,000 Tons</td></tr>
          <tr><td>Reinforcement Steel</td><td>228,000 Tons</td></tr>
          <tr><td>Structural Steel</td><td>73,000 Tons</td></tr>
          <tr><td>Asphalt</td><td>105,000 Tons</td></tr>
        </tbody>
      </table>
    </div>

    <!-- COST + BUDGET -->
    <div class="vr-two-col">

      <div class="vr-section" style="margin-bottom:0">
        <div class="vr-section-header">COST SUMMARY</div>
        <table class="vr-table">
          <tbody>
            <tr><td>Material Cost</td><td>$1,310 Million</td></tr>
            <tr><td>Worker Cost</td><td>$550 Million</td></tr>
            <tr><td>Contractor Cost</td><td>$640 Million</td></tr>
            <tr><td><strong>Total Cost</strong></td><td><strong>$2,500 Million</strong></td></tr>
          </tbody>
        </table>
      </div>

      <div class="vr-section" style="margin-bottom:0">
        <div class="vr-section-header">BUDGET SUMMARY</div>
        <table class="vr-table">
          <tbody>
            <tr><td>Total Budget</td><td>$2,500 Million</td></tr>
            <tr><td>Total Spent</td><td>$2,500 Million</td></tr>
            <tr><td>Remaining Budget</td><td>$0</td></tr>
            <tr><td><strong>Budget Usage</strong></td><td style="color:#2e7d32;font-weight:700">100%</td></tr>
          </tbody>
        </table>
      </div>

    </div>

    <!-- DOWNLOAD -->
    <div class="vr-footer">
      <button class="vr-download-btn" onclick="window.print()">
        <i class="fas fa-download"></i> Download Report
      </button>
    </div>

  </div>
</div>


<!-- ===== GANTT CHART MODAL ===== -->
<div class="gantt-overlay" id="ganttModal" onclick="if(event.target===this)closeGanttModal()">
  <div class="gantt-box">

    <div class="gantt-header">
      <h3>Milestone Progress</h3>
      <button class="gantt-close" onclick="closeGanttModal()">&times;</button>
    </div>

    <div class="gantt-body">

      <div class="gantt-chart">

        <!-- MONTH HEADER -->
        <div class="gantt-row gantt-row-header">
          <div class="gantt-label-col">Milestone</div>
          <div class="gantt-bar-col">
            <div class="gantt-months">
              <span>Dec</span>
              <span>Jan</span>
              <span>Feb</span>
              <span>Mar</span>
              <span>Apr</span>
              <span>May</span>
              <span>Sep</span>
            </div>
          </div>
          <div class="gantt-pct-col">%</div>
        </div>

        <!-- FOUNDATION -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot done"></span> Foundation
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar done" style="margin-left:0%;width:7%;"></div>
            </div>
          </div>
          <div class="gantt-pct-col done">100%</div>
        </div>

        <!-- STRUCTURE -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot done"></span> Structure
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar done" style="margin-left:7%;width:13%;"></div>
            </div>
          </div>
          <div class="gantt-pct-col done">100%</div>
        </div>

        <!-- ROOFING -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot active"></span> Roofing
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar active-bg" style="margin-left:20%;width:18%;"></div>
              <div class="gantt-bar active" style="margin-left:20%;width:11%;"></div>
            </div>
          </div>
          <div class="gantt-pct-col active">60%</div>
        </div>

        <!-- ELECTRICAL -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot pending"></span> Electrical
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar pending" style="margin-left:40%;width:18%;"></div>
            </div>
          </div>
          <div class="gantt-pct-col pending">0%</div>
        </div>

        <!-- INTERIOR -->
        <div class="gantt-row">
          <div class="gantt-label-col">
            <span class="gantt-dot pending"></span> Interior
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div class="gantt-bar pending" style="margin-left:59%;width:41%;"></div>
            </div>
          </div>
          <div class="gantt-pct-col pending">0%</div>
        </div>

      </div>

      <!-- LEGEND -->
      <div class="gantt-legend">
        <span class="gantt-legend-item"><span class="gantt-dot done"></span> Completed</span>
        <span class="gantt-legend-item"><span class="gantt-dot active"></span> In Progress</span>
        <span class="gantt-legend-item"><span class="gantt-dot pending"></span> Pending</span>
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