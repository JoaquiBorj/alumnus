<?php
/*
Plugin Name: Alumni Database Manager
Plugin URI: https://example.com/
Description: Manage Alumni, Course, and User Account tables with a Create button and confirmation prompt. Automatically creates tables on activation and deletes them on deactivation.
Version: 1.3
Author: Ryan Mocorro
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// =====================================================
// 🧱 CREATE TABLES FUNCTION
// =====================================================
function adm_create_alumni_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // === COURSE TABLE ===
    $sql_course = "CREATE TABLE IF NOT EXISTS course (
        course_id INT(11) NOT NULL,
        course VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        PRIMARY KEY (course_id)
    ) ENGINE=InnoDB $charset_collate;";

    // === ALUMNI TABLE ===
    $sql_alumni = "CREATE TABLE IF NOT EXISTS alumni (
        user_id VARCHAR(100) NOT NULL,
        year INT(11) NOT NULL,
        course_id INT(11) NOT NULL,
        firstname VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        lastname VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        email VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        contact_info INT(11) NOT NULL,
        career LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        skills LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // === USER ACCOUNT TABLE ===
    $sql_user_account = "CREATE TABLE IF NOT EXISTS user (
        user VARCHAR(100) NOT NULL,
        course_id INT(11) NOT NULL,
        year INT(11) NOT NULL,
        password VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        PRIMARY KEY (user),
        FOREIGN KEY (user) REFERENCES alumni(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (course_id) REFERENCES course(course_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_course);
    dbDelta($sql_alumni);
    dbDelta($sql_user_account);
}

// =====================================================
// 🔄 ACTIVATE → CREATE TABLES
// =====================================================
function adm_plugin_activate() {
    adm_create_alumni_tables();
}
register_activation_hook(__FILE__, 'adm_plugin_activate');

// =====================================================
// ❌ DEACTIVATE → DROP TABLES
// =====================================================
function adm_plugin_deactivate() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS user");
    $wpdb->query("DROP TABLE IF EXISTS alumni");
    $wpdb->query("DROP TABLE IF EXISTS course");
}
register_deactivation_hook(__FILE__, 'adm_plugin_deactivate');

// =====================================================
// 🧭 ADMIN MENU
// =====================================================
function adm_register_admin_menu() {
    add_menu_page(
        'Alumni Database Manager',
        'Alumni DB Manager',
        'manage_options',
        'alumni-database-manager',
        'adm_admin_page_content',
        'dashicons-database',
        26
    );
}
add_action('admin_menu', 'adm_register_admin_menu');

// =====================================================
// 🖥️ ADMIN PAGE CONTENT
// =====================================================
function adm_admin_page_content() {
    ?>
    <div class="wrap">
        <h1>🎓 Alumni Database Manager</h1>
        <p>Click the button below to manually create or refresh your database tables.</p>

        <?php if (isset($_POST['adm_create_tables'])): ?>
            <?php adm_create_alumni_tables(); ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>✅ Tables have been created or already exist!</strong></p>
            </div>
        <?php endif; ?>

        <form method="post" id="adm-create-form">
            <button type="submit" name="adm_create_tables" id="adm-create-btn" class="button button-primary button-large">
                🧱 Create Alumni Database Tables
            </button>
        </form>

        <hr style="margin:30px 0;">
        <h3>Tables managed by this plugin:</h3>
        <ul style="line-height:1.8;">
            <li>• <code>course</code></li>
            <li>• <code>alumni</code></li>
            <li>• <code>user</code></li>
        </ul>
    </div>

    <script>
        document.getElementById('adm-create-form').addEventListener('submit', function(event) {
            const confirmed = confirm('⚠️ Are you sure you want to create or refresh the Alumni Database tables?');
            if (!confirmed) {
                event.preventDefault();
            }
        });
    </script>
    <?php
}
