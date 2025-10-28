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
            <div class="ahb-right">
                <a href="http://localhost/alumnus/wordpress/directory/" class="ahb-directory-btn">Directory</a>
                <a href="http://localhost/alumnus/wordpress/alumni-profile/" class="ahb-profile-btn">Profile</a>
                <a href="http://localhost/alumnus/wordpress/landing-page/" class="ahb-logout-btn">Logout</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode( 'alumnus_header', 'alumnus_render_header_shortcode' );
