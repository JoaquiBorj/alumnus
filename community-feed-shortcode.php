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

	// Detect tables existence (allow deploying before migrations run without fatal errors)
	$has_posts  = $wpdb->get_var("SHOW TABLES LIKE 'posts'");
	$has_likes  = $wpdb->get_var("SHOW TABLES LIKE 'likes'");
	$has_shares = $wpdb->get_var("SHOW TABLES LIKE 'shares'");
	$has_alumni = $wpdb->get_var("SHOW TABLES LIKE 'alumni'");

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

	// Handle new post submission (simple non-AJAX form)
	$notice_msg = '';
	$notice_class = '';

	// Handle new post submission (simple non-AJAX form)
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['alumnus_post_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alumnus_post_nonce'] ) ), 'alumnus_post' ) ) {
		if ( $current_alumni_id === '' ) {
			$notice_msg = __( 'You must be signed in to post.', 'alumnus' );
			$notice_class = 'alumnus-error-message';
		} elseif ( ! $has_posts ) {
			$notice_msg = __( 'Posts table is not available yet. Please contact the site admin.', 'alumnus' );
			$notice_class = 'alumnus-error-message';
		} else {
			$raw = isset($_POST['alumnus_post_content']) ? wp_unslash( $_POST['alumnus_post_content'] ) : '';
			$content = wp_strip_all_tags( (string) $raw );
			$content = trim( $content );
			if ( $content === '' ) {
				$notice_msg = __( 'Post content cannot be empty.', 'alumnus' );
				$notice_class = 'alumnus-error-message';
			} else {
				// Enforce schema limit (varchar(40))
				if ( function_exists('mb_substr') ) {
					$content = mb_substr( $content, 0, 40, 'UTF-8' );
				} else {
					$content = substr( $content, 0, 40 );
				}
				$res = $wpdb->insert( 'posts', array(
					'user_id'  => $current_alumni_id,
					'content'  => $content,
					'post_date'=> current_time('Y-m-d'),
				), array('%s','%s','%s') );
				if ( false !== $res ) {
					$notice_msg = __( 'Your post has been published.', 'alumnus' );
					$notice_class = 'alumnus-success-message';
				} else {
					$notice_msg = __( 'Unable to publish your post. Please try again.', 'alumnus' );
					$notice_class = 'alumnus-error-message';
				}
			}
		}
	}

	// Fetch posts after potential insertion
	$posts = array();
	if ( $has_posts ) {
		// Build dynamic SELECT with optional subqueries for counts (only include if tables exist)
		$like_count_sql  = $has_likes  ? "(SELECT COUNT(*) FROM likes  l WHERE l.post_id = p.post_id) AS like_count," : "0 AS like_count,";
		$share_count_sql = $has_shares ? "(SELECT COUNT(*) FROM shares s WHERE s.post_id = p.post_id) AS share_count," : "0 AS share_count,";
		// Comments placeholder (table not present yet)
		$comment_count_sql = "0 AS comment_count";

		$name_join = $has_alumni ? "LEFT JOIN alumni a ON a.user_id = p.user_id" : "";
		$name_fields = $has_alumni ? "a.firstname, a.lastname," : "";

		$sql = "SELECT p.post_id, p.user_id, $name_fields p.content, p.post_date,
				$like_count_sql $share_count_sql $comment_count_sql
				FROM posts p $name_join
				ORDER BY p.post_date DESC
				LIMIT 20"; // Hard cap for initial feed performance.
		$posts = $wpdb->get_results( $sql );
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
							<span class="apc-role">Community Member</span>
							<p class="apc-since"><?php echo esc_html__( 'Welcome to the community feed', 'alumnus' ); ?></p>
						</div>
					</div>
					<ul class="apc-stats">
						<li><strong><?php esc_html_e( 'Posts Loaded:', 'alumnus' ); ?></strong> <?php echo (int) count( $posts ); ?></li>
						<li><strong><?php esc_html_e( 'Likes Table:', 'alumnus' ); ?></strong> <?php echo $has_likes ? '✓' : '–'; ?></li>
					</ul>
					<div class="apc-section">
						<h4><?php esc_html_e( 'Events', 'alumnus' ); ?></h4>
						<p class="apc-placeholder"><?php esc_html_e( 'No events to show.', 'alumnus' ); ?></p>
					</div>
					<div class="apc-section">
						<h4><?php esc_html_e( 'Recent Activity', 'alumnus' ); ?></h4>
						<p class="apc-placeholder"><?php esc_html_e( 'Activity tracking coming soon.', 'alumnus' ); ?></p>
					</div>
				</div>
			</aside>

			<!-- Main Feed Column -->
			<main class="alumnus-feed-main">
				<?php if ( ! empty( $notice_msg ) ) : ?>
					<div class="<?php echo esc_attr( $notice_class ); ?>"><?php echo esc_html( $notice_msg ); ?></div>
				<?php endif; ?>
				<div class="alumnus-post-composer">
					<div class="composer-avatar">
						<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $sidebar_initials !== '' ? $sidebar_initials : 'Y' ); ?></span></div>
					</div>
					<div class="composer-input">
						<?php if ( $current_alumni_id !== '' && $has_posts ) : ?>
							<form method="post">
								<textarea name="alumnus_post_content" placeholder="<?php esc_attr_e( 'Start a post...', 'alumnus' ); ?>" maxlength="40" required></textarea>
								<?php wp_nonce_field( 'alumnus_post', 'alumnus_post_nonce' ); ?>
								<div style="margin-top:8px;">
									<button type="submit" name="alumnus_new_post" class="btn-primary"><?php esc_html_e( 'Post', 'alumnus' ); ?></button>
								</div>
							</form>
						<?php else : ?>
							<textarea placeholder="<?php esc_attr_e( 'Sign in to post.', 'alumnus' ); ?>" disabled></textarea>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( empty( $posts ) ) : ?>
					<article class="alumnus-post-card">
						<div class="post-media placeholder">
							<div class="post-placeholder-block"><?php esc_html_e( 'No posts yet. Be the first to share!', 'alumnus' ); ?></div>
						</div>
						<div class="post-actions compact">
							<button class="btn-light" disabled><?php esc_html_e( 'Like', 'alumnus' ); ?></button>
							<button class="btn-light" disabled><?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
							<button class="btn-light" disabled><?php esc_html_e( 'Share', 'alumnus' ); ?></button>
						</div>
					</article>
				<?php else : ?>
					<?php foreach ( $posts as $post_row ) :
						$full_name = '';
						if ( isset( $post_row->firstname ) || isset( $post_row->lastname ) ) {
							$full_name = trim( (string) $post_row->firstname . ' ' . (string) $post_row->lastname );
						}
						$display_name = $full_name !== '' ? $full_name : $post_row->user_id;
						?>
						<article class="alumnus-post-card" data-post-id="<?php echo (int) $post_row->post_id; ?>">
							<header class="post-header">
								<?php
									$ai1 = '';
									$ai2 = '';
									if ( ! empty( $post_row->firstname ) || ! empty( $post_row->lastname ) ) {
										$ai1 = ! empty( $post_row->firstname ) ? strtoupper( substr( (string) $post_row->firstname, 0, 1 ) ) : '';
										$ai2 = ! empty( $post_row->lastname )  ? strtoupper( substr( (string) $post_row->lastname, 0, 1 ) )  : '';
									} else {
										$ai1 = strtoupper( substr( (string) $post_row->user_id, 0, 1 ) );
									}
									$author_initials = $ai1 . $ai2;
								?>
								<div class="apc-avatar apc-avatar--sm"><span class="apc-initials"><?php echo esc_html( $author_initials !== '' ? $author_initials : 'U' ); ?></span></div>
								<div class="ph-meta">
									<h5 class="ph-name"><?php echo esc_html( $display_name ); ?></h5>
									<div class="ph-date">
										<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $post_row->post_date ) ) ); ?> • <span class="ph-visibility" title="<?php esc_attr_e( 'Public', 'alumnus' ); ?>">🌐</span>
									</div>
								</div>
							</header>
							<div class="post-media placeholder">
								<div class="post-placeholder-block"><?php echo esc_html( $post_row->content ); ?></div>
							</div>
							<div class="post-engagement-bar">
								<div class="pe-stats">
									<span class="pe-icon" title="<?php esc_attr_e( 'Likes', 'alumnus' ); ?>">⭐ <?php echo (int) $post_row->like_count; ?></span>
									<span class="pe-icon" title="<?php esc_attr_e( 'Shares', 'alumnus' ); ?>">🔁 <?php echo (int) $post_row->share_count; ?></span>
									<span class="pe-icon" title="<?php esc_attr_e( 'Comments', 'alumnus' ); ?>">💬 <?php echo (int) $post_row->comment_count; ?></span>
								</div>
							</div>
							<div class="post-actions compact">
								<button class="btn-light" disabled><?php esc_html_e( 'Like', 'alumnus' ); ?></button>
								<button class="btn-light" disabled><?php esc_html_e( 'Comment', 'alumnus' ); ?></button>
								<button class="btn-light" disabled><?php esc_html_e( 'Share', 'alumnus' ); ?></button>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</main>

			<!-- Right Sidebar -->
			<aside class="alumnus-feed-sidebar-right">
				<div class="alumnus-members-card">
					<h4 class="amc-title"><?php esc_html_e( 'Community Members', 'alumnus' ); ?></h4>
					<ul class="amc-list">
						<?php
						// Lightweight member listing from alumni table if available
						if ( $has_alumni ) {
							$members = $wpdb->get_results( "SELECT firstname, lastname FROM alumni ORDER BY lastname ASC LIMIT 25" );
							if ( ! empty( $members ) ) {
								foreach ( $members as $m ) {
									$mn = trim( $m->firstname . ' ' . $m->lastname );
									echo '<li>' . esc_html( $mn ) . '</li>';
								}
							} else {
								echo '<li>' . esc_html__( 'No members found.', 'alumnus' ) . '</li>';
							}
						} else {
							// Fallback placeholders
							echo '<li>' . esc_html__( 'Members unavailable (alumni table missing).', 'alumnus' ) . '</li>';
						}
						?>
					</ul>
				</div>
			</aside>
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
