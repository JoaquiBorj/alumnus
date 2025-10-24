<?php
/**
 * Alumni Profile Shortcode
 * Usage: [alumni_profile] or [alumni_profile user_id="123"]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue profile styles and scripts
 */
function alumnus_enqueue_profile_styles() {
	$css_rel_path = 'assets/css/profile.css';
	$css_path     = plugin_dir_path( __FILE__ ) . $css_rel_path;
	$css_ver      = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';

	$js_rel_path  = 'assets/js/profile.js';
	$js_path      = plugin_dir_path( __FILE__ ) . $js_rel_path;
	$js_ver       = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

	// Ensure color-variables.css is loaded
	if ( ! wp_style_is( 'wordpress-plugin-template-colors', 'registered' ) ) {
		wp_register_style(
			'wordpress-plugin-template-colors',
			plugin_dir_url( __FILE__ ) . 'assets/css/color-variables.css',
			array(),
			$css_ver
		);
	}
	wp_enqueue_style( 'wordpress-plugin-template-colors' );

	wp_enqueue_style(
		'alumnus-profile',
		plugin_dir_url( __FILE__ ) . $css_rel_path,
		array( 'wordpress-plugin-template-colors' ),
		$css_ver
	);

	// Enqueue profile JavaScript
	wp_enqueue_script(
		'alumnus-profile',
		plugin_dir_url( __FILE__ ) . $js_rel_path,
		array(),
		$js_ver,
		true // Load in footer
	);

	// Localize script with translatable strings
	wp_localize_script(
		'alumnus-profile',
		'alumnusProfileStrings',
		array(
			'saving'             => __( 'Saving…', 'alumnus' ),
			'saveChanges'        => __( 'Save Changes', 'alumnus' ),
			'errorCareer'        => __( 'Failed to update career.', 'alumnus' ),
			'errorBio'           => __( 'Failed to update bio.', 'alumnus' ),
			'errorSkills'        => __( 'Failed to update skills.', 'alumnus' ),
			'networkErrorCareer' => __( 'Network error updating career.', 'alumnus' ),
			'networkErrorBio'    => __( 'Network error updating bio.', 'alumnus' ),
			'networkErrorSkills' => __( 'Network error updating skills.', 'alumnus' ),
		)
	);
}

/**
 * Render the alumni profile shortcode
 *
 * @param array $atts Shortcode attributes
 * @return string
 */
function alumnus_render_profile_shortcode($atts = array()) {
	// Enqueue CSS
	alumnus_enqueue_profile_styles();

	// Parse shortcode attributes
	$atts = shortcode_atts(array(
		'user_id' => '', // Can be set via shortcode attribute
	), $atts);

	// Check for URL parameter first (from directory links)
	if (isset($_GET['alumni_id']) && !empty($_GET['alumni_id'])) {
		$user_id = sanitize_text_field(wp_unslash($_GET['alumni_id']));
	} elseif (!empty($atts['user_id'])) {
		// Use shortcode attribute if provided
		$user_id = $atts['user_id'];
	} else {
		// Prefer our custom alumni session if available
		if (function_exists('alumnus_current_username')) {
			$session_user = alumnus_current_username();
			if ($session_user !== '') {
				$user_id = $session_user;
			}
		}
		// If still empty, fall back to WP user info
		if (empty($user_id)) {
			$current_user_obj = wp_get_current_user();
			if ($current_user_obj && $current_user_obj->exists() && !empty($current_user_obj->user_login)) {
				$user_id = (string) $current_user_obj->user_login;
			} else {
				$user_id = (string) get_current_user_id();
			}
		}
	}

	// If no user ID, show login message
	if (empty($user_id)) {
		return '<div class="alumnus-profile-error"><p>' . esc_html__('Please log in to view your profile.', 'alumnus') . '</p></div>';
	}

	global $wpdb;

	// Fetch alumni data from database
	$sql = "SELECT a.user_id, a.year, a.course_id, a.firstname, a.lastname, a.email, a.contact_info, a.career, a.bio_note, a.skills, c.course AS course_name 
			FROM alumni a
			LEFT JOIN course c ON a.course_id = c.course_id
			WHERE a.user_id = %s";
	
	$alumni_data = $wpdb->get_row($wpdb->prepare($sql, $user_id));

	if (!$alumni_data) {
		// Debug: Show what user_id we're looking for
		$debug_msg = sprintf(
			__('Profile not found for user ID: %s', 'alumnus'),
			esc_html($user_id)
		);
		
		// Check if there are any alumni in the database
		$total_alumni = $wpdb->get_var("SELECT COUNT(*) FROM alumni");
		
		if ($total_alumni == 0) {
			$debug_msg .= '<br><br>' . __('Note: There are no alumni records in the database yet.', 'alumnus');
		} else {
			$debug_msg .= '<br><br>' . sprintf(__('There are %d alumni in the database.', 'alumnus'), $total_alumni);
			// Show a sample of user_ids to help debug
			$sample_ids = $wpdb->get_col("SELECT user_id FROM alumni LIMIT 5");
			if (!empty($sample_ids)) {
				$debug_msg .= '<br>' . __('Sample user IDs: ', 'alumnus') . implode(', ', array_map('esc_html', $sample_ids));
			}
		}
		
		return '<div class="alumnus-profile-error"><p>' . $debug_msg . '</p></div>';
	}

	// Check if viewing own profile.
	// Prefer custom alumni session if present; otherwise fall back to native WP user.
	$is_own_profile = false;
	if ( function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() ) {
		$session_user = function_exists('alumnus_current_username') ? alumnus_current_username() : '';
		if ($session_user !== '') {
			$is_own_profile = ((string)$user_id === (string)$session_user);
		}
	} else {
		$current_user_id = get_current_user_id();
		$current_user_obj = wp_get_current_user();
		$current_user_login = ($current_user_obj && $current_user_obj->exists()) ? (string) $current_user_obj->user_login : '';
		$is_own_profile = is_user_logged_in() && (
			(string)$user_id === $current_user_login || (string)$user_id === (string)$current_user_id
		);
	}

	// Feature flag: control Recent Posts visibility (disabled by default; enable via filter)
	$alumnus_show_recent_posts = apply_filters('alumnus_profile_show_posts', false, $alumni_data, $is_own_profile);

	// Generate initials for avatar
	$initials = '';
	if (!empty($alumni_data->firstname)) {
		$initials .= strtoupper(substr($alumni_data->firstname, 0, 1));
	}
	if (!empty($alumni_data->lastname)) {
		$initials .= strtoupper(substr($alumni_data->lastname, 0, 1));
	}

	// Full name
	$full_name = trim(($alumni_data->firstname ?? '') . ' ' . ($alumni_data->lastname ?? ''));
	if (empty($full_name)) {
		$full_name = 'Alumni User';
	}

	// Fetch user posts (from community feed or custom posts table if exists)
	// For now, we'll show placeholder posts. You can integrate with your posts table later
	$posts = array(); // This can be populated from your database

	ob_start();
	?>
		<div class="alumnus-profile-wrapper" id="alumnus-profile-root" data-ajax-url="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce('alumnus_update_career') ); ?>" data-nonce-bio="<?php echo esc_attr( wp_create_nonce('alumnus_update_bio_note') ); ?>" data-nonce-skills="<?php echo esc_attr( wp_create_nonce('alumnus_update_skills') ); ?>" data-user-id="<?php echo esc_attr( (string) $user_id ); ?>">
		<div class="alumnus-profile-header">
			<div class="aph-gradient-bg"></div>
		<div class="aph-nav">
			<?php if ($is_own_profile): ?>
				<button id="alumnus-nav-edit" class="aph-nav-btn" type="button" onclick="alumnus_openModal()"><?php echo esc_html__('Edit', 'alumnus'); ?></button>
				<?php $logout_url = function_exists('alumnus_logout_url') ? alumnus_logout_url( home_url('/login-2') ) : wp_logout_url( home_url('/login-2') ); ?>
				<a class="aph-nav-btn" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html__('Logout', 'alumnus'); ?></a>
			<?php endif; ?>
			<a class="aph-nav-btn" href="<?php echo esc_url( apply_filters('alumnus_directory_page_url', home_url('/directory')) ); ?>"><?php echo esc_html__('Back to Directory', 'alumnus'); ?></a>
		</div>
		</div>


		<div class="alumnus-profile-container">
			<div class="alumnus-profile-card">
				<div class="apc-header">
					<div class="apc-avatar-wrapper">
						<div class="apc-avatar">
							<span class="apc-initials"><?php echo esc_html($initials); ?></span>
						</div>
					</div>
					
				<div class="apc-main-content">
					<div class="apc-left-column">
						<div class="apc-info">
							<h1 class="apc-name"><?php echo esc_html($full_name); ?></h1>
							<?php if (!empty($alumni_data->email)): ?>
								<p class="apc-email"><?php echo esc_html($alumni_data->email); ?></p>
							<?php endif; ?>
						</div>

						<!-- Career Section -->
						<div class="apc-career-section">
							<div class="apc-career-text" id="alumnus-career-view">
								<span class="apc-career-label">Current Career</span>
								<span class="apc-career-separator">-</span>
								<span class="apc-career-value">
									<?php if (!empty($alumni_data->career)): ?>
										<?php echo esc_html($alumni_data->career); ?>
									<?php else: ?>
										<span class="apc-placeholder"><?php echo esc_html__('Not specified', 'alumnus'); ?></span>
									<?php endif; ?>
								</span>
							</div>
						</div>

						<div class="apc-info">
							<p class="apc-subtitle">
								<?php 
								$subtitle_parts = array();
								if (!empty($alumni_data->year)) {
									$subtitle_parts[] = 'Batch ' . esc_html($alumni_data->year);
								}
								if (!empty($alumni_data->course_name)) {
									$subtitle_parts[] = esc_html($alumni_data->course_name);
								}
								echo implode(' | ', $subtitle_parts);
								?>
							</p>
							<?php if (!empty($alumni_data->contact_info)): ?>
								<p class="apc-contact"><?php echo esc_html($alumni_data->contact_info); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="apc-right-column">
						<!-- Bio Note Section -->
						<div class="apc-info-section apc-bio-section">
							<h2 class="apc-section-title">Bio</h2>
							<div class="apc-info-content" id="alumnus-bio-view">
								<?php if (!empty($alumni_data->bio_note)): ?>
									<?php echo wp_kses_post(nl2br($alumni_data->bio_note)); ?>
								<?php else: ?>
									<p class="apc-placeholder"><?php echo esc_html__('No bio provided yet.', 'alumnus'); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Skills Card (Separate) -->
		<div class="alumnus-skills-container">
			<div class="alumnus-skills-card">
				<h2 class="apc-section-title">Skills</h2>
				
				<div id="alumnus-skills-view">
					<?php if (!empty($alumni_data->skills)): ?>
						<div class="apc-skills-list">
							<?php 
							// Split skills by comma or newline
							$skills_array = preg_split('/[,\n]+/', $alumni_data->skills);
							foreach ($skills_array as $skill): 
								$skill = trim($skill);
								if (!empty($skill)):
							?>
								<span class="apc-skill-tag"><?php echo esc_html($skill); ?></span>
							<?php 
								endif;
							endforeach; 
							?>
						</div>
					<?php else: ?>
						<div class="apc-info-content">
							<p class="apc-placeholder"><?php echo esc_html__('No skills listed yet.', 'alumnus'); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( $alumnus_show_recent_posts ) : ?>
			<div class="alumnus-profile-container">
					<!-- Recent Posts Section -->
					<div class="apc-posts-section">
						<h2 class="apc-section-title">Recent Posts</h2>
						<?php if (empty($posts)): ?>
							<div class="apc-empty-state">
								<div class="apc-empty-icon">📝</div>
								<p class="apc-empty-text"><?php echo esc_html__('No posts yet.', 'alumnus'); ?></p>
								<?php if ($is_own_profile): ?>
									<p class="apc-empty-subtext"><?php echo esc_html__('Share your thoughts with the community!', 'alumnus'); ?></p>
								<?php endif; ?>
							</div>
						<?php else: ?>
							<?php foreach ($posts as $post): ?>
								<div class="apc-post">
									<div class="apc-post-header">
										<div class="apc-post-title"><?php echo esc_html($post['title']); ?></div>
										<div class="apc-post-time"><?php echo esc_html($post['time']); ?></div>
									</div>
									<?php if (!empty($post['content'])): ?>
										<div class="apc-post-content"><?php echo wp_kses_post($post['content']); ?></div>
									<?php endif; ?>
									<div class="apc-post-actions">
										<button class="apc-action-btn" onclick="alumnus_toggleLike(this)">
											<span class="apc-action-icon">👍</span>
											<span class="apc-action-text">Like</span>
										</button>
										<button class="apc-action-btn" onclick="alert('Comment feature coming soon')">
											<span class="apc-action-icon">💬</span>
											<span class="apc-action-text">Comment</span>
										</button>
										<button class="apc-action-btn" onclick="alert('Share feature coming soon')">
											<span class="apc-action-icon">🔗</span>
											<span class="apc-action-text">Share</span>
										</button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
			</div>
		<?php endif; ?>

		<?php if ($is_own_profile): ?>
			<!-- Edit Profile Modal -->
			<div id="alumnus-modal-overlay" class="alumnus-modal-overlay">
				<div class="alumnus-modal-card">
					<button id="alumnus-modal-close" class="alumnus-modal-close-btn" type="button">&times;</button>
					<h2 class="alumnus-modal-header"><?php echo esc_html__('Edit Profile', 'alumnus'); ?></h2>
					
					<div class="alumnus-modal-body">
						<div class="alumnus-modal-field">
							<label for="alumnus-modal-career-input" class="alumnus-modal-label"><?php echo esc_html__('Current Career', 'alumnus'); ?></label>
							<input type="text" id="alumnus-modal-career-input" name="career" class="alumnus-modal-input" placeholder="<?php echo esc_attr__('e.g., Research and Development Engineer at Company XYZ', 'alumnus'); ?>" value="<?php echo esc_attr( (string) $alumni_data->career ); ?>" />
						</div>

						<div class="alumnus-modal-field">
							<label for="alumnus-modal-bio-input" class="alumnus-modal-label"><?php echo esc_html__('Bio', 'alumnus'); ?></label>
							<textarea id="alumnus-modal-bio-input" name="bio_note" rows="8" maxlength="250" class="alumnus-modal-textarea" placeholder="<?php echo esc_attr__('Tell us about yourself…', 'alumnus'); ?>"><?php echo esc_textarea( (string) $alumni_data->bio_note ); ?></textarea>
							<div class="alumnus-char-counter">
								<span id="alumnus-bio-char-count"><?php echo esc_html( strlen( (string) $alumni_data->bio_note ) ); ?></span> / 250 <?php echo esc_html__('characters', 'alumnus'); ?>
							</div>
						</div>

						<div class="alumnus-modal-field">
							<label for="alumnus-modal-skills-input" class="alumnus-modal-label"><?php echo esc_html__('Skills (comma-separated)', 'alumnus'); ?></label>
							<textarea id="alumnus-modal-skills-input" name="skills" rows="3" class="alumnus-modal-textarea" placeholder="<?php echo esc_attr__('e.g., Project Management, Problem Solving, Data Analysis', 'alumnus'); ?>"><?php echo esc_textarea( (string) $alumni_data->skills ); ?></textarea>
						</div>
					</div>

					<div class="alumnus-modal-footer">
						<button type="button" class="aph-nav-btn alumnus-modal-btn-save" id="alumnus-modal-save"><?php echo esc_html__('Save Changes', 'alumnus'); ?></button>
						<button type="button" class="aph-nav-btn alumnus-modal-btn-cancel" id="alumnus-modal-cancel"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_profile', 'alumnus_render_profile_shortcode' );

/**
 * AJAX handler to update current career of the logged-in alumni user.
 * Accepts POST: user_id, career, _ajax_nonce
 */
function alumnus_update_career_ajax() {
	// Nonce check
	if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce( (string) $_POST['_ajax_nonce'], 'alumnus_update_career' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alumnus' ) ), 403 );
	}

	// Must have our custom alumni session and match user_id
	if ( ! function_exists('alumnus_is_logged_in') || ! alumnus_is_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'alumnus' ) ), 401 );
	}

	$session_user = function_exists('alumnus_current_username') ? alumnus_current_username() : '';
	$user_id = isset($_POST['user_id']) ? sanitize_text_field( wp_unslash($_POST['user_id']) ) : '';
	if ( $user_id === '' || (string) $user_id !== (string) $session_user ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied for this user.', 'alumnus' ) ), 403 );
	}

	// Sanitize and normalize input
	$career_raw = isset($_POST['career']) ? (string) wp_unslash($_POST['career']) : '';
	// Allow basic HTML similar to wp_kses_post; store cleaned HTML
	$career_clean = wp_kses_post( $career_raw );

	global $wpdb;
	$updated = $wpdb->update(
		'alumni',
		array( 'career' => $career_clean ),
		array( 'user_id' => $user_id ),
		array( '%s' ),
		array( '%s' )
	);

	if ( $updated === false ) {
		wp_send_json_error( array( 'message' => sprintf( __( 'Database error: %s', 'alumnus' ), $wpdb->last_error ) ), 500 );
	}

	// Prepare HTML for the view block
	if ( $career_clean !== '' ) {
		$html = '<span class="apc-career-label">Current Career</span><span class="apc-career-separator">-</span><span class="apc-career-value">' . esc_html( $career_clean ) . '</span>';
	} else {
		$html = '<span class="apc-career-label">Current Career</span><span class="apc-career-separator">-</span><span class="apc-career-value"><span class="apc-placeholder">' . esc_html__( 'Not specified', 'alumnus' ) . '</span></span>';
	}

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_alumnus_update_career', 'alumnus_update_career_ajax' );
add_action( 'wp_ajax_nopriv_alumnus_update_career', 'alumnus_update_career_ajax' );

/**
 * AJAX handler to update skills (CSV string) of the logged-in alumni user.
 * Accepts POST: user_id, skills, _ajax_nonce
 */
function alumnus_update_skills_ajax() {
	// Nonce check
	if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce( (string) $_POST['_ajax_nonce'], 'alumnus_update_skills' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alumnus' ) ), 403 );
	}

	// Must have our custom alumni session and match user_id
	if ( ! function_exists('alumnus_is_logged_in') || ! alumnus_is_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'alumnus' ) ), 401 );
	}

	$session_user = function_exists('alumnus_current_username') ? alumnus_current_username() : '';
	$user_id = isset($_POST['user_id']) ? sanitize_text_field( wp_unslash($_POST['user_id']) ) : '';
	if ( $user_id === '' || (string) $user_id !== (string) $session_user ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied for this user.', 'alumnus' ) ), 403 );
	}

	$skills_raw = isset($_POST['skills']) ? (string) wp_unslash($_POST['skills']) : '';
	// Parse into array by commas/newlines, trim, dedupe, limit size and length
	$parts = preg_split('/[,\n]+/', $skills_raw);
	$clean = array();
	if ( is_array($parts) ) {
		foreach ($parts as $p) {
			$p = trim( wp_strip_all_tags( $p ) ); // no HTML in skills
			if ($p === '') continue;
			// Limit individual skill length
			if ( strlen($p) > 64 ) { $p = substr($p, 0, 64); }
			$clean[] = $p;
		}
		// Dedupe and cap total skills
		$clean = array_values( array_unique( $clean ) );
		if ( count($clean) > 50 ) {
			$clean = array_slice($clean, 0, 50);
		}
	}
	$csv = implode(', ', $clean);

	global $wpdb;
	$updated = $wpdb->update(
		'alumni',
		array( 'skills' => $csv ),
		array( 'user_id' => $user_id ),
		array( '%s' ),
		array( '%s' )
	);

	if ( $updated === false ) {
		wp_send_json_error( array( 'message' => sprintf( __( 'Database error: %s', 'alumnus' ), $wpdb->last_error ) ), 500 );
	}

	// Build refreshed HTML for the skills view
	if ( ! empty($clean) ) {
		$html = '<div class="apc-skills-list">';
		foreach ($clean as $s) {
			$html .= '<span class="apc-skill-tag">' . esc_html( $s ) . '</span>';
		}
		$html .= '</div>';
	} else {
		$html = '<div class="apc-info-content"><p class="apc-placeholder">' . esc_html__( 'No skills listed yet.', 'alumnus' ) . '</p></div>';
	}

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_alumnus_update_skills', 'alumnus_update_skills_ajax' );
add_action( 'wp_ajax_nopriv_alumnus_update_skills', 'alumnus_update_skills_ajax' );

/**
 * AJAX handler to update bio_note of the logged-in alumni user.
 * Accepts POST: user_id, bio_note, _ajax_nonce
 */
function alumnus_update_bio_note_ajax() {
	// Nonce check
	if ( ! isset($_POST['_ajax_nonce']) || ! wp_verify_nonce( (string) $_POST['_ajax_nonce'], 'alumnus_update_bio_note' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alumnus' ) ), 403 );
	}

	// Must have our custom alumni session and match user_id
	if ( ! function_exists('alumnus_is_logged_in') || ! alumnus_is_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'alumnus' ) ), 401 );
	}

	$session_user = function_exists('alumnus_current_username') ? alumnus_current_username() : '';
	$user_id = isset($_POST['user_id']) ? sanitize_text_field( wp_unslash($_POST['user_id']) ) : '';
	if ( $user_id === '' || (string) $user_id !== (string) $session_user ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied for this user.', 'alumnus' ) ), 403 );
	}

	// Sanitize and normalize input
	$bio_raw = isset($_POST['bio_note']) ? (string) wp_unslash($_POST['bio_note']) : '';
	// Allow basic HTML similar to wp_kses_post; store cleaned HTML
	$bio_clean = wp_kses_post( $bio_raw );

	global $wpdb;
	$updated = $wpdb->update(
		'alumni',
		array( 'bio_note' => $bio_clean ),
		array( 'user_id' => $user_id ),
		array( '%s' ),
		array( '%s' )
	);

	if ( $updated === false ) {
		wp_send_json_error( array( 'message' => sprintf( __( 'Database error: %s', 'alumnus' ), $wpdb->last_error ) ), 500 );
	}

	// Prepare HTML for the view block
	$html = $bio_clean !== '' ? nl2br( $bio_clean ) : '<p class="apc-placeholder">' . esc_html__( 'No bio provided yet.', 'alumnus' ) . '</p>';

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_alumnus_update_bio_note', 'alumnus_update_bio_note_ajax' );
add_action( 'wp_ajax_nopriv_alumnus_update_bio_note', 'alumnus_update_bio_note_ajax' );

/**
 * Helper function to get profile URL for an alumni
 * 
 * @param string $user_id The alumni user ID
 * @param string $profile_page_url Optional. The URL of the page with [alumni_profile] shortcode. 
 *                                 If not provided, uses current page.
 * @return string The profile URL
 */
function alumnus_get_profile_url($user_id, $profile_page_url = '') {
	if (empty($profile_page_url)) {
		$profile_page_url = get_permalink();
	}
	return add_query_arg('alumni_id', urlencode($user_id), $profile_page_url);
}

