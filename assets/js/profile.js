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
	 * Submit bio field via AJAX
	 */
	window.alumnus_submitAllFields = function() {
		var root = document.getElementById('alumnus-profile-root');
		if (!root) return;

		var ajaxUrl = root.getAttribute('data-ajax-url');
		var userId  = root.getAttribute('data-user-id');
		
		var bioInput = document.getElementById('alumnus-modal-bio-input');
		var saveBtn = document.getElementById('alumnus-modal-save');

		if (!ajaxUrl || !userId || !bioInput) return;

		// Get localized strings
		var strings = window.alumnusProfileStrings || {};

		// Disable save button
		if (saveBtn) { 
			saveBtn.disabled = true; 
			saveBtn.textContent = strings.saving || 'Saving…'; 
		}

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
					window.alumnus_closeModal();
				} else {
					alert((json && json.data && json.data.message) ? json.data.message : (strings.errorBio || 'Failed to update bio.'));
				}
			})
			.catch(function(){ 
				alert(strings.networkErrorBio || 'Network error updating bio.'); 
			})
			.finally(function(){
				if (saveBtn) { 
					saveBtn.disabled = false; 
					saveBtn.textContent = strings.saveChanges || 'Save Changes'; 
				}
			});
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
			// Initialize the count from the textarea value
			bioCharCount.textContent = bioInput.value.length;
			
			// Update on input
			bioInput.addEventListener('input', function() {
				bioCharCount.textContent = bioInput.value.length;
			});
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function(){
			initializeModal();
			alumnus_initSkillsToggle();
			alumnus_initSkillsUI();
			alumnus_initExperienceUI();
		});
	} else {
		initializeModal();
		alumnus_initSkillsToggle();
		alumnus_initSkillsUI();
		alumnus_initExperienceUI();
	}

})();

/**
 * Skills list toggle: show only first two by default, expand/collapse on click.
 * Called on DOM ready and after AJAX updates.
 */
function alumnus_initSkillsToggle(rootEl) {
	var root = rootEl || document;
	var wrappers = root.querySelectorAll('.apc-skills-wrapper');
	wrappers.forEach(function(wrapper){
		var list = wrapper.querySelector('.apc-skills-list');
		var toggle = wrapper.querySelector('.apc-skills-toggle');
		if (!list || !toggle) return;

		var count = parseInt(list.getAttribute('data-skill-count') || '0', 10);
		if (isNaN(count) || count <= 2) {
			wrapper.classList.add('expanded');
			toggle.style.display = 'none';
			return;
		}

		wrapper.classList.remove('expanded');
		toggle.setAttribute('aria-expanded', 'false');

		toggle.addEventListener('click', function(){
			var expanded = wrapper.classList.toggle('expanded');
			toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			toggle.textContent = expanded ? 'Show less' : ('Show all skills (' + count + ')');
		});
	});
}

/**
 * Skills: open/close modal and submit skills via AJAX
 */
function alumnus_initSkillsUI() {
	var root = document.getElementById('alumnus-profile-root');
	if (!root) return;

	var addBtn = document.getElementById('alumnus-skills-add-btn');
	var overlay = document.getElementById('alumnus-skills-modal-overlay');
	var closeBtn = document.getElementById('alumnus-skills-modal-close');
	var cancelBtn = document.getElementById('alumnus-skills-cancel');
	var saveBtn = document.getElementById('alumnus-skills-save');
	var skillsInput = document.getElementById('alumnus-skills-modal-input');

	function open() {
		if (!overlay) return;
		overlay.classList.add('alumnus-modal-ready');
		setTimeout(function(){ overlay.classList.add('active'); }, 10);
	}

	function close() {
		if (!overlay) return;
		overlay.classList.remove('active');
	}

	if (addBtn) addBtn.addEventListener('click', open);
	if (closeBtn) closeBtn.addEventListener('click', close);
	if (cancelBtn) cancelBtn.addEventListener('click', close);
	if (overlay) {
		overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
	}

	if (saveBtn && skillsInput) {
		saveBtn.addEventListener('click', function(){
			var ajaxUrl = root.getAttribute('data-ajax-url');
			var userId = root.getAttribute('data-user-id');
			var nonce = root.getAttribute('data-nonce-skills');

			var skills = skillsInput.value.trim();

			var strings = window.alumnusProfileStrings || {};
			saveBtn.disabled = true;
			saveBtn.textContent = strings.savingSkills || 'Saving…';

			var payload = new FormData();
			payload.append('action', 'alumnus_update_skills');
			payload.append('_ajax_nonce', nonce);
			payload.append('user_id', userId);
			payload.append('skills', skills);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
				.then(function(res){ return res.json(); })
				.then(function(json){
					if (json && json.success) {
						var view = document.getElementById('alumnus-skills-view');
						if (view) {
							view.innerHTML = json.data.html;
							alumnus_initSkillsToggle(view);
						}
						close();
					} else {
						alert((json && json.data && json.data.message) ? json.data.message : (strings.errorSkills || 'Failed to update skills.'));
					}
				})
				.catch(function(){
					alert(strings.networkErrorSkills || 'Network error updating skills.');
				})
				.finally(function(){
					saveBtn.disabled = false;
					saveBtn.textContent = strings.saveChanges || 'Save';
				});
		});
	}
}

/**
 * Experience: open/close modal and submit new experience via AJAX
 */
function alumnus_initExperienceUI() {
	var root = document.getElementById('alumnus-profile-root');
	if (!root) return;

	var addBtn = document.getElementById('alumnus-exp-add-btn');
	var overlay = document.getElementById('alumnus-exp-modal-overlay');
	var closeBtn = document.getElementById('alumnus-exp-modal-close');
	var cancelBtn = document.getElementById('alumnus-exp-cancel');
	var saveBtn = document.getElementById('alumnus-exp-save');
	var currentChk = document.getElementById('alumnus-exp-current');
	var endInput = document.getElementById('alumnus-exp-end');

	// Edit modal elements
	var editOverlay = document.getElementById('alumnus-exp-edit-modal-overlay');
	var editCloseBtn = document.getElementById('alumnus-exp-edit-modal-close');
	var editCancelBtn = document.getElementById('alumnus-exp-edit-cancel');
	var editSaveBtn = document.getElementById('alumnus-exp-edit-save');
	var editId = document.getElementById('alumnus-exp-edit-id');
	var editTitle = document.getElementById('alumnus-exp-edit-title');
	var editCompany = document.getElementById('alumnus-exp-edit-company');
	var editLocation = document.getElementById('alumnus-exp-edit-location');
	var editStart = document.getElementById('alumnus-exp-edit-start');
	var editEnd = document.getElementById('alumnus-exp-edit-end');
	var editCurrent = document.getElementById('alumnus-exp-edit-current');

	function open() {
		if (!overlay) return;
		overlay.classList.add('alumnus-modal-ready');
		setTimeout(function(){ overlay.classList.add('active'); }, 10);
	}
	function close() {
		if (!overlay) return;
		overlay.classList.remove('active');
	}

	function openEdit() {
		if (!editOverlay) return;
		editOverlay.classList.add('alumnus-modal-ready');
		setTimeout(function(){ editOverlay.classList.add('active'); }, 10);
	}
	function closeEdit() {
		if (!editOverlay) return;
		editOverlay.classList.remove('active');
	}

	if (addBtn) addBtn.addEventListener('click', open);
	if (closeBtn) closeBtn.addEventListener('click', close);
	if (cancelBtn) cancelBtn.addEventListener('click', close);
	if (overlay) {
		overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
	}

	if (editOverlay) {
		editOverlay.addEventListener('click', function(e){ if (e.target === editOverlay) closeEdit(); });
	}

	if (currentChk && endInput) {
		function toggleEnd() { endInput.disabled = currentChk.checked; if (currentChk.checked) endInput.value = ''; }
		currentChk.addEventListener('change', toggleEnd);
		toggleEnd();
	}

	if (saveBtn) {
		saveBtn.addEventListener('click', function(){
			var ajaxUrl = root.getAttribute('data-ajax-url');
			var userId = root.getAttribute('data-user-id');
			var nonce = root.getAttribute('data-nonce-exp');

			var title = document.getElementById('alumnus-exp-title').value.trim();
			var company = document.getElementById('alumnus-exp-company').value.trim();
			var location = document.getElementById('alumnus-exp-location').value.trim();
			var start = document.getElementById('alumnus-exp-start').value;
			var end = document.getElementById('alumnus-exp-end').value;

			if (!title || !company || !start) {
				alert('Please fill in Title, Company, and Start date.');
				return;
			}

			var strings = window.alumnusProfileStrings || {};
			saveBtn.disabled = true;
			saveBtn.textContent = strings.savingExperience || 'Saving Experience…';

			var payload = new FormData();
			payload.append('action', 'alumnus_add_experience');
			payload.append('_ajax_nonce', nonce);
			payload.append('user_id', userId);
			payload.append('title', title);
			payload.append('company_name', company);
			payload.append('location', location);
			payload.append('start_date', start);
			payload.append('end_date', currentChk && currentChk.checked ? '' : end);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
				.then(function(res){ return res.json(); })
				.then(function(json){
					if (json && json.success) {
						var view = document.getElementById('alumnus-experience-view');
						if (view) view.innerHTML = json.data.html;
						close();
					} else {
						alert((json && json.data && json.data.message) ? json.data.message : (strings.errorExperience || 'Failed to add experience.'));
					}
				})
				.catch(function(){
					alert(strings.networkErrorExperience || 'Network error adding experience.');
				})
				.finally(function(){
					saveBtn.disabled = false;
					saveBtn.textContent = 'Save';
				});
		});
	}

	// Wire up Edit buttons (event delegation)
	var expView = document.getElementById('alumnus-experience-view');
	if (expView) {
		expView.addEventListener('click', function(e){
			var editBtn = e.target.closest('.apc-exp-edit');
			var delBtn = e.target.closest('.apc-exp-delete');
			var ajaxUrl = root.getAttribute('data-ajax-url');
			var userId = root.getAttribute('data-user-id');
			var nonce = root.getAttribute('data-nonce-exp');

			if (editBtn) {
				var li = editBtn.closest('.apc-exp-item');
				if (!li) return;
				var id = editBtn.getAttribute('data-exp-id');
				var titleEl = li.querySelector('.apc-exp-title');
				var companyEl = li.querySelector('.apc-exp-company');
				var title = titleEl ? titleEl.textContent.trim() : '';
				var company = companyEl ? companyEl.textContent.trim() : '';
				// Location is now in the second .apc-exp-dates element
				var datesEls = li.querySelectorAll('.apc-exp-dates');
				var location = datesEls.length > 1 ? datesEls[1].textContent.trim() : '';
				var startRaw = li.getAttribute('data-start') || '';
				var endRaw = li.getAttribute('data-end') || '';
				// Normalize sentinel values sometimes used by MySQL or backends
				if (endRaw === '0000-00-00' || endRaw === 'null' || endRaw === 'undefined') {
					endRaw = '';
				}

				editId.value = id;
				editTitle.value = title;
				editCompany.value = company;
				editLocation.value = location;
				editStart.value = startRaw;
				if (!endRaw) {
					editEnd.value = '';
					editCurrent.checked = true;
				} else {
					editEnd.value = endRaw;
					editCurrent.checked = false;
				}
				if (editCurrent && editEnd) { editEnd.disabled = editCurrent.checked; }
				// Ensure toggle updates disabled state
				if (editCurrent && editEnd) {
					editCurrent.onchange = function(){
						editEnd.disabled = editCurrent.checked;
						if (editCurrent.checked) editEnd.value = '';
					};
				}
				openEdit();
				return;
			}

			if (delBtn) {
				var id = delBtn.getAttribute('data-exp-id');
				if (!id) return;
				if (!confirm('Delete this experience?')) return;
				var payload = new FormData();
				payload.append('action', 'alumnus_delete_experience');
				payload.append('_ajax_nonce', nonce);
				payload.append('user_id', userId);
				payload.append('experience_id', id);
				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
					.then(function(res){ return res.json(); })
					.then(function(json){
						if (json && json.success) {
							if (expView) expView.innerHTML = json.data.html;
						} else {
							alert((json && json.data && json.data.message) ? json.data.message : 'Failed to delete experience.');
						}
					})
					.catch(function(){ alert('Network error deleting experience.'); });
				return;
			}
		});
	}

	// Save edit
	if (editSaveBtn) {
		editSaveBtn.addEventListener('click', function(){
			var ajaxUrl = root.getAttribute('data-ajax-url');
			var userId = root.getAttribute('data-user-id');
			var nonce = root.getAttribute('data-nonce-exp');

			var id = editId.value;
			var title = editTitle.value.trim();
			var company = editCompany.value.trim();
			var location = editLocation.value.trim();
			var start = editStart.value;
			var end = editEnd.value;
			if (!id || !title || !company) { alert('Please fill in Title and Company.'); return; }

			var payload = new FormData();
			payload.append('action', 'alumnus_update_experience');
			payload.append('_ajax_nonce', nonce);
			payload.append('user_id', userId);
			payload.append('experience_id', id);
			payload.append('title', title);
			payload.append('company_name', company);
			payload.append('location', location);
			payload.append('start_date', start);
			payload.append('end_date', (editCurrent && editCurrent.checked) ? '' : end);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: payload })
				.then(function(res){ return res.json(); })
				.then(function(json){
					if (json && json.success) {
						if (expView) expView.innerHTML = json.data.html;
						closeEdit();
					} else {
						alert((json && json.data && json.data.message) ? json.data.message : 'Failed to update experience.');
					}
				})
				.catch(function(){ alert('Network error updating experience.'); });
		});
	}

	if (editCloseBtn) editCloseBtn.addEventListener('click', closeEdit);
	if (editCancelBtn) editCancelBtn.addEventListener('click', closeEdit);
}

