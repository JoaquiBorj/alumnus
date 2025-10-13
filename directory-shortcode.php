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
		'1.0.2' // Updated to include filters
	);
	
	wp_enqueue_script(
		'alumnus-directory-filters',
		plugin_dir_url( __FILE__ ) . 'assets/js/directory-filters.js',
		array(),
		'1.0.0',
		true // Load in footer
	);
}

/**
 * Render the alumni directory markup with search interface
 *
 * @return string
 */
function alumnus_render_directory_shortcode() {
	// Enqueue the directory stylesheet
	alumnus_enqueue_directory_styles();
	
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
						<option value="">All Years</option>
						<option value="2024">2024</option>
						<option value="2023">2023</option>
						<option value="2022">2022</option>
						<option value="2021">2021</option>
						<option value="2020">2020</option>
						<option value="2019">2019</option>
						<option value="2018">2018</option>
						<option value="2017">2017</option>
						<option value="2016">2016</option>
						<option value="2015">2015</option>
						<option value="2014">2014</option>
						<option value="2013">2013</option>
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
						<option value="">All Courses</option>
						<option value="Chemical Engineering">Chemical Engineering</option>
						<option value="Civil Engineering">Civil Engineering</option>
						<option value="Electrical Engineering">Electrical Engineering</option>
						<option value="Electronics Engineering">Electronics Engineering</option>
						<option value="Industrial Engineering">Industrial Engineering</option>
						<option value="Mechanical Engineering">Mechanical Engineering</option>
					</select>
				</div>
			</div>
			
			<div class="alumnus-grid">
				<?php
				// TODO: Replace with actual database query to fetch alumni profiles
				// This should query a custom post type or user meta data
				// Sample alumni data for demonstration purposes
				$sample_alumni = array(
					array('name' => 'Sarah Johnson', 'year' => '2015', 'degree' => 'Electrical Engineering', 'position' => 'Senior Electrical Engineer', 'company' => 'Power Systems Inc', 'location' => 'San Francisco, CA'),
					array('name' => 'Michael Chen', 'year' => '2016', 'degree' => 'Mechanical Engineering', 'position' => 'Design Engineer', 'company' => 'Auto Innovations', 'location' => 'New York, NY'),
					array('name' => 'Emily Rodriguez', 'year' => '2014', 'degree' => 'Chemical Engineering', 'position' => 'Process Engineer', 'company' => 'ChemTech Solutions', 'location' => 'Los Angeles, CA'),
					array('name' => 'David Kim', 'year' => '2017', 'degree' => 'Mechanical Engineering', 'position' => 'Lead Engineer', 'company' => 'Aerospace Corp', 'location' => 'Seattle, WA'),
					array('name' => 'Jessica Martinez', 'year' => '2015', 'degree' => 'Industrial Engineering', 'position' => 'Operations Manager', 'company' => 'Manufacturing Co', 'location' => 'Chicago, IL'),
					array('name' => 'Robert Taylor', 'year' => '2013', 'degree' => 'Civil Engineering', 'position' => 'Project Engineer', 'company' => 'BuildRight Construction', 'location' => 'Boston, MA'),
					array('name' => 'Amanda Wilson', 'year' => '2016', 'degree' => 'Electronics Engineering', 'position' => 'Hardware Engineer', 'company' => 'Tech Devices', 'location' => 'Austin, TX'),
					array('name' => 'James Anderson', 'year' => '2018', 'degree' => 'Electrical Engineering', 'position' => 'Control Systems Engineer', 'company' => 'Smart Grid Tech', 'location' => 'Denver, CO'),
					array('name' => 'Sophia Lee', 'year' => '2014', 'degree' => 'Civil Engineering', 'position' => 'Structural Engineer', 'company' => 'Design Build', 'location' => 'Portland, OR'),
				);
				
				foreach ($sample_alumni as $alum) {
					// Generate initials for avatar
					$name_parts = explode(' ', $alum['name']);
					$initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
					?>
					<div class="alumni-card">
						<div class="ac-avatar">
							<span class="ac-initials"><?php echo esc_html($initials); ?></span>
						</div>
						<div class="ac-content">
							<h3 class="ac-name"><?php echo esc_html($alum['name']); ?></h3>
							<p class="ac-year">Class of <?php echo esc_html($alum['year']); ?></p>
							<p class="ac-degree"><?php echo esc_html($alum['degree']); ?></p>
							<div class="ac-divider"></div>
							<p class="ac-position"><?php echo esc_html($alum['position']); ?></p>
							<p class="ac-company"><?php echo esc_html($alum['company']); ?></p>
							<p class="ac-location">
								<svg class="ac-location-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="#64748b"/>
								</svg>
								<?php echo esc_html($alum['location']); ?>
							</p>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_directory', 'alumnus_render_directory_shortcode' );

