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
		
		if (!yearFilter || !courseFilter) {
			return;
		}
		
		// Filter function
		function filterAlumni() {
			const selectedYear = yearFilter.value;
			const selectedCourse = courseFilter.value;
			const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
			const cards = document.querySelectorAll('.alumni-card');
			
			cards.forEach(function(card) {
				const yearText = card.querySelector('.ac-year');
				const degreeText = card.querySelector('.ac-degree');
				const nameText = card.querySelector('.ac-name');
				const positionText = card.querySelector('.ac-position');
				const companyText = card.querySelector('.ac-company');
				const locationText = card.querySelector('.ac-location');
				
				let showCard = true;
				
				// Filter by year
				if (selectedYear && yearText) {
					const cardYear = yearText.textContent.replace('CLASS OF ', '');
					if (cardYear !== selectedYear) {
						showCard = false;
					}
				}
				
				// Filter by course
				if (selectedCourse && degreeText) {
					const cardDegree = degreeText.textContent.trim();
					if (cardDegree !== selectedCourse) {
						showCard = false;
					}
				}
				
				// Filter by search term
				if (searchTerm) {
					const searchableText = [
						nameText ? nameText.textContent : '',
						degreeText ? degreeText.textContent : '',
						positionText ? positionText.textContent : '',
						companyText ? companyText.textContent : '',
						locationText ? locationText.textContent : ''
					].join(' ').toLowerCase();
					
					if (!searchableText.includes(searchTerm)) {
						showCard = false;
					}
				}
				
				// Show or hide card
				if (showCard) {
					card.style.display = 'flex';
					card.style.opacity = '0';
					setTimeout(function() {
						card.style.transition = 'opacity 0.3s ease';
						card.style.opacity = '1';
					}, 10);
				} else {
					card.style.display = 'none';
				}
			});
			
			// Check if no results
			const visibleCards = document.querySelectorAll('.alumni-card[style*="display: flex"]');
			const grid = document.querySelector('.alumnus-grid');
			let noResultsMsg = document.querySelector('.no-results-message');
			
			if (visibleCards.length === 0) {
				if (!noResultsMsg) {
					noResultsMsg = document.createElement('div');
					noResultsMsg.className = 'no-results-message';
					noResultsMsg.innerHTML = '<p style="text-align: center; color: #64748b; font-size: 18px; padding: 40px;">No alumni found matching your filters.</p>';
					grid.appendChild(noResultsMsg);
				}
			} else {
				if (noResultsMsg) {
					noResultsMsg.remove();
				}
			}
		}
		
		// Add event listeners
		yearFilter.addEventListener('change', filterAlumni);
		courseFilter.addEventListener('change', filterAlumni);
		
		if (searchInput) {
			searchInput.addEventListener('input', filterAlumni);
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

