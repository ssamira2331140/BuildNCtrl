<?php
// ============================================================
// FILE:    auth/logout.php
// PURPOSE: Destroy the session completely and send the user
//          back to the login page.
//
// HOW IT IS CALLED:
//   Every sidebar "Logout" link across the app points here:
//   ../auth/logout.php
//
// THIS FILE HAS NO HTML — it only does logic then redirects.
// The original file was 100% empty (0 bytes). Everything here
// is new.
// ============================================================

// ── STEP 1: Start the session ─────────────────────────────
// You must call session_start() before you can read or destroy
// session data. Even though we are destroying the session, we
// still need to "open" it first.

session_start();

// ── STEP 2: Clear all session variables ───────────────────
// $_SESSION is a global array. Unsetting it removes all data
// (user_id, role, full_name, email, etc.) from memory.
// After this line $_SESSION is an empty array [].

$_SESSION = [];

// ── STEP 3: Destroy the session cookie ───────────────────
// The session ID is stored in a cookie in the user's browser
// (usually named PHPSESSID).
// This code overwrites that cookie with an expired date,
// which tells the browser to delete it immediately.
// Without this step the cookie would linger even though the
// server-side session data is gone.

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),     // e.g. "PHPSESSID"
        '',                 // empty value
        time() - 42000,     // expiry in the past — browser deletes it
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ── STEP 4: Destroy the session on the server ─────────────
// This deletes the session file (or database row if using DB
// sessions) from the server completely.
// After this, even if someone had the old session ID cookie,
// the server would not recognise it.

session_destroy();

// ── STEP 5: Redirect to login page ───────────────────────
// Send the now-logged-out user to the login page.
// exit() ensures no further PHP code runs after the redirect.

header("Location: login.php");
exit();
?>