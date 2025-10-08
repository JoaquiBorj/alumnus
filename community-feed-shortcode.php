<?php
/**
 * Community Feed Shortcode (static UI only – no functionality yet)
 * Usage: [community_feed]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render the community feed markup (LinkedIn style) – static placeholders.
 * Intentionally no dynamic queries yet per request.
 *
 * @return string
 */
function alumnus_render_community_feed_shortcode() {
	ob_start();
	?>
	<div class="alumnus-community-feed-wrapper">
		<div class="alumnus-feed-layout">
			<!-- Left Sidebar -->
			<aside class="alumnus-feed-sidebar-left">
				<div class="alumnus-profile-card">
					<div class="apc-header">
						<div class="apc-avatar">
							<img src="https://via.placeholder.com/72" alt="Profile" />
						</div>
						<div class="apc-meta">
							<h3 class="apc-name">James Caharian</h3>
							<span class="apc-role">Community Creator</span>
							<p class="apc-since">Created: Sep 29, 2022</p>
						</div>
					</div>
					<ul class="apc-stats">
						<li><strong>Pending Posts:</strong> 8</li>
						<li><strong>Join Requests:</strong> 30</li>
					</ul>
					<div class="apc-section">
						<h4>Events</h4>
						<p class="apc-placeholder">No events to show.</p>
					</div>
					<div class="apc-section">
						<h4>Recent Activity</h4>
						<p class="apc-placeholder">No recent activity.</p>
					</div>
				</div>
			</aside>

			<!-- Main Feed Column -->
			<main class="alumnus-feed-main">
				<div class="alumnus-post-composer">
					<div class="composer-avatar">
						<img src="https://via.placeholder.com/48" alt="You" />
					</div>
					<div class="composer-input">
						<textarea placeholder="Start a post..." disabled></textarea>
					</div>
				</div>

				<!-- Sample Post Card -->
				<article class="alumnus-post-card">
					<header class="post-header">
						<div class="ph-avatar"><img src="https://via.placeholder.com/48" alt="User" /></div>
						<div class="ph-meta">
							<h5 class="ph-name">Daryl Jubiar</h5>
							<div class="ph-date">September 17 • <span class="ph-visibility" title="Public">🌐</span></div>
						</div>
					</header>
					<div class="post-media">
						<img src="https://via.placeholder.com/900x360?text=Class+Reunion" alt="Post media" />
					</div>
					<div class="post-engagement-bar">
						<div class="pe-stats">
							<span class="pe-icon">⭐ 210</span>
							<span class="pe-icon">🔁 72</span>
							<span class="pe-icon">💬 108</span>
						</div>
					</div>
					<div class="post-actions">
						<button class="btn-secondary" disabled>Add to calendar</button>
						<button class="btn-primary" disabled>Attend</button>
					</div>
				</article>

				<!-- Another Placeholder Post -->
				<article class="alumnus-post-card">
					<header class="post-header">
						<div class="ph-avatar"><img src="https://via.placeholder.com/48" alt="User" /></div>
						<div class="ph-meta">
							<h5 class="ph-name">Gian Areño</h5>
							<div class="ph-date">September 17 • 🌐</div>
						</div>
					</header>
					<div class="post-media placeholder">
						<div class="post-placeholder-block">Content placeholder</div>
					</div>
					<div class="post-actions compact">
						<button class="btn-light" disabled>Like</button>
						<button class="btn-light" disabled>Comment</button>
						<button class="btn-light" disabled>Share</button>
					</div>
				</article>
			</main>

			<!-- Right Sidebar -->
			<aside class="alumnus-feed-sidebar-right">
				<div class="alumnus-members-card">
					<h4 class="amc-title">Community Members</h4>
					<ul class="amc-list">
						<li>Daniel Foster</li>
						<li>Maria Ortega</li>
						<li>Kevin Liu</li>
						<li>Aisha Rahman</li>
						<li>Lucas Bennett</li>
						<li>Chloe Gardner</li>
						<li>Hiroshi Tanaka</li>
						<li>Sophia Delgado</li>
						<li>Ethan Brooks</li>
						<li>Amara Singh</li>
						<li>Matteo Ricci</li>
						<li>Fatima Zahra</li>
						<li>Jack Thompson</li>
						<li>Olivia Carter</li>
						<li>Ahmed Khalil</li>
						<li>Hannah Weiss</li>
						<li>Diego Morales</li>
						<li>Grace Johnson</li>
					</ul>
				</div>
			</aside>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'community_feed', 'alumnus_render_community_feed_shortcode' );
