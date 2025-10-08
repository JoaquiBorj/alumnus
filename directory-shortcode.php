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
		'1.0.0'
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
				<svg class="adh-icon adh-icon-1" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="35" fill="white" opacity="0.9"/>
					<circle cx="50" cy="45" r="15" fill="#04324d"/>
					<path d="M 20 80 Q 50 65 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-2" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="35" fill="white" opacity="0.9"/>
					<circle cx="50" cy="45" r="15" fill="#04324d"/>
					<path d="M 20 80 Q 50 65 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-3" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="35" fill="white" opacity="0.9"/>
					<circle cx="50" cy="45" r="15" fill="#04324d"/>
					<path d="M 20 80 Q 50 65 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-4" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="35" fill="white" opacity="0.9"/>
					<circle cx="50" cy="45" r="15" fill="#04324d"/>
					<path d="M 20 80 Q 50 65 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<!-- Connection lines between icons -->
				<svg class="adh-network-lines" viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg">
					<line x1="100" y1="250" x2="200" y2="150" stroke="white" stroke-width="3" opacity="0.6"/>
					<line x1="200" y1="150" x2="300" y2="200" stroke="white" stroke-width="3" opacity="0.6"/>
					<line x1="100" y1="250" x2="150" y2="350" stroke="white" stroke-width="3" opacity="0.6"/>
				</svg>
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
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_directory', 'alumnus_render_directory_shortcode' );

