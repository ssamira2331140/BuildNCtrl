<?php
// ============================================================
// FILE:    auth/login.php
// PURPOSE: Authenticate users against the database,
//          create a secure session, and redirect to the
//          correct dashboard based on their role.
//
// CHANGES FROM ORIGINAL:
//   - session_start() added (was completely missing)
//   - require_once for db.php added (no DB used before)
//   - "TEMPORARY LOGIN" block replaced with real DB query
//   - password_verify() added (passwords never checked before)
//   - $_SESSION variables set after successful login
//   - Error messages displayed inside existing HTML/CSS
//   - Already-logged-in redirect added at top
//   - Email field value re-filled after failed attempt
//   - Role selector JS kept 100% intact
//   - All original HTML, CSS, classes unchanged
// ============================================================

// ── START SESSION ────────────────────────────────────────────
// Must be the very first thing before ANY output (even spaces).
// Sessions let PHP remember who is logged in across page loads.
session_start();

// ── REDIRECT IF ALREADY LOGGED IN ────────────────────────────
// If the user already has a valid session, send them to their
// dashboard instead of showing the login form again.
// This prevents the back-button from bringing them to login
// after they are already authenticated.

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit();
        case 'client':
            header("Location: ../client/my_projects.php");
            exit();
        case 'contractor':
            header("Location: ../contractor/my_projects.php");
            exit();
        case 'worker':
            header("Location: ../worker/mytasks.php");
            exit();
    }
}

// ── LOAD DATABASE CONNECTION ─────────────────────────────────
// $conn is now available for all queries below.
require_once '../config/db.php';

// ── INITIALISE STATE VARIABLES ───────────────────────────────
$error      = '';       // Single error string shown in red
$email_val  = '';       // Re-fills email field after failed attempt

// ── HANDLE FORM SUBMISSION ───────────────────────────────────
// Only runs when the form is submitted (POST).
// On first page load (GET) this block is completely skipped.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── STEP A: Collect inputs ────────────────────────────────
    // trim()      → strip accidental whitespace
    // strtolower  → normalise email so "User@Test.com" matches "user@test.com"
    // We do NOT use htmlspecialchars on email/password here because:
    //   - email goes into a prepared statement (safe already)
    //   - password goes into password_verify() (plain text needed)

    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $role_sel = $_POST['role']     ?? 'client';   // the role button the user clicked

    // Save email so we can re-fill the field if login fails
    $email_val = htmlspecialchars($email);

    // ── STEP B: Basic presence check ─────────────────────────
    // Do not query the database at all if fields are blank.
    // This saves a DB round-trip and gives a faster response.

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    }

    // ── STEP C: Validate email format ────────────────────────
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    // ── STEP D: Look up the user in the database ──────────────
    // We SELECT the columns we need for the session PLUS the
    // hashed password so we can verify it.
    //
    // WHY NOT query by role too?
    //   The role selector on the login page is a UI helper only.
    //   We do NOT filter the DB query by the selected role.
    //   Reason: if someone picks the wrong role button but enters
    //   correct credentials, we still log them in as their REAL
    //   role (from the database), not the button they clicked.
    //   This prevents "I keep getting an error" support tickets.
    //
    // PREPARED STATEMENT: the ? placeholder means $email is
    // never concatenated into the SQL string — SQL injection
    // is impossible regardless of what the user types.

    else {
        $stmt = mysqli_prepare($conn,
            "SELECT id, first_name, last_name, email, password, role
             FROM   users
             WHERE  email = ?
             LIMIT  1"
        );

        // "s" = one string parameter
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        // Get the result as an associative array
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // ── STEP E: Check if user was found ──────────────────
        // $user is NULL if no row matched the email.
        // We use a GENERIC error message intentionally.
        // Saying "email not found" vs "wrong password" separately
        // is a security risk — it tells attackers which emails exist.

        if (!$user) {
            $error = 'Invalid email or password. Please try again.';
        }

        // ── STEP F: Verify the password ──────────────────────
        // password_verify() takes the plain-text password the user
        // typed and the bcrypt hash stored in the database.
        // It re-hashes the input and compares — returns true/false.
        // This is the ONLY correct way to check bcrypt passwords.
        // NEVER use: md5(), sha1(), or direct string comparison.

        elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid email or password. Please try again.';
        }

        // ── STEP G: Successful authentication ────────────────
        // Both email found AND password matched.
        // Now we:
        //   1. Regenerate the session ID (security: prevents
        //      session fixation attacks)
        //   2. Store user data in $_SESSION
        //   3. Redirect to the correct dashboard

        else {
            // Regenerate session ID to prevent session fixation.
            // This gives the authenticated session a brand-new ID.
            session_regenerate_id(true);

            // Store everything we'll need on dashboard pages.
            // After Step 5 (session guards), every protected page
            // will read these values for:
            //   - Showing the user's name in the topbar
            //   - Checking their role to allow/deny access
            //   - Using their ID in database queries

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['full_name']  = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];

            // Redirect based on the role stored in the DATABASE
            // (not the role button the user clicked in the UI).
            // header("Location: ...") sends an HTTP redirect.
            // exit() stops PHP from running anything after it —
            // always call exit() immediately after header().

            switch ($user['role']) {
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'client':
                    header("Location: ../client/my_projects.php");
                    break;
                case 'contractor':
                    header("Location: ../contractor/my_projects.php");
                    break;
                case 'worker':
                    header("Location: ../worker/mytasks.php");
                    break;
                default:
                    // Fallback — should never happen given our ENUM constraint
                    header("Location: ../guest/home.php");
                    break;
            }
            exit();
        }
    }

} // end POST handler
?>
<!DOCTYPE html>
<html>
<head>
<title>Login — BuildNCtrl</title>
<link rel="stylesheet" href="../assets/css/style.css">

<!-- ── ADDED: Minimal inline styles for the error message ────
     The existing CSS has no error/alert class on the login page.
     We add only what is needed, matching the existing colour scheme.
     style.css is NOT modified.
-->
<style>
/* Error box shown below the "Log in to BuildNCtrl" subtitle */
.login-error-box {
    width: 320px;                   /* matches the form width in CSS */
    background: #fff3f3;
    border: 1.5px solid #e74c3c;
    border-radius: 12px;
    padding: 11px 16px;
    margin-bottom: 4px;
    color: #c0392b;
    font-size: 13px;
    text-align: center;
    box-sizing: border-box;
}
</style>
</head>

<body>

<div class="login-container">

  <!-- LEFT SIDE — structure identical to original -->
  <div class="login-left">

    <a href="../guest/home.php" class="back-btn">←</a>

    <h2>Welcome to <span>BuildNCtrl!</span></h2>
    <p>Log in to BuildNCtrl</p>

    <!-- ── ADDED: Error message display ─────────────────────
         $error is set by the PHP block above when login fails.
         On first page load and on successful login, $error is ''
         so this block is invisible.
         Placed between the subtitle and the form — naturally fits
         the existing layout without any layout changes.
    -->
    <?php if (!empty($error)): ?>
      <div class="login-error-box">
        ⚠️ <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <form action="" method="POST">

      <!-- ROLE SELECTOR — identical to original.
           These buttons are a UI hint only — the actual role
           comes from the database after the email is looked up.
           The active class pre-selects the button that matches
           the role the user last clicked before a failed attempt.
      -->
      <div class="role-selector">

        <?php
        // Pre-highlight whichever role button the user had selected
        // when they submitted the form. On first load, default to 'client'.
        $sel = htmlspecialchars($_POST['role'] ?? 'client');
        ?>

        <button type="button"
                onclick="setRole('client')"
                class="role-btn <?php echo $sel === 'client'     ? 'active' : ''; ?>">
          Client
        </button>

        <button type="button"
                onclick="setRole('worker')"
                class="role-btn <?php echo $sel === 'worker'     ? 'active' : ''; ?>">
          Worker
        </button>

        <button type="button"
                onclick="setRole('contractor')"
                class="role-btn <?php echo $sel === 'contractor' ? 'active' : ''; ?>">
          Contractor
        </button>

        <button type="button"
                onclick="setRole('admin')"
                class="role-btn <?php echo $sel === 'admin'      ? 'active' : ''; ?>">
          Admin
        </button>

      </div>

      <!-- Hidden role input — updated by JS setRole() -->
      <input type="hidden"
             name="role"
             id="roleInput"
             value="<?php echo $sel; ?>">

      <!-- Email field — ADDED value= to re-fill after failed attempt.
           The user should not have to re-type their email.
           $email_val is set in the POST handler above. -->
      <input type="email"
             name="email"
             placeholder="Email"
             value="<?php echo $email_val; ?>"
             required>

      <!-- Password field — intentionally NOT re-filled (security). -->
      <input type="password"
             name="password"
             placeholder="Password"
             required>

      <!-- Options row — identical to original.
           "Forgot Password?" links to # — not yet implemented. -->
      <div class="options">
        <label><input type="checkbox"> Remember for 30 days</label>
        <a href="#">Forgot Password?</a>
      </div>

      <!-- Login button — text updated by JS when role changes.
           Initial text reflects the pre-selected role. -->
      <button type="submit" id="loginBtn">
        Log in as <?php echo ucfirst($sel); ?>
      </button>

      <p class="register">
        Don't have an account? <a href="register.php">Register</a>
      </p>

    </form>

  </div>

  <!-- RIGHT SIDE — identical to original (background image via CSS) -->
  <div class="login-right"></div>

</div>

<!-- ── JAVASCRIPT — identical to original ────────────────────
     setRole() updates the hidden input and the button text.
     No changes needed — this already worked correctly.
-->
<script>
function setRole(role) {

    // Update hidden input so PHP receives the selected role on POST
    document.getElementById("roleInput").value = role;

    // Update button text to show selected role
    let roleText = role.charAt(0).toUpperCase() + role.slice(1);
    document.getElementById("loginBtn").innerText = "Log in as " + roleText;

    // Highlight the active role button
    let buttons = document.querySelectorAll(".role-btn");
    buttons.forEach(btn => btn.classList.remove("active"));
    event.target.classList.add("active");
}
</script>

</body>
</html>