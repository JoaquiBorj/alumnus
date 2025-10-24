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
				<button id="alumnus-nav-edit" class="aph-nav-btn" type="button" onclick="alumnus_toggleEdit(true)"><?php echo esc_html__('Edit', 'alumnus'); ?></button>
				<button id="alumnus-nav-save" class="aph-nav-btn" type="button" style="display:none;" onclick="alumnus_submitCareer(this)"><?php echo esc_html__('Save', 'alumnus'); ?></button>
				<button id="alumnus-nav-cancel" class="aph-nav-btn" type="button" style="display:none;" onclick="alumnus_toggleEdit(false)"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
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

							<?php if ($is_own_profile): ?>
								<form id="alumnus-career-form" class="apc-edit-form" style="display:none;">
									<label for="alumnus-career-input" class="apc-career-label"><?php echo esc_html__('Current Career', 'alumnus'); ?></label>
									<input type="text" id="alumnus-career-input" name="career" class="apc-career-input" placeholder="<?php echo esc_attr__('e.g., Research and Development Engineer at Company XYZ', 'alumnus'); ?>" value="<?php echo esc_attr( (string) $alumni_data->career ); ?>" />
								</form>
							<?php endif; ?>
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

							<?php if ($is_own_profile): ?>
								<form id="alumnus-bio-form" class="apc-edit-form" style="display:none;">
									<label for="alumnus-bio-input" class="screen-reader-text"><?php echo esc_html__('Bio', 'alumnus'); ?></label>
									<textarea id="alumnus-bio-input" name="bio_note" rows="8" class="apc-textarea" placeholder="<?php echo esc_attr__('Tell us about yourself…', 'alumnus'); ?>"><?php echo esc_textarea( (string) $alumni_data->bio_note ); ?></textarea>
									<div class="apc-edit-actions">
										<button type="button" class="aph-nav-btn" onclick="alumnus_submitBio(this)"><?php echo esc_html__('Save', 'alumnus'); ?></button>
										<button type="button" class="aph-nav-btn" onclick="alumnus_toggleBioEdit(false)"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
									</div>
								</form>
							<?php endif; ?>
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

				<?php if ($is_own_profile): ?>
					<form id="alumnus-skills-form" class="apc-edit-form" style="display:none;">
						<label for="alumnus-skills-input" class="apc-edit-label"><?php echo esc_html__('Comma-separated', 'alumnus'); ?></label>
						<textarea id="alumnus-skills-input" name="skills" rows="3" class="apc-textarea" placeholder="<?php echo esc_attr__('e.g., Project Management, Problem Solving, Data Analysis', 'alumnus'); ?>"><?php echo esc_textarea( (string) $alumni_data->skills ); ?></textarea>
						<div class="apc-edit-actions">
							<button type="button" class="aph-nav-btn" onclick="alumnus_submitSkills(this)"><?php echo esc_html__('Save', 'alumnus'); ?></button>
							<button type="button" class="aph-nav-btn" onclick="alumnus_toggleSkillsEdit(false)"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
						</div>
					</form>
				<?php endif; ?>
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

	function alumnus_toggleEdit(show) {
		var form = document.getElementById('alumnus-career-form');
		var view = document.getElementById('alumnus-career-view');
		var navEdit = document.getElementById('alumnus-nav-edit');
		var navSave = document.getElementById('alumnus-nav-save');
		var navCancel = document.getElementById('alumnus-nav-cancel');
		if (!form || !view) return;
		if (show) {
			form.style.display = '';
			view.style.display = 'none';
			var ta = document.getElementById('alumnus-career-input');
			if (ta) ta.focus();
			if (navEdit) navEdit.style.display = 'none';
			if (navSave) navSave.style.display = '';
			if (navCancel) navCancel.style.display = '';
			// Bring the edit form into view
			setTimeout(function(){
				form.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}, 50);
			// Also open bio and skills edit if available
			try { if (typeof alumnus_toggleBioEdit === 'function') { alumnus_toggleBioEdit(true); } } catch(e) {}
			try { if (typeof alumnus_toggleSkillsEdit === 'function') { alumnus_toggleSkillsEdit(true); } } catch(e) {}
		} else {
			form.style.display = 'none';
			view.style.display = '';
			if (navEdit) navEdit.style.display = '';
			if (navSave) navSave.style.display = 'none';
			if (navCancel) navCancel.style.display = 'none';
			// Also close bio and skills edit if available
			try { if (typeof alumnus_toggleBioEdit === 'function') { alumnus_toggleBioEdit(false); } } catch(e) {}
			try { if (typeof alumnus_toggleSkillsEdit === 'function') { alumnus_toggleSkillsEdit(false); } } catch(e) {}
		}
	}

	function alumnus_toggleBioEdit(show) {
		var form = document.getElementById('alumnus-bio-form');
		var view = document.getElementById('alumnus-bio-view');
		if (!form || !view) return;
		if (show) {
			form.style.display = '';
			view.style.display = 'none';
			var ta = document.getElementById('alumnus-bio-input');
			if (ta) ta.focus();
			setTimeout(function(){ form.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 50);
		} else {
			form.style.display = 'none';
			view.style.display = '';
		}
	}

	function alumnus_submitBio(btn) {
		var root = document.getElementById('alumnus-profile-root');
		if (!root) return;
		var ajaxUrl = root.getAttribute('data-ajax-url');
		var nonce   = root.getAttribute('data-nonce-bio');
		var userId  = root.getAttribute('data-user-id');
		var textarea = document.getElementById('alumnus-bio-input');
		if (!ajaxUrl || !nonce || !userId || !textarea) return;

		var payload = new FormData();
		payload.append('action', 'alumnus_update_bio_note');
		payload.append('_ajax_nonce', nonce);
		payload.append('user_id', userId);
		payload.append('bio_note', textarea.value);

		if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					var view = document.getElementById('alumnus-bio-view');
					if (view) { view.innerHTML = json.data.html; }
					alumnus_toggleBioEdit(false);
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : 'Failed to update bio.');
				}
			})
			.catch(function(){ alert('Network error. Please try again.'); })
			.finally(function(){ if (btn) { btn.disabled = false; btn.textContent = 'Save'; } });
	}

	function alumnus_toggleSkillsEdit(show) {
		var form = document.getElementById('alumnus-skills-form');
		var view = document.getElementById('alumnus-skills-view');
		if (!form || !view) return;
		if (show) {
			form.style.display = '';
			view.style.display = 'none';
			var ta = document.getElementById('alumnus-skills-input');
			if (ta) ta.focus();
			setTimeout(function(){ form.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 50);
		} else {
			form.style.display = 'none';
			view.style.display = '';
		}
	}

	function alumnus_submitSkills(btn) {
		var root = document.getElementById('alumnus-profile-root');
		if (!root) return;
		var ajaxUrl = root.getAttribute('data-ajax-url');
		var nonce   = root.getAttribute('data-nonce-skills');
		var userId  = root.getAttribute('data-user-id');
		var textarea = document.getElementById('alumnus-skills-input');
		if (!ajaxUrl || !nonce || !userId || !textarea) return;

		var payload = new FormData();
		payload.append('action', 'alumnus_update_skills');
		payload.append('_ajax_nonce', nonce);
		payload.append('user_id', userId);
		payload.append('skills', textarea.value);

		if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					var view = document.getElementById('alumnus-skills-view');
					if (view) { view.innerHTML = json.data.html; }
					alumnus_toggleSkillsEdit(false);
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : 'Failed to update skills.');
				}
			})
			.catch(function(){ alert('Network error. Please try again.'); })
			.finally(function(){ if (btn) { btn.disabled = false; btn.textContent = 'Save'; } });
	}

	function alumnus_submitCareer(btn) {
		var root = document.getElementById('alumnus-profile-root');
		if (!root) return;
		var ajaxUrl = root.getAttribute('data-ajax-url');
		var nonce   = root.getAttribute('data-nonce');
		var userId  = root.getAttribute('data-user-id');
		var textarea = document.getElementById('alumnus-career-input');
		if (!ajaxUrl || !nonce || !userId || !textarea) return;

		var payload = new FormData();
		payload.append('action', 'alumnus_update_career');
		payload.append('_ajax_nonce', nonce);
		payload.append('user_id', userId);
		payload.append('career', textarea.value);

		if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					// Replace view content
					var view = document.getElementById('alumnus-career-view');
					if (view) {
						view.innerHTML = json.data.html;
					}
					// Also attempt to save bio and skills if the forms exist
					try {
						if (document.getElementById('alumnus-bio-form')) {
							alumnus_submitBio(null);
						}
					} catch(e) {}
					try {
						if (document.getElementById('alumnus-skills-form')) {
							alumnus_submitSkills(null);
						}
					} catch(e) {}
					alumnus_toggleEdit(false);
					// Optional toast
					try { if (window.wp && wp.toast) { wp.toast('Career updated'); } } catch(e) {}
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : 'Failed to update career.');
				}
			})
			.catch(function(){ alert('Network error. Please try again.'); })
			.finally(function(){ if (btn) { btn.disabled = false; btn.textContent = 'Save'; } });
	}
	</script>
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

