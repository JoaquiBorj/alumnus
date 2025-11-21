<?php
/*
Plugin Name: Alumnus Alumni Manager
Description: Admin page to add Courses and Alumni records with list view, edit/delete, and bulk JSON import.
Version: 2.0.0
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
 * Generate a random 6-digit password
 */
function alumnus_generate_password() {
	return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Build a base username from first and last names using rules:
 * - Take only the first word of the first name
 * - Combine all words of the last name (remove spaces)
 * - Sanitize to alphanumeric and lowercase
 */
function alumnus_build_base_username($first_name, $last_name) {
	$first = trim((string)$first_name);
	$last  = trim((string)$last_name);
	$first_token = preg_split('/\s+/', $first);
	$first_token = isset($first_token[0]) ? $first_token[0] : '';
	$last_combined = preg_replace('/\s+/', '', $last);
	$base = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $first_token . $last_combined));
	if ($base === '') {
		$base = strtolower(wp_generate_password(6, false));
	}
	return $base;
}

/**
 * Generate a unique username for the user table. Appends a numeric suffix starting at 2 if needed.
 */
function alumnus_generate_unique_username($first_name, $last_name, $user_table) {
	global $wpdb;
	$base = alumnus_build_base_username($first_name, $last_name);
	$candidate = $base;
	$suffix = 2;
	while (true) {
		$exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$user_table} WHERE username = %s LIMIT 1", $candidate));
		if (!$exists) break;
		$candidate = $base . $suffix;
		$suffix++;
		if ($suffix > 1000) { // safety guard
			$candidate = $base . '-' . uniqid();
			break;
		}
	}
	return $candidate;
}

/**
 * Check if `username` column exists in the given user table
 */
function alumnus_user_table_has_username($user_table) {
    global $wpdb;
    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$user_table} LIKE %s", 'username'));
    return !empty($col);
}

/**
 * Check if `existed` column exists in the given user table
 */
function alumnus_user_table_has_existed($user_table) {
    global $wpdb;
    $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$user_table} LIKE %s", 'existed'));
    return !empty($col);
}

/**
 * Generate the next unique alumni user_id for a given year using the format: {year}{count3}
 * - Count is per-year and zero-padded to 3 digits (e.g., 2024 + 1 => 2024001)
 * - Uses MAX suffix among IDs starting with the year to avoid reuse when deletions exist
 */
function alumnus_generate_yearly_user_id($year, $alumni_table) {
	global $wpdb;
	$year = (int) $year;
	// Try to find the max numeric suffix for the given year among IDs that start with the year
	$max_suffix = $wpdb->get_var($wpdb->prepare(
		"SELECT MAX(CAST(SUBSTRING(user_id, 5) AS UNSIGNED))
		 FROM {$alumni_table}
		 WHERE `year` = %d AND user_id LIKE %s",
		$year,
		$year . '%'
	));
	if ($max_suffix === null) {
		// Fallback: count existing rows for the year, then add 1
		$count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$alumni_table} WHERE `year` = %d", $year));
		$next = $count + 1;
	} else {
		$next = ((int) $max_suffix) + 1;
	}
	// Build candidate ID
	$candidate = sprintf('%d%03d', $year, max(1, (int)$next));
	return $candidate;
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
 * Enqueue admin styles and scripts
 */
function alumnus_admin_scripts($hook) {
	if ($hook !== 'toplevel_page_alumnus-add-alumni') return;
	
	wp_add_inline_style('wp-admin', '
		.alumnus-table { margin-top: 20px; }
		.alumnus-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
		.alumnus-modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 5px; }
		.alumnus-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
		.alumnus-close:hover { color: #000; }
		.alumnus-tabs { border-bottom: 1px solid #ccc; margin-bottom: 20px; }
		.alumnus-tab { display: inline-block; padding: 10px 20px; cursor: pointer; border: 1px solid transparent; margin-bottom: -1px; }
		.alumnus-tab.active { border: 1px solid #ccc; border-bottom-color: white; background: white; }
		.alumnus-tab-content { display: none; }
		.alumnus-tab-content.active { display: block; }
		.alumnus-password-display { background: #ffffcc; padding: 5px 10px; border-radius: 3px; font-family: monospace; font-weight: bold; }
		.alumnus-json-textarea { width: 100%; min-height: 200px; font-family: monospace; }
	');
	
	wp_add_inline_script('jquery', '
		jQuery(document).ready(function($) {
			$(".alumnus-tab").click(function() {
				var tab = $(this).data("tab");
				$(".alumnus-tab").removeClass("active");
				$(".alumnus-tab-content").removeClass("active");
				$(this).addClass("active");
				$("#tab-" + tab).addClass("active");
			});
			
			$(".edit-course-btn").click(function() {
				var id = $(this).data("id");
				var name = $(this).data("name");
				$("#edit_course_id").val(id);
				$("#edit_course_name").val(name);
				$("#editCourseModal").show();
			});
			
			$(".delete-course-btn").click(function() {
				var id = $(this).data("id");
				if (confirm("Are you sure you want to delete this course?")) {
					$("#delete_course_id").val(id);
					$("#deleteCourseForm").submit();
				}
			});
			
			$(".edit-alumni-btn").click(function() {
				var id = $(this).data("id");
				var courseId = $(this).data("course");
				var firstName = $(this).data("firstname");
				var lastName = $(this).data("lastname");
				var year = $(this).data("year");
				
				$("#edit_alumni_id").val(id);
				$("#edit_alumni_course_id").val(courseId);
				$("#edit_alumni_first_name").val(firstName);
				$("#edit_alumni_last_name").val(lastName);
				$("#edit_alumni_batch_year").val(year);
				$("#editAlumniModal").show();
			});
			
			$(".delete-alumni-btn").click(function() {
				var id = $(this).data("id");
				if (confirm("Are you sure you want to delete this alumni record?")) {
					$("#delete_alumni_id").val(id);
					$("#deleteAlumniForm").submit();
				}
			});
			
			$(".alumnus-close").click(function() {
				$(".alumnus-modal").hide();
			});
			
			$(window).click(function(event) {
				if ($(event.target).hasClass("alumnus-modal")) {
					$(".alumnus-modal").hide();
				}
			});
		});
	');
}
add_action('admin_enqueue_scripts', 'alumnus_admin_scripts');

/**
 * Handle form submissions
 */
function alumnus_handle_post() {
	if (!is_admin() || !current_user_can('manage_options') || !isset($_POST['alumnus_action'])) return;
	
	$tables = alumnus_get_table_names();
	global $wpdb;
	
	// Add Course
	if ($_POST['alumnus_action'] === 'add_course') {
		check_admin_referer('alumnus_add_course');
		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$course_name = isset($_POST['course_name']) ? sanitize_text_field(wp_unslash($_POST['course_name'])) : '';
		
		if ($course_id <= 0 || $course_name === '') {
			add_settings_error('alumnus', 'course_empty', __('Course ID and name are required.', 'alumnus'), 'error');
			return;
		}
		
		$exists_id = $wpdb->get_var($wpdb->prepare("SELECT course_id FROM {$tables['courses']} WHERE course_id = %d LIMIT 1", $course_id));
		if ($exists_id) {
			add_settings_error('alumnus', 'course_id_exists', __('Course ID already exists.', 'alumnus'), 'error');
			return;
		}
		
		$inserted = $wpdb->insert($tables['courses'], ['course_id' => $course_id, 'course' => $course_name], ['%d', '%s']);
		if ($inserted === false) {
			add_settings_error('alumnus', 'course_insert_fail', sprintf(__('Failed to add course. DB error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
		} else {
			add_settings_error('alumnus', 'course_insert_ok', __('Course added successfully.', 'alumnus'), 'updated');
		}
	}
	
	// Edit Course
	if ($_POST['alumnus_action'] === 'edit_course') {
		check_admin_referer('alumnus_edit_course');
		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$course_name = isset($_POST['course_name']) ? sanitize_text_field(wp_unslash($_POST['course_name'])) : '';
		
		if ($course_name === '') {
			add_settings_error('alumnus', 'course_empty', __('Course name is required.', 'alumnus'), 'error');
			return;
		}
		
		$updated = $wpdb->update($tables['courses'], ['course' => $course_name], ['course_id' => $course_id], ['%s'], ['%d']);
		if ($updated === false) {
			add_settings_error('alumnus', 'course_update_fail', __('Failed to update course.', 'alumnus'), 'error');
		} else {
			add_settings_error('alumnus', 'course_update_ok', __('Course updated successfully.', 'alumnus'), 'updated');
		}
	}
	
	// Delete Course
	if ($_POST['alumnus_action'] === 'delete_course') {
		check_admin_referer('alumnus_delete_course');
		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$deleted = $wpdb->delete($tables['courses'], ['course_id' => $course_id], ['%d']);
		add_settings_error('alumnus', 'course_delete_ok', __('Course deleted successfully.', 'alumnus'), 'updated');
	}
	
	// Add Alumni
	if ($_POST['alumnus_action'] === 'add_alumni') {
        check_admin_referer('alumnus_add_alumni');
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : 0;

        $errors = [];
        if ($course_id <= 0) $errors[] = __('Course is required.', 'alumnus');
        if ($first_name === '' || $last_name === '') $errors[] = __('First and last name are required.', 'alumnus');
        if ($batch_year < 1900 || $batch_year > date('Y')) $errors[] = __('Invalid batch year.', 'alumnus');

        if (!empty($errors)) {
            foreach ($errors as $e) add_settings_error('alumnus', 'alumni_error', $e, 'error');
            return;
        }

        // Generate alumni_id using yearly generator (avoids collisions after deletions)
        $alumni_id = alumnus_generate_yearly_user_id($batch_year, $tables['alumni']);

        // Build alumni row using existing alumni table columns
        $alumni_columns = $wpdb->get_results("SHOW COLUMNS FROM {$tables['alumni']}");
        $alumni_column_names = array_column($alumni_columns, 'Field');
        $alumni_full = [
            'user_id' => $alumni_id,
            'year' => $batch_year,
            'course_id' => $course_id,
            'firstname' => $first_name,
            'lastname' => $last_name,
            'email' => '',
            'contact_info' => 0,
            'bio_note' => ''
        ];
        $alumni_data = array_filter($alumni_full, function($key) use ($alumni_column_names) {
            return in_array($key, $alumni_column_names, true);
        }, ARRAY_FILTER_USE_KEY);
        $alumni_formats = [];
        foreach (array_keys($alumni_data) as $key) {
            $alumni_formats[] = in_array($key, ['year','course_id','contact_info'], true) ? '%d' : '%s';
        }

        $insert_alumni = $wpdb->insert($tables['alumni'], $alumni_data, $alumni_formats);
        if ($insert_alumni === false) {
            add_settings_error('alumnus', 'alumni_insert_fail', sprintf(__('Failed to add alumni. Error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
            return;
        }

        // Generate password and hash
        $plain_password = alumnus_generate_password();
        $password_hash = function_exists('wp_hash_password') ? wp_hash_password($plain_password) : password_hash($plain_password, PASSWORD_DEFAULT);

        // Prepare user row
        $user_insert_data = [
            'user' => $alumni_id,
            'course_id' => $course_id,
            'year' => $batch_year,
            'password' => $password_hash,
        ];
        $user_insert_formats = ['%s','%d','%d','%s'];

        if (alumnus_user_table_has_username($tables['user'])) {
            $username = alumnus_generate_unique_username($first_name, $last_name, $tables['user']);
            $user_insert_data['username'] = $username;
            $user_insert_formats[] = '%s';
        }
        if (alumnus_user_table_has_existed($tables['user'])) {
            $user_insert_data['existed'] = 1;
            $user_insert_formats[] = '%d';
        }

        $insert_user = $wpdb->insert($tables['user'], $user_insert_data, $user_insert_formats);
        if ($insert_user === false) {
            // rollback alumni insert on failure to create user
            $wpdb->delete($tables['alumni'], ['user_id' => $alumni_id], ['%s']);
            add_settings_error('alumnus', 'user_insert_fail', sprintf(__('Failed to create user account. Error: %s', 'alumnus'), esc_html($wpdb->last_error)), 'error');
            return;
        }

        // store plain password temporarily for admin to view
        set_transient('alumnus_new_password_' . $alumni_id, $plain_password, HOUR_IN_SECONDS);

        add_settings_error('alumnus', 'alumni_insert_ok', __('Alumni and user account created successfully.', 'alumnus'), 'updated');
    }
	
	// Edit Alumni
	if ($_POST['alumnus_action'] === 'edit_alumni') {
		check_admin_referer('alumnus_edit_alumni');
		$alumni_id = isset($_POST['alumni_id']) ? intval($_POST['alumni_id']) : 0;
		$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : 0;
		
		$wpdb->update($tables['alumni'], [
			'year' => $batch_year, 'course_id' => $course_id, 'firstname' => $first_name, 'lastname' => $last_name
		], ['user_id' => $alumni_id], ['%d', '%d', '%s', '%s'], ['%s']);
		
		$wpdb->update($tables['user'], ['course_id' => $course_id, 'year' => $batch_year], ['user' => $alumni_id], ['%d', '%d'], ['%s']);
		add_settings_error('alumnus', 'alumni_update_ok', __('Alumni updated successfully.', 'alumnus'), 'updated');
	}
	
	// Delete Alumni
	if ($_POST['alumnus_action'] === 'delete_alumni') {
        check_admin_referer('alumnus_delete_alumni');
        $alumni_id = isset($_POST['alumni_id']) ? intval($_POST['alumni_id']) : 0;
        $wpdb->delete($tables['user'], ['user' => $alumni_id], ['%s']);
        $wpdb->delete($tables['alumni'], ['user_id' => $alumni_id], ['%s']);
        add_settings_error('alumnus', 'alumni_delete_ok', __('Alumni deleted successfully.', 'alumnus'), 'updated');
    }

    // Delete All Alumni & User Accounts (NEW)
    if ($_POST['alumnus_action'] === 'delete_all_alumni') {
        check_admin_referer('alumnus_delete_all_alumni');

        // Delete all rows from user and alumni tables.
        // Use DELETE instead of TRUNCATE for broader permission compatibility.
        $deleted_users  = $wpdb->query("DELETE FROM {$tables['user']}");
        $deleted_alumni = $wpdb->query("DELETE FROM {$tables['alumni']}");

        if ($deleted_users === false || $deleted_alumni === false) {
            add_settings_error('alumnus', 'delete_all_fail', __('Failed to delete all records. Check DB permissions.', 'alumnus'), 'error');
        } else {
            $deleted_users  = (int) $deleted_users;
            $deleted_alumni = (int) $deleted_alumni;
            add_settings_error('alumnus', 'delete_all_ok', sprintf(__('Deleted %d user rows and %d alumni rows.', 'alumnus'), $deleted_users, $deleted_alumni), 'updated');
        }
    }
    
    // Bulk Import CSV (uploaded file)
    if ($_POST['alumnus_action'] === 'bulk_import') {
        check_admin_referer('alumnus_bulk_import');
        $success_count = 0;
        $error_count = 0;
        $passwords = [];

        // require uploaded CSV file
        if ( empty($_FILES['csv_file']['tmp_name']) || ! is_uploaded_file($_FILES['csv_file']['tmp_name']) ) {
            add_settings_error('alumnus', 'csv_missing', __('Please upload a CSV file.', 'alumnus'), 'error');
            return;
        }

        $fp = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($fp === false) {
            add_settings_error('alumnus', 'csv_open_fail', __('Failed to open uploaded CSV file.', 'alumnus'), 'error');
            return;
        }

        $headers = fgetcsv($fp);
        if ($headers === false) {
            fclose($fp);
            add_settings_error('alumnus', 'csv_header', __('CSV header row is required.', 'alumnus'), 'error');
            return;
        }

        // normalize headers (remove BOM, lowercase, trim)
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        $headers = array_map(function($h){ return strtolower(trim($h)); }, $headers);

        // required columns for this CSV import
        $required = ['course_id','first_name','last_name','year'];
        foreach ($required as $r) {
            if (!in_array($r, $headers, true)) {
                fclose($fp);
                add_settings_error('alumnus', 'csv_missing_cols', sprintf(__('CSV must contain headers: %s', 'alumnus'), implode(', ', $required)), 'error');
                return;
            }
        }

        global $wpdb;
        $tables = alumnus_get_table_names();

        while (($row = fgetcsv($fp)) !== false) {
            // skip empty rows
            $all_empty = true;
            foreach ($row as $cell) { if (trim((string)$cell) !== '') { $all_empty = false; break; } }
            if ($all_empty) continue;

            // pad/truncate to headers length
            if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
            if (count($row) > count($headers)) $row = array_slice($row, 0, count($headers));

            $record = array_combine($headers, $row);
            if ($record === false) { $error_count++; continue; }

            $course_id  = isset($record['course_id']) ? intval($record['course_id']) : 0;
            $first_name = isset($record['first_name']) ? sanitize_text_field($record['first_name']) : '';
            $last_name  = isset($record['last_name']) ? sanitize_text_field($record['last_name']) : '';
            $batch_year = isset($record['year']) ? intval($record['year']) : 0;

            // basic validation
            if ($course_id <= 0 || $first_name === '' || $last_name === '' || $batch_year < 1900) {
                $error_count++;
                continue;
            }

            // generate alumni id for the year
            $alumni_id = alumnus_generate_yearly_user_id($batch_year, $tables['alumni']);

            // prepare plain password and hash
            $plain_password = alumnus_generate_password();
            $password_hash = function_exists('wp_hash_password') ? wp_hash_password($plain_password) : password_hash($plain_password, PASSWORD_DEFAULT);

            // build alumni row using existing alumni table columns
            $alumni_columns = $wpdb->get_results("SHOW COLUMNS FROM {$tables['alumni']}");
            $alumni_column_names = array_column($alumni_columns, 'Field');
            $alumni_data = [
                'user_id' => $alumni_id,
                'year' => $batch_year,
                'course_id' => $course_id,
                'firstname' => $first_name,
                'lastname' => $last_name,
                'email' => '',
                'contact_info' => 0,
                'bio_note' => ''
            ];
            $alumni_data = array_filter($alumni_data, function($key) use ($alumni_column_names) {
                return in_array($key, $alumni_column_names, true);
            }, ARRAY_FILTER_USE_KEY);
            $alumni_formats = [];
            foreach (array_keys($alumni_data) as $key) {
                $alumni_formats[] = in_array($key, ['year','course_id','contact_info'], true) ? '%d' : '%s';
            }

            $insert_alumni = $wpdb->insert($tables['alumni'], $alumni_data, $alumni_formats);

            // prepare user row: username = firstname+lastname (unique), password = hash, existed = 1 (if column present)
            $user_insert_data = [
                'user' => $alumni_id,
                'course_id' => $course_id,
                'year' => $batch_year,
                'password' => $password_hash,
            ];
            $user_insert_formats = ['%s','%d','%d','%s'];

            if (alumnus_user_table_has_username($tables['user'])) {
                $username = alumnus_generate_unique_username($first_name, $last_name, $tables['user']);
                $user_insert_data['username'] = $username;
                $user_insert_formats[] = '%s';
            }
            if (alumnus_user_table_has_existed($tables['user'])) {
                $user_insert_data['existed'] = 1;
                $user_insert_formats[] = '%d';
            }

            $insert_user = $wpdb->insert($tables['user'], $user_insert_data, $user_insert_formats);

            if ($insert_alumni && $insert_user) {
                $success_count++;
                $passwords[] = "ID {$alumni_id}: {$plain_password}";
                // keep plain password for short time for admin to view
                set_transient('alumnus_new_password_' . $alumni_id, $plain_password, HOUR_IN_SECONDS);
            } else {
                // cleanup partial inserts
                if ($insert_alumni && ! $insert_user) { $wpdb->delete($tables['alumni'], ['user_id' => $alumni_id], ['%s']); }
                if (! $insert_alumni && $insert_user) { $wpdb->delete($tables['user'], ['user' => $alumni_id], ['%s']); }
                $error_count++;
            }
        }

        fclose($fp);

        $message = sprintf(__('Import complete. Success: %d, Errors: %d', 'alumnus'), $success_count, $error_count);
        if (!empty($passwords)) {
            $message .= '<br><strong>Generated Passwords:</strong><br>' . implode('<br>', $passwords);
        }
        add_settings_error('alumnus', 'bulk_import_complete', $message, $error_count > 0 ? 'error' : 'updated');
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
	$courses = $wpdb->get_results("SELECT course_id, course FROM {$tables['courses']} ORDER BY course ASC");
	// Detect if username column exists to include it in list
	$has_username = alumnus_user_table_has_username($tables['user']);
	if ($has_username) {
		$alumni_list = $wpdb->get_results("
			SELECT a.user_id, a.firstname, a.lastname, a.year, a.course_id, c.course, u.username
			FROM {$tables['alumni']} a
			LEFT JOIN {$tables['courses']} c ON a.course_id = c.course_id
			LEFT JOIN {$tables['user']} u ON u.user = a.user_id
			ORDER BY a.user_id ASC
		");
	} else {
		$alumni_list = $wpdb->get_results("
			SELECT a.user_id, a.firstname, a.lastname, a.year, a.course_id, c.course 
			FROM {$tables['alumni']} a
			LEFT JOIN {$tables['courses']} c ON a.course_id = c.course_id
			ORDER BY a.user_id ASC
		");
	}
	
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Alumnus Manager', 'alumnus') . '</h1>';
	settings_errors('alumnus');
	
	// Tabs
	echo '<div class="alumnus-tabs">';
	echo '<span class="alumnus-tab active" data-tab="courses">Courses</span>';
	echo '<span class="alumnus-tab" data-tab="alumni">Alumni</span>';
	echo '<span class="alumnus-tab" data-tab="import">Bulk Import</span>';
	echo '</div>';
	
	// Courses Tab
	echo '<div id="tab-courses" class="alumnus-tab-content active">';
	echo '<h2>Add Course</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_add_course');
	echo '<input type="hidden" name="alumnus_action" value="add_course" />';
	echo '<table class="form-table"><tr><th><label for="course_id">Course ID</label></th>';
	echo '<td><input name="course_id" id="course_id" type="number" class="small-text" required /></td></tr>';
	echo '<tr><th><label for="course_name">Course Name</label></th>';
	echo '<td><input name="course_name" id="course_name" type="text" class="regular-text" required /></td></tr></table>';
	submit_button('Add Course');
	echo '</form>';
	
	echo '<h2>Courses List</h2>';
	if (!empty($courses)) {
		echo '<table class="wp-list-table widefat fixed striped alumnus-table">';
		echo '<thead><tr><th>ID</th><th>Course Name</th><th>Actions</th></tr></thead><tbody>';
		foreach ($courses as $course) {
			echo '<tr><td>' . esc_html($course->course_id) . '</td>';
			echo '<td>' . esc_html($course->course) . '</td>';
			echo '<td><button class="button edit-course-btn" data-id="' . esc_attr($course->course_id) . '" data-name="' . esc_attr($course->course) . '">Edit</button> ';
			echo '<button class="button delete-course-btn" data-id="' . esc_attr($course->course_id) . '">Delete</button></td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>No courses found.</p>';
	}
	echo '</div>';
	
	// Alumni Tab
	echo '<div id="tab-alumni" class="alumnus-tab-content">';
	echo '<h2>Add Alumni</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_add_alumni');
	echo '<input type="hidden" name="alumnus_action" value="add_alumni" />';
	echo '<table class="form-table">';
	// User ID is now auto-generated based on Year + per-year count
	echo '<tr><th>User ID</th><td><em>Will be generated after save (format: Year + sequential number, e.g., 2024001)</em></td></tr>';
	echo '<tr><th><label for="course_id_alumni">Course</label></th><td>';
	if (!empty($courses)) {
		echo '<select name="course_id" id="course_id_alumni" required><option value="">Select a course</option>';
		foreach ($courses as $course) {
			echo '<option value="' . esc_attr($course->course_id) . '">' . esc_html($course->course) . '</option>';
		}
		echo '</select>';
	} else {
		echo '<em>No courses yet. Add a course first.</em>';
	}
	echo '</td></tr>';
	echo '<tr><th><label for="first_name">First Name</label></th><td><input name="first_name" id="first_name" type="text" class="regular-text" required /></td></tr>';
	echo '<tr><th><label for="last_name">Last Name</label></th><td><input name="last_name" id="last_name" type="text" class="regular-text" required /></td></tr>';
	echo '<tr><th><label for="batch_year">Year</label></th><td><input name="batch_year" id="batch_year" type="number" min="1900" max="' . esc_attr(date('Y')) . '" class="small-text" required /></td></tr>';
	echo '</table>';
	if (!empty($courses)) submit_button('Add Alumni');
	echo '</form>';

    // Delete All form (dangerous) — requires explicit confirmation
    echo '<h3 style="color:#a00;">Danger Zone: Delete All Alumni & User Accounts</h3>';
    echo '<form method="post" onsubmit="return confirm(\'Are you sure? This will permanently delete ALL alumni records and their user accounts. This action cannot be undone.\');">';
    wp_nonce_field('alumnus_delete_all_alumni');
    echo '<input type="hidden" name="alumnus_action" value="delete_all_alumni" />';
    submit_button('Delete All Alumni & Users', 'delete');
    echo '</form>';

    echo '<h2>Alumni List</h2>';
	if (!empty($alumni_list)) {
		echo '<table class="wp-list-table widefat fixed striped alumnus-table">';
		echo '<thead><tr><th>User ID</th><th>Name</th>' . ($has_username ? '<th>Username</th>' : '') . '<th>Course</th><th>Year</th><th>Default Password</th><th>Actions</th></tr></thead><tbody>';
		foreach ($alumni_list as $alumni) {
			$password = get_transient('alumnus_new_password_' . $alumni->user_id);
			echo '<tr><td>' . esc_html($alumni->user_id) . '</td>';
			echo '<td>' . esc_html($alumni->firstname . ' ' . $alumni->lastname) . '</td>';
			if ($has_username) { echo '<td>' . esc_html($alumni->username) . '</td>'; }
			echo '<td>' . esc_html($alumni->course) . '</td>';
			echo '<td>' . esc_html($alumni->year) . '</td>';
			echo '<td>' . ($password ? '<span class="alumnus-password-display">' . esc_html($password) . '</span>' : '<em>Not available</em>') . '</td>';
			echo '<td><button class="button edit-alumni-btn" data-id="' . esc_attr($alumni->user_id) . '" data-course="' . esc_attr($alumni->course_id) . '" data-firstname="' . esc_attr($alumni->firstname) . '" data-lastname="' . esc_attr($alumni->lastname) . '" data-year="' . esc_attr($alumni->year) . '">Edit</button> ';
			echo '<button class="button delete-alumni-btn" data-id="' . esc_attr($alumni->user_id) . '">Delete</button></td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p>No alumni found.</p>';
	}
	echo '</div>';
	
	// Import Tab
	echo '<div id="tab-import" class="alumnus-tab-content">';
	
	echo '</form>';
	
	// Bulk Import CSV
	echo '<h2>Bulk Import CSV</h2>';
	echo '<p>Upload a CSV file. Required headers (case-insensitive): <code>course_id,first_name,last_name,year</code>.</p>';
	echo '<h3>Example CSV (first row = headers):</h3>';
	echo '<pre>course_id,first_name,last_name,year
1,John,Doe,2024
1,Jane,Smith,2024
2,Alan,Turing,2023</pre>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field('alumnus_bulk_import');
	echo '<input type="hidden" name="alumnus_action" value="bulk_import" />';
	echo '<input type="file" name="csv_file" accept=".csv,text/csv" required />';
	submit_button('Import CSV');
	echo '</form>';
	echo '</div>';
	
	echo '</div>'; // wrap
	
	// Edit Course Modal
	echo '<div id="editCourseModal" class="alumnus-modal">';
	echo '<div class="alumnus-modal-content">';
	echo '<span class="alumnus-close">&times;</span>';
	echo '<h2>Edit Course</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_edit_course');
	echo '<input type="hidden" name="alumnus_action" value="edit_course" />';
	echo '<input type="hidden" name="course_id" id="edit_course_id" />';
	echo '<table class="form-table"><tr><th><label for="edit_course_name">Course Name</label></th>';
	echo '<td><input name="course_name" id="edit_course_name" type="text" class="regular-text" required /></td></tr></table>';
	submit_button('Update Course');
	echo '</form></div></div>';
	
	// Delete Course Form
	echo '<form id="deleteCourseForm" method="post" style="display:none;">';
	wp_nonce_field('alumnus_delete_course');
	echo '<input type="hidden" name="alumnus_action" value="delete_course" />';
	echo '<input type="hidden" name="course_id" id="delete_course_id" />';
	echo '</form>';
	
	// Edit Alumni Modal
	echo '<div id="editAlumniModal" class="alumnus-modal">';
	echo '<div class="alumnus-modal-content">';
	echo '<span class="alumnus-close">&times;</span>';
	echo '<h2>Edit Alumni</h2>';
	echo '<form method="post">';
	wp_nonce_field('alumnus_edit_alumni');
	echo '<input type="hidden" name="alumnus_action" value="edit_alumni" />';
	echo '<input type="hidden" name="alumni_id" id="edit_alumni_id" />';
	echo '<table class="form-table">';
	echo '<tr><th><label for="edit_alumni_course_id">Course</label></th><td><select name="course_id" id="edit_alumni_course_id" required>';
	foreach ($courses as $course) {
		echo '<option value="' . esc_attr($course->course_id) . '">' . esc_html($course->course) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th><label for="edit_alumni_first_name">First Name</label></th><td><input name="first_name" id="edit_alumni_first_name" type="text" class="regular-text" required /></td></tr>';
	echo '<tr><th><label for="edit_alumni_last_name">Last Name</label></th><td><input name="last_name" id="edit_alumni_last_name" type="text" class="regular-text" required /></td></tr>';
	echo '<tr><th><label for="edit_alumni_batch_year">Year</label></th><td><input name="batch_year" id="edit_alumni_batch_year" type="number" min="1900" max="' . esc_attr(date('Y')) . '" class="small-text" required /></td></tr>';
	echo '</table>';
	submit_button('Update Alumni');
	echo '</form></div></div>';
	
	// Delete Alumni Form
	echo '<form id="deleteAlumniForm" method="post" style="display:none;">';
	wp_nonce_field('alumnus_delete_alumni');
	echo '<input type="hidden" name="alumnus_action" value="delete_alumni" />';
	echo '<input type="hidden" name="alumni_id" id="delete_alumni_id" />';
	echo '</form>';
}

?>
