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
	// Cache-bust styles/scripts by using file modification time as the version
	$css_rel_path = 'assets/css/directory.css';
	$js_rel_path  = 'assets/js/directory-filters.js';
	$css_path     = plugin_dir_path( __FILE__ ) . $css_rel_path;
	$js_path      = plugin_dir_path( __FILE__ ) . $js_rel_path;
	$css_ver      = file_exists( $css_path ) ? filemtime( $css_path ) : '1.1.0';
	$js_ver       = file_exists( $js_path ) ? filemtime( $js_path ) : '1.1.0';

	wp_enqueue_style(
		'alumnus-directory',
		plugin_dir_url( __FILE__ ) . $css_rel_path,
		array(),
		$css_ver
	);

	wp_enqueue_script(
		'alumnus-directory-filters',
		plugin_dir_url( __FILE__ ) . $js_rel_path,
		array('jquery'),
		$js_ver,
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
	// Using actual tables from schema: course, alumni
	$courses = $wpdb->get_results( "SELECT course_id, course FROM course ORDER BY course ASC" );
	$years   = $wpdb->get_col( "SELECT DISTINCT `year` FROM alumni WHERE `year` IS NOT NULL ORDER BY `year` DESC" );

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
							<option value="<?php echo esc_attr((string) $course->course_id); ?>"><?php echo esc_html($course->course); ?></option>
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
	$full_name = trim(($row->firstname ?? '') . ' ' . ($row->lastname ?? ''));
	$initials = '';
	if (!empty($row->firstname)) { $initials .= strtoupper(substr($row->firstname, 0, 1)); }
	if (!empty($row->lastname)) { $initials .= strtoupper(substr($row->lastname, 0, 1)); }
	if ($initials === '' && $full_name !== '') { $initials = strtoupper(substr($full_name, 0, 1)); }

	ob_start();
	?>
	<div class="alumni-card">
		<div class="ac-avatar"><span class="ac-initials"><?php echo esc_html($initials); ?></span></div>
		<div class="ac-content">
			<h3 class="ac-name"><?php echo esc_html($full_name ?: ($row->user_id ?? '')); ?></h3>
			<?php if (!empty($row->year)) : ?>
				<p class="ac-year"><?php echo esc_html(sprintf(__('Class of %d', 'alumnus'), (int)$row->year)); ?></p>
			<?php endif; ?>
			<?php if (!empty($row->course_name)) : ?>
				<p class="ac-degree"><?php echo esc_html($row->course_name); ?></p>
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

	$year      = isset($_POST['year']) ? intval($_POST['year']) : 0;
	$course_id = isset($_POST['course_id']) && $_POST['course_id'] !== '' ? intval($_POST['course_id']) : 0;
	$search    = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

	$where = array();
	$params = array();

	if ($year > 0) {
		$where[] = "a.`year` = %d";
		$params[] = $year;
	}
	if ($course_id > 0) {
		$where[] = "a.course_id = %d";
		$params[] = $course_id;
	}

	$search_sql = '';
	if ($search !== '') {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$search_sql = "(a.firstname LIKE %s OR a.lastname LIKE %s OR a.user_id LIKE %s OR c.course LIKE %s OR a.email LIKE %s)";
		array_push($params, $like, $like, $like, $like, $like);
		$where[] = $search_sql;
	}

	$where_clause = '';
	if (!empty($where)) {
		$where_clause = 'WHERE ' . implode(' AND ', $where);
	}

	$sql = "SELECT a.user_id, a.firstname, a.lastname, a.`year`, a.email, a.contact_info, c.course AS course_name
			FROM alumni a
			LEFT JOIN course c ON a.course_id = c.course_id
			$where_clause
			ORDER BY a.`year` DESC, a.lastname ASC, a.firstname ASC";

	// Prepare if we have parameters; otherwise run raw
	$rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

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

