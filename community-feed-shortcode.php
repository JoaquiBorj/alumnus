<?php
/**
 * Community Feed Shortcode (static UI only – no functionality yet)
 * Usage: [community_feed]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the community feed markup (dynamic posts; initial minimal implementation).
 * Pulls recent posts from custom `posts` table and aggregates counts from
 * `likes` and `shares` tables if they exist. Comments not yet implemented.
 *
 * @return string
 */
function alumnus_render_community_feed_shortcode() {
	global $wpdb;

	// Enqueue Font Awesome icons
	wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1');

	// Enqueue interactive JS for likes/shares/comments
	$js_rel = 'assets/js/community-feed.js';
	$js_abs = plugin_dir_path(__FILE__) . $js_rel;
	$js_ver = file_exists($js_abs) ? filemtime($js_abs) : '1.0.0';
	wp_enqueue_script('alumnus-community-feed', plugin_dir_url(__FILE__) . $js_rel, array(), $js_ver, true);
	wp_localize_script('alumnus-community-feed', 'AlumnusFeed', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonceLike' => wp_create_nonce('alumnus_like_toggle'),
		'nonceShare' => wp_create_nonce('alumnus_share'),
		'nonceComment' => wp_create_nonce('alumnus_add_comment'),
		'noncePost' => wp_create_nonce('alumnus_add_post'),
	));

	// Detect tables existence (allow deploying before migrations run without fatal errors)
	$has_posts  = $wpdb->get_var("SHOW TABLES LIKE 'posts'");
	$has_likes  = $wpdb->get_var("SHOW TABLES LIKE 'likes'");
	$has_shares = $wpdb->get_var("SHOW TABLES LIKE 'shares'");
	$has_alumni = $wpdb->get_var("SHOW TABLES LIKE 'alumni'");
	$has_comments = $wpdb->get_var("SHOW TABLES LIKE 'comments'");

	// Determine current alumni identity (prefer custom alumni session over WP account)
	$current_alumni_id = '';
	if ( function_exists('alumnus_is_logged_in') && function_exists('alumnus_current_username') && alumnus_is_logged_in() ) {
		$current_alumni_id = (string) alumnus_current_username();
	} elseif ( function_exists('alumnus_get_current_alumni_id') ) {
		$current_alumni_id = (string) alumnus_get_current_alumni_id();
	}

	// Resolve current user's display name and initials for avatars
	$sidebar_name = '';
	$sidebar_initials = '';
	if ( $current_alumni_id !== '' && $has_alumni ) {
		$row = $wpdb->get_row( $wpdb->prepare("SELECT firstname, lastname FROM alumni WHERE user_id = %s LIMIT 1", $current_alumni_id) );
		if ( $row ) {
			$sidebar_name = trim( (string) $row->firstname . ' ' . (string) $row->lastname );
			$fi = ! empty( $row->firstname ) ? strtoupper( substr( (string) $row->firstname, 0, 1 ) ) : '';
			$li = ! empty( $row->lastname )  ? strtoupper( substr( (string) $row->lastname, 0, 1 ) )  : '';
			$sidebar_initials = $fi . $li;
		}
		if ( $sidebar_name === '' ) { $sidebar_name = $current_alumni_id; }
	}
	if ( $sidebar_name === '' && function_exists('is_user_logged_in') && is_user_logged_in() ) {
		$wpuser = wp_get_current_user();
		if ( $wpuser && $wpuser->display_name ) { $sidebar_name = $wpuser->display_name; }
	}
	if ( $sidebar_initials === '' && $sidebar_name !== '' ) {
		$parts = preg_split('/\s+/', (string) $sidebar_name);
		$first = isset($parts[0]) ? strtoupper(substr($parts[0],0,1)) : '';
		$second = isset($parts[1]) ? strtoupper(substr($parts[1],0,1)) : '';
		$sidebar_initials = $first . $second;
	}

    // Legacy non-AJAX post composer removed; posting now handled via AJAX modal.
    $notice_msg = '';
    $notice_class = '';

	// Fetch posts after potential insertion
	$posts = array();
	if ( $has_posts ) {
		// Build dynamic SELECT with optional subqueries for counts (only include if tables exist)
		$like_count_sql  = $has_likes  ? "(SELECT COUNT(*) FROM likes  l WHERE l.post_id = p.post_id) AS like_count," : "0 AS like_count,";
		$share_count_sql = $has_shares ? "(SELECT COUNT(*) FROM shares s WHERE s.post_id = p.post_id) AS share_count," : "0 AS share_count,";
		$comment_count_sql = $has_comments ? "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count" : "0 AS comment_count";

		// Per-user liked/shared state
		$liked_by_me_sql = ($has_likes && $current_alumni_id !== '') ? "(SELECT COUNT(*) FROM likes l2 WHERE l2.post_id=p.post_id AND l2.user_id=%s) AS liked_by_me," : "0 AS liked_by_me,";
		$shared_by_me_sql = ($has_shares && $current_alumni_id !== '') ? "(SELECT COUNT(*) FROM shares s2 WHERE s2.post_id=p.post_id AND s2.user_id=%s) AS shared_by_me," : "0 AS shared_by_me,";

		$name_join = $has_alumni ? "LEFT JOIN alumni a ON a.user_id = p.user_id" : "";
		$name_fields = $has_alumni ? "a.firstname, a.lastname," : "";

		$sql = "SELECT p.post_id, p.user_id, $name_fields p.content, p.post_date, p.post_time,
				$like_count_sql $share_count_sql $liked_by_me_sql $shared_by_me_sql $comment_count_sql
				FROM posts p $name_join
				ORDER BY p.post_date DESC, p.post_id DESC
				LIMIT 20"; // Hard cap for initial feed performance. Also break ties by newest ID.
		$params = array();
		if ($has_likes && $current_alumni_id !== '') { $params[] = $current_alumni_id; }
		if ($has_shares && $current_alumni_id !== '') { $params[] = $current_alumni_id; }
		if (!empty($params)) {
			$posts = $wpdb->get_results( $wpdb->prepare($sql, $params) );
		} else {
			$posts = $wpdb->get_results( $sql );
		}
	}
	ob_start();
	?>
	<div class="alumnus-community-feed-wrapper">
				<div class="alumnus-feed-layout">
			<!-- Left Sidebar -->
			<aside class="alumnus-feed-sidebar-left">
				<div class="alumnus-profile-card">
					<div class="apc-header">
								<div class="apc-avatar apc-avatar--lg"><span class="apc-initials"><?php echo esc_html( $sidebar_initials !== '' ? $sidebar_initials : 'A' ); ?></span></div>
						<div class="apc-meta">
							<h3 class="apc-name">
										<?php echo esc_html( $sidebar_name !== '' ? $sidebar_name : __( 'Guest', 'alumnus' ) ); ?>
							</h3>
						</div>
					</div>
				</div>
			</aside>

			<!-- Main Feed Column -->
			<main class="alumnus-feed-main">
				<?php if ( ! empty( $notice_msg ) ) : ?>
					<div class="<?php echo esc_attr( $notice_class ); ?>"><?php echo esc_html( $notice_msg ); ?></div>
				<?php endif; ?>
				<div class="alumnus-welcome-message">
					<h2><?php esc_html_e( 'Welcome to the Community Feed', 'alumnus' ); ?></h2>
				</div>

				<?php
				// Build unified chronological feed (posts + shares)
				$share_rows = array();
				if ( $has_shares ) {
					$like_count_sql_sh  = $has_likes ? "(SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count," : "0 AS like_count,";
					$comment_count_sql_sh = $has_comments ? "(SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count," : "0 AS comment_count,";
					$share_count_sql_sh = "(SELECT COUNT(*) FROM shares sx WHERE sx.post_id = p.post_id) AS share_count,";
					$liked_by_me_sql_sh = ($has_likes && $current_alumni_id !== '') ? "(SELECT COUNT(*) FROM likes l2 WHERE l2.post_id=p.post_id AND l2.user_id=%s) AS liked_by_me," : "0 AS liked_by_me,";
					$shared_by_me_sql_sh = ($current_alumni_id !== '') ? "(SELECT COUNT(*) FROM shares s2 WHERE s2.post_id=p.post_id AND s2.user_id=%s) AS shared_by_me," : "0 AS shared_by_me,";
					$name_fields_sharer = $has_alumni ? "sh.firstname AS sharer_firstname, sh.lastname AS sharer_lastname," : "";
					$name_fields_orig   = $has_alumni ? "orig.firstname AS orig_firstname, orig.lastname AS orig_lastname," : "";
					$sql_shares = "SELECT s.share_id, s.post_id, s.user_id AS sharer_user_id, s.share_date, s.share_time, p.user_id AS orig_user_id, p.content, p.post_date, p.post_time, "
						. $name_fields_sharer . $name_fields_orig
						. $like_count_sql_sh . $share_count_sql_sh . $liked_by_me_sql_sh . $shared_by_me_sql_sh . rtrim($comment_count_sql_sh, ',') . " 
						FROM shares s 
						JOIN posts p ON p.post_id = s.post_id 
						LEFT JOIN alumni sh ON sh.user_id = s.user_id 
						LEFT JOIN alumni orig ON orig.user_id = p.user_id 
						ORDER BY s.share_date DESC, s.share_time DESC, s.share_id DESC 
						LIMIT 20";
					$params_sh = array();
					if ($has_likes && $current_alumni_id !== '') { $params_sh[] = $current_alumni_id; }
					if ($current_alumni_id !== '') { $params_sh[] = $current_alumni_id; }
					$share_rows = ! empty($params_sh) ? $wpdb->get_results( $wpdb->prepare($sql_shares, $params_sh) ) : $wpdb->get_results($sql_shares);
				}

				$feed_items = array();
				// Normalize posts
				if ( ! empty( $posts ) ) {
					foreach ( $posts as $p_row ) {
						$__ts = strtotime( (string)$p_row->post_date . ' ' . ( isset($p_row->post_time)? (string)$p_row->post_time : '00:00:00' ) );
						$feed_items[] = array( 'type' => 'post', 'ts' => $__ts, 'row' => $p_row );
					}
				}
				// Normalize shares
				if ( ! empty( $share_rows ) ) {
					foreach ( $share_rows as $s_row ) {
						$__sts = strtotime( (string)$s_row->share_date . ' ' . ( isset($s_row->share_time)? (string)$s_row->share_time : '00:00:00' ) );
						$feed_items[] = array( 'type' => 'share', 'ts' => $__sts, 'row' => $s_row );
					}
				}

				// Sort newest first
				usort( $feed_items, function( $a, $b ) {
					if ( $a['ts'] === $b['ts'] ) { return 0; }
					return ($a['ts'] > $b['ts']) ? -1 : 1;
				});

				// Hard cap overall (same as individual limits combined)
				$feed_items = array_slice( $feed_items, 0, 40 );

				if ( empty( $feed_items ) ) : ?>
					<article class="alumnus-post-card">
						<div class="post-text"><?php esc_html_e( 'No activity yet. Be the first to share or post!', 'alumnus' ); ?></div>
						<div class="post-actions compact">
							<button class="btn-light" disabled><i class="fa-solid fa-thumbs-up"></i> <?php esc_html_e( 'Like', 'alumnus' ); ?></button>
							<button class="btn-light" disabled><i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
							<button class="btn-light" disabled><i class="fa-solid fa-share"></i> <?php esc_html_e( 'Share', 'alumnus' ); ?></button>
						</div>
					</article>
				<?php else :
					foreach ( $feed_items as $item ) :
						if ( $item['type'] === 'post' ) {
							$post_row = $item['row'];
							$full_name = '';
							if ( isset( $post_row->firstname ) || isset( $post_row->lastname ) ) { $full_name = trim( (string)$post_row->firstname . ' ' . (string)$post_row->lastname ); }
							$display_name = $full_name !== '' ? $full_name : $post_row->user_id;
							$ai1 = '';
							$ai2 = '';
							if ( ! empty( $post_row->firstname ) || ! empty( $post_row->lastname ) ) {
								$ai1 = ! empty( $post_row->firstname ) ? strtoupper( substr( (string)$post_row->firstname, 0, 1 ) ) : '';
								$ai2 = ! empty( $post_row->lastname ) ? strtoupper( substr( (string)$post_row->lastname, 0, 1 ) ) : '';
							} else {
								$ai1 = strtoupper( substr( (string)$post_row->user_id, 0, 1 ) );
							}
							$author_initials = $ai1 . $ai2;
							$profile_url = '';
							if ( ! empty( $post_row->user_id ) && function_exists( 'alumnus_get_profile_url' ) ) { $profile_url = alumnus_get_profile_url( $post_row->user_id ); }
							?>
							<article class="alumnus-post-card" data-post-id="<?php echo (int)$post_row->post_id; ?>">
								<header class="post-header">
									<?php if ( $profile_url ) : ?><a class="ph-author-link" href="<?php echo esc_url( $profile_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s profile', 'alumnus' ), $display_name ) ); ?>"><?php endif; ?>
									<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $author_initials !== '' ? $author_initials : 'U' ); ?></span></div>
									<div class="ph-meta">
										<h5 class="ph-name"><?php echo esc_html( $display_name ); ?></h5>
										<div class="ph-date"><?php echo esc_html( date_i18n( 'F j Y \a\t g:i A', $item['ts'] ) ); ?></div>
									</div>
									<?php if ( $profile_url ) : ?></a><?php endif; ?>
								</header>
								<div class="post-text"><?php echo esc_html( $post_row->content ); ?></div>
								<div class="post-engagement-bar"><div class="pe-stats">
									<span class="pe-icon pe-like-count" data-post-id="<?php echo (int)$post_row->post_id; ?>" title="<?php esc_attr_e( 'Likes', 'alumnus' ); ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo (int)$post_row->like_count; ?></span>
									<span class="pe-icon pe-comment-count" data-post-id="<?php echo (int)$post_row->post_id; ?>" title="<?php esc_attr_e( 'Comments', 'alumnus' ); ?>"><i class="fa-solid fa-comment"></i> <?php echo (int)$post_row->comment_count; ?></span>
									<span class="pe-icon pe-share-count" data-post-id="<?php echo (int)$post_row->post_id; ?>" title="<?php esc_attr_e( 'Shares', 'alumnus' ); ?>"><i class="fa-solid fa-share"></i> <?php echo (int)$post_row->share_count; ?></span>
								</div></div>
								<div class="post-actions compact">
									<button class="btn-light btn-like <?php echo (!empty($post_row->liked_by_me) ? 'is-active' : ''); ?>" data-post-id="<?php echo (int)$post_row->post_id; ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo !empty($post_row->liked_by_me) ? esc_html__('Liked','alumnus') : esc_html__('Like','alumnus'); ?></button>
									<button class="btn-light btn-comment" data-post-id="<?php echo (int)$post_row->post_id; ?>"><i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
									<button class="btn-light btn-share <?php echo (!empty($post_row->shared_by_me) ? 'is-active' : ''); ?>" data-post-id="<?php echo (int)$post_row->post_id; ?>"><i class="fa-solid fa-share"></i> <?php echo !empty($post_row->shared_by_me) ? esc_html__('Shared','alumnus') : esc_html__('Share','alumnus'); ?></button>
								</div>
								<?php if ( $has_comments ): ?>
									<div class="post-comments" id="comments-<?php echo (int)$post_row->post_id; ?>">
										<?php
										$comments = $wpdb->get_results( $wpdb->prepare(
											"SELECT c.comment_id, c.user_id, c.content, c.comment_date, c.comment_time, a.firstname, a.lastname
											 FROM comments c LEFT JOIN alumni a ON a.user_id=c.user_id
											 WHERE c.post_id=%d ORDER BY c.comment_id DESC LIMIT 3",
											(int)$post_row->post_id
										));
										if ( ! empty( $comments ) ) {
											echo '<ul class="comments-list">';
											foreach ( $comments as $cm ) {
												$cn = trim( (string)$cm->firstname . ' ' . (string)$cm->lastname );
												if ($cn === '') { $cn = (string)$cm->user_id; }
												$__cm_ts = strtotime( (string)$cm->comment_date . ' ' . ( isset($cm->comment_time)? (string)$cm->comment_time : '00:00:00' ) );
												$__now = current_time('timestamp');
												$__diff = $__now - $__cm_ts;
												if ($__diff < 60) { $rel = __('Just now','alumnus'); }
												elseif ($__diff < 3600) { $rel = sprintf(__('%dm','alumnus'), floor($__diff/60)); }
												elseif ($__diff < 86400) { $rel = sprintf(__('%dh','alumnus'), floor($__diff/3600)); }
												elseif ($__diff < 172800) { $rel = __('Yesterday','alumnus'); }
												elseif ($__diff < 604800) { $rel = sprintf(__('%dd','alumnus'), floor($__diff/86400)); }
												elseif ($__diff < 2592000) { $rel = sprintf(__('%dw','alumnus'), floor($__diff/604800)); }
												else { $rel = date_i18n('F j Y', $__cm_ts); }
												echo '<li class="comment-item"><div class="comment-bubble"><strong>' . esc_html($cn) . ':</strong> ' . esc_html($cm->content) . '</div><div class="comment-timestamp">' . esc_html($rel) . '</div></li>';
											}
											echo '</ul>';
										} else {
											echo '<div class="apc-placeholder">' . esc_html__('No comments yet.','alumnus') . '</div>';
										}
										?>
									</div>
								<?php endif; ?>
							</article>
							<?php
						} else { // share item
							$sr = $item['row'];
							$sh_name = '';
							if ( isset($sr->sharer_firstname) || isset($sr->sharer_lastname) ) { $sh_name = trim( (string)$sr->sharer_firstname . ' ' . (string)$sr->sharer_lastname ); }
							if ( $sh_name === '' ) { $sh_name = (string)$sr->sharer_user_id; }
							$sh_i1 = ! empty($sr->sharer_firstname) ? strtoupper(substr((string)$sr->sharer_firstname,0,1)) : strtoupper(substr((string)$sr->sharer_user_id,0,1));
							$sh_i2 = ! empty($sr->sharer_lastname) ? strtoupper(substr((string)$sr->sharer_lastname,0,1)) : '';
							$sh_initials = $sh_i1 . $sh_i2;
							$orig_name = '';
							if ( isset($sr->orig_firstname) || isset($sr->orig_lastname) ) { $orig_name = trim( (string)$sr->orig_firstname . ' ' . (string)$sr->orig_lastname ); }
							if ( $orig_name === '' ) { $orig_name = (string)$sr->orig_user_id; }
							$orig_i1 = ! empty($sr->orig_firstname) ? strtoupper(substr((string)$sr->orig_firstname,0,1)) : strtoupper(substr((string)$sr->orig_user_id,0,1));
							$orig_i2 = ! empty($sr->orig_lastname) ? strtoupper(substr((string)$sr->orig_lastname,0,1)) : '';
							$orig_initials = $orig_i1 . $orig_i2;
							$orig_ts = strtotime( (string)$sr->post_date . ' ' . ( isset($sr->post_time)? (string)$sr->post_time : '00:00:00' ) );
							$orig_date_display = esc_html( date_i18n( 'F j Y \a\t g:i A', $orig_ts ) );
							$like_c = isset($sr->like_count)? (int)$sr->like_count : 0;
							$share_c = isset($sr->share_count)? (int)$sr->share_count : 0;
							$comment_c = isset($sr->comment_count)? (int)$sr->comment_count : 0;
							$liked_me = !empty($sr->liked_by_me);
							$shared_me = !empty($sr->shared_by_me);
							?>
							<article class="alumnus-post-card alumnus-post-card--share" data-share-origin-post="<?php echo (int)$sr->post_id; ?>" data-share-id="<?php echo (int)$sr->share_id; ?>">
								<header class="post-header share-header">
									<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $sh_initials !== '' ? $sh_initials : 'U' ); ?></span></div>
									<div class="ph-meta">
										<h5 class="ph-name"><?php echo esc_html( $sh_name ); ?> <span class="ph-share-action"><?php esc_html_e('reposted this','alumnus'); ?></span></h5>
										<div class="ph-date"><?php echo esc_html( date_i18n( 'F j Y \a\t g:i A', $item['ts'] ) ); ?></div>
									</div>
								</header>
								<div class="shared-original-wrapper">
									<div class="shared-original-card">
										<header class="post-header original-header">
											<div class="apc-avatar apc-avatar--xs"><span class="apc-initials"><?php echo esc_html( $orig_initials !== '' ? $orig_initials : 'U' ); ?></span></div>
											<div class="ph-meta">
												<h6 class="ph-name"><?php echo esc_html( $orig_name ); ?></h6>
												<div class="ph-date"><?php echo $orig_date_display; ?></div>
											</div>
										</header>
										<div class="post-text shared-text"><?php echo esc_html( (string)$sr->content ); ?></div>
									</div>
								</div>
								<div class="post-engagement-bar"><div class="pe-stats">
									<span class="pe-icon pe-like-count" data-post-id="<?php echo (int)$sr->post_id; ?>" title="<?php esc_attr_e('Likes','alumnus'); ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo (int)$like_c; ?></span>
									<span class="pe-icon pe-comment-count" data-post-id="<?php echo (int)$sr->post_id; ?>" title="<?php esc_attr_e('Comments','alumnus'); ?>"><i class="fa-solid fa-comment"></i> <?php echo (int)$comment_c; ?></span>
									<span class="pe-icon pe-share-count" data-post-id="<?php echo (int)$sr->post_id; ?>" title="<?php esc_attr_e('Shares','alumnus'); ?>"><i class="fa-solid fa-share"></i> <?php echo (int)$share_c; ?></span>
								</div></div>
								<div class="post-actions compact">
									<button class="btn-light btn-like <?php echo ( $liked_me ? 'is-active' : '' ); ?>" data-post-id="<?php echo (int)$sr->post_id; ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo $liked_me? esc_html__('Liked','alumnus'): esc_html__('Like','alumnus'); ?></button>
									<button class="btn-light btn-comment" data-post-id="<?php echo (int)$sr->post_id; ?>"><i class="fa-solid fa-comment"></i> <?php esc_html_e('Comment','alumnus'); ?></button>
									<button class="btn-light btn-share <?php echo ( $shared_me ? 'is-active' : '' ); ?>" data-post-id="<?php echo (int)$sr->post_id; ?>"><i class="fa-solid fa-share"></i> <?php echo $shared_me? esc_html__('Shared','alumnus'): esc_html__('Share','alumnus'); ?></button>
								</div>
								<div class="post-comments" id="comments-<?php echo (int)$sr->post_id; ?>">
									<div class="apc-placeholder"><?php esc_html_e('Comments hidden. Open original to view.','alumnus'); ?></div>
								</div>
							</article>
							<?php
						}
						endforeach; // feed_items loop
				endif; // empty feed_items
				?>
			</main>

			<!-- Right Sidebar -->
			<aside class="alumnus-feed-sidebar-right">
				<div class="alumnus-post-composer">
					<div class="composer-input">
						<?php if ( $current_alumni_id !== '' && $has_posts ) : ?>
							<button type="button" class="btn-secondary btn-open-post-modal" aria-haspopup="dialog" aria-controls="alumnus-post-modal"><?php esc_html_e( 'Make a post', 'alumnus' ); ?></button>
						<?php else : ?>
							<button type="button" class="btn-secondary" disabled><?php esc_html_e( 'Sign in to post', 'alumnus' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			</aside>
		</div>

		<!-- Post Modal -->
		<div class="alumnus-modal-overlay" id="alumnus-post-modal" aria-hidden="true">
			<div class="alumnus-modal" role="dialog" aria-modal="true" aria-labelledby="alumnus-post-modal-title">
				<button type="button" class="alumnus-modal-close" data-close-modal>&times;</button>
				<h3 id="alumnus-post-modal-title" class="alumnus-modal-title"><?php esc_html_e('Create Post','alumnus'); ?></h3>
				<?php if ( $current_alumni_id !== '' && $has_posts ): ?>
				<form id="alumnus-post-modal-form">
					<textarea name="content" maxlength="500" placeholder="<?php esc_attr_e('What do you want to say? (max 500 chars)','alumnus'); ?>" required></textarea>
					<div class="alumnus-modal-actions">
						<button type="submit" class="btn-primary"><?php esc_html_e('Post','alumnus'); ?></button>
					</div>
				</form>
				<?php else: ?>
					<p><?php esc_html_e('Sign in to create a post.','alumnus'); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Comment Modal -->
		<div class="alumnus-modal-overlay" id="alumnus-comment-modal" aria-hidden="true">
			<div class="alumnus-modal alumnus-modal--comment" role="dialog" aria-modal="true" aria-labelledby="alumnus-comment-modal-title">
				<header class="alumnus-modal-header">
					<h3 id="alumnus-comment-modal-title" class="alumnus-modal-title"><?php esc_html_e("Anonymous participant's Post",'alumnus'); ?></h3>
					<button type="button" class="alumnus-modal-close" data-close-modal aria-label="<?php esc_attr_e('Close','alumnus'); ?>">&times;</button>
				</header>
				<div class="alumnus-modal-content">
					<div class="alumnus-comment-modal-post" id="alumnus-comment-modal-post"><!-- cloned post card inserted here --></div>
					<div class="alumnus-modal-comments" id="alumnus-comment-list-wrapper">
						<div class="alumnus-modal-comments-empty">
							<div class="alumnus-modal-comments-empty-icon"><i class="fa-solid fa-comments"></i></div>
							<p class="alumnus-modal-comments-empty-text"><?php esc_html_e('No comments yet','alumnus'); ?></p>
							<p class="alumnus-modal-comments-empty-sub"><?php esc_html_e('Be the first to comment.','alumnus'); ?></p>
						</div>
					</div>
				</div>
				<?php if ( $current_alumni_id !== '' && $has_comments ): ?>
				<form id="alumnus-comment-modal-form" class="alumnus-modal-composer">
					<input type="hidden" name="postId" value="" />
					<div class="alumnus-modal-composer-inner">
						<div class="amc-avatar-wrap"><div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $sidebar_initials !== '' ? $sidebar_initials : 'A' ); ?></span></div></div>
						<div class="amc-input-wrap"><input type="text" name="comment" maxlength="200" placeholder="<?php echo esc_attr( sprintf( __('Comment as %s','alumnus'), $sidebar_name !== '' ? $sidebar_name : __('Anonymous participant','alumnus') ) ); ?>" required /></div>
						<div class="amc-actions-wrap">
							<button type="submit" class="btn-primary amc-submit" aria-label="<?php esc_attr_e('Submit comment','alumnus'); ?>">➤</button>
						</div>
					</div>
				</form>
				<?php else: ?>
					<p style="margin:12px 18px 24px; font-size:14px; opacity:.8; text-align:center; "><?php esc_html_e('Sign in to comment.','alumnus'); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'community_feed', 'alumnus_render_community_feed_shortcode' );

// Helper: Resolve the current WordPress user's mapped alumni.user_id
if ( ! function_exists( 'alumnus_get_current_alumni_id' ) ) {
	function alumnus_get_current_alumni_id() {
		if ( ! is_user_logged_in() ) { return ''; }
		global $wpdb;
		$u = wp_get_current_user();
		if ( ! $u || ! $u->ID ) { return ''; }

		// 0) Explicit user meta link takes precedence
		$meta_link = get_user_meta( $u->ID, 'alumnus_user_id', true );
		if ( ! empty( $meta_link ) ) { return (string) $meta_link; }

		// Try via `user` table mapping by username -> user.user -> alumni.user_id
		$alumni_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT u.user FROM `user` u WHERE u.username = %s LIMIT 1",
			$u->user_login
		) );
		if ( ! empty( $alumni_id ) ) { return (string) $alumni_id; }

		// Try direct match where alumni.user_id equals WP username
		$alumni_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT a.user_id FROM alumni a WHERE a.user_id = %s LIMIT 1",
			$u->user_login
		) );
		if ( ! empty( $alumni_id ) ) { return (string) $alumni_id; }

		// Fallback: match by email
		$alumni_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT a.user_id FROM alumni a WHERE a.email = %s LIMIT 1",
			$u->user_email
		) );
		if ( ! empty( $alumni_id ) ) { return (string) $alumni_id; }

		return '';
	}
}

// =============================
// AJAX: Like toggle
// =============================
function alumnus_ajax_like_toggle() {
	check_ajax_referer('alumnus_like_toggle', 'nonce');
	global $wpdb;
	$post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
	$uid = ( function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() && function_exists('alumnus_current_username') ) ? (string) alumnus_current_username() : '';
	if ( $post_id <= 0 || $uid === '' ) { wp_send_json_error(array('message'=>'forbidden'), 403); }

	// Toggle like (unique key on (post_id,user_id))
	$liked = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM likes WHERE post_id=%d AND user_id=%s", $post_id, $uid) );
	if ( $liked > 0 ) {
		$wpdb->delete('likes', array('post_id'=>$post_id, 'user_id'=>$uid), array('%d','%s'));
		$new_state = false;
	} else {
		$wpdb->insert('likes', array('post_id'=>$post_id, 'user_id'=>$uid), array('%d','%s'));
		$new_state = true;
	}
	$count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM likes WHERE post_id=%d", $post_id) );
	wp_send_json_success(array('liked'=>$new_state, 'count'=>$count));
}
add_action('wp_ajax_alumnus_like_toggle', 'alumnus_ajax_like_toggle');
add_action('wp_ajax_nopriv_alumnus_like_toggle', 'alumnus_ajax_like_toggle');

// =============================
// AJAX: Share (idempotent per user)
// =============================
function alumnus_ajax_share_post() {
	check_ajax_referer('alumnus_share', 'nonce');
	global $wpdb;
	$post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
	$uid = ( function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() && function_exists('alumnus_current_username') ) ? (string) alumnus_current_username() : '';
	if ( $post_id <= 0 || $uid === '' ) { wp_send_json_error(array('message'=>'forbidden'), 403); }

	// Insert share row if not already shared by this user
	$exists = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM shares WHERE post_id=%d AND user_id=%s", $post_id, $uid) );
	if ( $exists === 0 ) {
		$wpdb->insert('shares', array(
			'post_id'=>$post_id,
			'user_id'=>$uid,
			'share_date'=>current_time('Y-m-d'),
			'share_time'=>current_time('H:i:s')
		), array('%d','%s','%s','%s'));
	}

	// Fetch original post + names for rendering share card
	$orig = $wpdb->get_row( $wpdb->prepare(
		"SELECT p.content, p.user_id, p.post_date, p.post_time, a.firstname, a.lastname
		 FROM posts p LEFT JOIN alumni a ON a.user_id = p.user_id WHERE p.post_id=%d",
		$post_id
	) );
	$sharer_row = $wpdb->get_row( $wpdb->prepare("SELECT firstname, lastname FROM alumni WHERE user_id=%s", $uid) );
	$sharer_name = '';
	if ( $sharer_row ) { $sharer_name = trim( (string)$sharer_row->firstname . ' ' . (string)$sharer_row->lastname ); }
	if ( $sharer_name === '' ) { $sharer_name = $uid; }
	$sharer_initials = '';
	if ( $sharer_row ) {
		$sharer_initials = ( ! empty($sharer_row->firstname) ? strtoupper(substr((string)$sharer_row->firstname,0,1)) : '' ) . ( ! empty($sharer_row->lastname) ? strtoupper(substr((string)$sharer_row->lastname,0,1)) : '' );
	} else { $sharer_initials = strtoupper(substr($uid,0,1)); }

	$html = '';
	if ( $orig && isset($orig->content) ) {
		$orig_initials = '';
		if ( ! empty($orig->firstname) || ! empty($orig->lastname) ) {
			$orig_initials = ( ! empty($orig->firstname) ? strtoupper(substr((string)$orig->firstname,0,1)) : '' ) . ( ! empty($orig->lastname) ? strtoupper(substr((string)$orig->lastname,0,1)) : '' );
		} else { $orig_initials = strtoupper(substr((string)$orig->user_id,0,1)); }
		$orig_name = '';
		if ( isset($orig->firstname) || isset($orig->lastname) ) { $orig_name = trim( (string)$orig->firstname . ' ' . (string)$orig->lastname ); }
		if ( $orig_name === '' ) { $orig_name = (string)$orig->user_id; }
		$share_date_display = esc_html( date_i18n( 'F j Y \a\t g:i A', current_time('timestamp') ) );
		$orig_ts = strtotime( (string)$orig->post_date . ' ' . ( isset($orig->post_time)? (string)$orig->post_time : '00:00:00' ) );
		$orig_date_display = esc_html( date_i18n( 'F j Y \a\t g:i A', $orig_ts ) );
		$like_count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM likes WHERE post_id=%d", $post_id) );
		$comment_count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM comments WHERE post_id=%d", $post_id) );
		$share_count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM shares WHERE post_id=%d", $post_id) );
		$liked_by_me = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM likes WHERE post_id=%d AND user_id=%s", $post_id, $uid) );
		$shared_by_me = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM shares WHERE post_id=%d AND user_id=%s", $post_id, $uid) );
		ob_start(); ?>
		<article class="alumnus-post-card alumnus-post-card--share" data-share-origin-post="<?php echo (int)$post_id; ?>" data-share-user="<?php echo esc_attr($uid); ?>">
			<header class="post-header share-header">
				<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $sharer_initials !== '' ? $sharer_initials : 'U' ); ?></span></div>
				<div class="ph-meta">
					<h5 class="ph-name"><?php echo esc_html( $sharer_name ); ?> <span class="ph-share-action"><?php esc_html_e('reposted this','alumnus'); ?></span></h5>
					<div class="ph-date"><?php echo $share_date_display; ?></div>
				</div>
			</header>
			<div class="shared-original-wrapper">
				<div class="shared-original-card">
					<header class="post-header original-header">
						<div class="apc-avatar apc-avatar--xs"><span class="apc-initials"><?php echo esc_html( $orig_initials !== '' ? $orig_initials : 'U' ); ?></span></div>
						<div class="ph-meta">
							<h6 class="ph-name"><?php echo esc_html( $orig_name ); ?></h6>
							<div class="ph-date"><?php echo $orig_date_display; ?></div>
						</div>
					</header>
					<div class="post-text shared-text"><?php echo esc_html( (string)$orig->content ); ?></div>
				</div>
			</div>
			<div class="post-engagement-bar">
				<div class="pe-stats">
					<span class="pe-icon pe-like-count" data-post-id="<?php echo (int)$post_id; ?>" title="<?php esc_attr_e( 'Likes', 'alumnus' ); ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo (int)$like_count; ?></span>
					<span class="pe-icon pe-comment-count" data-post-id="<?php echo (int)$post_id; ?>" title="<?php esc_attr_e( 'Comments', 'alumnus' ); ?>"><i class="fa-solid fa-comment"></i> <?php echo (int)$comment_count; ?></span>
					<span class="pe-icon pe-share-count" data-post-id="<?php echo (int)$post_id; ?>" title="<?php esc_attr_e( 'Shares', 'alumnus' ); ?>"><i class="fa-solid fa-share"></i> <?php echo (int)$share_count; ?></span>
				</div>
			</div>
			<div class="post-actions compact">
				<button class="btn-light btn-like <?php echo ( $liked_by_me ? 'is-active' : '' ); ?>" data-post-id="<?php echo (int)$post_id; ?>"><i class="fa-solid fa-thumbs-up"></i> <?php echo $liked_by_me? esc_html__('Liked','alumnus'): esc_html__('Like','alumnus'); ?></button>
				<button class="btn-light btn-comment" data-post-id="<?php echo (int)$post_id; ?>"><i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
				<button class="btn-light btn-share <?php echo ( $shared_by_me ? 'is-active' : '' ); ?>" data-post-id="<?php echo (int)$post_id; ?>"><i class="fa-solid fa-share"></i> <?php echo $shared_by_me? esc_html__('Shared','alumnus'): esc_html__('Share','alumnus'); ?></button>
			</div>
			<div class="post-comments" id="comments-<?php echo (int)$post_id; ?>">
				<div class="apc-placeholder"><?php esc_html_e( 'Comments hidden. Open original to view.', 'alumnus' ); ?></div>
			</div>
		</article>
		<?php
		$html = ob_get_clean();
	}
	$count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM shares WHERE post_id=%d", $post_id) );
	wp_send_json_success(array('count'=>$count, 'html'=>$html));
}
add_action('wp_ajax_alumnus_share_post', 'alumnus_ajax_share_post');
add_action('wp_ajax_nopriv_alumnus_share_post', 'alumnus_ajax_share_post');

// =============================
// AJAX: Add comment
// =============================
function alumnus_ajax_add_comment() {
	check_ajax_referer('alumnus_add_comment', 'nonce');
	global $wpdb;
	$post_id = isset($_POST['postId']) ? absint($_POST['postId']) : 0;
	$raw = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
	$content = trim( wp_strip_all_tags( (string) $raw ) );
	$uid = ( function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() && function_exists('alumnus_current_username') ) ? (string) alumnus_current_username() : '';
	if ( $post_id <= 0 || $uid === '' || $content === '' ) { wp_send_json_error(array('message'=>'forbidden'), 403); }
	if ( function_exists('mb_substr') ) { $content = mb_substr($content, 0, 200, 'UTF-8'); } else { $content = substr($content, 0, 200); }

	$res = $wpdb->insert('comments', array(
		'post_id'       => $post_id,
		'user_id'       => $uid,
		'content'       => $content,
		'comment_date'  => current_time('Y-m-d'),
		'comment_time'  => current_time('H:i:s'),
	), array('%d','%s','%s','%s','%s'));
	if ( false === $res ) { wp_send_json_error(array('message'=>'db-error'), 500); }

	// Build small HTML snippet for the new comment
	$row = $wpdb->get_row( $wpdb->prepare("SELECT a.firstname, a.lastname FROM alumni a WHERE a.user_id=%s", $uid) );
	$name = '';
	if ($row) { $name = trim( (string)$row->firstname . ' ' . (string)$row->lastname ); }
	if ($name === '') { $name = $uid; }
	$html = '<li class="comment-item"><div class="comment-bubble"><strong>' . esc_html($name) . ':</strong> ' . esc_html($content) . '</div><div class="comment-timestamp">' . esc_html__('Just now','alumnus') . '</div></li>';

	$count = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM comments WHERE post_id=%d", $post_id) );
	wp_send_json_success(array('count'=>$count, 'html'=>$html));
}
add_action('wp_ajax_alumnus_add_comment', 'alumnus_ajax_add_comment');
add_action('wp_ajax_nopriv_alumnus_add_comment', 'alumnus_ajax_add_comment');

// =============================
// AJAX: Add post (modal composer)
// =============================
function alumnus_ajax_add_post() {
	check_ajax_referer('alumnus_add_post', 'nonce');
	global $wpdb;
	$uid = ( function_exists('alumnus_is_logged_in') && alumnus_is_logged_in() && function_exists('alumnus_current_username') ) ? (string) alumnus_current_username() : '';
	if ( $uid === '' ) { wp_send_json_error(array('message'=>'forbidden'), 403); }
	$raw = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
	$content = trim( wp_strip_all_tags( (string) $raw ) );
	if ( $content === '' ) { wp_send_json_error(array('message'=>'empty'), 400); }
	if ( function_exists('mb_substr') ) { $content = mb_substr($content, 0, 500, 'UTF-8'); } else { $content = substr($content, 0, 500); }

	// Insert post with date + time
	$res = $wpdb->insert( 'posts', array(
		'user_id'   => $uid,
		'content'   => $content,
		'post_date' => current_time('Y-m-d'),
		'post_time' => current_time('H:i:s'),
	), array('%s','%s','%s','%s') );
	if ( false === $res ) { wp_send_json_error(array('message'=>'db-error'), 500); }
	$post_id = (int) $wpdb->insert_id;

	// Fetch author name for display
	$row = $wpdb->get_row( $wpdb->prepare("SELECT firstname, lastname FROM alumni WHERE user_id=%s", $uid) );
	$full_name = '';
	if ( $row ) { $full_name = trim( (string)$row->firstname . ' ' . (string)$row->lastname ); }
	if ( $full_name === '' ) { $full_name = $uid; }

	// Initials
	$ai1 = '';
	$ai2 = '';
	if ( $row ) {
		$ai1 = ! empty( $row->firstname ) ? strtoupper( substr( (string) $row->firstname, 0, 1 ) ) : '';
		$ai2 = ! empty( $row->lastname )  ? strtoupper( substr( (string) $row->lastname, 0, 1 ) )  : '';
	} else {
		$ai1 = strtoupper( substr( $uid, 0, 1 ) );
	}
	$author_initials = $ai1 . $ai2;

	// Build HTML (counts all start at 0; liked/shared state false)
	$date_display = esc_html( date_i18n( 'F j Y \a\t g:i A', current_time('timestamp') ) );
	ob_start();
	?>
	<article class="alumnus-post-card" data-post-id="<?php echo (int) $post_id; ?>">
		<header class="post-header">
			<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $author_initials !== '' ? $author_initials : 'U' ); ?></span></div>
			<div class="ph-meta">
				<h5 class="ph-name"><?php echo esc_html( $full_name ); ?></h5>
				<div class="ph-date"><?php echo $date_display; ?> • <span class="ph-visibility" title="<?php esc_attr_e( 'Public', 'alumnus' ); ?>">🌐</span></div>
			</div>
		</header>
		<div class="post-text"><?php echo esc_html( $content ); ?></div>
		<div class="post-engagement-bar">
			<div class="pe-stats">
				<span class="pe-icon pe-like-count" data-post-id="<?php echo (int) $post_id; ?>" title="<?php esc_attr_e( 'Likes', 'alumnus' ); ?>"><i class="fa-solid fa-thumbs-up"></i> 0</span>
				<span class="pe-icon pe-comment-count" data-post-id="<?php echo (int) $post_id; ?>" title="<?php esc_attr_e( 'Comments', 'alumnus' ); ?>"><i class="fa-solid fa-comment"></i> 0</span>
				<span class="pe-icon pe-share-count" data-post-id="<?php echo (int) $post_id; ?>" title="<?php esc_attr_e( 'Shares', 'alumnus' ); ?>"><i class="fa-solid fa-share"></i> 0</span>
			</div>
		</div>
		<div class="post-actions compact">
			<button class="btn-light btn-like" data-post-id="<?php echo (int) $post_id; ?>"><i class="fa-solid fa-thumbs-up"></i> <?php esc_html_e( 'Like', 'alumnus' ); ?></button>
			<button class="btn-light btn-comment" data-post-id="<?php echo (int) $post_id; ?>"><i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
			<button class="btn-light btn-share" data-post-id="<?php echo (int) $post_id; ?>"><i class="fa-solid fa-share"></i> <?php esc_html_e( 'Share', 'alumnus' ); ?></button>
		</div>
		<div class="post-comments" id="comments-<?php echo (int) $post_id; ?>">
			<div class="apc-placeholder"><?php esc_html_e( 'No comments yet.', 'alumnus' ); ?></div>
		</div>
	</article>
	<?php
	$html = ob_get_clean();
	wp_send_json_success(array('html'=>$html));
}
add_action('wp_ajax_alumnus_add_post', 'alumnus_ajax_add_post');
add_action('wp_ajax_nopriv_alumnus_add_post', 'alumnus_ajax_add_post');
