<?php
/**
 * Header Navigation Shortcode
 * Usage: [alumnus_header]
 * Static design only (no dynamic menu fetching yet) per request.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function alumnus_render_header_shortcode() {
    ob_start();
    ?>
    <div class="alumnus-header-bar">
        <div class="alumnus-header-inner">
            <div class="ahb-left">
                <a class="ahb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Home">
                    <span class="ahb-logo-icon">🏠</span>
                </a>
                <nav class="ahb-nav" aria-label="Primary">
                    <ul>
                        <li><a href="#" class="active">Request Credentials</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Community Feed</a></li>
                        <li class="current"><a href="#">Directory</a><span class="ahb-indicator" aria-hidden="true"></span></li>
                    </ul>
                </nav>
            </div>
            <div class="ahb-right">
                <button class="ahb-icon-btn" type="button" aria-label="Search" disabled>
                    <span>🔍</span>
                </button>
                <button class="ahb-icon-btn" type="button" aria-label="Profile" disabled>
                    <span>👤</span>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'alumnus_header', 'alumnus_render_header_shortcode' );
