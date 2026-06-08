<?php
session_start();
$required_role = 'admin';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
 <head>
   <title>Projects</title>

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
       <li class="active"><a href="projects.php"><i class="fas fa-folder"></i> Projects</a></li>
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

      <!-- TOPBAR -->
      <div class="admin-topbar">
        <h2>Active Projects</h2>

        <div class="admin-user">
           <span class="avatar"><?php echo $sess_initials; ?></span>
           <span class="name"><?php echo $sess_fullname; ?></span>
        </div>
      </div>

      <p class="chat-subtitle">Manage and monitor all ongoing construction projects.</p>

      <!-- ACTION BAR -->
      <div class="project-actions">

         <input type="text" placeholder="Search projects..." class="project-search">

         <button onclick="openModal()" class="create-btn">+ Create Project</button>

      </div>

      <!-- ===== PROJECT LIST ===== -->
      <div class="admin-project-list">

        <div class="admin-project-card">
          <img src="../assets/images/bridge.jpg" alt="" class="admin-project-img">
          <div class="admin-project-body">
            <div class="admin-project-top">
              <div>
                <h3>Bridge Construction</h3>
                <p><i class="fas fa-map-marker-alt"></i> Badda, Dhaka</p>
              </div>
              <a href="view_details.php?id=1" class="view-btn">View Details</a>
            </div>
            <div class="admin-project-progress-row">
              <span>Overall Progress</span>
              <span class="admin-project-pct">85%</span>
            </div>
            <div class="admin-project-bar"><div style="width:85%"></div></div>
          </div>
        </div>

        <div class="admin-project-card">
          <img src="../assets/images/house.jpg" alt="" class="admin-project-img">
          <div class="admin-project-body">
            <div class="admin-project-top">
              <div>
                <h3>House Construction</h3>
                <p><i class="fas fa-map-marker-alt"></i> Gulshan, Dhaka</p>
              </div>
              <a href="view_details.php?id=2" class="view-btn">View Details</a>
            </div>
            <div class="admin-project-progress-row">
              <span>Overall Progress</span>
              <span class="admin-project-pct">77%</span>
            </div>
            <div class="admin-project-bar"><div style="width:77%"></div></div>
          </div>
        </div>

      </div>

</div>

</div>

<!-- ===== CREATE PROJECT MODAL ===== -->
<div id="projectModal" class="modal">

<div class="modal-content large">

<div class="modal-header">
<h2>Create Project</h2>
<span class="close" onclick="closeModal()">&times;</span>
</div>

<p class="modal-subtitle">Enter project details and assign a contractor</p>

<form class="project-form">

  <!-- FULL WIDTH -->
  <div class="form-group">
    <input type="text" placeholder="Project Name">
  </div>

  <div class="form-group">
    <textarea placeholder="Description"></textarea>
  </div>

  <!-- ROW 1 -->
  <div class="form-row">
    <select>
      <option>Select contractor</option>
    </select>

    <input type="number" placeholder="Project Budget">
  </div>

  <!-- ROW 2 -->
  <div class="form-row">
    <input type="date">
    <input type="date">
  </div>

  <!-- MILESTONES -->
  <div class="form-group">
    <label>Project Milestones</label>

    <div id="milestoneList">

       <div id="milestones-container">

         <div class="milestone-item">

              <!-- NAME -->
            <div class="field-group">
              <label>Milestone Name</label>
              <input type="text" name="milestone_name[]" placeholder="Enter milestone name" required>
            </div>

              <!-- DATES -->
            <div class="form-row">
               <div class="field-group">
                 <label>Start Date</label>
                 <input type="date" name="milestone_start[]" required>
                </div>

                <div class="field-group">
                 <label>Deadline</label>
                 <input type="date" name="milestone_end[]" required>
                </div>
             </div>

                <!-- BUDGET -->
              <div class="field-group">
                <label>Budget</label>
                <input type="number" name="milestone_budget[]" placeholder="Enter budget" required>
              </div>

               <!-- DESCRIPTION -->
              <div class="field-group">
               <label>Description</label>
               <textarea name="milestone_desc[]" placeholder="Enter description..." required></textarea>
              </div>

                <!-- REMOVE -->
              <button type="button" class="remove-btn" onclick="removeMilestone(this)"> ✖ Remove </button>
           </div>

        </div>

      </div>

      <button type="button" onclick="addMilestone()" class="add-milestone"> + Add Milestone</button>
    </div>

    <!-- BUTTONS -->
    <div class="modal-actions">
     <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
     <button type="submit" class="submit">Create Project</button>
    </div>

</form>

</div>

</div>

<script>
function openModal(){
  document.getElementById("projectModal").classList.add("active");
}

function closeModal(){
  document.getElementById("projectModal").classList.remove("active");
}

function addMilestone(){
  const container = document.getElementById("milestones-container");

  const div = document.createElement("div");
  div.className = "milestone-item";

  div.innerHTML = `
    <div class="field-group">
      <label>Milestone Name</label>
      <input type="text" name="milestone_name[]" placeholder="Enter milestone name" required>
    </div>

    <div class="form-row">
      <div class="field-group">
        <label>Start Date</label>
        <input type="date" name="milestone_start[]" required>
      </div>

      <div class="field-group">
        <label>Deadline</label>
        <input type="date" name="milestone_end[]" required>
      </div>
    </div>

    <div class="field-group">
      <label>Budget</label>
      <input type="number" name="milestone_budget[]" placeholder="Enter budget" required>
    </div>

    <div class="field-group">
      <label>Description</label>
      <textarea name="milestone_desc[]" placeholder="Enter description..." required></textarea>
    </div>

    <button type="button" class="remove-btn" onclick="removeMilestone(this)">✖ Remove</button>
  `;

  container.appendChild(div);
}

function removeMilestone(btn){
  const container = document.getElementById("milestones-container");

  if(container.children.length > 1){
    btn.parentElement.remove();
  }
}
</script>

</body>
</html>