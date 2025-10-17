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

// ===== Helper: Resolve Profile Page URL =====
if (!function_exists('alumnus_resolve_profile_page_url')) {
    function alumnus_resolve_profile_page_url() {
        // 1) Allow override via saved option
        $opt_page_id = (int) get_option('alumnus_profile_page_id');
        if ($opt_page_id) {
            $link = get_permalink($opt_page_id);
            if ($link) {
                return apply_filters('alumnus_profile_page_url', $link);
            }
        }

        // 2) Discover first published page that contains [alumni_profile] shortcode
        $candidate = '';
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'suppress_filters' => true,
        ]);
        
        if ($pages) {
            foreach ($pages as $p) {
                if (is_object($p) && !empty($p->post_content) && function_exists('has_shortcode') && has_shortcode($p->post_content, 'alumni_profile')) {
                    $candidate = get_permalink($p->ID);
                    break;
                }
            }
        }

        if (!$candidate) {
            $candidate = home_url('/');
        }

        return apply_filters('alumnus_profile_page_url', $candidate);
    }
}

// ===== Helper: Get Profile URL with alumni_id =====
if (!function_exists('alumnus_get_profile_url')) {
    function alumnus_get_profile_url($alumni_id, $profile_page_url = '') {
        if (!$profile_page_url) {
            $profile_page_url = alumnus_resolve_profile_page_url();
        }
        return add_query_arg('alumni_id', rawurlencode($alumni_id), $profile_page_url);
    }
}

// ===== Shortcode: User Login with WordPress Authentication =====
function coenect_login_form_shortcode() {
    ob_start();

    global $wpdb;
    $db = $wpdb;

    // Helper: detect table names (prefixed or plain)
    $detect_tables = function() use ($db) {
        $tables = ['user' => 'user', 'alumni' => 'alumni'];
        foreach ([$db->prefix . 'user', 'user'] as $cand) {
            $exists = $db->get_var($db->prepare('SHOW TABLES LIKE %s', $cand));
            if (!empty($exists)) {
                $tables['user'] = $cand;
                break;
            }
        }
        foreach ([$db->prefix . 'alumni', 'alumni'] as $cand) {
            $exists = $db->get_var($db->prepare('SHOW TABLES LIKE %s', $cand));
            if (!empty($exists)) {
                $tables['alumni'] = $cand;
                break;
            }
        }
        return $tables;
    };

    $tables = $detect_tables();

    // Ensure pluggable functions are available
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

                    // Verify password with multiple legacy formats
                    if (preg_match('/^\$(2y|2a|argon2id|argon2i)\$/', $stored)) {
                        if (password_verify($password, $stored)) {
                            $verified = true;
                            if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                                $should_upgrade_hash = true;
                            }
                        }
                    } elseif (preg_match('/^\$(P|H)\$/', $stored)) {
                        if (function_exists('wp_check_password') && wp_check_password($password, $stored)) {
                            $verified = true;
                            $should_upgrade_hash = true;
                        }
                    } elseif (ctype_xdigit($stored) && strlen($stored) === 32) { // md5
                        if (md5($password) === strtolower($stored)) {
                            $verified = true;
                            $should_upgrade_hash = true;
                        }
                    } elseif (ctype_xdigit($stored) && strlen($stored) === 40) { // sha1
                        if (sha1($password) === strtolower($stored)) {
                            $verified = true;
                            $should_upgrade_hash = true;
                        }
                    } else { // plain text
                        if (hash_equals($stored, $password)) {
                            $verified = true;
                            $should_upgrade_hash = true;
                        }
                    }

                    if (!$verified && function_exists('wp_check_password') && wp_check_password($password, $stored)) {
                        $verified = true;
                        $should_upgrade_hash = true;
                    }

                    if ($verified) {
                        // Upgrade legacy hash to bcrypt
                        if ($should_upgrade_hash) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $db->query($db->prepare("UPDATE `{$tables['user']}` SET `password` = %s WHERE `user` = %s", $newHash, $username));
                        }

                        // Ensure a WP user exists and sync password
                        $wp_user = get_user_by('login', $username);
                        if (!$wp_user) {
                            // Create WP user with alumni data
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

                        // Try to sign on again using WordPress
                        $user_obj = wp_signon($creds, is_ssl());
                    }
                }
            }

            if (!is_wp_error($user_obj)) {
                // Authenticated: build redirect URL to user's profile
                $urow = $db->get_row($db->prepare("SELECT * FROM `{$tables['user']}` WHERE `user` = %s LIMIT 1", $username));
                $course_id = !empty($urow->course_id) ? $urow->course_id : '';
                $year = !empty($urow->year) ? $urow->year : '';
                
                // Respect explicit redirect_to if provided
                $target = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
                if (!$target) {
                    $profile_page_url = alumnus_resolve_profile_page_url();
                    if (function_exists('alumnus_get_profile_url')) {
                        $target = alumnus_get_profile_url($username, $profile_page_url);
                    } else {
                        $target = add_query_arg('alumni_id', rawurlencode($username), $profile_page_url);
                    }
                }

                // Allow final override via filter
                $target = apply_filters('alumnus_login_redirect_url', $target, $user_obj, $username, [
                    'course_id' => $course_id,
                    'year' => $year,
                ]);

                // If password is default 12345, show reset modal instead
                if ($password === '12345') {
                    ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            document.getElementById("resetModal").classList.add("active");
                        });
                    </script>
                    <?php
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
                if ($wp_user) {
                    wp_set_password($new_password, $wp_user->ID);
                }

                if ($updated !== false) {
                    $urow = $db->get_row($db->prepare("SELECT * FROM `{$tables['user']}` WHERE `user` = %s LIMIT 1", $username));
                    
                    // Redirect to profile page
                    $profile_page_url = alumnus_resolve_profile_page_url();
                    $target = alumnus_get_profile_url($username, $profile_page_url);
                    
                    echo "<script>alert('" . esc_js(__('Password reset successfully! Redirecting...', 'alumnus')) . "'); window.location.href='" . esc_url($target) . "';</script>";
                } else {
                    $errors[] = __('Failed to reset password.', 'alumnus');
                }
            }
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
            <span class="coenect-login-home-text"><?php echo esc_html__('Home Page', 'alumnus'); ?></span>
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
                        <?php wp_nonce_field('coenect_login_action', 'coenect_login_nonce'); ?>
                        
                        <?php if (!empty($errors)) : ?>
                            <div class="coenect-error-message">
                                <?php echo esc_html(implode(' ', $errors)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <input 
                            type="text" 
                            name="username" 
                            class="coenect-form-input" 
                            placeholder="<?php echo esc_attr__('Username / User ID', 'alumnus'); ?>" 
                            value="<?php echo esc_attr($username_echo); ?>"
                            required
                        >
                        
                        <input 
                            type="password" 
                            name="password" 
                            class="coenect-form-input" 
                            placeholder="<?php echo esc_attr__('Password', 'alumnus'); ?>" 
                            required
                        >

                        <div class="coenect-form-options">
                            <label class="coenect-remember-me">
                                <input type="checkbox" name="remember_me" value="1" class="coenect-remember-checkbox-input" style="display:none;">
                                <div class="coenect-remember-checkbox"></div>
                                <span class="coenect-remember-label"><?php echo esc_html__('Remember me', 'alumnus'); ?></span>
                            </label>
                            
                            <a href="#" class="coenect-forgot-link"><?php echo esc_html__('Forgot password', 'alumnus'); ?></a>
                        </div>

                        <input type="hidden" name="redirect_to" value="<?php echo isset($_GET['redirect_to']) ? esc_url(wp_unslash($_GET['redirect_to'])) : ''; ?>">

                        <button type="submit" name="login_submit" class="coenect-login-btn">
                            <?php echo esc_html__('Log in', 'alumnus'); ?>
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
                <h3><?php echo esc_html__('Reset Password', 'alumnus'); ?></h3>
                <form method="post" class="coenect-modal-form">
                    <?php wp_nonce_field('coenect_reset_action', 'coenect_reset_nonce'); ?>
                    <input type="hidden" name="reset_username" value="<?php echo isset($username_echo) ? esc_attr($username_echo) : ''; ?>">
                    
                    <label class="coenect-modal-label"><?php echo esc_html__('New Password', 'alumnus'); ?></label>
                    <input 
                        type="password" 
                        name="new_password" 
                        class="coenect-form-input" 
                        placeholder="<?php echo esc_attr__('Enter new password (min. 6 characters)', 'alumnus'); ?>" 
                        required
                    >

                    <button type="submit" name="reset_submit" class="coenect-login-btn">
                        <?php echo esc_html__('Update Password', 'alumnus'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Remember me checkbox toggle
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.querySelector('.coenect-remember-checkbox');
        const hiddenInput = document.querySelector('.coenect-remember-checkbox-input');
        if (checkbox && hiddenInput) {
            checkbox.addEventListener('click', function() {
                this.classList.toggle('checked');
                hiddenInput.checked = this.classList.contains('checked');
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('coenect_login', 'coenect_login_form_shortcode');
