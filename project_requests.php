<?php
session_start();
$required_role = 'admin';

require_once '../includes/session_guard.php';
require_once '../config/db.php';

$approve_error = '';
$approve_success = '';
$reject_success = '';
$reject_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject_request') {
    $req_id = (int)($_POST['request_id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($req_id <= 0) {
        $reject_error = 'Invalid request.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE project_requests
             SET status = 'rejected',
                 rejection_reason = ?
             WHERE id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, "si", $reason, $req_id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $reject_success = $affected > 0
            ? 'Project request rejected.'
            : 'Request already processed or not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_request') {
    $req_id        = (int)($_POST['request_id'] ?? 0);
    $contractor_id = (int)($_POST['contractor_id'] ?? 0);
    $budget        = (float)($_POST['budget'] ?? 0);
    $start_date    = trim($_POST['start_date'] ?? '');
    $end_date      = trim($_POST['end_date'] ?? '');

    $ms_names      = $_POST['milestone_name'] ?? [];
    $ms_starts     = $_POST['milestone_start'] ?? [];
    $ms_ends       = $_POST['milestone_end'] ?? [];
    $ms_budgets    = $_POST['milestone_budget'] ?? [];
    $ms_descs      = $_POST['milestone_desc'] ?? [];
    $ms_priorities = $_POST['milestone_priority'] ?? [];

    if ($req_id <= 0) {
        $approve_error = 'Invalid request.';
    } elseif ($contractor_id <= 0) {
        $approve_error = 'Please select a contractor.';
    } elseif ($budget <= 0) {
        $approve_error = 'Budget must be greater than 0.';
    } elseif (empty($start_date)) {
        $approve_error = 'Start date is required.';
    } elseif (empty($end_date)) {
        $approve_error = 'End date is required.';
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT client_id, project_name, location, project_type, description, document_path
             FROM project_requests
             WHERE id = ? AND status = 'pending'
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "i", $req_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $request = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        if (!$request) {
            $approve_error = 'Request not found or already processed.';
        } else {
            $stmt = mysqli_prepare($conn,
                "SELECT id FROM users WHERE id = ? AND role = 'contractor' LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, "i", $contractor_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $contractor_ok = mysqli_num_rows($result) > 0;
            mysqli_free_result($result);
            mysqli_stmt_close($stmt);

            if (!$contractor_ok) {
                $approve_error = 'Invalid contractor selected.';
            } else {
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO projects
                        (request_id, client_id, contractor_id, project_name,
                         location, project_type, budget, start_date, end_date,
                         description, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                );

                mysqli_stmt_bind_param($stmt, "iiisssdsss",
                    $req_id,
                    $request['client_id'],
                    $contractor_id,
                    $request['project_name'],
                    $request['location'],
                    $request['project_type'],
                    $budget,
                    $start_date,
                    $end_date,
                    $request['description']
                );

                $project_saved = mysqli_stmt_execute($stmt);
                $project_id = $project_saved ? mysqli_insert_id($conn) : 0;
                mysqli_stmt_close($stmt);

                if (!$project_saved || $project_id <= 0) {
                    $approve_error = 'Failed to create project.';
                } else {
                    $milestone_errors = 0;

                    foreach ($ms_names as $i => $name) {
                        $name = trim($name);
                        if ($name === '') {
                            continue;
                        }

                        $priority = trim($ms_priorities[$i] ?? 'medium');
                        if (!in_array($priority, ['low', 'medium', 'high'])) {
                            $priority = 'medium';
                        }

                        $ms_start = trim($ms_starts[$i] ?? '') ?: null;
                        $ms_end   = trim($ms_ends[$i] ?? '') ?: null;
                        $ms_budget = (float)($ms_budgets[$i] ?? 0);
                        $ms_desc = trim($ms_descs[$i] ?? '');

                        $stmt = mysqli_prepare($conn,
                            "INSERT INTO milestones
                                (project_id, title, description, start_date,
                                 end_date, budget, priority, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
                        );

                        mysqli_stmt_bind_param($stmt, "issssds",
                            $project_id,
                            $name,
                            $ms_desc,
                            $ms_start,
                            $ms_end,
                            $ms_budget,
                            $priority
                        );

                        if (!mysqli_stmt_execute($stmt)) {
                            $milestone_errors++;
                        }

                        mysqli_stmt_close($stmt);
                    }

                    $stmt = mysqli_prepare($conn,
                        "UPDATE project_requests SET status = 'approved' WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($stmt, "i", $req_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $approve_success = 'Project approved and assigned to contractor.';
                    if ($milestone_errors > 0) {
                        $approve_success .= " {$milestone_errors} milestone(s) failed to save.";
                    }
                }
            }
        }
    }
}

$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending', 'approved', 'rejected', 'all'])) {
    $filter = 'pending';
}

if ($filter === 'all') {
    $stmt = mysqli_prepare($conn,
        "SELECT pr.id, pr.project_name, pr.location, pr.project_type,
                pr.budget AS requested_budget, pr.deadline,
                pr.description, pr.document_path, pr.submitted_at,
                pr.status, pr.rejection_reason,
                CONCAT(u.first_name, ' ', u.last_name) AS client_name,
                u.email AS client_email
         FROM project_requests pr
         JOIN users u ON u.id = pr.client_id
         ORDER BY pr.submitted_at DESC"
    );
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT pr.id, pr.project_name, pr.location, pr.project_type,
                pr.budget AS requested_budget, pr.deadline,
                pr.description, pr.document_path, pr.submitted_at,
                pr.status, pr.rejection_reason,
                CONCAT(u.first_name, ' ', u.last_name) AS client_name,
                u.email AS client_email
         FROM project_requests pr
         JOIN users u ON u.id = pr.client_id
         WHERE pr.status = ?
         ORDER BY pr.submitted_at DESC"
    );
    mysqli_stmt_bind_param($stmt, "s", $filter);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$requests = [];

while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = $row;
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn,
    "SELECT status, COUNT(*) AS total
     FROM project_requests
     GROUP BY status"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$counts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

while ($row = mysqli_fetch_assoc($result)) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['total'];
    }
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn,
    "SELECT id, CONCAT(first_name, ' ', last_name) AS name, specialization
     FROM users
     WHERE role = 'contractor'
     ORDER BY first_name ASC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$contractors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $contractors[] = $row;
}

mysqli_free_result($result);
mysqli_stmt_close($stmt);

function format_date_value($date) {
    if (empty($date)) return '—';
    return date('F j, Y', strtotime($date));
}

function format_money_value($amount) {
    return '$' . number_format((float)$amount, 0);
}

function status_class($status) {
    if ($status === 'approved') return 'done';
    if ($status === 'rejected') return 'rejected';
    return 'pending';
}

function document_url($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    return '../' . ltrim($path, '/');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Requests</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body class="admin-page">

<div class="admin-container">

    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-hard-hat"></i>
            <h2>BuildNCtrl</h2>
        </div>

        <ul class="menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="projects.php"><i class="fas fa-folder"></i> Projects</a></li>
            <li class="active"><a href="project_requests.php"><i class="fas fa-file-alt"></i> Project Requests</a></li>
            <li><a href="assign_contractor.php"><i class="fas fa-user-tie"></i> Contractors</a></li>
            <li><a href="materials.php"><i class="fas fa-box"></i> Materials</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
            <li class="logout"><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="admin-main">

        <div class="admin-topbar">
            <div>
                <h2>Project Requests</h2>
                <p class="chat-subtitle">Review and approve incoming project requests from clients.</p>
            </div>

            <div class="admin-user">
                <span class="avatar"><?php echo htmlspecialchars($sess_initials); ?></span>
                <span><?php echo htmlspecialchars($sess_fullname); ?></span>
            </div>
        </div>

        <?php if ($approve_error): ?>
            <div class="feedback-error">⚠️ <?php echo htmlspecialchars($approve_error); ?></div>
        <?php endif; ?>

        <?php if ($approve_success): ?>
            <div class="feedback-success">✅ <?php echo htmlspecialchars($approve_success); ?></div>
        <?php endif; ?>

        <?php if ($reject_error): ?>
            <div class="feedback-error">⚠️ <?php echo htmlspecialchars($reject_error); ?></div>
        <?php endif; ?>

        <?php if ($reject_success): ?>
            <div class="feedback-success">✅ <?php echo htmlspecialchars($reject_success); ?></div>
        <?php endif; ?>

        <div class="pr-filter-tabs">
            <a class="pr-filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" href="project_requests.php?filter=pending">
                <i class="fas fa-clock"></i> Pending
                <span class="pr-tab-count"><?php echo $counts['pending']; ?></span>
            </a>

            <a class="pr-filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>" href="project_requests.php?filter=approved">
                <i class="fas fa-check-circle"></i> Approved
                <span class="pr-tab-count"><?php echo $counts['approved']; ?></span>
            </a>

            <a class="pr-filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>" href="project_requests.php?filter=rejected">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="pr-tab-count"><?php echo $counts['rejected']; ?></span>
            </a>

            <a class="pr-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" href="project_requests.php?filter=all">
                <i class="fas fa-list"></i> All
                <span class="pr-tab-count"><?php echo array_sum($counts); ?></span>
            </a>
        </div>

        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                No project requests found.
            </div>
        <?php else: ?>

            <div class="pr-cards-list">

                <?php foreach ($requests as $req): ?>
                    <?php
                        $req_id = (int)$req['id'];
                        $project_name = htmlspecialchars($req['project_name']);
                        $client_name = htmlspecialchars($req['client_name']);
                        $description = htmlspecialchars($req['description'] ?? '');
                        $budget = format_money_value($req['requested_budget']);
                        $location = htmlspecialchars($req['location']);
                        $submitted = format_date_value($req['submitted_at']);
                        $deadline = format_date_value($req['deadline']);
                        $status = $req['status'];
                        $badge_class = status_class($status);
                        $doc_path = trim($req['document_path'] ?? '');
                        $doc_href = document_url($doc_path);
                    ?>

                    <div class="request-card frontend-request-card">

                        <div class="request-header frontend-request-header">
                            <h3><?php echo $project_name; ?></h3>

                            <span class="status <?php echo $badge_class; ?>">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>

                        <p class="frontend-request-line">
                            <strong>Client:</strong>
                            <?php echo $client_name; ?> | <?php echo $submitted; ?>
                        </p>

                        <p class="frontend-request-desc">
                            <?php echo $description; ?>
                        </p>

                        <p class="frontend-request-line">
                            <strong>Budget:</strong> <?php echo $budget; ?>
                        </p>

                        <p class="frontend-request-line">
                            <strong>Location:</strong> <?php echo $location; ?>
                        </p>

                        <?php if ($status === 'rejected' && !empty($req['rejection_reason'])): ?>
                            <p class="pr-rejection-note">
                                <i class="fas fa-exclamation-circle"></i>
                                Rejection reason: <?php echo htmlspecialchars($req['rejection_reason']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="frontend-request-footer">

                            <div class="frontend-request-actions-left">
                                <?php if ($status === 'pending'): ?>
                                    <button class="approve-btn"
                                            data-req-id="<?php echo $req_id; ?>"
                                            data-proj-name="<?php echo htmlspecialchars($req['project_name'], ENT_QUOTES); ?>"
                                            data-proj-desc="<?php echo htmlspecialchars($req['description'] ?? '', ENT_QUOTES); ?>"
                                            data-budget="<?php echo htmlspecialchars($req['requested_budget'], ENT_QUOTES); ?>"
                                            data-deadline="<?php echo htmlspecialchars($req['deadline'] ?? '', ENT_QUOTES); ?>"
                                            onclick="openApproveModal(this)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>

                                    <button class="reject-btn"
                                            data-req-id="<?php echo $req_id; ?>"
                                            data-proj-name="<?php echo htmlspecialchars($req['project_name'], ENT_QUOTES); ?>"
                                            onclick="openRejectModal(this)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="frontend-request-actions-right">
                                <?php if (!empty($doc_href)): ?>
                                    <a class="req-doc-download-btn"
                                       href="<?php echo htmlspecialchars($doc_href); ?>"
                                       download>
                                        <i class="fas fa-file-download"></i> Documents
                                    </a>
                                <?php else: ?>
                                    <button class="req-doc-download-btn" type="button" disabled>
                                        <i class="fas fa-file-download"></i> No Document
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

</div>

<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reject Request</h2>
            <span class="close" id="closeRejectModal">&times;</span>
        </div>

        <p class="modal-subtitle" id="rejectModalSubtitle"></p>

        <form method="POST" action="" id="rejectForm">
            <input type="hidden" name="action" value="reject_request">
            <input type="hidden" name="request_id" id="rejectReqId">

            <div class="form-group">
                <label>Rejection Reason <span class="pr-optional">(optional)</span></label>
                <textarea name="rejection_reason"
                          class="pr-reject-textarea"
                          placeholder="Explain why this request is being rejected..."
                          rows="4"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="cancel" id="cancelRejectModal">Cancel</button>
                <button type="submit" class="submit pr-reject-submit">
                    <i class="fas fa-times"></i> Confirm Reject
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="approveModal">
    <div class="modal-content large">
        <div class="modal-header">
            <h2>Approve & Create Project</h2>
            <span class="close" id="closeApproveModal">&times;</span>
        </div>

        <p class="modal-subtitle">Assign a contractor, set dates, and define project milestones.</p>

        <form method="POST" action="" id="approveForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="approve_request">
            <input type="hidden" name="request_id" id="approveReqId">

            <h3 class="project-title" id="approveProjectName"></h3>
            <p class="project-desc" id="approveProjectDesc"></p>

            <div class="form-row">
                <div class="form-group">
                    <label>Assign Contractor</label>
                    <select name="contractor_id" required>
                        <option value="" disabled selected>Select contractor</option>

                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?php echo (int)$contractor['id']; ?>">
                                <?php echo htmlspecialchars($contractor['name']); ?>
                                <?php if (!empty($contractor['specialization'])): ?>
                                    — <?php echo htmlspecialchars($contractor['specialization']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>

                        <?php if (empty($contractors)): ?>
                            <option disabled>No contractors registered yet</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Project Budget ($)</label>
                    <input type="number"
                           name="budget"
                           id="approveBudget"
                           min="0"
                           step="0.01"
                           required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label>Target End Date</label>
                    <input type="date" name="end_date" id="approveEndDate" required>
                </div>
            </div>

            <div class="form-group">
                <label>Project Milestones</label>
                <div id="milestones-container"></div>

                <button type="button" onclick="addMilestone()" class="add-milestone">
                    <i class="fas fa-plus"></i> Add Milestone
                </button>
            </div>
            <div class="form-group">
    <label>
        Project Documents for Contractor
        <span class="pr-optional">(optional - PDF, DOC, DOCX, JPG, PNG, max 5 MB)</span>
    </label>

    <small class="pr-upload-help">
        Upload admin-selected files such as plans, BOQ, contracts, drawings, or instructions for the contractor.
    </small>

    <input type="file"
           name="contractor_document"
           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
</div>
            <div class="modal-actions">
                <button type="button" class="cancel" id="cancelApproveModal">Cancel</button>
                <button type="submit" class="submit">
                    <i class="fas fa-check"></i> Approve & Create Project
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>