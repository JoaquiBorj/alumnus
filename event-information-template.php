<?php
/**
 * Event Information Template and Shortcode
 *
 * Provides a frontend display for a single event including: image, interested count,
 * organizers, date, location, description and action buttons.
 *
 * Usage: [alumnus_event_info id="123"]
 * If no id attribute is passed the current post ID is used.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Fetch event meta with sensible defaults.
 *
 * @param int $event_id Event post ID.
 * @return array
 */
function alumnus_get_event_info( $event_id ) {
	$fields = array(
		'image_id'        => 0,
		'interested_count'=> 0,
		'organizers'      => array(), // array of taxonomy term IDs or plain strings.
		'date'            => '',
		'location'        => '',
		'description'     => '',
	);

	// Core fields from post & meta.
	$thumbnail_id = get_post_thumbnail_id( $event_id );
	if ( $thumbnail_id ) {
		$fields['image_id'] = $thumbnail_id;
	}

	$fields['interested_count'] = (int) get_post_meta( $event_id, 'interested_count', true );
	$fields['date']             = get_post_meta( $event_id, 'event_date', true );
	$fields['location']         = get_post_meta( $event_id, 'event_location', true );
	$fields['description']      = get_post_meta( $event_id, 'event_description', true );

	// Organizers can be stored either as a taxonomy (e.g., organizer) or post meta (comma separated).
	$organizer_terms = get_the_terms( $event_id, 'organizer' );
	if ( ! is_wp_error( $organizer_terms ) && ! empty( $organizer_terms ) ) {
		$fields['organizers'] = wp_list_pluck( $organizer_terms, 'name' );
	} else {
		$raw_organizers = get_post_meta( $event_id, 'event_organizers', true );
		if ( $raw_organizers ) {
			$fields['organizers'] = array_map( 'trim', explode( ',', $raw_organizers ) );
		}
	}

	return $fields;
}

/**
 * Render badge helper.
 */
function alumnus_event_badge( $text, $color = 'default' ) {
	$colors = array(
		'green'  => '#4CAF50',
		'orange' => '#F37F3C',
		'teal'   => '#00A6C7',
		'default'=> '#E0E0E0',
	);
	$bg = isset( $colors[ $color ] ) ? $colors[ $color ] : $colors['default'];
	return '<span class="alumnus-badge alumnus-badge-' . esc_attr( $color ) . '" style="display:inline-block;padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;background:' . esc_attr( $bg ) . ';line-height:1;">' . esc_html( $text ) . '</span>';
}

/**
 * Shortcode callback.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function alumnus_event_info_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'id' => get_the_ID(),
	), $atts, 'alumnus_event_info' );

	$event_id = absint( $atts['id'] );
	if ( ! $event_id || 'publish' !== get_post_status( $event_id ) ) {
		return '';
	}

	$event = get_post( $event_id );
	$data  = alumnus_get_event_info( $event_id );

	$image_html = '';
	if ( $data['image_id'] ) {
		$image_html = wp_get_attachment_image( $data['image_id'], 'large', false, array( 'class' => 'alumnus-event-image' ) );
	} else {
		$image_html = '<div class="alumnus-event-image alumnus-event-image--placeholder" style="width:100%;height:100%;background:#f1f1f1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;color:#777;">No Image</div>';
	}

	$organizers_html = '';
	if ( ! empty( $data['organizers'] ) ) {
		$badges = array();
		foreach ( $data['organizers'] as $org ) {
			$badges[] = alumnus_event_badge( $org, 'green' );
		}
		$organizers_html = implode( ' ', $badges );
	}

	$date_badge     = $data['date'] ? alumnus_event_badge( date_i18n( 'F j, Y', strtotime( $data['date'] ) ), 'orange' ) : '';
	$location_badge = $data['location'] ? alumnus_event_badge( $data['location'], 'teal' ) : '';

	$interested = intval( $data['interested_count'] );

	ob_start();
	?>
	<div class="alumnus-event-wrapper" style="--alumnus-gap:40px;display:grid;grid-template-columns:320px 1fr;gap:var(--alumnus-gap);align-items:start;margin:40px 0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
		<div class="alumnus-event-left" style="text-align:center;">
			<div class="alumnus-event-image-wrapper" style="width:310px;height:310px;border-radius:50%;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.12);margin:0 auto 30px;position:relative;background:#fff;">
				<?php echo $image_html; //phpcs:ignore ?>
			</div>
			<a href="#" class="alumnus-event-join" style="display:inline-flex;align-items:center;gap:8px;background:#06b365;color:#fff;text-decoration:none;padding:14px 34px;border-radius:40px;font-size:18px;font-weight:600;transition:.25s;box-shadow:0 6px 16px -4px rgba(6,179,101,.45);">Join Event <span style="font-size:20px;line-height:0;">→</span></a>
		</div>
		<div class="alumnus-event-right" style="border-left:3px solid #111;padding-left:50px;">
			<h2 style="font-size:38px;margin:0 0 14px;font-weight:700;line-height:1.15;"><?php echo esc_html( get_the_title( $event ) ); ?></h2>
			<p style="margin:0 0 26px;font-size:14px;font-weight:500;color:#222;"><?php echo esc_html( $interested ); ?> people interested</p>
			<div class="alumnus-event-meta" style="display:grid;grid-template-columns:140px 1fr;row-gap:18px;column-gap:10px;font-size:16px;">
				<div style="font-weight:700;">Organizers:</div>
				<div><?php echo $organizers_html; //phpcs:ignore ?></div>
				<div style="font-weight:700;">When:</div>
				<div><?php echo $date_badge; //phpcs:ignore ?></div>
				<div style="font-weight:700;">Where:</div>
				<div><?php echo $location_badge; //phpcs:ignore ?></div>
			</div>
			<div class="alumnus-event-description" style="margin-top:34px;">
				<h3 style="font-size:34px;margin:0 0 14px;font-weight:700;">About The Event</h3>
				<div style="font-size:18px;line-height:1.55;color:#111;max-width:760px;">
					<?php echo wpautop( esc_html( $data['description'] ? $data['description'] : $event->post_excerpt ) ); ?>
				</div>
				<div style="margin-top:40px;">
					<a href="<?php echo esc_url( get_permalink( $event ) ); ?>" class="alumnus-event-readmore" style="display:inline-flex;align-items:center;gap:6px;padding:20px 32px;border:2px solid #111;border-radius:40px;font-weight:600;font-size:18px;text-decoration:none;color:#111;transition:.25s;">Read More <span style="font-size:22px;line-height:0;">↓</span></a>
				</div>
			</div>
		</div>
	</div>
	<style>
		@media (max-width: 900px){
			.alumnus-event-wrapper{grid-template-columns:1fr;}
			.alumnus-event-right{border-left:none;padding-left:0;}
			.alumnus-event-left{margin-bottom:20px;}
		}
		.alumnus-event-join:hover{background:#049250;}
		.alumnus-event-readmore:hover{background:#111;color:#fff;}
	</style>
	<?php
	return ob_get_clean();
}
add_shortcode( 'alumnus_event_info', 'alumnus_event_info_shortcode' );
