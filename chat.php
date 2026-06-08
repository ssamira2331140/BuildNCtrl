<?php
// ============================================================
// FILE:    contractor/chat.php
// PURPOSE: Real-time-style chat between contractor and their
//          allowed contacts: clients of their projects, workers
//          on their projects, and any admin users.
//
// TABLES USED:
//   messages        → all chat messages; project_id scopes them
//   projects        → get clients assigned to this contractor
//   project_workers → get workers hired by this contractor
//   users           → names, initials, roles for contacts + messages
//
// CONTACT RULES (server-enforced):
//   1. Workers  → users.id IN (SELECT DISTINCT worker_id
//                              FROM project_workers
//                              WHERE contractor_id = $sess_id)
//   2. Admins   → users WHERE role = 'admin'
//   Clients are NOT allowed — contractor cannot message clients.
//
// URL:
//   chat.php                          → contact list only
//   chat.php?user_id=N                → open conversation with user N
//   chat.php?user_id=N&project_id=N  → project-scoped conversation
//
// DB PATCH REQUIRED: run add_messages_project_id.sql first.
// NO INLINE CSS. NO INLINE JS.
// ============================================================

session_start();
$required_role = 'contractor';
require_once '../includes/session_guard.php';
require_once '../config/db.php';

// ── HANDLE: Send message ──────────────────────────────────────
$send_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'send_message') {

    $receiver_id = (int)   ($_POST['receiver_id'] ?? 0);
    $project_id  = (int)   ($_POST['project_id']  ?? 0);
    $message     = trim(   $_POST['message']       ?? '');
    $proj_val    = $project_id > 0 ? $project_id : null;

    if ($receiver_id <= 0)  { $send_error = 'Invalid recipient.'; }
    elseif (empty($message)){ $send_error = 'Message cannot be empty.'; }
    else {
        // ── Security: verify receiver is an allowed contact ───
        // Build a one-query check: receiver must be a client of
        // one of our projects, a worker on one of our projects,
        // or an admin.
        // Allowed: workers on contractor's projects + admins only.
        // Clients are explicitly excluded — no project relationship
        // entitles a contractor to message a client directly.
        $stmt = mysqli_prepare($conn,
            "SELECT u.id FROM users u
             WHERE u.id = ?
             AND (
               -- worker hired by this contractor on any project
               u.id IN (
                 SELECT pw.worker_id FROM project_workers pw
                 WHERE pw.contractor_id = ?
               )
               OR
               -- admin user
               u.role = 'admin'
             )
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ii",
            $receiver_id, $sess_id
        );
        mysqli_stmt_execute($stmt);
        $res      = mysqli_stmt_get_result($stmt);
        $allowed  = mysqli_num_rows($res) > 0;
        mysqli_free_result($res);
        mysqli_stmt_close($stmt);

        if (!$allowed) {
            $send_error = 'You are not allowed to message this user.';
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO messages
                     (sender_id, receiver_id, project_id, message)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "iiis",
                $sess_id, $receiver_id, $proj_val, $message
            );
            $saved = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($saved) {
                // Mark sent — redirect back to same conversation to
                // prevent form resubmission on refresh
                $redir = "chat.php?user_id={$receiver_id}";
                if ($proj_val) $redir .= "&project_id={$proj_val}";
                header("Location: $redir");
                exit();
            } else {
                $send_error = 'Failed to send. Please try again.';
            }
        }
    }
}

// ── GET params ────────────────────────────────────────────────
$active_user_id  = (int) ($_GET['user_id']    ?? 0);
$active_proj_id  = (int) ($_GET['project_id'] ?? 0);

// ── MARK messages as read when conversation is opened ─────────
if ($active_user_id > 0) {
    $stmt = mysqli_prepare($conn,
        "UPDATE messages SET is_read = 1
         WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, "ii", $active_user_id, $sess_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ── QUERY A: Build allowed contact list ──────────────────────
// One query using UNION to get all three contact categories.
// Each row: user id, name, role, related project name, initials.
// LEFT JOIN messages to get last message preview per contact.
$stmt = mysqli_prepare($conn,
    "SELECT
         u.id,
         CONCAT(u.first_name,' ',u.last_name) AS full_name,
         u.role,
         p_rel.project_name                   AS project_name,
         p_rel.id                             AS project_id,
         SUBSTRING(u.first_name,1,1)          AS init1,
         SUBSTRING(u.last_name,1,1)           AS init2,
         lm.last_msg,
         lm.last_time,
         lm.unread_count
     FROM (
         -- Workers hired by this contractor (distinct per project)
         SELECT pw.worker_id AS uid, p2.project_name, p2.id AS proj_id
         FROM project_workers pw
         JOIN projects p2 ON p2.id = pw.project_id
         WHERE pw.contractor_id = ?

         UNION

         -- All admin users (no specific project context)
         SELECT u2.id AS uid, NULL AS project_name, NULL AS proj_id
         FROM users u2
         WHERE u2.role = 'admin'
     ) AS contacts
     JOIN users u ON u.id = contacts.uid
     LEFT JOIN projects p_rel ON p_rel.id = contacts.proj_id
     LEFT JOIN (
         SELECT
             CASE WHEN sender_id   = ? THEN receiver_id
                  WHEN receiver_id = ? THEN sender_id
             END AS other_id,
             MAX(sent_at) AS last_time,
             SUBSTRING(message, 1, 50) AS last_msg,
             SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
         FROM messages
         WHERE sender_id = ? OR receiver_id = ?
         GROUP BY other_id
     ) lm ON lm.other_id = u.id
     WHERE u.id != ?
     GROUP BY u.id
     ORDER BY lm.last_time DESC, u.first_name ASC"
);
mysqli_stmt_bind_param($stmt, "iiiiiii",
    $sess_id,                               // UNION: workers param
    $sess_id, $sess_id, $sess_id,           // lm subquery params
    $sess_id, $sess_id,                     // lm subquery WHERE
    $sess_id                                // exclude self
);
mysqli_stmt_execute($stmt);
$res      = mysqli_stmt_get_result($stmt);
$contacts = [];
while ($r = mysqli_fetch_assoc($res)) $contacts[] = $r;
mysqli_free_result($res);
mysqli_stmt_close($stmt);

// If no active_user_id set, default to first contact.
// Also: if active_user_id is not in the allowed contacts list
// (e.g. URL-tampered client ID), silently redirect to first contact.
if (!empty($contacts)) {
    $allowed_ids = array_column($contacts, 'id');
    if ($active_user_id <= 0 || !in_array((string)$active_user_id, array_map('strval', $allowed_ids))) {
        $active_user_id = (int) $contacts[0]['id'];
        $active_proj_id = (int) ($contacts[0]['project_id'] ?? 0);
    }
}

// Find active contact details from contacts list
$active_contact = null;
foreach ($contacts as $c) {
    if ((int)$c['id'] === $active_user_id) {
        $active_contact = $c;
        break;
    }
}

// ── QUERY B: Message history with active contact ──────────────
$messages = [];
if ($active_user_id > 0) {
    $stmt = mysqli_prepare($conn,
        "SELECT m.id, m.sender_id, m.message, m.sent_at, m.is_read
         FROM   messages m
         WHERE  (m.sender_id = ? AND m.receiver_id = ?)
            OR  (m.sender_id = ? AND m.receiver_id = ?)
         ORDER  BY m.sent_at ASC"
    );
    mysqli_stmt_bind_param($stmt, "iiii",
        $sess_id, $active_user_id,
        $active_user_id, $sess_id
    );
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($m = mysqli_fetch_assoc($res)) $messages[] = $m;
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);
}

// ── HELPERS ───────────────────────────────────────────────────
function chat_time(string $dt): string {
    $d = new DateTime($dt);
    $now = new DateTime();
    if ($d->format('Y-m-d') === $now->format('Y-m-d')) {
        return $d->format('g:i A');
    }
    if ($d->format('Y') === $now->format('Y')) {
        return $d->format('M j');
    }
    return $d->format('M j, Y');
}
function role_badge_css(string $role): string {
    return match($role) {
        'admin'      => 'admin',
        'worker'     => 'worker',
        'client'     => 'client',
        'contractor' => 'contractor',
        default      => 'worker',
    };
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0] ?? '', 0, 1));
    $i .= strtoupper(substr($parts[1] ?? '', 0, 1));
    return $i ?: '??';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contractor Chat</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="contractor-page">

  <!-- SIDEBAR -->
  <aside class="contractor-sidebar">
    <div class="sidebar-logo">
      <i class="fas fa-hard-hat"></i><h2>BuildNCtrl</h2>
    </div>
    <ul class="sidebar-menu">
      <li><a href="my_projects.php"><i class="fas fa-folder"></i><span>My Projects</span></a></li>
      <li><a href="workers.php"><i class="fas fa-users"></i><span>Workers</span></a></li>
      <li><a href="milestone.php"><i class="fas fa-list-check"></i><span>Milestones</span></a></li>
      <li><a href="worklogs.php"><i class="fas fa-briefcase"></i><span>Work Logs</span></a></li>
      <li><a href="materials.php"><i class="fas fa-box"></i><span>Materials</span></a></li>
      <li class="active"><a href="chat.php"><i class="fas fa-comments"></i><span>Chat</span></a></li>
      <li class="logout"><a href="../auth/logout.php"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
    </ul>
  </aside>

  <!-- MAIN -->
  <main class="contractor-main">

    <div class="contractor-topbar">
      <div>
        <h2>Contractor Dashboard</h2>
        <p>Communicate with clients, workers, and admin</p>
      </div>
      <div class="contractor-user">
        <div class="contractor-avatar"><?php echo $sess_initials; ?></div>
        <span class="contractor-name"><?php echo $sess_fullname; ?></span>
      </div>
    </div>

    <!-- CHAT WRAPPER -->
    <div class="contractor-chat-wrapper">

      <!-- LEFT: CONTACT LIST -->
      <div class="contractor-chat-sidebar">

        <!-- Search — JS filters contact items client-side -->
        <div class="contractor-chat-search">
          <i class="fas fa-search"></i>
          <input type="text" id="chatContactSearch"
                 placeholder="Search conversations...">
        </div>

        <div class="contractor-conversation-list" id="chatContactList">

          <?php if (empty($contacts)): ?>
            <p class="chat-no-contacts">
              <i class="fas fa-user-slash"></i><br>
              No contacts yet. Contacts appear when clients or workers are linked to your projects.
            </p>

          <?php else: ?>
            <?php foreach ($contacts as $contact):
              $c_id        = (int) $contact['id'];
              $c_name      = htmlspecialchars($contact['full_name']);
              $c_role      = $contact['role'];
              $c_badge     = role_badge_css($c_role);
              $c_proj      = htmlspecialchars($contact['project_name'] ?? '');
              $c_proj_id   = (int) ($contact['project_id'] ?? 0);
              $c_inits     = strtoupper(($contact['init1'] ?? '') . ($contact['init2'] ?? ''));
              $c_preview   = !empty($contact['last_msg'])
                              ? htmlspecialchars(substr($contact['last_msg'], 0, 40))
                              : ($c_proj ?: 'No messages yet');
              $c_time      = !empty($contact['last_time'])
                              ? chat_time($contact['last_time'])
                              : '';
              $c_unread    = (int) ($contact['unread_count'] ?? 0);
              $is_active   = ($c_id === $active_user_id);
              // Build URL for clicking this contact
              $c_url       = "chat.php?user_id={$c_id}";
              if ($c_proj_id > 0) $c_url .= "&project_id={$c_proj_id}";
            ?>
            <a href="<?php echo $c_url; ?>"
               class="contractor-conversation-item<?php echo $is_active ? ' active' : ''; ?>"
               data-name="<?php echo strtolower($c_name); ?>">

              <div class="contractor-conversation-avatar">
                <?php echo $c_inits; ?>
              </div>

              <div class="contractor-conversation-info">
                <div class="contractor-conversation-top">
                  <span class="contractor-conversation-name"><?php echo $c_name; ?></span>
                  <span class="contractor-role-badge <?php echo $c_badge; ?>">
                    <?php echo ucfirst($c_role); ?>
                  </span>
                </div>

                <div class="chat-contact-meta">
                  <?php if (!empty($c_proj)): ?>
                    <span class="chat-contact-project">
                      <i class="fas fa-folder"></i> <?php echo $c_proj; ?>
                    </span>
                  <?php endif; ?>
                  <span class="chat-contact-preview"><?php echo $c_preview; ?></span>
                </div>

                <div class="chat-contact-bottom">
                  <?php if (!empty($c_time)): ?>
                    <span class="chat-contact-time"><?php echo $c_time; ?></span>
                  <?php endif; ?>
                  <?php if ($c_unread > 0): ?>
                    <span class="chat-unread-badge"><?php echo $c_unread; ?></span>
                  <?php endif; ?>
                </div>
              </div>

            </a>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>

      <!-- RIGHT: CHAT WINDOW -->
      <div class="contractor-chat-window">

        <?php if ($active_contact === null): ?>
          <!-- No conversation selected -->
          <div class="chat-empty-window">
            <i class="fas fa-comments"></i>
            <p>Select a contact to start chatting</p>
          </div>

        <?php else:
          $ac_name    = htmlspecialchars($active_contact['full_name']);
          $ac_role    = $active_contact['role'];
          $ac_badge   = role_badge_css($ac_role);
          $ac_inits   = strtoupper(
              ($active_contact['init1'] ?? '') . ($active_contact['init2'] ?? '')
          );
          $ac_proj    = htmlspecialchars($active_contact['project_name'] ?? '');
        ?>

          <!-- CHAT HEADER -->
          <div class="contractor-chat-header">
            <div class="contractor-chat-user">
              <div class="contractor-conversation-avatar">
                <?php echo $ac_inits; ?>
              </div>
              <div>
                <div class="contractor-chat-name"><?php echo $ac_name; ?></div>
                <div class="chat-header-meta">
                  <span class="contractor-role-badge <?php echo $ac_badge; ?>">
                    <?php echo ucfirst($ac_role); ?>
                  </span>
                  <?php if (!empty($ac_proj)): ?>
                    <span class="chat-header-project">
                      <i class="fas fa-folder"></i> <?php echo $ac_proj; ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- MESSAGE HISTORY -->
          <!--
            id="chatMessages" lets JS scroll to bottom on load.
            data-user-id lets JS auto-refresh poll (if added later).
          -->
          <div class="contractor-chat-messages" id="chatMessages"
               data-active-user="<?php echo $active_user_id; ?>">

            <?php if (empty($messages)): ?>
              <div class="chat-no-messages">
                <i class="fas fa-comment-slash"></i>
                <p>No messages yet. Send the first message below.</p>
              </div>

            <?php else: ?>

              <?php
              // Group messages by date for date separators
              $prev_date = '';
              foreach ($messages as $msg):
                $is_sent   = ((int)$msg['sender_id'] === $sess_id);
                $msg_class = $is_sent ? 'sent' : 'received';
                $msg_time  = chat_time($msg['sent_at']);
                $msg_date  = (new DateTime($msg['sent_at']))->format('Y-m-d');
                $show_date = ($msg_date !== $prev_date);
                $prev_date = $msg_date;
              ?>

              <?php if ($show_date): ?>
                <div class="chat-date-separator">
                  <span><?php echo (new DateTime($msg['sent_at']))->format('F j, Y'); ?></span>
                </div>
              <?php endif; ?>

              <div class="contractor-message <?php echo $msg_class; ?>">
                <div class="contractor-message-bubble">
                  <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                  <span class="contractor-message-time"><?php echo $msg_time; ?></span>
                </div>
              </div>

              <?php endforeach; ?>
            <?php endif; ?>

          </div>

          <!-- SEND MESSAGE FORM -->
          <!--
            POST to same page; handler at top redirects after success.
            Textarea used instead of input for multi-line messages.
            JS in script.js handles Enter key to submit.
          -->
          <?php if (!empty($send_error)): ?>
            <div class="chat-send-error"><?php echo htmlspecialchars($send_error); ?></div>
          <?php endif; ?>

          <div class="contractor-chat-input">
            <form method="POST" action="" id="chatSendForm">
              <input type="hidden" name="action"      value="send_message">
              <input type="hidden" name="receiver_id" value="<?php echo $active_user_id; ?>">
              <input type="hidden" name="project_id"  value="<?php echo $active_proj_id; ?>">
              <textarea name="message"
                        id="chatMessageInput"
                        placeholder="Type a message…"
                        required
                        rows="1"></textarea>
              <button type="submit" id="chatSendBtn">
                <i class="fas fa-paper-plane"></i>
              </button>
            </form>
          </div>

        <?php endif; ?>

      </div><!-- end .contractor-chat-window -->

    </div><!-- end .contractor-chat-wrapper -->

  </main>

  <script src="../assets/js/script.js"></script>
</body>
</html>
