/**
 * Alumni Directory Filters
 * Handles filtering of alumni cards by year and course
 */
(function() {
	'use strict';
	
	function initFilters() {
		const yearFilter = document.getElementById('filter-year');
		const courseFilter = document.getElementById('filter-course');
		const searchInput = document.getElementById('alumnus-directory-search');
		const grid = document.getElementById('alumnus-grid');
		
		if (!yearFilter || !courseFilter || !grid || typeof AlumnusDirectory === 'undefined') {
			return;
		}
		
		// Fetch and render from server
		let currentRequest = null;
		function fetchAlumni() {
			const selectedYear = yearFilter.value;
			const selectedCourse = courseFilter.value;
			const searchTerm = searchInput ? searchInput.value : '';
			
			grid.innerHTML = '<div class="no-results-message"><p>' + (AlumnusDirectory?.i18n?.loading || 'Loading...') + '</p></div>';
			
			const formData = new FormData();
			formData.append('action', 'alumnus_fetch_alumni');
			formData.append('nonce', AlumnusDirectory.nonce);
			formData.append('year', selectedYear);
			formData.append('course_id', selectedCourse);
			formData.append('search', searchTerm);
			formData.append('profile_url', AlumnusDirectory.profile_url || '');
			
			if (currentRequest && typeof currentRequest.abort === 'function') {
				try { currentRequest.abort(); } catch(e) {}
			}
			
			currentRequest = fetch(AlumnusDirectory.ajax_url, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(res) { return res.json(); })
			.then(function(json) {
				if (json && json.success) {
					grid.innerHTML = json.data || '<div class="no-results-message"><p>' + (AlumnusDirectory?.i18n?.noResults || 'No alumni found matching your filters.') + '</p></div>';
				} else {
					grid.innerHTML = '<div class="no-results-message"><p>' + (AlumnusDirectory?.i18n?.noResults || 'No alumni found matching your filters.') + '</p></div>';
				}
			})
			.catch(function() {
				grid.innerHTML = '<div class="no-results-message"><p>' + (AlumnusDirectory?.i18n?.noResults || 'No alumni found matching your filters.') + '</p></div>';
			});
		}
		
		// Add event listeners
		yearFilter.addEventListener('change', fetchAlumni);
		courseFilter.addEventListener('change', fetchAlumni);
		
		if (searchInput) {
			searchInput.addEventListener('input', function() {
				// Debounce input
				if (searchInput._debounce) clearTimeout(searchInput._debounce);
				searchInput._debounce = setTimeout(fetchAlumni, 300);
			});
		}

		// Initial fetch
		fetchAlumni();
	}
	
	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFilters);
	} else {
		initFilters();
	}
	
	// Also initialize after a short delay to catch dynamically loaded content
	window.addEventListener('load', function() {
		setTimeout(initFilters, 100);
	});
})();

