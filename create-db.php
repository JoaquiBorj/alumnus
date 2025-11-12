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
        bio_note LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB $charset_collate;";

    // === SKILLS TABLE ===
    $sql_skills = "CREATE TABLE IF NOT EXISTS skills (
        skill_id INT(11) NOT NULL AUTO_INCREMENT,
        skill VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        PRIMARY KEY (skill_id),
        UNIQUE KEY uniq_skill (skill)
    ) ENGINE=InnoDB $charset_collate;";

    // === ALUMNI_SKILLS PIVOT TABLE ===
    $sql_alumni_skills = "CREATE TABLE IF NOT EXISTS alumni_skills (
        user_id VARCHAR(100) NOT NULL,
        skill_id INT(11) NOT NULL,
        PRIMARY KEY (user_id, skill_id),
        KEY idx_skill_id (skill_id),
        CONSTRAINT fk_alumni_skills_user FOREIGN KEY (user_id) REFERENCES alumni(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_alumni_skills_skill FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE ON UPDATE CASCADE
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

    // === EXPERIENCE TABLE ===
    // Link experiences directly to alumni.user_id (no dependency on wp_users)
    $sql_experience = "CREATE TABLE IF NOT EXISTS experience (
        experience_id INT(11) NOT NULL AUTO_INCREMENT,
        user_id VARCHAR(100) NOT NULL,
        company_name VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        title VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        location VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NULL,
        PRIMARY KEY (experience_id),
        KEY idx_user_id (user_id),
        CONSTRAINT fk_experience_alumni FOREIGN KEY (user_id) REFERENCES alumni(user_id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_course);
    dbDelta($sql_alumni);
    dbDelta($sql_skills);
    dbDelta($sql_alumni_skills);
    dbDelta($sql_user_account);
    dbDelta($sql_experience);

    // Migrate existing experience table if it was previously linked to wp_users
    adm_migrate_experience_to_alumni_link();

    // Run migration to move legacy alumni.skills CSV data into new tables, then drop the column.
    adm_migrate_skills_to_table();
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
    $wpdb->query("DROP TABLE IF EXISTS alumni_skills");
    $wpdb->query("DROP TABLE IF EXISTS skills");
    $wpdb->query("DROP TABLE IF EXISTS user");
    $wpdb->query("DROP TABLE IF EXISTS experience");
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

        <hr class="alumnus-admin-hr">
        <h3>Tables managed by this plugin:</h3>
        <ul class="alumnus-admin-list">
            <li>• <code>course</code></li>
            <li>• <code>alumni</code></li>
            <li>• <code>skills</code></li>
            <li>• <code>alumni_skills</code></li>
            <li>• <code>user</code></li>
            <li>• <code>experience</code></li>
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

// =====================================================
// 🔁 MIGRATION: Move alumni.skills -> skills + alumni_skills
// =====================================================
function adm_migrate_skills_to_table() {
    global $wpdb;

    // Detect if legacy column exists
    $has_column = $wpdb->get_var("SHOW COLUMNS FROM alumni LIKE 'skills'");
    if (empty($has_column)) {
        return; // Nothing to migrate
    }

    // Ensure new tables exist (idempotent)
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $sql_skills = "CREATE TABLE IF NOT EXISTS skills (
        skill_id INT(11) NOT NULL AUTO_INCREMENT,
        skill VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
        PRIMARY KEY (skill_id),
        UNIQUE KEY uniq_skill (skill)
    ) ENGINE=InnoDB $charset_collate;";
    $sql_alumni_skills = "CREATE TABLE IF NOT EXISTS alumni_skills (
        user_id VARCHAR(100) NOT NULL,
        skill_id INT(11) NOT NULL,
        PRIMARY KEY (user_id, skill_id),
        KEY idx_skill_id (skill_id)
    ) ENGINE=InnoDB $charset_collate;";
    dbDelta($sql_skills);
    dbDelta($sql_alumni_skills);

    // Fetch all alumni with non-empty skills
    $rows = $wpdb->get_results("SELECT user_id, skills FROM alumni WHERE skills IS NOT NULL AND TRIM(skills) <> ''");
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $user_id = (string) $row->user_id;
            $skills_raw = (string) $row->skills;
            // Parse by comma/newline similar to profile AJAX
            $parts = preg_split('/[\,\n]+/', $skills_raw);
            $clean = array();
            if (is_array($parts)) {
                foreach ($parts as $p) {
                    $p = trim(wp_strip_all_tags($p));
                    if ($p === '') continue;
                    if (strlen($p) > 64) { $p = substr($p, 0, 64); }
                    $clean[] = $p;
                }
                $clean = array_values(array_unique($clean));
                if (count($clean) > 50) {
                    $clean = array_slice($clean, 0, 50);
                }
            }

            // Replace any existing links for this alumni
            $wpdb->delete('alumni_skills', array('user_id' => $user_id), array('%s'));

            foreach ($clean as $skill) {
                // Find or create skill id
                $skill_id = $wpdb->get_var($wpdb->prepare("SELECT skill_id FROM skills WHERE skill = %s", $skill));
                if (empty($skill_id)) {
                    $ins = $wpdb->insert('skills', array('skill' => $skill), array('%s'));
                    if ($ins !== false) {
                        $skill_id = $wpdb->insert_id;
                    } else {
                        // If insert failed (race/duplicate), try select again
                        $skill_id = $wpdb->get_var($wpdb->prepare("SELECT skill_id FROM skills WHERE skill = %s", $skill));
                    }
                }
                if (!empty($skill_id)) {
                    // Link
                    $wpdb->insert('alumni_skills', array('user_id' => $user_id, 'skill_id' => (int)$skill_id), array('%s','%d'));
                }
            }
        }
    }

    // Finally, drop legacy column
    $wpdb->query("ALTER TABLE alumni DROP COLUMN skills");
}

// =====================================================
// 🔁 MIGRATION: Experience.user_id from wp_users.ID -> alumni.user_id
// =====================================================
function adm_migrate_experience_to_alumni_link() {
    global $wpdb;
    // Check if experience table exists
    $table = $wpdb->get_var("SHOW TABLES LIKE 'experience'");
    if (!$table) return;

    // Inspect user_id column type
    $col = $wpdb->get_row("SHOW COLUMNS FROM experience LIKE 'user_id'");
    if (!$col) return;

    $type = strtolower((string)$col->Type);
    if (strpos($type, 'bigint') === false) {
        return; // already VARCHAR(100) or similar
    }

    // Best-effort: drop old FK if named as earlier
    $wpdb->query("ALTER TABLE experience DROP FOREIGN KEY fk_experience_user");

        // Change column type to VARCHAR(100)
        $wpdb->query("ALTER TABLE experience MODIFY COLUMN user_id VARCHAR(100) NOT NULL");

        // Best-effort data migration: map numeric wp_user IDs to alumni.user_id via user_login or email
        $wp_users = $wpdb->users;
        // 1) Match by user_login
        $wpdb->query(
                "UPDATE experience e
                    JOIN `$wp_users` u ON CAST(e.user_id AS UNSIGNED) = u.ID
                    JOIN alumni a ON a.user_id = u.user_login
                SET e.user_id = a.user_id"
        );
        // 2) Match by email if still numeric
        $wpdb->query(
                "UPDATE experience e
                    JOIN `$wp_users` u ON CAST(e.user_id AS UNSIGNED) = u.ID
                    JOIN alumni a ON a.email = u.user_email
                SET e.user_id = a.user_id"
        );

        // Add new FK to alumni
        $wpdb->query("ALTER TABLE experience ADD CONSTRAINT fk_experience_alumni FOREIGN KEY (user_id) REFERENCES alumni(user_id) ON DELETE CASCADE ON UPDATE CASCADE");
}
