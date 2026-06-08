<?php
// ============================================================
// FILE:    includes/session_guard.php
// PURPOSE: One reusable file that protects every dashboard page.
//          Include it at the top of ANY protected PHP file.
//
// HOW TO USE — add these 3 lines at the very top of a page:
//
//   session_start();
//   $required_role = 'admin';           // set the role needed
//   require_once '../includes/session_guard.php';
//
// WHAT IT DOES:
//   1. Checks if the user is logged in (session exists)
//   2. Checks if their role matches the required role
//   3. Redirects to login if either check fails
//   4. Prepares a set of ready-to-use PHP variables for the page
//
// VARIABLES AVAILABLE AFTER INCLUDING THIS FILE:
//   $sess_id        → user's database ID  (e.g. 3)
//   $sess_role      → user's role         (e.g. 'admin')
//   $sess_firstname → user's first name   (e.g. 'Farah')
//   $sess_lastname  → user's last name    (e.g. 'Zabin')
//   $sess_fullname  → full display name   (e.g. 'Farah Zabin')
//   $sess_initials  → avatar initials     (e.g. 'FZ')
//   $sess_email     → user's email
// ============================================================

// ── CHECK 1: Is the user logged in at all? ───────────────────
// If user_id is not in the session, the user has not logged in
// (or their session expired). Redirect them to login.

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// ── CHECK 2: Does the user have the required role? ───────────
// $required_role must be set in the page BEFORE including this file.
// If the role does not match, they are sent back to login.
//
// Example: a client trying to open admin/dashboard.php
// would have $_SESSION['role'] === 'client' but
// $required_role === 'admin' → redirect.
//
// WHY redirect to login instead of showing "Access Denied"?
//   Simplicity — for this project level, login is the safe default.
//   In a production app you would redirect to their own dashboard.

if (!isset($required_role) || $_SESSION['role'] !== $required_role) {
    header("Location: ../auth/login.php");
    exit();
}

// ── PREPARE DISPLAY VARIABLES ────────────────────────────────
// Read session data into shorter, clearly named variables.
// Every page can use these instead of writing $_SESSION[...] repeatedly.

$sess_id        = (int) $_SESSION['user_id'];
$sess_role      = $_SESSION['role'];
$sess_firstname = htmlspecialchars($_SESSION['first_name'] ?? '');
$sess_lastname  = htmlspecialchars($_SESSION['last_name']  ?? '');
$sess_fullname  = htmlspecialchars($_SESSION['full_name']  ?? ($sess_firstname . ' ' . $sess_lastname));
$sess_email     = htmlspecialchars($_SESSION['email']      ?? '');

// ── BUILD INITIALS FOR THE AVATAR CIRCLE ─────────────────────
// Takes the first character of first name and first character
// of last name, both uppercase.
// "Farah Zabin"  → "FZ"
// "Sumsun Samira"→ "SS"
// "Maria Tabassum"→"MT"
// If either name is somehow empty, we fall back to "??"

$initial_f  = !empty($sess_firstname) ? strtoupper($sess_firstname[0]) : '?';
$initial_l  = !empty($sess_lastname)  ? strtoupper($sess_lastname[0])  : '?';
$sess_initials = $initial_f . $initial_l;
// ============================================================
?>
