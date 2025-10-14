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
 * Returns array with keys 'courses' and 'alumni'.
 */
function alumnus_get_table_names() {
	global $wpdb;
	$tables = [
		'courses' => 'courses',
		'alumni'  => 'alumni',
	];

	// Try prefixed tables first (e.g., wp_courses, wp_alumni)
	$prefixed_courses = $wpdb->prefix . 'courses';
	$prefixed_alumni  = $wpdb->prefix . 'alumni';

	$has_prefixed_courses = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $prefixed_courses));
	$has_prefixed_alumni  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $prefixed_alumni));

	if (!empty($has_prefixed_courses)) {
		$tables['courses'] = $prefixed_courses;
	}
	if (!empty($has_prefixed_alumni)) {
		$tables['alumni'] = $prefixed_alumni;
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

		$course_name = isset($_POST['course_name']) ? sanitize_text_field(wp_unslash($_POST['course_name'])) : '';

		if ($course_name === '') {
			add_settings_error('alumnus', 'course_empty', __('Course name is required.', 'alumnus'), 'error');
			return;
		}

		// Check duplicate
		$exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['courses']} WHERE course_name = %s LIMIT 1", $course_name));
		if ($exists) {
			add_settings_error('alumnus', 'course_exists', __('Course already exists.', 'alumnus'), 'error');
			return;
		}

		$inserted = $wpdb->insert(
			$tables['courses'],
			[ 'course_name' => $course_name ],
			[ '%s' ]
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

		$alumni_id  = isset($_POST['alumni_id']) ? sanitize_text_field(wp_unslash($_POST['alumni_id'])) : '';
		$course_id  = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : 0;
		$email      = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
	$phone      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

		// Basic validation
		$errors = [];
		if ($alumni_id === '') $errors[] = __('School ID is required.', 'alumnus');
		if ($course_id <= 0) $errors[] = __('Course is required.', 'alumnus');
		if ($first_name === '') $errors[] = __('First name is required.', 'alumnus');
		if ($last_name === '') $errors[] = __('Last name is required.', 'alumnus');
		$current_year = (int) date('Y');
		if ($batch_year < 1900 || $batch_year > $current_year) $errors[] = __('Batch year must be between 1900 and current year.', 'alumnus');
		if ($email && !is_email($email)) $errors[] = __('Email is invalid.', 'alumnus');

		// Validate course exists
		$course_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['courses']} WHERE id = %d", $course_id));
		if (!$course_exists) $errors[] = __('Selected course does not exist.', 'alumnus');

		// Check alumni id uniqueness
		$alumni_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['alumni']} WHERE id = %s LIMIT 1", $alumni_id));
		if ($alumni_exists) $errors[] = __('An alumni with that School ID already exists.', 'alumnus');

		// Ensure email is unique (if provided)
		if ($email !== '') {
			$email_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['alumni']} WHERE email = %s LIMIT 1", $email));
			if ($email_exists) {
				$errors[] = __('An alumni with that email already exists.', 'alumnus');
			}
		}

		// Ensure phone is unique (if provided)
		if ($phone !== '') {
			$phone_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['alumni']} WHERE phone = %s LIMIT 1", $phone));
			if ($phone_exists) {
				$errors[] = __('An alumni with that phone number already exists.', 'alumnus');
			}
		}

		if (!empty($errors)) {
			foreach ($errors as $e) {
				add_settings_error('alumnus', 'alumni_error_' . md5($e), $e, 'error');
			}
			return;
		}

		// Auto-generate a secure random password and hash it (no plaintext stored)
		$generated_password = function_exists('wp_generate_password') ? wp_generate_password(12, true, true) : bin2hex(random_bytes(8));
		if (function_exists('wp_hash_password')) {
			$password_hash = wp_hash_password($generated_password);
		} else {
			$password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
		}

		$inserted = $wpdb->insert(
			$tables['alumni'],
			[
				'id'            => $alumni_id,
				'course_id'     => $course_id,
				'first_name'    => $first_name,
				'last_name'     => $last_name,
				'batch_year'    => $batch_year,
				'email'         => $email ?: null,
				'phone'         => $phone ?: null,
				'password_hash' => $password_hash,
			],
			[ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		if ($inserted === false) {
			add_settings_error('alumnus', 'alumni_insert_fail', sprintf(__('Failed to add alumni. DB error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
		} else {
			// Show one-time password to admin so it can be communicated to the alumni securely
			add_settings_error(
				'alumnus',
				'alumni_insert_ok',
				sprintf(
					/* translators: %s is the generated password */
					__('Alumni added successfully. One-time password: %s (copy now; it will not be shown again).', 'alumnus'),
					'<code>' . esc_html($generated_password) . '</code>'
				),
				'updated'
			);
		}
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
	$courses = $wpdb->get_results("SELECT id, course_name FROM {$tables['courses']} ORDER BY course_name ASC");

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
	echo '    <th scope="row"><label for="alumni_id">' . esc_html__('School ID', 'alumnus') . '</label></th>';
	echo '    <td><input name="alumni_id" id="alumni_id" type="text" class="regular-text" required /></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="course_id">' . esc_html__('Course', 'alumnus') . '</label></th>';
	echo '    <td>';
	if (!empty($courses)) {
		echo '<select name="course_id" id="course_id" required>';
		echo '<option value="">' . esc_html__('Select a course', 'alumnus') . '</option>';
		foreach ($courses as $course) {
			echo '<option value="' . esc_attr((string)$course->id) . '">' . esc_html($course->course_name) . '</option>';
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
	echo '    <th scope="row"><label for="batch_year">' . esc_html__('Batch Year', 'alumnus') . '</label></th>';
	echo '    <td><input name="batch_year" id="batch_year" type="number" min="1900" max="' . esc_attr(date('Y')) . '" class="small-text" required /> <span class="description">' . esc_html__('e.g., 2024', 'alumnus') . '</span></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="email">' . esc_html__('Email', 'alumnus') . '</label></th>';
	echo '    <td><input name="email" id="email" type="email" class="regular-text" /></td>';
	echo '  </tr>';

	echo '  <tr valign="top">';
	echo '    <th scope="row"><label for="phone">' . esc_html__('Phone', 'alumnus') . '</label></th>';
	echo '    <td><input name="phone" id="phone" type="text" class="regular-text" /></td>';
	echo '  </tr>';

	// Password field removed; password will be auto-generated server-side and shown once after save

	echo '</table>';
	if (!empty($courses)) {
		submit_button(__('Add Alumni', 'alumnus'));
	}
	echo '</form>';

	echo '</div>';
}

?>
