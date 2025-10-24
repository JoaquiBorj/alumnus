/**
 * Alumni Profile JavaScript
 */

(function() {
	'use strict';

	/**
	 * Toggle like button state
	 */
	window.alumnus_toggleLike = function(btn) {
		if (btn.classList.contains('liked')) {
			btn.classList.remove('liked');
			btn.querySelector('.apc-action-text').textContent = 'Like';
		} else {
			btn.classList.add('liked');
			btn.querySelector('.apc-action-text').textContent = 'Liked';
		}
	};

	/**
	 * Open the edit profile modal
	 */
	window.alumnus_openModal = function() {
		var modalOverlay = document.getElementById('alumnus-modal-overlay');
		if (modalOverlay) {
			// Enable transitions before opening (only after first interaction)
			modalOverlay.classList.add('alumnus-modal-ready');
			// Small delay to ensure transition class is applied before showing
			setTimeout(function() {
				modalOverlay.classList.add('active');
			}, 10);
		}
	};

	/**
	 * Close the edit profile modal
	 */
	window.alumnus_closeModal = function() {
		var modalOverlay = document.getElementById('alumnus-modal-overlay');
		if (modalOverlay) {
			modalOverlay.classList.remove('active');
		}
	};

	/**
	 * Submit all profile fields via AJAX
	 */
	window.alumnus_submitAllFields = function() {
		var root = document.getElementById('alumnus-profile-root');
		if (!root) return;

		var ajaxUrl = root.getAttribute('data-ajax-url');
		var userId  = root.getAttribute('data-user-id');
		
		var careerInput = document.getElementById('alumnus-modal-career-input');
		var bioInput = document.getElementById('alumnus-modal-bio-input');
		var skillsInput = document.getElementById('alumnus-modal-skills-input');
		var saveBtn = document.getElementById('alumnus-modal-save');

		if (!ajaxUrl || !userId || !careerInput || !bioInput || !skillsInput) return;

		// Get localized strings
		var strings = window.alumnusProfileStrings || {};

		// Disable save button
		if (saveBtn) { 
			saveBtn.disabled = true; 
			saveBtn.textContent = strings.saving || 'Saving…'; 
		}

		var completedRequests = 0;
		var totalRequests = 3;
		var hasError = false;

		function checkCompletion() {
			completedRequests++;
			if (completedRequests === totalRequests) {
				if (saveBtn) { 
					saveBtn.disabled = false; 
					saveBtn.textContent = strings.saveChanges || 'Save Changes'; 
				}
				if (!hasError) {
					window.alumnus_closeModal();
				}
			}
		}

		// Update Career
		var careerPayload = new FormData();
		careerPayload.append('action', 'alumnus_update_career');
		careerPayload.append('_ajax_nonce', root.getAttribute('data-nonce'));
		careerPayload.append('user_id', userId);
		careerPayload.append('career', careerInput.value);

		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: careerPayload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					var view = document.getElementById('alumnus-career-view');
					if (view) { view.innerHTML = json.data.html; }
				} else {
					hasError = true;
					alert((json && json.data && json.data.message) ? json.data.message : (strings.errorCareer || 'Failed to update career.'));
				}
			})
			.catch(function(){ 
				hasError = true;
				alert(strings.networkErrorCareer || 'Network error updating career.'); 
			})
			.finally(checkCompletion);

		// Update Bio
		var bioPayload = new FormData();
		bioPayload.append('action', 'alumnus_update_bio_note');
		bioPayload.append('_ajax_nonce', root.getAttribute('data-nonce-bio'));
		bioPayload.append('user_id', userId);
		bioPayload.append('bio_note', bioInput.value);

		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: bioPayload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					var view = document.getElementById('alumnus-bio-view');
					if (view) { view.innerHTML = json.data.html; }
				} else {
					hasError = true;
					alert((json && json.data && json.data.message) ? json.data.message : (strings.errorBio || 'Failed to update bio.'));
				}
			})
			.catch(function(){ 
				hasError = true;
				alert(strings.networkErrorBio || 'Network error updating bio.'); 
			})
			.finally(checkCompletion);

		// Update Skills
		var skillsPayload = new FormData();
		skillsPayload.append('action', 'alumnus_update_skills');
		skillsPayload.append('_ajax_nonce', root.getAttribute('data-nonce-skills'));
		skillsPayload.append('user_id', userId);
		skillsPayload.append('skills', skillsInput.value);

		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: skillsPayload })
			.then(function(res){ return res.json(); })
			.then(function(json){
				if (json && json.success) {
					var view = document.getElementById('alumnus-skills-view');
					if (view) { view.innerHTML = json.data.html; }
				} else {
					hasError = true;
					alert((json && json.data && json.data.message) ? json.data.message : (strings.errorSkills || 'Failed to update skills.'));
				}
			})
			.catch(function(){ 
				hasError = true;
				alert(strings.networkErrorSkills || 'Network error updating skills.'); 
			})
			.finally(checkCompletion);
	};

	/**
	 * Initialize modal event listeners
	 */
	function initializeModal() {
		var modalOverlay = document.getElementById('alumnus-modal-overlay');
		var modalClose = document.getElementById('alumnus-modal-close');
		var modalSave = document.getElementById('alumnus-modal-save');
		var modalCancel = document.getElementById('alumnus-modal-cancel');
		var bioInput = document.getElementById('alumnus-modal-bio-input');
		var bioCharCount = document.getElementById('alumnus-bio-char-count');

		if (modalClose) {
			modalClose.addEventListener('click', window.alumnus_closeModal);
		}

		if (modalSave) {
			modalSave.addEventListener('click', window.alumnus_submitAllFields);
		}

		if (modalCancel) {
			modalCancel.addEventListener('click', window.alumnus_closeModal);
		}

		if (modalOverlay) {
			modalOverlay.addEventListener('click', function(event) {
				// Close only if clicking the overlay itself, not the card
				if (event.target === modalOverlay) {
					window.alumnus_closeModal();
				}
			});
		}

		// Update bio character counter
		if (bioInput && bioCharCount) {
			bioInput.addEventListener('input', function() {
				bioCharCount.textContent = bioInput.value.length;
			});
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeModal);
	} else {
		initializeModal();
	}

})();

