<?php
/**
 * Alumni Directory Shortcode
 * Usage: [alumni_directory]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue directory styles and scripts
 */
function alumnus_enqueue_directory_styles() {
	wp_enqueue_style(
		'alumnus-directory',
		plugin_dir_url( __FILE__ ) . 'assets/css/directory.css',
		array(),
		'1.1.0'
	);

	wp_enqueue_script(
		'alumnus-directory-filters',
		plugin_dir_url( __FILE__ ) . 'assets/js/directory-filters.js',
		array(),
		'1.1.0',
		true
	);

	// Localize AJAX settings
	wp_localize_script('alumnus-directory-filters', 'AlumnusDirectory', array(
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce'    => wp_create_nonce('alumnus_directory'),
		'i18n'     => array(
			'loading' => __('Loading alumni...', 'alumnus'),
			'noResults' => __('No alumni found matching your filters.', 'alumnus'),
		)
	));
}

/**
 * Render the alumni directory markup with search interface
 *
 * @return string
 */
function alumnus_render_directory_shortcode() {
	// Enqueue CSS/JS
	alumnus_enqueue_directory_styles();

	// Fetch filter data from DB
	global $wpdb;
	$tables = function_exists('alumnus_get_table_names') ? alumnus_get_table_names() : array('courses' => $wpdb->prefix.'courses', 'alumni' => $wpdb->prefix.'alumni');
	$courses = $wpdb->get_results( "SELECT id, course_name FROM {$tables['courses']} ORDER BY course_name ASC" );
	$years   = $wpdb->get_col( "SELECT DISTINCT batch_year FROM {$tables['alumni']} WHERE batch_year IS NOT NULL ORDER BY batch_year DESC" );

	ob_start();
	?>
	<div class="alumnus-directory-wrapper">
		<div class="alumnus-directory-hero">
			<div class="adh-icon-bg">
				<!-- Network connection icon overlay -->
				<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
				<svg class="adh-icon adh-icon-<?php echo $i; ?>" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
					<circle cx="50" cy="38" r="18" fill="#04324d"/>
					<path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<?php endfor; ?>
			</div>
			
			<div class="adh-content">
				<h1 class="adh-title">Directory</h1>
				<p class="adh-tagline">Stay connected! Find your peers.</p>
				
				<div class="alumnus-search-box">
					<div class="asb-inner">
						<svg class="asb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/>
							<path d="M20 20L16.65 16.65" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
						</svg>
						<input 
							type="text" 
							class="asb-input" 
							placeholder="Search Alum" 
							id="alumnus-directory-search"
						/>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Alumni Grid -->
		<div class="alumnus-grid-container">
			<!-- Filters -->
			<div class="alumnus-filters">
				<div class="af-filter-group">
					<label for="filter-year" class="af-label">
						<svg class="af-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="3" y="4" width="18" height="18" rx="2" stroke="#04324d" stroke-width="2" fill="none"/>
							<line x1="3" y1="9" x2="21" y2="9" stroke="#04324d" stroke-width="2"/>
							<line x1="8" y1="2" x2="8" y2="6" stroke="#04324d" stroke-width="2" stroke-linecap="round"/>
							<line x1="16" y1="2" x2="16" y2="6" stroke="#04324d" stroke-width="2" stroke-linecap="round"/>
						</svg>
						Year
					</label>
					<select id="filter-year" class="af-select">
						<option value=""><?php echo esc_html__('All Years', 'alumnus'); ?></option>
						<?php if (!empty($years)) : foreach ($years as $year) : ?>
							<option value="<?php echo esc_attr((string) $year); ?>"><?php echo esc_html((string) $year); ?></option>
						<?php endforeach; endif; ?>
					</select>
				</div>
				
				<div class="af-filter-group">
					<label for="filter-course" class="af-label">
						<svg class="af-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 14l9-5-9-5-9 5 9 5z" stroke="#04324d" stroke-width="2" stroke-linejoin="round" fill="none"/>
							<path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" stroke="#04324d" stroke-width="2" stroke-linejoin="round" fill="none"/>
						</svg>
						Course
					</label>
					<select id="filter-course" class="af-select">
						<option value=""><?php echo esc_html__('All Courses', 'alumnus'); ?></option>
						<?php if (!empty($courses)) : foreach ($courses as $course) : ?>
							<option value="<?php echo esc_attr((string) $course->id); ?>"><?php echo esc_html($course->course_name); ?></option>
						<?php endforeach; endif; ?>
					</select>
				</div>
			</div>
			
			<div class="alumnus-grid" id="alumnus-grid">
				<div class="no-results-message" data-initial="1">
					<p><?php echo esc_html__('Loading alumni...', 'alumnus'); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_directory', 'alumnus_render_directory_shortcode' );

/**
 * Helper: Render a single alumni card as HTML
 */
function alumnus_render_alumni_card($row) {
	$full_name = trim($row->first_name . ' ' . $row->last_name);
	$initials = '';
	if ($row->first_name) { $initials .= strtoupper(substr($row->first_name, 0, 1)); }
	if ($row->last_name) { $initials .= strtoupper(substr($row->last_name, 0, 1)); }
	if ($initials === '' && $full_name !== '') { $initials = strtoupper(substr($full_name, 0, 1)); }

	ob_start();
	?>
	<div class="alumni-card">
		<div class="ac-avatar"><span class="ac-initials"><?php echo esc_html($initials); ?></span></div>
		<div class="ac-content">
			<h3 class="ac-name"><?php echo esc_html($full_name ?: $row->id); ?></h3>
			<?php if (!empty($row->batch_year)) : ?>
				<p class="ac-year"><?php echo esc_html(sprintf(__('Class of %d', 'alumnus'), (int)$row->batch_year)); ?></p>
			<?php endif; ?>
			<?php if (!empty($row->course_name)) : ?>
				<p class="ac-degree"><?php echo esc_html($row->course_name); ?></p>
			<?php endif; ?>
			<div class="ac-divider"></div>
			<?php if (!empty($row->email)) : ?>
				<p class="ac-company"><?php echo esc_html($row->email); ?></p>
			<?php endif; ?>
			<?php if (!empty($row->phone)) : ?>
				<p class="ac-position"><?php echo esc_html($row->phone); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX: Fetch alumni with filters
 */
function alumnus_directory_fetch_alumni() {
	check_ajax_referer('alumnus_directory', 'nonce');

	global $wpdb;
	$tables = function_exists('alumnus_get_table_names') ? alumnus_get_table_names() : array('courses' => $wpdb->prefix.'courses', 'alumni' => $wpdb->prefix.'alumni');

	$year      = isset($_POST['year']) ? intval($_POST['year']) : 0;
	$course_id = isset($_POST['course_id']) && $_POST['course_id'] !== '' ? intval($_POST['course_id']) : 0;
	$search    = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

	$where = array();
	$params = array();

	if ($year > 0) {
		$where[] = "a.batch_year = %d";
		$params[] = $year;
	}
	if ($course_id > 0) {
		$where[] = "a.course_id = %d";
		$params[] = $course_id;
	}

	$search_sql = '';
	if ($search !== '') {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$search_sql = "(a.first_name LIKE %s OR a.last_name LIKE %s OR a.id LIKE %s OR c.course_name LIKE %s)";
		array_push($params, $like, $like, $like, $like);
		$where[] = $search_sql;
	}

	$where_clause = '';
	if (!empty($where)) {
		$where_clause = 'WHERE ' . implode(' AND ', $where);
	}

	$sql = "SELECT a.id, a.first_name, a.last_name, a.batch_year, a.email, a.phone, c.course_name
			FROM {$tables['alumni']} a
			LEFT JOIN {$tables['courses']} c ON a.course_id = c.id
			$where_clause
			ORDER BY a.batch_year DESC, a.last_name ASC, a.first_name ASC";

	if (!empty($params)) {
		// Build prepared statement with correct placeholders
		$types = array();
		foreach ($where as $clause) {
			if (strpos($clause, '%d') !== false) { $types[] = '%d'; }
			if (strpos($clause, 'LIKE %s') !== false) { $types[] = '%s'; $types[] = '%s'; $types[] = '%s'; $types[] = '%s'; break; }
		}
		// Prepare using $wpdb->prepare with flat params
		$query = $wpdb->prepare($sql, $params);
		$rows = $wpdb->get_results($query);
	} else {
		$rows = $wpdb->get_results($sql);
	}

	if (empty($rows)) {
		wp_send_json_success('<div class="no-results-message"><p>'. esc_html__('No alumni found matching your filters.', 'alumnus') .'</p></div>');
	}

	$html = '';
	foreach ($rows as $row) {
		$html .= alumnus_render_alumni_card($row);
	}

	wp_send_json_success($html);
}
add_action('wp_ajax_alumnus_fetch_alumni', 'alumnus_directory_fetch_alumni');
add_action('wp_ajax_nopriv_alumnus_fetch_alumni', 'alumnus_directory_fetch_alumni');

