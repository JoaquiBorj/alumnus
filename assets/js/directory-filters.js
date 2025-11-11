/**
 * Alumni Directory Filters
 * Handles filtering of alumni cards by year and course
 */

// Auto-load alumni results on initial page view without requiring an explicit Search click.
(function() {
	'use strict';
    
	// Prevent double initialization if script runs twice (DOMContentLoaded + load timeout)
	function initFilters() {
		if (window.AlumnusDirectoryFiltersInitialized) { return; }
		window.AlumnusDirectoryFiltersInitialized = true;
		const yearFilter = document.getElementById('filter-year');
		const courseFilter = document.getElementById('filter-course');
		const searchInput = document.getElementById('alumnus-directory-search');
		const searchTextButton = document.getElementById('alumnus-directory-submit-btn'); // Text button
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
		
		// Add event listeners (no real-time; only on button click or Enter key)
		if (searchTextButton) {
			searchTextButton.addEventListener('click', fetchAlumni);
		}

		// Also allow Enter key in the search input to trigger search
		if (searchInput) {
			searchInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					fetchAlumni();
				}
			});
		}

		// Initial automatic fetch so users immediately see results.
		// Only trigger if the grid exists and hasn't already been populated during this run.
		if (grid && grid.querySelector('[data-initial="1"]')) {
			fetchAlumni();
		} else if (grid && grid.children.length === 0) {
			fetchAlumni();
		}
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

