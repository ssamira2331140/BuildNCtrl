<?php
session_start();
$required_role = 'client';
require_once '../includes/session_guard.php';
require_once '../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>My Projects</title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="client-page">

<!-- ================= SIDEBAR ================= -->
<div class="client-sidebar">

  <div class="sidebar-logo">
    <i class="fas fa-hard-hat"></i>
    <h2>BuildNCtrl</h2>
  </div>

  <ul class="menu">

    <li class="active">
      <a href="my_projects.php">
        <i class="fas fa-folder"></i> My Projects
      </a>
    </li>

    <li>
      <a href="reports.php">
        <i class="fas fa-chart-bar"></i> Reports
      </a>
    </li>

    <li>
      <a href="chat.php">
        <i class="fas fa-comments"></i> Chat
      </a>
    </li>

    <li class="logout">
      <a href="../auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>

  </ul>

</div>

<!-- ================= MAIN ================= -->
<div class="client-main scrollable">

  <!-- TOPBAR -->
  <div class="client-topbar">

    <h2>Client Dashboard</h2>

    <div class="client-user">
      <span class="avatar"><?php echo $sess_initials; ?></span>
      <span class="name"><?php echo $sess_fullname; ?></span>
    </div>

  </div>

  <!-- PAGE HEADER -->
  <div class="projects-topbar">

    <div>
      <h2>My Projects</h2>
      <p>Track and manage your projects here</p>
    </div>

    <button class="request-btn" onclick="openModal()">
      <i class="fas fa-plus-circle"></i>
      Request New Project
    </button>

  </div>

  <!-- FILTER BAR -->
  <div class="filter-bar">

    <div class="filter-search">
      <i class="fas fa-search"></i>

      <input
        type="text"
        placeholder="Search projects..."
        oninput="searchProjects(this.value)"
      >
    </div>

    <div class="filter-tabs">

      <button class="filter-tab active"
              onclick="filterProjects('all', this)">
        All
      </button>

      <button class="filter-tab"
              onclick="filterProjects('active', this)">
        Active
      </button>

      <button class="filter-tab"
              onclick="filterProjects('completed', this)">
        Completed
      </button>

      <button class="filter-tab"
              onclick="filterProjects('pending', this)">
        Pending
      </button>

    </div>

  </div>

  <!-- ================= PROJECT LIST ================= -->
  <div class="projects-grid" id="projectsGrid">

    <!-- CARD -->
    <div class="project-card-new"
         data-status="active"
         onclick="window.location='project_details.php?id=1'">

      <img src="../assets/images/house.jpg"
           class="card-image">

      <div class="card-body">

        <h3 class="card-title">House Construction</h3>

        <p class="card-location">
          <i class="fas fa-map-marker-alt"></i>
          Chittagong
        </p>

        <div class="progress-row">

          <div class="progress-track">
            <div class="progress-fill" style="width:34%"></div>
          </div>

          <span class="progress-pct">34%</span>

        </div>

        <p class="card-meta">
          Started : 10th December, 2025
        </p>

        <p class="card-meta">
          Deadline : 19th September, 2026
        </p>

        <p class="card-status">
          Status :
          <span class="status-label active">Active</span>
        </p>

      </div>

    </div>

    <!-- CARD -->
    <div class="project-card-new"
         data-status="active"
         onclick="window.location='project_details.php?id=2'">

      <img src="../assets/images/school.jpg"
           class="card-image">

      <div class="card-body">

        <h3 class="card-title">School Building</h3>

        <p class="card-location">
          <i class="fas fa-map-marker-alt"></i>
          Dhaka
        </p>

        <div class="progress-row">

          <div class="progress-track">
            <div class="progress-fill" style="width:41%"></div>
          </div>

          <span class="progress-pct">41%</span>

        </div>

        <p class="card-meta">
          Started : 30th October, 2025
        </p>

        <p class="card-meta">
          Deadline : 3rd July, 2026
        </p>

        <p class="card-status">
          Status :
          <span class="status-label active">Active</span>
        </p>

      </div>

    </div>

    <!-- CARD -->
    <div class="project-card-new"
         data-status="completed"
         onclick="window.location='project_details.php?id=3'">

      <img src="../assets/images/bridge.jpg"
           class="card-image">

      <div class="card-body">

        <h3 class="card-title">Padma Bridge</h3>

        <p class="card-location">
          <i class="fas fa-map-marker-alt"></i>
          Munshiganj
        </p>

        <div class="progress-row">

          <div class="progress-track">
            <div class="progress-fill" style="width:100%"></div>
          </div>

          <span class="progress-pct">100%</span>

        </div>

        <p class="card-meta">
          Started : 26th November, 2014
        </p>

        <p class="card-meta">
          Deadline : 25th June, 2022
        </p>

        <p class="card-status">
          Status :
          <span class="status-label completed">Completed</span>
        </p>

      </div>

    </div>

    <!-- CARD -->
    <div class="project-card-new"
         data-status="pending"
         onclick="window.location='project_details.php?id=4'">

      <img src="../assets/images/hospital.jpg"
           class="card-image">

      <div class="card-body">

        <h3 class="card-title">Hospital Building</h3>

        <p class="card-location">
          <i class="fas fa-map-marker-alt"></i>
          Panchogor
        </p>

        <div class="progress-row">

          <div class="progress-track">
            <div class="progress-fill" style="width:0%"></div>
          </div>

          <span class="progress-pct">0%</span>

        </div>

        <p class="card-meta">
          Started : Not Yet
        </p>

        <p class="card-meta">
          Deadline : 20th October, 2027
        </p>

        <p class="card-status">
          Status :
          <span class="status-label pending">Pending</span>
        </p>

      </div>

    </div>

  </div>

</div>

<!-- ================= REQUEST PROJECT MODAL ================= -->
<div class="modal" id="requestModal">

  <div class="modal-content request-project-modal">

    <span class="close" onclick="closeModal()">&times;</span>

    <h3>Request a new project below</h3>

    <form>

      <div class="req-form-group">
        <label>Project Name</label>

        <input type="text"
               placeholder="Name of your project">
      </div>

      <div class="req-form-group">
        <label>Location</label>

        <input type="text"
               placeholder="Location of your project">
      </div>

      <div class="req-form-group">
        <label>Project Type</label>

        <select>
          <option>Select project type</option>
          <option>Residential</option>
          <option>Commercial</option>
          <option>Industrial</option>
          <option>Infrastructure</option>
        </select>
      </div>

      <div class="req-form-row">

        <div class="req-form-group">
          <label>Budget</label>

          <input type="number"
                 placeholder="Estimated budget">
        </div>

        <div class="req-form-group">
          <label>Deadline</label>

          <input type="date">
        </div>

      </div>

      <div class="req-form-group">
        <label>Description</label>

        <textarea placeholder="Describe about the requirements of your project here ..."></textarea>

        <div class="req-docs-row">
          <button type="button" class="req-docs-btn" onclick="document.getElementById('docUpload').click()">
            <i class="fas fa-paperclip"></i>Add Documents
          </button>
          <span class="req-docs-name" id="docFileName">No file chosen</span>
          <input type="file" id="docUpload" style="display:none;" onchange="handleDocUpload(this)">
        </div>
      </div>

      <button type="submit" class="req-submit-btn">
        Request
      </button>

    </form>

  </div>

</div>

<script>
  function handleDocUpload(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('docFileName').textContent = file.name;

    // Trigger download of the selected file
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = file.name;
    link.click();
    URL.revokeObjectURL(url);
  }

let currentFilter = 'all';

function filterProjects(status, btn){

  currentFilter = status;

  document.querySelectorAll('.filter-tab')
    .forEach(tab => tab.classList.remove('active'));

  btn.classList.add('active');

  document.querySelectorAll('.project-card-new')
    .forEach(card => {

      if(status === 'all' || card.dataset.status === status){
        card.style.display = 'flex';
      }
      else{
        card.style.display = 'none';
      }

    });
}

function searchProjects(query){

  query = query.toLowerCase();

  document.querySelectorAll('.project-card-new')
    .forEach(card => {

      const title =
        card.querySelector('.card-title')
            .textContent
            .toLowerCase();

      const matchesFilter =
        currentFilter === 'all'
        || card.dataset.status === currentFilter;

      if(title.includes(query) && matchesFilter){
        card.style.display = 'flex';
      }
      else{
        card.style.display = 'none';
      }

    });
}

function openModal(){
  document.getElementById("requestModal")
          .classList.add("active");
}

function closeModal(){
  document.getElementById("requestModal")
          .classList.remove("active");
}

</script>

</body>
</html>