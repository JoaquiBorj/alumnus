<?php
/**
 * Alumni Directory Shortcode
 * Usage: [alumni_directory]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue directory styles
 */
function alumnus_enqueue_directory_styles() {
	wp_enqueue_style(
		'alumnus-directory',
		plugin_dir_url( __FILE__ ) . 'assets/css/directory.css',
		array(),
		'1.0.1' // Updated to force cache refresh
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
			<div class="alumnus-grid">
				<?php
				// TODO: Replace with actual database query to fetch alumni profiles
				// This should query a custom post type or user meta data
				// Sample alumni data for demonstration purposes
				$sample_alumni = array(
					array('name' => 'Sarah Johnson', 'year' => '2015', 'degree' => 'Computer Science', 'position' => 'Senior Software Engineer', 'company' => 'Tech Corp', 'location' => 'San Francisco, CA'),
					array('name' => 'Michael Chen', 'year' => '2016', 'degree' => 'Business Administration', 'position' => 'Product Manager', 'company' => 'Innovate Inc', 'location' => 'New York, NY'),
					array('name' => 'Emily Rodriguez', 'year' => '2014', 'degree' => 'Graphic Design', 'position' => 'Creative Director', 'company' => 'Design Studio', 'location' => 'Los Angeles, CA'),
					array('name' => 'David Kim', 'year' => '2017', 'degree' => 'Mechanical Engineering', 'position' => 'Lead Engineer', 'company' => 'Aerospace Corp', 'location' => 'Seattle, WA'),
					array('name' => 'Jessica Martinez', 'year' => '2015', 'degree' => 'Marketing', 'position' => 'Marketing Director', 'company' => 'Brand Co', 'location' => 'Chicago, IL'),
					array('name' => 'Robert Taylor', 'year' => '2013', 'degree' => 'Finance', 'position' => 'Financial Analyst', 'company' => 'Investment Group', 'location' => 'Boston, MA'),
					array('name' => 'Amanda Wilson', 'year' => '2016', 'degree' => 'Psychology', 'position' => 'HR Manager', 'company' => 'People First', 'location' => 'Austin, TX'),
					array('name' => 'James Anderson', 'year' => '2018', 'degree' => 'Data Science', 'position' => 'Data Scientist', 'company' => 'Analytics Pro', 'location' => 'Denver, CO'),
					array('name' => 'Sophia Lee', 'year' => '2014', 'degree' => 'Architecture', 'position' => 'Senior Architect', 'company' => 'Design Build', 'location' => 'Portland, OR'),
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

