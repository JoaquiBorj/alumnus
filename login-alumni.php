<?php
// ===== Shortcode: User Login with Redirect and Data Passing =====
function coenect_login_form_shortcode() {
    ob_start();

    global $wpdb;
    // Use the same DB connection/config as WordPress (same as create-db.php)
    $db = $wpdb;

    // Ensure password helper functions are available
    if (!function_exists('wp_check_password')) {
        if (defined('ABSPATH')) {
            @require_once ABSPATH . WPINC . '/pluggable.php';
        }
    }

    // --- Handle login submission ---
    if (isset($_POST['login_submit'])) {
        $username = sanitize_text_field($_POST['username']);
        // Do not sanitize password; keep exact characters
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        // Query user data from the `user` table (primary key `user` references alumni.user_id)
        $user = $db->get_row(
            $db->prepare(
                "SELECT * FROM `user` WHERE `user` = %s LIMIT 1",
                $username
            )
        );

        if ($user) {
            // Verify password supporting multiple legacy formats
            $stored = (string) $user->password;
            $verified = false;
            $should_upgrade_hash = false;
            $hash_type = 'unknown';

            // 1) Modern hashes (bcrypt/argon2)
            if (preg_match('/^\$(2y|2a|argon2id|argon2i)\$/', $stored)) {
                $hash_type = 'bcrypt/argon2';
                if (password_verify($password, $stored)) {
                    $verified = true;
                    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                        $should_upgrade_hash = true;
                    }
                }
            }
            // 2) WordPress portable hashes ($P$ or $H$)
            elseif (preg_match('/^\$(P|H)\$/', $stored)) {
                $hash_type = 'wp-portable';
                if (function_exists('wp_check_password') && wp_check_password($password, $stored)) {
                    $verified = true;
                    $should_upgrade_hash = true; // migrate to bcrypt in our table
                }
            }
            // 3) md5 legacy
            elseif (ctype_xdigit($stored) && strlen($stored) === 32) {
                $hash_type = 'md5';
                if (md5($password) === strtolower($stored)) {
                    $verified = true;
                    $should_upgrade_hash = true;
                }
            }
            // 4) sha1 legacy
            elseif (ctype_xdigit($stored) && strlen($stored) === 40) {
                $hash_type = 'sha1';
                if (sha1($password) === strtolower($stored)) {
                    $verified = true;
                    $should_upgrade_hash = true;
                }
            }
            // 5) Plain text fallback
            else {
                $hash_type = 'plain-text';
                if (hash_equals($stored, $password)) {
                    $verified = true;
                    $should_upgrade_hash = true;
                }
            }

            // Final safety: try wp_check_password on any unknown format
            if (!$verified && function_exists('wp_check_password')) {
                if (wp_check_password($password, $stored)) {
                    $verified = true;
                    $should_upgrade_hash = true;
                    if ($hash_type === 'unknown') { $hash_type = 'wp-check-fallback'; }
                }
            }

            // Minimal server-side log (no password) for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[alumnus-login] user=' . $username . ' type=' . $hash_type . ' verified=' . ($verified ? '1' : '0'));
            }

            if ($verified) {
                // Upgrade legacy hashes to bcrypt transparently
                if ($should_upgrade_hash) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $db->query(
                        $db->prepare("UPDATE `user` SET `password` = %s WHERE `user` = %s", $newHash, $username)
                    );
                }
                // Get course_id and year for redirect
                $course_id = urlencode(isset($user->course_id) ? $user->course_id : '');
                $year = urlencode(isset($user->year) ? $user->year : '');
                // In this schema, `user` equals the user's ID (FK to alumni.user_id)
                $username_encoded = urlencode($user->user);

                // If password is default 12345 -> show reset modal
                if ($password === '12345') {
                    ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            document.getElementById("resetModal").style.display = "block";
                        });
                    </script>
                    <?php
                } else {
                    // Redirect to shortcode page (for example: [user_dashboard])
                    $redirect_url = add_query_arg([
                        'user' => $username_encoded,
                        'course_id' => $course_id,
                        'year' => $year
                    ], get_permalink()); // same page OR change to your shortcode page permalink
                    echo "<script>window.location.href='" . esc_url($redirect_url) . "';</script>";
                }
            } else {
                echo '<div class="error">Incorrect password.</div>';
            }
        } else {
            $db_error = !empty($db->last_error) ? ' DB error: ' . esc_html($db->last_error) : '';
            echo '<div class="error">User not found.' . $db_error . '</div>';
        }
    }

    // --- Handle password reset --
    if (isset($_POST['reset_submit'])) {
        $username = sanitize_text_field($_POST['reset_username']);
        $new_password = sanitize_text_field($_POST['new_password']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update matched user by `user` (same identifier used on login)
        $updated = $db->query(
            $db->prepare(
                "UPDATE `user` SET `password` = %s WHERE `user` = %s",
                $hashed_password,
                $username
            )
        );

        if ($updated !== false) {
            // Get updated user data for redirect
            $user = $db->get_row(
                $db->prepare(
                    "SELECT * FROM `user` WHERE `user` = %s LIMIT 1",
                    $username
                )
            );
            if ($user) {
                $course_id = urlencode(isset($user->course_id) ? $user->course_id : '');
                $year = urlencode(isset($user->year) ? $user->year : '');
                $username_encoded = urlencode($user->user);

                // Redirect to shortcode page with query params
                $redirect_url = add_query_arg([
                    'user' => $username_encoded,
                    'course_id' => $course_id,
                    'year' => $year
                ], get_permalink());
                echo "<script>alert('Password reset successfully! Redirecting...'); window.location.href='" . esc_url($redirect_url) . "';</script>";
            } else {
                echo "<div class='error'>Password updated, but user lookup failed.</div>";
            }
        } else {
            echo "<div class='error'>Failed to reset password.</div>";
        }
    }
    ?>

    <!-- ===== Login Form ===== -->
    <form method="post" class="coenect-login-form" style="max-width:400px;margin:auto;">
        <h3>Login</h3>
    <label>Username / User ID</label>
        <input type="text" name="username" required class="widefat">

        <label>Password</label>
        <input type="password" name="password" required class="widefat">

        <button type="submit" name="login_submit" class="button button-primary" style="margin-top:10px;">Login</button>
    </form>

    <!-- ===== Password Reset Modal ===== -->
    <div id="resetModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999;">
        <div style="background:#fff; width:300px; margin:100px auto; padding:20px; border-radius:8px; position:relative;">
            <h3>Reset Password</h3>
            <form method="post">
                <input type="hidden" name="reset_username" value="<?php echo isset($username) ? esc_attr($username) : ''; ?>">
                <label>New Password</label>
                <input type="password" name="new_password" required class="widefat">

                <button type="submit" name="reset_submit" class="button button-primary" style="margin-top:10px;">Update Password</button>
            </form>
            <button onclick="document.getElementById('resetModal').style.display='none'" style="position:absolute; top:10px; right:10px;">✖</button>
        </div>
    </div>

    <style>
        .error { color: red; margin: 10px 0; }
    </style>

    <?php
    return ob_get_clean();
}
add_shortcode('coenect_login', 'coenect_login_form_shortcode');
