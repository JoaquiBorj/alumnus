<?php
// ===== Enqueue Login Styles =====
function coenect_login_enqueue_styles() {
    wp_enqueue_style(
        'coenect-login-styles',
        plugin_dir_url(__FILE__) . 'assets/css/login.css',
        array(),
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'coenect_login_enqueue_styles');

// ===== Shortcode: User Login with Redirect and Data Passing =====
function coenect_login_form_shortcode() {
    ob_start();

    global $wpdb;
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
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        $user = $db->get_row(
            $db->prepare(
                "SELECT * FROM `user` WHERE `user` = %s LIMIT 1",
                $username
            )
        );

        if ($user) {
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
                    $should_upgrade_hash = true;
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

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[alumnus-login] user=' . $username . ' type=' . $hash_type . ' verified=' . ($verified ? '1' : '0'));
            }

            if ($verified) {
                // Upgrade legacy hashes to bcrypt
                if ($should_upgrade_hash) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $db->query(
                        $db->prepare("UPDATE `user` SET `password` = %s WHERE `user` = %s", $newHash, $username)
                    );
                }

                $course_id = urlencode(isset($user->course_id) ? $user->course_id : '');
                $year = urlencode(isset($user->year) ? $user->year : '');
                $username_encoded = urlencode($user->user);

                // If password is default 12345 -> show reset modal
                if ($password === '12345') {
                    ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            document.getElementById("resetModal").classList.add("active");
                        });
                    </script>
                    <?php
                } else {
                    // Redirect to dashboard
                    $redirect_url = add_query_arg([
                        'user' => $username_encoded,
                        'course_id' => $course_id,
                        'year' => $year
                    ], get_permalink());
                    echo "<script>window.location.href='" . esc_url($redirect_url) . "';</script>";
                }
            } else {
                echo '<div class="coenect-error-message">Incorrect password. Please try again.</div>';
            }
        } else {
            echo '<div class="coenect-error-message">User not found. Please check your username.</div>';
        }
    }

    // --- Handle password reset ---
    if (isset($_POST['reset_submit'])) {
        $username = sanitize_text_field($_POST['reset_username']);
        $new_password = sanitize_text_field($_POST['new_password']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $updated = $db->query(
            $db->prepare(
                "UPDATE `user` SET `password` = %s WHERE `user` = %s",
                $hashed_password,
                $username
            )
        );

        if ($updated !== false) {
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

                $redirect_url = add_query_arg([
                    'user' => $username_encoded,
                    'course_id' => $course_id,
                    'year' => $year
                ], get_permalink());
                echo "<script>alert('Password reset successfully! Redirecting...'); window.location.href='" . esc_url($redirect_url) . "';</script>";
            }
        } else {
            echo "<div class='coenect-error-message'>Failed to reset password.</div>";
        }
    }
    ?>

    <div class="coenect-login-wrapper">
        <!-- Home Button -->
        <a href="<?php echo esc_url(home_url('/')); ?>" class="coenect-login-home-btn">
            <div class="coenect-login-home-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <span class="coenect-login-home-text">Home Page</span>
        </a>

        <!-- Main Container -->
        <div class="coenect-login-container">
            <div class="coenect-login-split">
                <!-- Left Side - Logo -->
                <div class="coenect-login-left">
                    <h1 class="coenect-logo-text">Logo of COE</h1>
                </div>

                <!-- Vertical Divider -->
                <div class="coenect-login-divider"></div>

                <!-- Right Side - Login Form -->
                <div class="coenect-login-right">
                    <form method="post" class="coenect-login-form">
                        <input 
                            type="text" 
                            name="username" 
                            class="coenect-form-input" 
                            placeholder="Username / User ID" 
                            required
                        >
                        
                        <input 
                            type="password" 
                            name="password" 
                            class="coenect-form-input" 
                            placeholder="Password" 
                            required
                        >

                        <div class="coenect-form-options">
                            <label class="coenect-remember-me">
                                <div class="coenect-remember-checkbox checked"></div>
                                <span class="coenect-remember-label">Remember me</span>
                            </label>
                            
                            <a href="#" class="coenect-forgot-link">Forgot password</a>
                        </div>

                        <button type="submit" name="login_submit" class="coenect-login-btn">
                            Log in
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password Reset Modal -->
        <div id="resetModal" class="coenect-modal-overlay">
            <div class="coenect-modal">
                <button type="button" class="coenect-modal-close" onclick="document.getElementById('resetModal').classList.remove('active')">
                    ✖
                </button>
                <h3>Reset Password</h3>
                <form method="post" class="coenect-modal-form">
                    <input type="hidden" name="reset_username" value="<?php echo isset($username) ? esc_attr($username) : ''; ?>">
                    
                    <label class="coenect-modal-label">New Password</label>
                    <input 
                        type="password" 
                        name="new_password" 
                        class="coenect-form-input" 
                        placeholder="Enter new password" 
                        required
                    >

                    <button type="submit" name="reset_submit" class="coenect-login-btn">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Remember me checkbox toggle
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.querySelector('.coenect-remember-checkbox');
        if (checkbox) {
            checkbox.addEventListener('click', function() {
                this.classList.toggle('checked');
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('coenect_login', 'coenect_login_form_shortcode');
