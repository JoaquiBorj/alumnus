<?php
// ===== Shortcode: User Login with Redirect and Data Passing =====
function coenect_login_form_shortcode() {
    ob_start();

    global $wpdb;
    $db = new wpdb('root', '', 'coenect', 'localhost'); // Adjust credentials

    // --- Handle login submission ---
    if (isset($_POST['login_submit'])) {
        $username = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);

        // Query user data
        $user = $db->get_row($db->prepare("SELECT * FROM user WHERE user = %s", $username));

        if ($user) {
            // Check password (plain or hashed)
            if ($user->password === $password || password_verify($password, $user->password)) {
                // Get course_id and year for redirect
                $course_id = urlencode($user->course_id);
                $year = urlencode($user->year);
                $username_encoded = urlencode($user->username);

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
            echo '<div class="error">User not found.</div>';
        }
    }

    // --- Handle password reset ---
    if (isset($_POST['reset_submit'])) {
        $username = sanitize_text_field($_POST['reset_username']);
        $new_password = sanitize_text_field($_POST['new_password']);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $updated = $db->update(
            'user',
            ['password' => $hashed_password],
            ['user' => $username],
            ['%s'],
            ['%s']
        );

        if ($updated !== false) {
            // Get updated user data for redirect
            $user = $db->get_row($db->prepare("SELECT * FROM user WHERE use = %s", $username));
            $course_id = urlencode($user->course_id);
            $year = urlencode($user->year);
            $username_encoded = urlencode($user->username);

            // Redirect to shortcode page with query params
            $redirect_url = add_query_arg([
                'user' => $username_encoded,
                'course_id' => $course_id,
                'year' => $year
            ], get_permalink());
            echo "<script>alert('Password reset successfully! Redirecting...'); window.location.href='" . esc_url($redirect_url) . "';</script>";
        } else {
            echo "<div class='error'>Failed to reset password.</div>";
        }
    }
    ?>

    <!-- ===== Login Form ===== -->
    <form method="post" class="coenect-login-form" style="max-width:400px;margin:auto;">
        <h3>Login</h3>
        <label>Username</label>
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
