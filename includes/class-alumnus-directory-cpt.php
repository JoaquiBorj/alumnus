<?php
/**
 * Registers CPTs and meta for the Alumni Directory: Batch and Course (students).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Alumnus_Directory_CPT {
	const CPT_BATCH  = 'al_batch';
	const CPT_COURSE = 'al_course';

	// Course meta keys.
	const META_BATCH_ID   = '_al_batch_id';
	const META_FIRST_NAME = '_al_first_name';
	const META_LAST_NAME  = '_al_last_name';
	const META_EMAIL      = '_al_email';
	const META_PASSWORD   = '_al_password_hash';
	const META_COURSE     = '_al_course_name';
	const META_CAREER     = '_al_career';
	const META_ACHIEV     = '_al_achievements';

	// Batch meta keys.
	const META_BATCH_YEAR = '_al_batch_year';

	public function __construct() {
		add_action( 'init', [ $this, 'register_cpts' ] );
		add_action( 'init', [ $this, 'register_meta' ] );

		// Metaboxes and save handlers.
		add_action( 'add_meta_boxes', [ $this, 'add_metaboxes' ] );
		add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );

		// For directory rendering.
		add_filter( 'alumnus/get_batches', [ $this, 'get_batches' ] );
		add_filter( 'alumnus/get_courses_by_batch', [ $this, 'get_courses_by_batch' ], 10, 2 );
	}

	public function register_cpts() {
		// Batch CPT.
		register_post_type( self::CPT_BATCH, [
			'labels' => [
				'name'          => __( 'Batches', 'alumnus' ),
				'singular_name' => __( 'Batch', 'alumnus' ),
			],
			'public'             => true,
			'show_in_rest'       => true,
			'supports'           => [ 'title' ],
			'menu_icon'          => 'dashicons-groups',
			'has_archive'        => false,
			'show_in_menu'       => true,
		] );

		// Course CPT (student record).
		register_post_type( self::CPT_COURSE, [
			'labels' => [
				'name'          => __( 'Courses (Students)', 'alumnus' ),
				'singular_name' => __( 'Course (Student)', 'alumnus' ),
			],
			'public'             => true,
			'show_in_rest'       => true,
			'supports'           => [ 'title', 'editor' ],
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'has_archive'        => false,
			'show_in_menu'       => true,
		] );
	}

	public function register_meta() {
		// Batch year.
		register_post_meta( self::CPT_BATCH, self::META_BATCH_YEAR, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => [ $this, 'can_edit_post_meta' ],
		] );

		// Course fields.
		$course_meta = [
			self::META_BATCH_ID   => [ 'type' => 'integer' ],
			self::META_FIRST_NAME => [ 'type' => 'string' ],
			self::META_LAST_NAME  => [ 'type' => 'string' ],
			self::META_EMAIL      => [ 'type' => 'string' ],
			self::META_PASSWORD   => [ 'type' => 'string', 'show_in_rest' => false ], // store hash only.
			self::META_COURSE     => [ 'type' => 'string' ],
			self::META_CAREER     => [ 'type' => 'string' ],
			self::META_ACHIEV     => [ 'type' => 'string' ],
		];

		foreach ( $course_meta as $key => $schema ) {
			register_post_meta( self::CPT_COURSE, $key, [
				'type'              => $schema['type'],
				'single'            => true,
				'show_in_rest'      => isset( $schema['show_in_rest'] ) ? (bool) $schema['show_in_rest'] : true,
				'sanitize_callback' => in_array( $key, [ self::META_EMAIL ], true ) ? 'sanitize_email' : 'sanitize_text_field',
				'auth_callback'     => [ $this, 'can_edit_post_meta' ],
			] );
		}
	}

	public function can_edit_post_meta( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) { // phpcs:ignore
		return current_user_can( 'edit_post', $post_id );
	}

	public function add_metaboxes() {
		add_meta_box( 'al-batch-meta', __( 'Batch Details', 'alumnus' ), [ $this, 'render_batch_metabox' ], self::CPT_BATCH, 'normal', 'default' );
		add_meta_box( 'al-course-meta', __( 'Course (Student) Details', 'alumnus' ), [ $this, 'render_course_metabox' ], self::CPT_COURSE, 'normal', 'default' );
	}

	public function render_batch_metabox( $post ) {
		wp_nonce_field( 'al_save_meta', 'al_meta_nonce' );
		$batch_year = get_post_meta( $post->ID, self::META_BATCH_YEAR, true );
		echo '<p><label for="al_batch_year">' . esc_html__( 'Batch Year', 'alumnus' ) . '</label><br/>';
		echo '<input type="text" id="al_batch_year" name="al_batch_year" value="' . esc_attr( $batch_year ) . '" class="regular-text" placeholder="2021" />';
		echo '</p>';
	}

	public function render_course_metabox( $post ) {
		wp_nonce_field( 'al_save_meta', 'al_meta_nonce' );

		$values = [
			'batch_id'   => get_post_meta( $post->ID, self::META_BATCH_ID, true ),
			'first_name' => get_post_meta( $post->ID, self::META_FIRST_NAME, true ),
			'last_name'  => get_post_meta( $post->ID, self::META_LAST_NAME, true ),
			'email'      => get_post_meta( $post->ID, self::META_EMAIL, true ),
			'course'     => get_post_meta( $post->ID, self::META_COURSE, true ),
			'career'     => get_post_meta( $post->ID, self::META_CAREER, true ),
			'achievements' => get_post_meta( $post->ID, self::META_ACHIEV, true ),
		];

		// Batch dropdown.
		$batches = $this->get_batches();
		echo '<p><label for="al_batch_id">' . esc_html__( 'Batch', 'alumnus' ) . '</label><br/>';
		echo '<select id="al_batch_id" name="al_batch_id">';
		echo '<option value="">' . esc_html__( 'Select a batch', 'alumnus' ) . '</option>';
		foreach ( $batches as $batch ) {
			$selected = selected( (int) $values['batch_id'], (int) $batch->ID, false );
			$year     = get_post_meta( $batch->ID, self::META_BATCH_YEAR, true );
			echo '<option value="' . esc_attr( $batch->ID ) . '" ' . $selected . '>' . esc_html( $batch->post_title . ( $year ? " (" . $year . ")" : '' ) ) . '</option>';
		}
		echo '</select></p>';

		$fields = [
			'first_name' => [ 'label' => __( 'First Name', 'alumnus' ), 'type' => 'text' ],
			'last_name'  => [ 'label' => __( 'Last Name', 'alumnus' ), 'type' => 'text' ],
			'email'      => [ 'label' => __( 'Email', 'alumnus' ), 'type' => 'email' ],
			'course'     => [ 'label' => __( 'Course', 'alumnus' ), 'type' => 'text' ],
			'career'     => [ 'label' => __( 'Career', 'alumnus' ), 'type' => 'text' ],
		];

		foreach ( $fields as $key => $cfg ) {
			echo '<p><label for="al_' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label><br/>';
			echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="al_' . esc_attr( $key ) . '" name="al_' . esc_attr( $key ) . '" value="' . esc_attr( $values[ $key ] ) . '" class="regular-text" /></p>';
		}

		echo '<p><label for="al_achievements">' . esc_html__( 'Achievements', 'alumnus' ) . '</label><br/>';
		echo '<textarea id="al_achievements" name="al_achievements" class="large-text" rows="4">' . esc_textarea( $values['achievements'] ) . '</textarea></p>';

		echo '<p class="description">' . esc_html__( 'Password will be stored as a hash when entered below. Leave blank to keep unchanged.', 'alumnus' ) . '</p>';
		echo '<p><label for="al_password">' . esc_html__( 'Password', 'alumnus' ) . '</label><br/>';
		echo '<input type="password" id="al_password" name="al_password" class="regular-text" autocomplete="new-password" /></p>';
	}

	public function save_meta( $post_id, $post ) {
		if ( ! in_array( $post->post_type, [ self::CPT_BATCH, self::CPT_COURSE ], true ) ) {
			return;
		}

		if ( ! isset( $_POST['al_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['al_meta_nonce'] ) ), 'al_save_meta' ) ) { // phpcs:ignore
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( self::CPT_BATCH === $post->post_type ) {
			$year = isset( $_POST['al_batch_year'] ) ? sanitize_text_field( wp_unslash( $_POST['al_batch_year'] ) ) : '';
			update_post_meta( $post_id, self::META_BATCH_YEAR, $year );
			return;
		}

		// Course fields.
		$batch_id   = isset( $_POST['al_batch_id'] ) ? (int) $_POST['al_batch_id'] : 0; // phpcs:ignore
		$first_name = isset( $_POST['al_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['al_first_name'] ) ) : '';
		$last_name  = isset( $_POST['al_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['al_last_name'] ) ) : '';
		$email      = isset( $_POST['al_email'] ) ? sanitize_email( wp_unslash( $_POST['al_email'] ) ) : '';
		$course     = isset( $_POST['al_course'] ) ? sanitize_text_field( wp_unslash( $_POST['al_course'] ) ) : '';
		$career     = isset( $_POST['al_career'] ) ? sanitize_text_field( wp_unslash( $_POST['al_career'] ) ) : '';
		$achievements = isset( $_POST['al_achievements'] ) ? sanitize_text_field( wp_unslash( $_POST['al_achievements'] ) ) : '';

		update_post_meta( $post_id, self::META_BATCH_ID, $batch_id );
		update_post_meta( $post_id, self::META_FIRST_NAME, $first_name );
		update_post_meta( $post_id, self::META_LAST_NAME, $last_name );
		update_post_meta( $post_id, self::META_EMAIL, $email );
		update_post_meta( $post_id, self::META_COURSE, $course );
		update_post_meta( $post_id, self::META_CAREER, $career );
		update_post_meta( $post_id, self::META_ACHIEV, $achievements );

		// Handle password hashing if provided.
		if ( ! empty( $_POST['al_password'] ) ) { // phpcs:ignore
			$raw = (string) wp_unslash( $_POST['al_password'] ); // phpcs:ignore
			$hash = wp_hash_password( $raw );
			update_post_meta( $post_id, self::META_PASSWORD, $hash );
		}
	}

	// Data accessors for shortcode/templates.
	public function get_batches() {
		return get_posts( [
			'post_type'      => self::CPT_BATCH,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
	}

	public function get_courses_by_batch( $unused, $batch_id ) { // filter signature compatibility
		return get_posts( [
			'post_type'      => self::CPT_COURSE,
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'   => self::META_BATCH_ID,
					'value' => (int) $batch_id,
					'compare' => '=',
				],
			],
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
	}
}

