<?php
// ============================================================
// FILE:    auth/register.php
// PURPOSE: Register new users (client, contractor, worker)
//          Save them into the database with a hashed password
// CHANGES FROM ORIGINAL:
//   - Added PHP POST handler at the top (the original had none)
//   - Added server-side validation (original had no validation)
//   - Added duplicate email check (original had no check)
//   - Added password hashing (original stored nothing)
//   - Added DB insert using prepared statement (original did nothing)
//   - Added success and error message display inside existing HTML
//   - All original HTML, CSS, and JS kept 100% intact
//   - The <form> tag already had method="POST" — no change needed
//   - All input name="" attributes already matched what we need
// ============================================================

// Start session so we can redirect after success (used in Step 4)
session_start();

// Load the database connection
// db.php gives us the $conn variable
require_once '../config/db.php';

// ── INITIALISE VARIABLES ────────────────────────────────────
// These hold messages shown to the user after form submission.
// Empty by default — only filled when form is submitted.

$errors        = [];    // Array of error strings to display in red
$success       = '';    // Single success string to display in green
$form_data     = [];    // Re-populate form fields after a failed submit

// ── HANDLE FORM SUBMISSION ──────────────────────────────────
// $_SERVER['REQUEST_METHOD'] === 'POST' means the form was submitted.
// On first page load it is 'GET', so this block is skipped.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── STEP A: Collect and sanitise input ──────────────────
    // trim()              → removes accidental spaces before/after
    // htmlspecialchars()  → converts < > & " to safe HTML entities
    //                       prevents XSS (Cross-Site Scripting) attacks
    // strtolower()        → makes email lowercase for consistency

    $first_name     = trim(htmlspecialchars($_POST['first_name']     ?? ''));
    $last_name      = trim(htmlspecialchars($_POST['last_name']      ?? ''));
    $email          = strtolower(trim(htmlspecialchars($_POST['email']         ?? '')));
    $contact        = trim(htmlspecialchars($_POST['contact']        ?? ''));
    $specialization = trim(htmlspecialchars($_POST['specialization'] ?? ''));
    $password       = $_POST['password']         ?? '';   // NOT htmlspecialchars — hash will handle it
    $confirm        = $_POST['confirm_password'] ?? '';
    $role           = $_POST['role']             ?? 'client';

    // Save form data so we can re-fill the form on error
    $form_data = [
        'first_name'     => $first_name,
        'last_name'      => $last_name,
        'email'          => $email,
        'contact'        => $contact,
        'specialization' => $specialization,
        'role'           => $role,
    ];

    // ── STEP B: Validate role ───────────────────────────────
    // Only these three roles are allowed on the register page.
    // Admin accounts can only be created directly in the database.
    // This prevents someone from hacking the hidden field to become admin.

    $allowed_roles = ['client', 'contractor', 'worker'];
    if (!in_array($role, $allowed_roles)) {
        $errors[] = 'Invalid role selected. Please choose Client, Worker, or Contractor.';
    }

    // ── STEP C: Validate names ──────────────────────────────

    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    } elseif (strlen($first_name) < 2) {
        $errors[] = 'First name must be at least 2 characters.';
    } elseif (strlen($first_name) > 100) {
        $errors[] = 'First name must be 100 characters or fewer.';
    }

    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    } elseif (strlen($last_name) < 2) {
        $errors[] = 'Last name must be at least 2 characters.';
    } elseif (strlen($last_name) > 100) {
        $errors[] = 'Last name must be 100 characters or fewer.';
    }

    // ── STEP D: Validate email ──────────────────────────────
    // Workers have email marked Optional in the UI,
    // but if they provide one it must be valid.
    // Clients and contractors MUST have an email (it's their login ID).

    if ($role === 'worker' && empty($email)) {
        // Workers can skip email — generate a placeholder so the
        // NOT NULL constraint on the column is satisfied.
        // We use a unique placeholder they can update later.
        $email = 'worker_' . time() . '_' . rand(1000,9999) . '@placeholder.local';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // FILTER_VALIDATE_EMAIL is PHP's built-in email format checker
        $errors[] = 'Please enter a valid email address (e.g. name@example.com).';
    } elseif (strlen($email) > 150) {
        $errors[] = 'Email address is too long (max 150 characters).';
    }

    // ── STEP E: Validate contact number ─────────────────────

    if (!empty($contact)) {
        // Allow digits, spaces, +, -, ()
        // This covers formats like: 01712345678, +8801712345678, (017) 123-45678
        if (!preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $contact)) {
            $errors[] = 'Contact number can only contain digits, spaces, +, -, and () and must be 7-20 characters.';
        }
    }

    // ── STEP F: Validate password ───────────────────────────

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (strlen($password) > 72) {
        // bcrypt silently truncates at 72 bytes — warn the user
        $errors[] = 'Password must be 72 characters or fewer.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match. Please re-enter both passwords.';
    }

    // ── STEP G: Duplicate email check ───────────────────────
    // Only run this check if there are no other errors yet,
    // and only for real emails (not worker placeholders).
    // We use a PREPARED STATEMENT — never put variables directly
    // into SQL strings. Prepared statements prevent SQL injection.

    if (empty($errors) && strpos($email, '@placeholder.local') === false) {

        // Prepare the statement (? is a placeholder for our value)
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");

        // Bind our variable to the placeholder
        // "s" means the variable is a string
        mysqli_stmt_bind_param($stmt, "s", $email);

        // Run the query
        mysqli_stmt_execute($stmt);

        // Get the result
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'This email address is already registered. Please use a different email or login instead.';
        }

        // Always close prepared statements when done
        mysqli_stmt_close($stmt);
    }

    // ── STEP H: Save to database ────────────────────────────
    // Only reach here if $errors is still empty (all checks passed)

    if (empty($errors)) {

        // Hash the password using bcrypt (PHP's recommended algorithm)
        // password_hash() automatically:
        //   - Generates a random salt
        //   - Applies bcrypt with cost factor 10
        //   - Returns a 60-char string like: $2y$10$...
        // NEVER store plain-text passwords.

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert the new user with a prepared statement
        // 7 placeholders = 7 values: first_name, last_name, email,
        //                             password, role, contact, specialization

        $stmt = mysqli_prepare($conn,
            "INSERT INTO users
                (first_name, last_name, email, password, role, contact, specialization)
             VALUES
                (?, ?, ?, ?, ?, ?, ?)"
        );

        // Bind all 7 values
        // "sssssss" = 7 strings
        mysqli_stmt_bind_param(
            $stmt,
            "sssssss",
            $first_name,
            $last_name,
            $email,
            $hashed_password,
            $role,
            $contact,
            $specialization
        );

        // Execute the INSERT
        $inserted = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($inserted) {
            // Registration worked!
            // Set the success message. The HTML below will display it.
            // We also clear form_data so the form appears empty/reset.
            $success   = 'Your account has been created successfully! You can now <a href="login.php">login here</a>.';
            $form_data = []; // clear the form

        } else {
            // INSERT failed for an unexpected reason (e.g. DB offline mid-request)
            $errors[] = 'Registration failed due to a server error. Please try again. (MySQL error: ' . mysqli_error($conn) . ')';
        }
    }

} // end of POST handler
?>
<!DOCTYPE html>
<html>
<head>
<title>Register — BuildNCtrl</title>
<link rel="stylesheet" href="../assets/css/style.css">

<!-- ── ADDED: Inline styles for error/success messages ──────
     We use inline styles here (not a separate CSS file) because:
     1. The existing style.css has .vr-success-banner for success
     2. There is no error banner style in the existing CSS
     3. Adding just these styles here avoids modifying style.css
     The design matches the existing orange/brown colour scheme.
-->
<style>
/* Error message box — shown when validation fails */
.reg-error-box {
    background: #fff3f3;
    border: 1.5px solid #e74c3c;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
    text-align: left;
    color: #c0392b;
    font-size: 13px;
}
.reg-error-box ul {
    margin: 6px 0 0 0;
    padding-left: 18px;
}
.reg-error-box li { margin-bottom: 4px; }

/* Success message box — reuses .vr-success-banner colours */
.reg-success-box {
    background: #f7fff7;
    border: 1.5px solid #42b44e;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
    text-align: left;
    color: #27683a;
    font-size: 13px;
    font-weight: 600;
}
.reg-success-box a {
    color: #27683a;
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="login-container">

  <!-- LEFT SIDE — unchanged from original -->
  <div class="register-left">

    <a href="../guest/home.php" class="back-btn"> ← </a>

    <div class="overlay"></div>

    <div class="left-content">
      <h1>BuildNCtrl!</h1>
      <h2>Build<br>Smarter.<br>Manage<br>Better.</h2>
      <!-- roleText is updated by the JS setRole() function below -->
      <p id="roleText">
        <?php
        // Show the correct role text if the form was re-submitted
        $displayed_role = $form_data['role'] ?? 'client';
        echo ucfirst($displayed_role) . ' Registration →';
        ?>
      </p>
    </div>

  </div>

  <!-- RIGHT SIDE — unchanged from original -->
  <div class="register-right">

    <div class="form-card">

      <h2>Join BuildNCtrl!</h2>
      <p>
        Already have account?
        <a href="login.php">Login</a>
      </p>

      <!-- ── ADDED: Error messages ────────────────────────────
           Shown only when $errors array is not empty.
           $errors is filled by the validation logic above.
           Each error becomes a <li> item in the list.
      -->
      <?php if (!empty($errors)): ?>
        <div class="reg-error-box">
          <strong>⚠️ Please fix the following:</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?php echo $err; ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- ── ADDED: Success message ────────────────────────────
           Shown only when $success is not empty.
           $success is set after a successful DB insert.
           We use echo with no htmlspecialchars here because
           $success contains our own trusted HTML (the <a> tag).
      -->
      <?php if (!empty($success)): ?>
        <div class="reg-success-box">
          ✅ <?php echo $success; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="">

        <!-- ROLE SELECTOR — unchanged from original -->
        <div class="role-selector">
          <button type="button"
                  class="role-btn <?php echo (!isset($form_data['role']) || $form_data['role']==='client') ? 'active' : ''; ?>"
                  onclick="setRole('client')">Client</button>

          <button type="button"
                  class="role-btn <?php echo (isset($form_data['role']) && $form_data['role']==='worker') ? 'active' : ''; ?>"
                  onclick="setRole('worker')">Worker</button>

          <button type="button"
                  class="role-btn <?php echo (isset($form_data['role']) && $form_data['role']==='contractor') ? 'active' : ''; ?>"
                  onclick="setRole('contractor')">Contractor</button>
        </div>

        <!-- Hidden role field — updated by JS when role button clicked -->
        <input type="hidden"
               name="role"
               id="roleInput"
               value="<?php echo htmlspecialchars($form_data['role'] ?? 'client'); ?>">

        <!-- Name fields — added value= to re-fill on error -->
        <div class="row">
          <input type="text"
                 name="first_name"
                 placeholder="First name"
                 value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>"
                 required>
          <input type="text"
                 name="last_name"
                 placeholder="Last name"
                 value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>"
                 required>
        </div>

        <!-- Dynamic fields — JS rebuilds this div when role changes.
             On re-render after error, PHP pre-fills the values.
             We keep the same id="dynamicFields" the JS uses. -->
        <div id="dynamicFields">
          <?php
          // Determine which role's fields to show on re-render
          $r = $form_data['role'] ?? 'client';

          if ($r === 'client'): ?>
            <input type="text"  name="username"   placeholder="Username">
            <input type="email" name="email"       placeholder="Email address"
                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
            <input type="text"  name="contact"     placeholder="Contact number"
                   value="<?php echo htmlspecialchars($form_data['contact'] ?? ''); ?>">

          <?php elseif ($r === 'worker'): ?>
            <input type="email" name="email"   placeholder="Email address (Optional)"
                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
            <input type="text"  name="contact" placeholder="Contact number"
                   value="<?php echo htmlspecialchars($form_data['contact'] ?? ''); ?>">

          <?php elseif ($r === 'contractor'): ?>
            <input type="email" name="email"          placeholder="Email address"
                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
            <input type="text"  name="contact"        placeholder="Contact number"
                   value="<?php echo htmlspecialchars($form_data['contact'] ?? ''); ?>">
            <input type="text"  name="specialization" placeholder="Specialization"
                   value="<?php echo htmlspecialchars($form_data['specialization'] ?? ''); ?>">
          <?php endif; ?>
        </div>

        <!-- Password fields — intentionally NOT pre-filled for security.
             Users must re-type their password after a failed attempt. -->
        <input type="password" name="password"         placeholder="Create Password"  required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>

        <!-- Submit button — unchanged from original -->
        <button type="submit" class="register-btn">Register</button>

      </form>

    </div>

  </div>

</div>

<!-- ── JAVASCRIPT — unchanged from original ─────────────────
     The only change: setRole() now also updates the hidden
     #roleInput value that our PHP reads on POST submission.
     The original already did this — confirmed in the analysis.
-->
<script>
function setRole(role) {

  // Update the hidden input so PHP knows which role was selected
  document.getElementById("roleInput").value = role;

  // Update the left-panel text
  let text = role.charAt(0).toUpperCase() + role.slice(1);
  document.getElementById("roleText").innerText = text + " Registration →";

  // Update active button highlight
  let buttons = document.querySelectorAll(".role-btn");
  buttons.forEach(btn => btn.classList.remove("active"));
  event.target.classList.add("active");

  // Rebuild the dynamic fields for the selected role
  let container = document.getElementById("dynamicFields");

  if (role === "client") {
    container.innerHTML = `
      <input type="text"  name="username"   placeholder="Username">
      <input type="email" name="email"       placeholder="Email address">
      <input type="text"  name="contact"     placeholder="Contact number">
    `;
  }

  else if (role === "worker") {
    container.innerHTML = `
      <input type="email" name="email"   placeholder="Email address (Optional)">
      <input type="text"  name="contact" placeholder="Contact number">
    `;
  }

  else if (role === "contractor") {
    container.innerHTML = `
      <input type="email" name="email"          placeholder="Email address">
      <input type="text"  name="contact"        placeholder="Contact number">
      <input type="text"  name="specialization" placeholder="Specialization">
    `;
  }

}
</script>

</body>
</html>