<?php
// ===== Shortcode: User Login with real WordPress authentication =====
function coenect_login_form_shortcode() {
    ob_start();

    global $wpdb;
    $db = $wpdb;

    // Helper: detect table names (prefixed or plain)
    $detect_tables = function() use ($db) {
        $tables = [ 'user' => 'user', 'alumni' => 'alumni' ];
        foreach ([ $db->prefix . 'user', 'user' ] as $cand) {
            $exists = $db->get_var($db->prepare('SHOW TABLES LIKE %s', $cand));
            if (!empty($exists)) { $tables['user'] = $cand; break; }
        }
        foreach ([ $db->prefix . 'alumni', 'alumni' ] as $cand) {
            $exists = $db->get_var($db->prepare('SHOW TABLES LIKE %s', $cand));
            if (!empty($exists)) { $tables['alumni'] = $cand; break; }
        }
        return $tables;
    };

    $tables = $detect_tables();

    // Ensure pluggable functions
    if (!function_exists('wp_signon') && defined('ABSPATH')) {
        @require_once ABSPATH . WPINC . '/pluggable.php';
    }

    $errors = [];
    $username_echo = '';

    // --- Handle login submission ---
    if (isset($_POST['login_submit'])) {
        $nonce = isset($_POST['coenect_login_nonce']) ? $_POST['coenect_login_nonce'] : '';
        if (!wp_verify_nonce($nonce, 'coenect_login_action')) {
            $errors[] = __('Security check failed. Please try again.', 'alumnus');
        } else {
            $username = sanitize_text_field(wp_unslash($_POST['username'] ?? ''));
            $username_echo = $username;
            $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
            $remember = !empty($_POST['remember_me']);

            // Try native WordPress login first
            $creds = [
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => $remember,
            ];
            $user_obj = wp_signon($creds, is_ssl());

            if (is_wp_error($user_obj)) {
                // Fallback: verify against custom credentials table and sync WP user
                $urow = $db->get_row($db->prepare("SELECT * FROM `{$tables['user']}` WHERE `user` = %s LIMIT 1", $username));
                if ($urow) {
                    $stored = (string) $urow->password;
                    $verified = false;
                    $should_upgrade_hash = false;

                    if (preg_match('/^\$(2y|2a|argon2id|argon2i)\$/', $stored)) {
                        if (password_verify($password, $stored)) { $verified = true; if (password_needs_rehash($stored, PASSWORD_DEFAULT)) $should_upgrade_hash = true; }
                    } elseif (preg_match('/^\$(P|H)\$/', $stored)) {
                        if (function_exists('wp_check_password') && wp_check_password($password, $stored)) { $verified = true; $should_upgrade_hash = true; }
                    } elseif (ctype_xdigit($stored) && strlen($stored) === 32) { // md5
                        if (md5($password) === strtolower($stored)) { $verified = true; $should_upgrade_hash = true; }
                    } elseif (ctype_xdigit($stored) && strlen($stored) === 40) { // sha1
                        if (sha1($password) === strtolower($stored)) { $verified = true; $should_upgrade_hash = true; }
                    } else { // plain
                        if (hash_equals($stored, $password)) { $verified = true; $should_upgrade_hash = true; }
                    }

                    if (!$verified && function_exists('wp_check_password') && wp_check_password($password, $stored)) {
                        $verified = true; $should_upgrade_hash = true;
                    }

                    if ($verified) {
                        if ($should_upgrade_hash) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $db->query($db->prepare("UPDATE `{$tables['user']}` SET `password` = %s WHERE `user` = %s", $newHash, $username));
                        }

                        // Ensure a WP user exists and password matches; create or sync as needed
                        $wp_user = get_user_by('login', $username);
                        if (!$wp_user) {
                            // Try to enrich with alumni data
                            $alumni = $db->get_row($db->prepare("SELECT firstname, lastname FROM `{$tables['alumni']}` WHERE `user_id` = %s LIMIT 1", $username));
                            $userdata = [
                                'user_login'   => $username,
                                'user_pass'    => $password,
                                'user_email'   => 'alumni' . $username . '@example.invalid',
                                'first_name'   => $alumni ? $alumni->firstname : '',
                                'last_name'    => $alumni ? $alumni->lastname : '',
                                'display_name' => $alumni ? trim($alumni->firstname . ' ' . $alumni->lastname) : $username,
                                'role'         => 'subscriber',
                            ];
                            $new_id = wp_insert_user($userdata);
                            if (!is_wp_error($new_id)) {
                                $wp_user = get_user_by('ID', $new_id);
                            }
                        } else {
                            // Update password to keep WP in sync
                            wp_set_password($password, $wp_user->ID);
                        }

                        // Try to sign on again using WP
                        $user_obj = wp_signon($creds, is_ssl());
                    }
                }
            }

            if (!is_wp_error($user_obj)) {
                // Authenticated: build redirect URL
                $urow = $db->get_row($db->prepare("SELECT * FROM `{$tables['user']}` WHERE `user` = %s LIMIT 1", $username));
                $course_id = !empty($urow->course_id) ? $urow->course_id : '';
                $year = !empty($urow->year) ? $urow->year : '';
                $target = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
                if (!$target) {
                    // default to current page with query params (keeps existing behavior)
                    $target = add_query_arg([
                        'user' => rawurlencode($username),
                        'course_id' => rawurlencode((string) $course_id),
                        'year' => rawurlencode((string) $year),
                    ], get_permalink());
                }

                // If password is default 12345, show reset modal instead of redirect
                if ($password === '12345') {
                    echo '<script>document.addEventListener("DOMContentLoaded",function(){document.getElementById("resetModal").style.display="block";});</script>';
                } else {
                    wp_safe_redirect($target);
                    exit;
                }
            } else {
                $errors[] = __('Invalid username or password.', 'alumnus');
            }
        }
    }

    // --- Handle password reset ---
    if (isset($_POST['reset_submit'])) {
        $nonce = isset($_POST['coenect_reset_nonce']) ? $_POST['coenect_reset_nonce'] : '';
        if (!wp_verify_nonce($nonce, 'coenect_reset_action')) {
            $errors[] = __('Security check failed on password reset.', 'alumnus');
        } else {
            $username = sanitize_text_field(wp_unslash($_POST['reset_username'] ?? ''));
            $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
            if (strlen($new_password) < 6) {
                $errors[] = __('Password must be at least 6 characters.', 'alumnus');
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $updated = $db->query($db->prepare("UPDATE `{$tables['user']}` SET `password` = %s WHERE `user` = %s", $hashed_password, $username));
                // Sync WP user password as well
                $wp_user = get_user_by('login', $username);
                if ($wp_user) { wp_set_password($new_password, $wp_user->ID); }

                if ($updated !== false) {
                    $urow = $db->get_row($db->prepare("SELECT * FROM `{$tables['user']}` WHERE `user` = %s LIMIT 1", $username));
                    $course_id = urlencode(isset($urow->course_id) ? $urow->course_id : '');
                    $year = urlencode(isset($urow->year) ? $urow->year : '');
                    $redirect_url = add_query_arg([
                        'user' => rawurlencode($username),
                        'course_id' => $course_id,
                        'year' => $year
                    ], get_permalink());
                    echo "<script>alert('" . esc_js(__('Password reset successfully! Redirecting...', 'alumnus')) . "'); window.location.href='" . esc_url($redirect_url) . "';</script>";
                } else {
                    $errors[] = __('Failed to reset password.', 'alumnus');
                }
            }
        }
    }
    ?>

    <!-- ===== Login Form ===== -->
    <form method="post" class="coenect-login-form" style="max-width:400px;margin:auto;">
        <?php wp_nonce_field('coenect_login_action','coenect_login_nonce'); ?>
        <h3><?php echo esc_html__('Login', 'alumnus'); ?></h3>
        <?php if (!empty($errors)) { echo '<div class="error">' . esc_html(implode(' ', $errors)) . '</div>'; } ?>

        <label><?php echo esc_html__('Username / User ID', 'alumnus'); ?></label>
        <input type="text" name="username" required class="widefat" value="<?php echo esc_attr($username_echo); ?>">

        <label><?php echo esc_html__('Password', 'alumnus'); ?></label>
        <input type="password" name="password" required class="widefat">

        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" name="remember_me" value="1"> <?php echo esc_html__('Remember me', 'alumnus'); ?>
        </label>

        <input type="hidden" name="redirect_to" value="<?php echo isset($_GET['redirect_to']) ? esc_url( wp_unslash($_GET['redirect_to']) ) : ''; ?>">

        <button type="submit" name="login_submit" class="button button-primary" style="margin-top:10px;"><?php echo esc_html__('Login', 'alumnus'); ?></button>
    </form>

    <!-- ===== Password Reset Modal ===== -->
    <div id="resetModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999;">
        <div style="background:#fff; width:300px; margin:100px auto; padding:20px; border-radius:8px; position:relative;">
            <h3><?php echo esc_html__('Reset Password', 'alumnus'); ?></h3>
            <form method="post">
                <?php wp_nonce_field('coenect_reset_action','coenect_reset_nonce'); ?>
                <input type="hidden" name="reset_username" value="<?php echo isset($username_echo) ? esc_attr($username_echo) : ''; ?>">
                <label><?php echo esc_html__('New Password', 'alumnus'); ?></label>
                <input type="password" name="new_password" required class="widefat">

                <button type="submit" name="reset_submit" class="button button-primary" style="margin-top:10px;"><?php echo esc_html__('Update Password', 'alumnus'); ?></button>
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
