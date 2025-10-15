<?php
/*
Plugin Name: Alumnus Alumni Manager
Description: Admin page to add Courses and Alumni records into custom tables (courses, alumni).
Version: 1.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Resolve actual table names. Prefer prefixed tables if found; otherwise fall back to unprefixed.
 * Supports both legacy `courses` and new `course` table; returns keys: 'courses' (alias for actual course table), 'alumni', 'user'.
 */
function alumnus_get_table_names() {
	global $wpdb;
	$tables = [
		'courses' => 'courses', // alias; may map to 'course'
		'alumni'  => 'alumni',
		'user'    => 'user',
	];

	// Courses table detection: try wp_courses, then wp_course, then courses, then course
	$candidates_courses = [ $wpdb->prefix . 'courses', $wpdb->prefix . 'course', 'courses', 'course' ];
	foreach ($candidates_courses as $cand) {
		$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $cand));
		if (!empty($exists)) { $tables['courses'] = $cand; break; }
	}

	// Alumni table detection: try prefixed then plain
	$candidates_alumni = [ $wpdb->prefix . 'alumni', 'alumni' ];
	foreach ($candidates_alumni as $cand) {
		$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $cand));
		if (!empty($exists)) { $tables['alumni'] = $cand; break; }
	}

	// User account table detection: try prefixed then plain
	$candidates_user = [ $wpdb->prefix . 'user', 'user' ];
	foreach ($candidates_user as $cand) {
		$exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $cand));
		if (!empty($exists)) { $tables['user'] = $cand; break; }
	}

	return $tables;
}

/**
 * Add top-level admin menu
 */
function alumnus_admin_menu() {
	add_menu_page(
		__('Alumnus', 'alumnus'),
		__('Alumnus', 'alumnus'),
		'manage_options',
		'alumnus-add-alumni',
		'alumnus_render_admin_page',
		'dashicons-welcome-learn-more',
		30
	);
}
add_action('admin_menu', 'alumnus_admin_menu');

/**
 * Handle form submissions
 */
function alumnus_handle_post() {
	if (!is_admin()) return;
	if (!current_user_can('manage_options')) return;

	if (!isset($_POST['alumnus_action'])) return;

	$tables = alumnus_get_table_names();
	global $wpdb;

	// Add Course
	if ($_POST['alumnus_action'] === 'add_course') {
		check_admin_referer('alumnus_add_course');

		$course_id   = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$course_name = isset($_POST['course_name']) ? sanitize_text_field(wp_unslash($_POST['course_name'])) : '';

		if ($course_id <= 0) {
			add_settings_error('alumnus', 'course_id_empty', __('Course ID is required and must be a positive number.', 'alumnus'), 'error');
			return;
		}
		if ($course_name === '') {
			add_settings_error('alumnus', 'course_empty', __('Course name is required.', 'alumnus'), 'error');
			return;
		}

		// Check duplicate id or name
		$exists_id = $wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$tables['courses']} WHERE course_id = %d LIMIT 1", $course_id));
		if ($exists_id) {
			add_settings_error('alumnus', 'course_id_exists', __('A course with that ID already exists.', 'alumnus'), 'error');
			return;
		}
		// Attempt to detect name column ('course' in new schema)
		$name_col = 'course';
		$exists_name = $wpdb->get_var($wpdb->prepare("SELECT {$name_col} FROM {$tables['courses']} WHERE {$name_col} = %s LIMIT 1", $course_name));
		if ($exists_name) {
			add_settings_error('alumnus', 'course_exists', __('Course name already exists.', 'alumnus'), 'error');
			return;
		}

		$inserted = $wpdb->insert(
			$tables['courses'],
			[ 'course_id' => $course_id, $name_col => $course_name ],
			[ '%d', '%s' ]
		);

		if ($inserted === false) {
			add_settings_error('alumnus', 'course_insert_fail', sprintf(__('Failed to add course. DB error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
		} else {
			add_settings_error('alumnus', 'course_insert_ok', __('Course added successfully.', 'alumnus'), 'updated');
		}
	}

	// Add Alumni
	if ($_POST['alumnus_action'] === 'add_alumni') {
		check_admin_referer('alumnus_add_alumni');

		$alumni_id  = isset($_POST['alumni_id']) ? intval($_POST['alumni_id']) : 0;
		$course_id  = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : 0;
		$password   = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

		// Basic validation
		$errors = [];
		if ($alumni_id <= 0) $errors[] = __('User ID is required and must be a positive number.', 'alumnus');
		if ($course_id <= 0) $errors[] = __('Course is required.', 'alumnus');
		if ($first_name === '') $errors[] = __('First name is required.', 'alumnus');
		if ($last_name === '') $errors[] = __('Last name is required.', 'alumnus');
		$current_year = (int) date('Y');
		if ($batch_year < 1900 || $batch_year > $current_year) $errors[] = __('Batch year must be between 1900 and current year.', 'alumnus');
		if ($password === '' || strlen($password) < 6) $errors[] = __('Password must be at least 6 characters.', 'alumnus');

		// Validate course exists
        $course_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT course_id FROM {$tables['courses']} WHERE course_id = %d LIMIT 1", $course_id)
        );
        if (!$course_exists) $errors[] = __('Selected course does not exist.', 'alumnus');

		// Check alumni user_id uniqueness
		$alumni_exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$tables['alumni']} WHERE user_id = %d LIMIT 1", $alumni_id));
		if ($alumni_exists) $errors[] = __('An alumni with that User ID already exists.', 'alumnus');

		if (!empty($errors)) {
			foreach ($errors as $e) {
				add_settings_error('alumnus', 'alumni_error_' . md5($e), $e, 'error');
			}
			return;
		}

		// Hash password
		if (function_exists('wp_hash_password')) {
			$password_hash = wp_hash_password($password);
		} else {
			$password_hash = password_hash($password, PASSWORD_DEFAULT);
		}

		// Insert into alumni (fill required non-null fields with safe defaults)
		$insert_alumni = $wpdb->insert(
			$tables['alumni'],
			[
				'user_id'   => $alumni_id,
				'year'      => $batch_year,
				'course_id' => $course_id,
				'firstname' => $first_name,
				'lastname'  => $last_name,
				'email'     => '',        // placeholder; not collected here
				'contact_info' => 0,      // placeholder; not collected here
				'career'    => '',        // placeholder
				'skills'    => '',        // placeholder
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' ]
		);
		if ($insert_alumni === false) {
			add_settings_error('alumnus', 'alumni_insert_fail', sprintf(__('Failed to add alumni. DB error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
			return;
		}

		// Insert into user table
		$insert_user = $wpdb->insert(
			$tables['user'],
			[
				'user'      => $alumni_id,
				'course_id' => $course_id,
				'year'      => $batch_year,
				'password'  => $password_hash,
			],
			[ '%d', '%d', '%d', '%s' ]
		);

		if ($insert_user === false) {
			// Roll back alumni insert to keep consistency
			$wpdb->delete($tables['alumni'], [ 'user_id' => $alumni_id ], [ '%d' ]);
			add_settings_error('alumnus', 'user_insert_fail', sprintf(__('Failed to add user credentials. DB error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
			return;
		}

		add_settings_error('alumnus', 'alumni_insert_ok', __('Alumni added successfully.', 'alumnus'), 'updated');
	}
}
add_action('admin_init', 'alumnus_handle_post');

/**
 * Render admin page
 */
function alumnus_render_admin_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	$tables = alumnus_get_table_names();
	global $wpdb;
	// Fetch courses according to new schema (course_id, course)
	$courses = $wpdb->get_results("SELECT course_id, course FROM {$tables['courses']} ORDER BY course ASC");

	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Alumnus Manager', 'alumnus') . '</h1>';

	settings_errors('alumnus');

	// Add Course Form
	echo '<h2>' . esc_html__('Add Course', 'alumnus') . '</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_add_course');
	echo '<input type="hidden" name="alumnus_action" value="add_course" />';
	echo '<table class="form-table" role="presentation">';
	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="course_id">' . esc_html__('Course ID', 'alumnus') . '</label></th>';
	echo '    <td><input name="course_id" id="course_id" type="text" inputmode="numeric" pattern="[0-9]*" class="small-text" required /></td>';
	echo '  </tr>';
	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="course_name">' . esc_html__('Course Name', 'alumnus') . '</label></th>';
	echo '    <td><input name="course_name" id="course_name" type="text" class="regular-text" required /></td>';
	echo '  </tr>';
	echo '</table>';
	submit_button(__('Add Course', 'alumnus'));
	echo '</form>';

	// Divider
	echo '<hr />';

	// Add Alumni Form
	echo '<h2>' . esc_html__('Add Alumni', 'alumnus') . '</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_add_alumni');
	echo '<input type="hidden" name="alumnus_action" value="add_alumni" />';
	echo '<table class="form-table" role="presentation">';
	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="alumni_id">' . esc_html__('User ID', 'alumnus') . '</label></th>';
	echo '    <td><input name="alumni_id" id="alumni_id" type="text" inputmode="numeric" pattern="[0-9]*" class="regular-text" required /></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="course_id">' . esc_html__('Course', 'alumnus') . '</label></th>';
	echo '    <td>';
	if (!empty($courses)) {
		echo '<select name="course_id" id="course_id" required>';
		echo '<option value="">' . esc_html__('Select a course', 'alumnus') . '</option>';
		foreach ($courses as $course) {
			echo '<option value="' . esc_attr((string)$course->course_id) . '">' . esc_html($course->course) . '</option>';
		}
		echo '</select>';
	} else {
		echo '<em>' . esc_html__('No courses yet. Add a course first.', 'alumnus') . '</em>';
	}
	echo '    </td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="first_name">' . esc_html__('First Name', 'alumnus') . '</label></th>';
	echo '    <td><input name="first_name" id="first_name" type="text" class="regular-text" required /></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="last_name">' . esc_html__('Last Name', 'alumnus') . '</label></th>';
	echo '    <td><input name="last_name" id="last_name" type="text" class="regular-text" required /></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="batch_year">' . esc_html__('Year', 'alumnus') . '</label></th>';
	echo '    <td><input name="batch_year" id="batch_year" type="text" inputmode="numeric" pattern="[0-9]*" class="small-text" required /> <span class="description">' . esc_html__('e.g., 2024', 'alumnus') . '</span></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="password">' . esc_html__('Password', 'alumnus') . '</label></th>';
	echo '    <td><input name="password" id="password" type="password" class="regular-text" required /></td>';
	echo '  </tr>';

	echo '</table>';
	if (!empty($courses)) {
		submit_button(__('Add Alumni', 'alumnus'));
	}
	echo '</form>';

	echo '</div>';
}

?>
