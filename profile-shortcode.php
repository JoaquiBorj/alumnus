<?php
/**
 * Alumni Profile Shortcode
 * Usage: [alumni_profile] or [alumni_profile user_id="123"]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue profile styles
 */
function alumnus_enqueue_profile_styles() {
	$css_rel_path = 'assets/css/profile.css';
	$css_path     = plugin_dir_path( __FILE__ ) . $css_rel_path;
	$css_ver      = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';

	wp_enqueue_style(
		'alumnus-profile',
		plugin_dir_url( __FILE__ ) . $css_rel_path,
		array(),
		$css_ver
	);

	// Enqueue jQuery if not already loaded
	wp_enqueue_script('jquery');
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

	// Determine the effective user whose profile will be displayed.
	// Security: Do NOT trust URL parameters to pick arbitrary users.
	$user_id        = '';
	$session_user   = '';
	$wp_login_user  = '';

	if ( function_exists('alumnus_current_username') && function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() ) {
		$session_user = alumnus_current_username();
	}

	$current_user_obj = wp_get_current_user();
	if ( $current_user_obj && $current_user_obj->exists() && !empty($current_user_obj->user_login) ) {
		$wp_login_user = (string) $current_user_obj->user_login;
	}

	// Default to the authenticated alumni session if present; otherwise WP user login.
	$user_id = $session_user !== '' ? $session_user : $wp_login_user;

	// Optional: allow admins (or via filter) to view others by specifying a target ID.
	$can_view_others = current_user_can('manage_options');
	$can_view_others = (bool) apply_filters('alumnus_can_view_other_profiles', $can_view_others);

	$requested_id = '';
	if ( isset($_GET['alumni_id']) && $_GET['alumni_id'] !== '' ) {
		$requested_id = sanitize_text_field( wp_unslash( $_GET['alumni_id'] ) );
	} elseif ( !empty($atts['user_id']) ) {
		$requested_id = sanitize_text_field( (string) $atts['user_id'] );
	}

	if ( $requested_id !== '' ) {
		if ( $can_view_others ) {
			$user_id = $requested_id; // permitted override for admins
		} else {
			// Non-admins can only see their own profile even if URL is tampered
			// Keep $user_id as derived from session/WP login
		}
	}

	// If no user ID, show login message
	if (empty($user_id)) {
		return '<div class="alumnus-profile-error"><p>' . esc_html__('Please log in to view your profile.', 'alumnus') . '</p></div>';
	}

	global $wpdb;

	// Fetch alumni data from database
	$sql = "SELECT a.user_id, a.year, a.course_id, a.firstname, a.lastname, a.email, a.contact_info, a.career, a.skills, c.course AS course_name 
			FROM alumni a
			LEFT JOIN course c ON a.course_id = c.course_id
			WHERE a.user_id = %s";
	
	$alumni_data = $wpdb->get_row($wpdb->prepare($sql, $user_id));

	if (!$alumni_data) {
		// Avoid leaking database details to non-admins
		if ( current_user_can('manage_options') ) {
			$debug_msg = sprintf(
				__('Profile not found for user ID: %s', 'alumnus'),
				esc_html($user_id)
			);
			return '<div class="alumnus-profile-error"><p>' . $debug_msg . '</p></div>';
		}
		return '<div class="alumnus-profile-error"><p>' . esc_html__('Profile not found or inaccessible.', 'alumnus') . '</p></div>';
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
	<div class="alumnus-profile-wrapper">
		<div class="alumnus-profile-header">
			<div class="aph-gradient-bg"></div>
		<div class="aph-nav">
			<?php if ($is_own_profile): ?>
				<button class="aph-nav-btn" type="button" onclick="document.dispatchEvent(new CustomEvent('alumnus:editProfile')); alert('Edit feature coming soon');"><?php echo esc_html__('Edit', 'alumnus'); ?></button>
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
						<div class="apc-info">
							<h1 class="apc-name"><?php echo esc_html($full_name); ?></h1>
							<?php if (!empty($alumni_data->email)): ?>
								<p class="apc-email"><?php echo esc_html($alumni_data->email); ?></p>
							<?php endif; ?>
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

						<div class="apc-sidebar">
							<!-- Career Section -->
							<div class="apc-info-section apc-sidebar-section">
								<h2 class="apc-section-title">Career</h2>
								<div class="apc-info-content">
									<?php if (!empty($alumni_data->career)): ?>
										<?php echo wp_kses_post(nl2br($alumni_data->career)); ?>
									<?php else: ?>
										<p class="apc-placeholder"><?php echo esc_html__('No career information provided yet.', 'alumnus'); ?></p>
									<?php endif; ?>
								</div>
							</div>

							<!-- Skills Section -->
							<div class="apc-info-section apc-sidebar-section">
								<h2 class="apc-section-title">Skills</h2>
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
				</div>

				<?php if ( $alumnus_show_recent_posts ) : ?>
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
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	function alumnus_toggleLike(btn) {
		if (btn.classList.contains('liked')) {
			btn.classList.remove('liked');
			btn.querySelector('.apc-action-text').textContent = 'Like';
		} else {
			btn.classList.add('liked');
			btn.querySelector('.apc-action-text').textContent = 'Liked';
		}
	}
	</script>
	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_profile', 'alumnus_render_profile_shortcode' );

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
	// For compatibility, keep alumni_id in URL but server-side will ignore it for non-admins.
	return add_query_arg('alumni_id', urlencode($user_id), $profile_page_url);
}

