<?php
/**
 * Alumni Directory Shortcode
 * Usage: [alumni_directory]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue directory styles
 */
function alumnus_enqueue_directory_styles() {
	wp_enqueue_style(
		'alumnus-directory',
		plugin_dir_url( __FILE__ ) . 'assets/css/directory.css',
		array(),
		'1.0.0'
	);
}

/**
 * Render the alumni directory markup with search interface
 *
 * @return string
 */
function alumnus_render_directory_shortcode() {
	// Enqueue the directory stylesheet
	alumnus_enqueue_directory_styles();
	
	ob_start();
	?>
	<div class="alumnus-directory-wrapper">
		<div class="alumnus-directory-hero">
			<div class="adh-icon-bg">
				<!-- Network connection icon overlay -->
				<svg class="adh-icon adh-icon-1" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
					<circle cx="50" cy="38" r="18" fill="#04324d"/>
					<path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-2" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
					<circle cx="50" cy="38" r="18" fill="#04324d"/>
					<path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-3" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
					<circle cx="50" cy="38" r="18" fill="#04324d"/>
					<path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
				<svg class="adh-icon adh-icon-4" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
					<circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
					<circle cx="50" cy="38" r="18" fill="#04324d"/>
					<path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
				</svg>
                <svg class="adh-icon adh-icon-5" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
                    <circle cx="50" cy="38" r="18" fill="#04324d"/>
                    <path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
                </svg>
                <svg class="adh-icon adh-icon-6" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="42" fill="white" opacity="0.9"/>
                    <circle cx="50" cy="38" r="18" fill="#04324d"/>
                    <path d="M 20 80 Q 50 50 80 80" stroke="#04324d" stroke-width="4" fill="none"/>
                </svg>
			</div>
			
			<div class="adh-content">
				<h1 class="adh-title">Directory</h1>
				<p class="adh-tagline">Stay connected! Find your peers.</p>
				
				<div class="alumnus-search-box">
					<div class="asb-inner">
						<svg class="asb-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<circle cx="11" cy="11" r="7" stroke="#94a3b8" stroke-width="2"/>
							<path d="M20 20L16.65 16.65" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
						</svg>
						<input 
							type="text" 
							class="asb-input" 
							placeholder="Search Alum" 
							id="alumnus-directory-search"
						/>
					</div>
				</div>
			</div>
		</div>
		<div class="alumnus-directory-content">
			<?php
			// Fetch batches and render students per batch.
			$batches = get_posts([
				'post_type'      => 'al_batch',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]);

			if ( empty( $batches ) ) : ?>
				<p>No batches found yet.</p>
			<?php else : ?>
				<div class="alumnus-batch-list">
				<?php foreach ( $batches as $batch ) :
					$batch_year = get_post_meta( $batch->ID, '_al_batch_year', true );
					$batch_title = $batch->post_title;
					?>
					<section class="alumnus-batch" data-batch-id="<?php echo esc_attr( $batch->ID ); ?>">
						<h2 class="alumnus-batch-title">
							<?php echo esc_html( $batch_title . ( $batch_year ? " ({$batch_year})" : '' ) ); ?>
						</h2>
						<div class="alumnus-student-grid">
							<?php
							$students = get_posts([
								'post_type'      => 'al_course',
								'posts_per_page' => -1,
								'meta_query'     => [[
									'key'   => '_al_batch_id',
									'value' => (int) $batch->ID,
									'compare' => '=',
								]],
								'orderby'        => 'title',
								'order'          => 'ASC',
							]);

							if ( empty( $students ) ) : ?>
								<p class="alumnus-empty">No students in this batch yet.</p>
							<?php else :
								foreach ( $students as $student ) :
									$first = get_post_meta( $student->ID, '_al_first_name', true );
									$last  = get_post_meta( $student->ID, '_al_last_name', true );
									$email = get_post_meta( $student->ID, '_al_email', true );
									$course = get_post_meta( $student->ID, '_al_course_name', true );
									$career = get_post_meta( $student->ID, '_al_career', true );
									$ach   = get_post_meta( $student->ID, '_al_achievements', true );
									$full  = trim( $first . ' ' . $last );
									$search_blob = strtolower( implode( ' ', array_filter( [ $full, $email, $course, $career, $ach, $batch_title, $batch_year ] ) ) );
									?>
									<article class="alumnus-student-card" data-search="<?php echo esc_attr( $search_blob ); ?>">
										<h3 class="alumnus-student-name"><?php echo esc_html( $full ?: get_the_title( $student ) ); ?></h3>
										<?php if ( $course ) : ?><p class="alumnus-student-course"><strong>Course:</strong> <?php echo esc_html( $course ); ?></p><?php endif; ?>
										<?php if ( $email ) : ?><p class="alumnus-student-email"><strong>Email:</strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p><?php endif; ?>
										<?php if ( $career ) : ?><p class="alumnus-student-career"><strong>Career:</strong> <?php echo esc_html( $career ); ?></p><?php endif; ?>
										<?php if ( $ach ) : ?><p class="alumnus-student-achievements"><strong>Achievements:</strong> <?php echo esc_html( $ach ); ?></p><?php endif; ?>
									</article>
								<?php endforeach;
							endif; ?>
						</div>
					</section>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<script>
	(function(){
		const input = document.getElementById('alumnus-directory-search');
		if(!input) return;
		const cards = Array.from(document.querySelectorAll('.alumnus-student-card'));
		const sections = Array.from(document.querySelectorAll('.alumnus-batch'));
		const normalize = s => (s||'').toLowerCase();
		input.addEventListener('input', function(){
			const q = normalize(this.value);
			cards.forEach(card => {
				const blob = card.getAttribute('data-search') || '';
				const show = !q || blob.indexOf(q) !== -1;
				card.style.display = show ? '' : 'none';
			});
			// Hide empty batch sections
			sections.forEach(sec => {
				const visible = sec.querySelector('.alumnus-student-card:not([style*="display: none"])');
				sec.style.display = visible ? '' : 'none';
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

add_shortcode( 'alumni_directory', 'alumnus_render_directory_shortcode' );

