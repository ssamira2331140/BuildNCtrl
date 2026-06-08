<?php
// ============================================================
// FILE:    contractor/materials.php
// UPDATED: Added material cost calculations.
//          Total Cost per row  = quantity × unit_cost (PHP)
//          Project subtotal    = SUM of row totals (PHP)
//          Overall total       = SUM of all project subtotals (PHP)
//          No extra DB queries — calculated from already-fetched data.
//          New columns in table: Unit Cost | Total Cost
//          New project footer: Project Total Cost
//          New overall banner: Overall Material Usage Cost
//
//  unit_cost column EXISTS in materials table (DECIMAL 10,2).
//  No SQL patch required.
//
// EXISTING FUNCTIONALITY UNCHANGED:
//   - Add / Edit / Delete material (all handlers untouched)
//   - Search bar, modals, fetch() delete — all untouched
//   - CSS classes, HTML structure — all preserved
// NO INLINE CSS. NO INLINE JS.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── HANDLE: DELETE material ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_material') {
    $mat_id = (int) ($_POST['mat_id'] ?? 0);
    if ($mat_id > 0) {
        $stmt = mysqli_prepare($conn,
            "DELETE m FROM materials m
             JOIN projects p ON p.id = m.project_id
             WHERE m.id = ? AND p.contractor_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $mat_id, $sess_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit();
}

// ── HANDLE: ADD material ──────────────────────────────────────
$add_error   = '';
$add_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_material') {

    $project_id = (int)   ($_POST['project_id']   ?? 0);
    $mat_name   = trim(   $_POST['material_name'] ?? '');
    $quantity   = (float) ($_POST['quantity']      ?? 0);
    $unit       = trim(   $_POST['unit']           ?? '');
    $unit_cost  = (float) ($_POST['unit_cost']     ?? 0);
    $date_used  = trim(   $_POST['date_used']      ?? '');
    $status     = trim(   $_POST['status']         ?? 'in_stock');
    $date_val   = !empty($date_used) ? $date_used : null;

    $allowed_statuses = ['in_stock', 'low_stock', 'out_of_stock'];

    if ($project_id <= 0)                          { $add_error = 'Please select a project.'; }
    elseif (empty($mat_name))                      { $add_error = 'Material name is required.'; }
    elseif ($quantity <= 0)                        { $add_error = 'Quantity must be greater than 0.'; }
    elseif (empty($unit))                          { $add_error = 'Unit is required (e.g. Bags, Tons).'; }
    elseif (!in_array($status, $allowed_statuses)) { $add_error = 'Invalid status value.'; }
    else {
        $stmt = mysqli_prepare($conn,
            "SELECT id FROM projects WHERE id = ? AND contractor_id = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ii", $project_id, $sess_id);
        mysqli_stmt_execute($stmt);
        $res     = mysqli_stmt_get_result($stmt);
        $proj_ok = (mysqli_num_rows($res) > 0);
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);

        if (!$proj_ok) {
            $add_error = 'Invalid project selected.';
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO materials
                     (project_id, added_by, material_name, quantity,
                      unit, unit_cost, date_used, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "iisdsdss",
                $project_id, $sess_id, $mat_name,
                $quantity, $unit, $unit_cost, $date_val, $status
            );
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($saved) {
                $add_success = htmlspecialchars($mat_name) . ' added successfully.';
            } else {
                $add_error = 'Failed to save. Please try again.';
            }
        }
    }
}

// ── HANDLE: EDIT material ─────────────────────────────────────
$edit_error   = '';
$edit_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_material') {

    $mat_id    = (int)   ($_POST['mat_id']        ?? 0);
    $mat_name  = trim(   $_POST['material_name']  ?? '');
    $quantity  = (float) ($_POST['quantity']       ?? 0);
    $unit      = trim(   $_POST['unit']            ?? '');
    $unit_cost = (float) ($_POST['unit_cost']      ?? 0);
    $date_used = trim(   $_POST['date_used']       ?? '');
    $status    = trim(   $_POST['status']          ?? 'in_stock');
    $date_val  = !empty($date_used) ? $date_used : null;

    $allowed_statuses = ['in_stock', 'low_stock', 'out_of_stock'];

    if ($mat_id <= 0 || empty($mat_name) || $quantity <= 0 || empty($unit)) {
        $edit_error = 'Please fill in all required fields.';
    } elseif (!in_array($status, $allowed_statuses)) {
        $edit_error = 'Invalid status value.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE materials m
             JOIN   projects p ON p.id = m.project_id
             SET    m.material_name = ?,
                    m.quantity      = ?,
                    m.unit          = ?,
                    m.unit_cost     = ?,
                    m.date_used     = ?,
                    m.status        = ?
             WHERE  m.id = ? AND p.contractor_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "sdsdssii",
            $mat_name, $quantity, $unit,
            $unit_cost, $date_val, $status,
            $mat_id, $sess_id
        );
        $updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($updated) {
            $edit_success = htmlspecialchars($mat_name) . ' updated successfully.';
        } else {
            $edit_error = 'Update failed. Please try again.';
        }
    }
}

// ── QUERY: Contractor's projects ──────────────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT id, project_name FROM projects
     WHERE  contractor_id = ?
     ORDER  BY FIELD(status,'active','pending','on_hold','completed'),
               project_name ASC"
);
mysqli_stmt_bind_param($stmt, "i", $sess_id);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$projects = [];
while ($r = mysqli_fetch_assoc($result)) {
    $projects[] = $r;
}
mysqli_free_result($result);
mysqli_stmt_close($stmt);

// ── QUERY: All materials for all projects ─────────────────────
$materials_by_project = [];
$proj_ids = array_column($projects, 'id');

if (!empty($proj_ids)) {
    $ph    = implode(',', array_fill(0, count($proj_ids), '?'));
    $types = str_repeat('i', count($proj_ids));
    $stmt  = mysqli_prepare($conn,
        "SELECT id, project_id, material_name, quantity, unit,
                unit_cost, date_used, status
         FROM   materials
         WHERE  project_id IN ($ph)
         ORDER  BY id ASC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...array_values($proj_ids));
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($m = mysqli_fetch_assoc($result)) {
        $materials_by_project[$m['project_id']][] = $m;
    }
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
}

// ── COST CALCULATIONS (PHP — no extra queries) ────────────────
// Walk the already-fetched $materials_by_project array.
// Row total    = quantity × unit_cost  (per material row)
// Proj total   = sum of row totals     (per project block)
// Overall total = sum of proj totals   (page-level banner)

$project_totals = [];   // [ project_id => float ]
$overall_total  = 0.0;

foreach ($materials_by_project as $pid => $mats) {
    $proj_sum = 0.0;
    foreach ($mats as $mat) {
        $row_total = (float)$mat['quantity'] * (float)$mat['unit_cost'];
        $proj_sum += $row_total;
    }
    $project_totals[$pid] = $proj_sum;
    $overall_total        += $proj_sum;
}

// ── HELPERS ───────────────────────────────────────────────────
function mat_css(string $s): string {
    return match($s) {
        'in_stock'     => 'instock',
        'low_stock'    => 'low',
        'out_of_stock' => 'out',
        default        => 'instock',
    };
}
function mat_label(string $s): string {
    return match($s) {
        'in_stock'     => 'In Stock',
        'low_stock'    => 'Low Stock',
        'out_of_stock' => 'Out of Stock',
        default        => ucfirst($s),
    };
}
function mat_date(?string $d): string {
    if (empty($d)) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d M Y') : $d;
}
// Format a float as a currency string: 1234.5 → "$1,234.50"
function fmt_cost(float $n): string {
    return '$' . number_format($n, 2, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contractor Materials</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <aside class="contractor-sidebar">
    <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2>
    </div>
    <ul class="sidebar-menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li class="active"><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

  <main class="contractor-main">

    <div class="contractor-topbar">
      <div>
        <h2>Contractor Dashboard</h2>
        <p>Manage your projects, workers, and daily tasks</p>
      </div>
      <div class="contractor-user">
        <div class="contractor-avatar"><?php echo $sess_initials; ?></div>
        <span class="contractor-name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <div class="mat-page">

      <!--
        MAT-TOPBAR: unchanged structure, title and subtitle on left.
        Overall cost summary added on RIGHT side of the same flex row.
        .mat-topbar is display:flex; justify-content:space-between
        so adding a right-side child is layout-safe.
      -->
      <div class="mat-topbar">
        <div>
          <h2>Materials</h2>
          <p>Track material usage for each project</p>
        </div>

        <!--
          OVERALL COST BANNER — right side of topbar.
          Only shown when there are materials with costs.
          .mat-overall-cost styled in additions.css.
          id="overallCostDisplay" read by JS to update on delete.
        -->
        <?php if ($overall_total > 0): ?>
        <div class="mat-overall-cost">
          <span class="mat-overall-label">
            <i class="fas fa-coins"></i> Overall Material Cost
          </span>
          <span class="mat-overall-value" id="overallCostDisplay">
            <?php echo fmt_cost($overall_total); ?>
          </span>
        </div>
        <?php endif; ?>
      </div>

      <?php if (!empty($add_error)):   ?><div class="feedback-error">⚠️ <?php echo $add_error;    ?></div><?php endif; ?>
      <?php if (!empty($add_success)): ?><div class="feedback-success">✅ <?php echo $add_success; ?></div><?php endif; ?>
      <?php if (!empty($edit_error)):  ?><div class="feedback-error">⚠️ <?php echo $edit_error;   ?></div><?php endif; ?>
      <?php if (!empty($edit_success)): ?><div class="feedback-success">✅ <?php echo $edit_success; ?></div><?php endif; ?>

      <!-- SEARCH BAR — unchanged -->
      <div class="mat-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="projectSearch" class="mat-search"
               placeholder="Search project by name...">
        <button class="mat-search-clear" id="searchClear" hidden>
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="mat-no-results" id="noResults" hidden>
        <i class="fas fa-folder-open"></i>
        <p>No project found for "<span id="searchTerm"></span>"</p>
      </div>

      <!-- PROJECT BLOCKS -->
      <?php if (empty($projects)): ?>
        <div class="empty-state">
          <i class="fas fa-box-open"></i>
          No projects found. Materials appear once you are assigned to a project.
        </div>

      <?php else: ?>
        <?php foreach ($projects as $idx => $proj):
          $proj_id    = (int) $proj['id'];
          $proj_name  = htmlspecialchars($proj['project_name'], ENT_QUOTES);
          $proj_mats  = $materials_by_project[$proj_id] ?? [];
          $mat_count  = count($proj_mats);
          $proj_total = $project_totals[$proj_id] ?? 0.0;
        ?>

        <div class="mat-project-block"
             data-project="<?php echo strtolower($proj_name); ?>"
             data-project-total="<?php echo number_format($proj_total, 2, '.', ''); ?>"
             <?php echo ($idx > 0) ? 'hidden' : ''; ?>>

          <!-- PROJECT HEADER — unchanged structure -->
          <div class="mat-project-header">
            <div class="mat-project-title">
              <i class="fas fa-folder"></i>
              <h3><?php echo $proj_name; ?></h3>
              <span class="mat-project-badge">
                <?php echo $mat_count; ?> material<?php echo $mat_count !== 1 ? 's' : ''; ?>
              </span>
            </div>
            <button class="mat-entry-btn"
                    data-project-id="<?php echo $proj_id; ?>"
                    data-project-name="<?php echo $proj_name; ?>"
                    onclick="openAddModal(this)">
              <i class="fas fa-plus"></i> Add Entry
            </button>
          </div>

          <?php if (empty($proj_mats)): ?>
            <p class="mat-empty-row">No materials recorded for this project yet.</p>

          <?php else: ?>
            <table class="mat-table">
              <thead>
                <tr>
                  <th>SR</th>
                  <th>Material</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Unit Cost</th>
                  <th>Total Cost</th>
                  <th>Date Used</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($proj_mats as $sr => $mat):
                  $mat_id       = (int) $mat['id'];
                  $mat_name_d   = htmlspecialchars($mat['material_name'], ENT_QUOTES);
                  $mat_qty      = rtrim(rtrim(number_format((float)$mat['quantity'], 2), '0'), '.');
                  $mat_unit     = htmlspecialchars($mat['unit'],      ENT_QUOTES);
                  $mat_cost_raw = (float) $mat['unit_cost'];
                  $mat_cost     = htmlspecialchars($mat['unit_cost'], ENT_QUOTES);
                  $mat_date_d   = mat_date($mat['date_used']);
                  $mat_css      = mat_css($mat['status']);
                  $mat_lbl      = mat_label($mat['status']);
                  $mat_raw_d    = htmlspecialchars($mat['date_used'] ?? '', ENT_QUOTES);
                  $mat_status   = $mat['status'];
                  // Row total cost: quantity × unit_cost
                  $row_total    = (float)$mat['quantity'] * $mat_cost_raw;
                  $row_total_fmt = fmt_cost($row_total);
                  // Pass numeric value to JS via data attribute for live recalculation
                  $row_total_raw = number_format($row_total, 2, '.', '');
                ?>
                <tr>
                  <td><?php echo $sr + 1; ?></td>
                  <td><?php echo $mat_name_d; ?></td>
                  <td><?php echo $mat_qty; ?></td>
                  <td><?php echo $mat_unit; ?></td>
                  <td class="mat-cost-cell"><?php echo fmt_cost($mat_cost_raw); ?></td>
                  <td class="mat-total-cell"
                      data-row-total="<?php echo $row_total_raw; ?>">
                    <?php echo $row_total_fmt; ?>
                  </td>
                  <td><?php echo $mat_date_d; ?></td>
                  <td><span class="mat-status <?php echo $mat_css; ?>"><?php echo $mat_lbl; ?></span></td>
                  <td>
                    <button class="edit-btn"
                            data-mat-id="<?php echo $mat_id; ?>"
                            data-mat-name="<?php echo $mat_name_d; ?>"
                            data-mat-qty="<?php echo $mat_qty; ?>"
                            data-mat-unit="<?php echo $mat_unit; ?>"
                            data-mat-cost="<?php echo $mat_cost; ?>"
                            data-mat-date="<?php echo $mat_raw_d; ?>"
                            data-mat-status="<?php echo $mat_status; ?>"
                            data-proj-name="<?php echo $proj_name; ?>"
                            onclick="openEditModal(this)">
                      <i class="fas fa-pen"></i>
                    </button>
                    <button class="delete-btn"
                            data-mat-id="<?php echo $mat_id; ?>"
                            data-mat-name="<?php echo $mat_name_d; ?>"
                            onclick="deleteMaterial(this)">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <!--
              PROJECT SUBTOTAL — shown below each project's table.
              .mat-proj-total-footer styled in additions.css.
              id uses proj_id so JS can target it per block.
              data-proj-id on the footer lets JS find it by project.
            -->
            <div class="mat-proj-total-footer" id="projTotal_<?php echo $proj_id; ?>">
              <span class="mat-proj-total-label">
                <i class="fas fa-calculator"></i>
                <?php echo htmlspecialchars($proj_name, ENT_QUOTES); ?> — Total Material Cost
              </span>
              <span class="mat-proj-total-value">
                <?php echo fmt_cost($proj_total); ?>
              </span>
            </div>

          <?php endif; ?>

        </div><!-- end .mat-project-block -->

        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- end .mat-page -->
  </main>

  <!-- ADD MATERIAL MODAL — unchanged -->
  <div class="modal" id="addMaterialModal">
    <div class="modal-content large">
      <div class="modal-header">
        <h2>Add Material Entry</h2>
        <span class="close" id="closeAddModal">&times;</span>
      </div>
      <p class="modal-subtitle">Record material usage for a project</p>
      <form method="POST" action="" class="project-form">
        <input type="hidden" name="action" value="add_material">
        <div class="form-group">
          <label>Project</label>
          <select name="project_id" id="addProjectSelect" required>
            <option value="" disabled selected>Select project</option>
            <?php foreach ($projects as $pr): ?>
              <option value="<?php echo (int)$pr['id']; ?>">
                <?php echo htmlspecialchars($pr['project_name'], ENT_QUOTES); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Material Name</label>
          <input type="text" name="material_name"
                 placeholder="e.g. Cement, Steel Rod" required>
        </div>
        <div class="form-row">
          <input type="number" name="quantity" placeholder="Quantity"
                 step="0.01" min="0.01" required>
          <input type="text"   name="unit"     placeholder="Unit (e.g. Bags, Tons)" required>
        </div>
        <div class="form-group">
          <label>Unit Cost ($)</label>
          <input type="number" name="unit_cost" placeholder="Cost per unit"
                 step="0.01" min="0" value="0">
        </div>
        <div class="form-group">
          <label>Date Used</label>
          <input type="date" name="date_used">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="in_stock">In Stock</option>
            <option value="low_stock">Low Stock</option>
            <option value="out_of_stock">Out of Stock</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="cancel" id="cancelAddModal">Cancel</button>
          <button type="submit" class="submit">Add Entry</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT MATERIAL MODAL — unchanged -->
  <div class="modal" id="editMaterialModal">
    <div class="modal-content large">
      <div class="modal-header">
        <h2>Edit Material Entry</h2>
        <span class="close" id="closeEditModal">&times;</span>
      </div>
      <p class="modal-subtitle">Update material details for this project</p>
      <form method="POST" action="" class="project-form">
        <input type="hidden" name="action"  value="edit_material">
        <input type="hidden" name="mat_id"  id="editMatId">
        <div class="form-group">
          <label>Project</label>
          <input type="text" id="editProject" class="readonly-field" readonly>
        </div>
        <div class="form-group">
          <label>Material Name</label>
          <input type="text" name="material_name" id="editMaterial" required>
        </div>
        <div class="form-row">
          <input type="number" name="quantity"  id="editQty"  step="0.01" min="0.01" required>
          <input type="text"   name="unit"      id="editUnit" required>
        </div>
        <div class="form-group">
          <label>Unit Cost ($)</label>
          <input type="number" name="unit_cost" id="editCost" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label>Date Used</label>
          <input type="date" name="date_used" id="editDate">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="editStatus">
            <option value="in_stock">In Stock</option>
            <option value="low_stock">Low Stock</option>
            <option value="out_of_stock">Out of Stock</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="button" class="cancel" id="cancelEditModal">Cancel</button>
          <button type="submit" class="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/script.js"></script>
</body>
</html>
