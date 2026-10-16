/**
 * WP Quiet Admin JavaScript
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// 1. Defensively silence isolated client-rendered review prompts (e.g. React/Vue SPAs)
		initSpaReviewSuppression();

		// Only run hub page logic if on the Quiet Admin hub screen
		if (typeof wpQuietAdminData !== 'undefined' && !wpQuietAdminData.isHubPage && $('.wp-quiet-admin-wrap').length === 0) {
			return;
		}

		// Prevent WordPress common.js from moving notices inside our cards
		$('.qa-raw-notice-wrapper').find('div.updated, div.error, div.notice').addClass('inline');

		initDismissNotice();
		initDismissTab();
		initClearAll();
		initQuickFilters();
		initSettingsCheckboxes();
		initCollapsibleSections();
		initWpSubfilters();
		initMutedSubfilters();
		initHistorySubfilters();
		initSnooze();
		initUnsnooze();
		initUndoToast();
		initRestoreNotice();
		initPurgeHistory();
		initBulkActions();
		initWakeAll();
	});

	/**
	 * Defensively hides isolated client-rendered review prompts (e.g. React/Vue SPAs)
	 * that bypass PHP admin_notices output buffering, without altering page layouts.
	 */
	function initSpaReviewSuppression() {
		var selectors = [
			'.cff-review-notice',
			'.cff_review_notice',
			'.cff_review_notice_step_1',
			'.sbi-review-notice',
			'.sbi_review_notice',
			'.sb-review-notice',
			'.cff-notice-alert',
			'#cff-admin-app .cff-review-notice',
			'#sbi-admin-app .sbi-review-notice',
			'#cff-admin-app .cff_review_notice',
			'#sbi-admin-app .sbi_review_notice'
		];

		var selectorStr = selectors.join(',');

		// Immediate hide
		$(selectorStr).hide();

		// If a modern SPA container exists, observe child additions to catch deferred renders
		var appContainer = document.querySelector('#cff-admin-app, #sbi-admin-app, #sb-admin-app');
		if (appContainer && window.MutationObserver) {
			var observer = new MutationObserver(function () {
				$(selectorStr, appContainer).hide();
			});
			observer.observe(appContainer, { childList: true, subtree: true });
		}
	}

	var lastDismissedItem = null;
	var undoToastTimer    = null;

	/**
	 * Handle single notice dismissal via AJAX.
	 */
	function initDismissNotice() {
		// Delegate clicks on native notice-dismiss 'X' buttons to the card's dismiss handler
		$(document).on('click', '.qa-raw-notice-wrapper .notice-dismiss', function (e) {
			e.preventDefault();
			var $card = $(this).closest('.qa-notice-card');
			$card.find('.qa-btn-dismiss').trigger('click');
		});

		$(document).on('click', '.qa-btn-dismiss', function (e) {
			e.preventDefault();

			var $btn = $(this);
			var $card = $btn.closest('.qa-notice-card');
			var noticeId = $card.data('notice-id');

			if (!noticeId) {
				return;
			}

			$card.addClass('is-dismissing');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_dismiss',
					notice_id: noticeId,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						var $cardClone = $card.clone(true);
						var $parentGrid = $card.parent();

						$card.slideUp(250, function () {
							$(this).remove();
							updateBadgeCounters(response.data.counts);
							checkEmptyState();
							showUndoToast(noticeId, $cardClone, $parentGrid);
						});
					} else {
						$card.removeClass('is-dismissing');
						alert((response.data && response.data.message) || 'Error dismissing notice.');
					}
				}
			).fail(function () {
				$card.removeClass('is-dismissing');
			});
		});
	}

	/**
	 * Handle Dismiss All for a specific tab.
	 */
	function initDismissTab() {
		$(document).on('click', '.qa-btn-dismiss-tab', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var tab = $btn.data('tab');

			if (!tab) return;

			var tabNames = {
				wordpress: 'WordPress Core notices',
				high: 'Critical notices',
				medium: 'Warnings & Updates',
				muted: 'Reviews & Promos'
			};
			var label = tabNames[tab] || 'notices in this tab';

			if (!confirm('Are you sure you want to dismiss all ' + label + '? They will be moved to the Archive where you can restore them at any time.')) {
				return;
			}

			$btn.prop('disabled', true);
			var $cards = $('.qa-notices-grid .qa-notice-card');
			var $cardsClone = $cards.clone(true);
			var $parentGrid = $('.qa-notices-grid');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_dismiss_tab',
					tab: tab,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$cards.slideUp(250, function () {
							$(this).remove();
							checkEmptyState();
						});
						updateBadgeCounters(response.data.counts);
						showUndoToast(null, $cardsClone, $parentGrid, 'All ' + label + ' dismissed.', response.data.dismissed_ids);
					} else {
						$btn.prop('disabled', false);
						alert((response.data && response.data.message) || 'Error dismissing notices.');
					}
				}
			).fail(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	/**
	 * Handle Clear All notices.
	 */
	function initClearAll() {
		$('.qa-btn-clear-all').on('click', function (e) {
			e.preventDefault();

			if (!confirm(wpQuietAdminData.strings.confirmClear)) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_clear_all',
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$('.qa-notices-grid').slideUp(200, function () {
							$(this).remove();
							checkEmptyState();
						});
						updateBadgeCounters(response.data.counts);
					} else {
						$btn.prop('disabled', false);
						alert((response.data && response.data.message) || 'Error clearing notices.');
					}
				}
			).fail(function (xhr) {
				$btn.prop('disabled', false);
				alert((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error clearing notices.');
			});
		});
	}

	/**
	 * Handle quick Whitelist / Blacklist actions on notice cards.
	 */
	function initQuickFilters() {
		// Show in Admin / Pass-Through Toggle
		$(document).on('click', '.qa-btn-whitelist', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $card = $btn.closest('.qa-notice-card');
			var slug = $card.data('plugin-slug');

			if (!slug) return;

			var isCurrentlyActive = $btn.hasClass('is-active');
			var action = isCurrentlyActive ? 'normal' : 'whitelist';

			$btn.prop('disabled', true).text('Updating...');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_toggle_plugin',
					plugin_slug: slug,
					plugin_action: action,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					$btn.prop('disabled', false);
					if (response && response.success) {
						if (isCurrentlyActive) {
							$btn.removeClass('is-active button-primary').html('<span class="dashicons dashicons-visibility"></span> Show in Admin');
						} else {
							$btn.addClass('is-active button-primary').html('<span class="dashicons dashicons-yes"></span> Showing in Admin');
						}
					} else {
						$btn.text(isCurrentlyActive ? 'Showing in Admin' : 'Show in Admin');
						alert((response.data && response.data.message) || 'Error updating rule.');
					}
				}
			);
		});

		// Blacklist Plugin
		$(document).on('click', '.qa-btn-blacklist', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $card = $btn.closest('.qa-notice-card');
			var slug = $card.data('plugin-slug');

			if (!slug) return;

			$btn.prop('disabled', true).text('Blacklisting...');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_toggle_plugin',
					plugin_slug: slug,
					plugin_action: 'blacklist',
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						// Fade out and remove all cards for this blacklisted plugin
						$('.qa-notice-card[data-plugin-slug="' + slug + '"]').slideUp(250, function () {
							$(this).remove();
							checkEmptyState();
						});
						if (response.data && response.data.counts) {
							updateBadgeCounters(response.data.counts);
						}
					} else {
						$btn.prop('disabled', false).text('Blacklist Plugin');
					}
				}
			);
		});
	}

	/**
	 * Interactive 3-State Segmented Control for plugin notice rules.
	 */
	function initSettingsCheckboxes() {
		$(document).on('click', '.qa-segment', function () {
			var $label = $(this);
			var $control = $label.closest('.qa-segmented-control');
			$control.find('.qa-segment').removeClass('is-active');
			$label.addClass('is-active');
			$label.find('input[type="radio"]').prop('checked', true).trigger('change');
		});
	}

	/**
	 * Interactive sub-filter pills inside WordPress Core tab.
	 */
	function initWpSubfilters() {
		$(document).on('click', '.qa-wp-filter-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var filter = $btn.data('filter');

			$('.qa-wp-filter-btn').removeClass('is-active');
			$btn.addClass('is-active');

			if (filter === 'all') {
				$('.qa-notices-grid .qa-notice-card').fadeIn(150);
			} else {
				$('.qa-notices-grid .qa-notice-card').hide();
				$('.qa-notices-grid .qa-notice-card.qa-card-' + filter).fadeIn(150);
			}
		});
	}

	/**
	 * Interactive sub-filter pills inside Muted Notices tab.
	 */
	function initMutedSubfilters() {
		$(document).on('click', '.qa-muted-filter-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var filter = $btn.data('filter');

			$('.qa-muted-filter-btn').removeClass('is-active');
			$btn.addClass('is-active');

			if (filter === 'all') {
				$('.qa-notices-grid .qa-notice-card').fadeIn(150);
			} else {
				$('.qa-notices-grid .qa-notice-card').hide();
				$('.qa-notices-grid .qa-notice-card[data-priority="' + filter + '"]').fadeIn(150);
			}
		});
	}

	/**
	 * Interactive sub-filter pills inside Archive Tab.
	 */
	function initHistorySubfilters() {
		$(document).on('click', '.qa-history-filter-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var filter = $btn.data('filter');

			$('.qa-history-filter-btn').removeClass('is-active');
			$btn.addClass('is-active');

			if (filter === 'all') {
				$('.qa-notices-grid .qa-notice-card').fadeIn(150);
			} else if (filter === 'wordpress') {
				$('.qa-notices-grid .qa-notice-card').hide();
				$('.qa-notices-grid .qa-notice-card[data-is-core="1"]').fadeIn(150);
			} else {
				$('.qa-notices-grid .qa-notice-card').hide();
				$('.qa-notices-grid .qa-notice-card[data-priority-group="' + filter + '"]').fadeIn(150);
			}
		});
	}

	/**
	 * Handle Snooze notice actions.
	 */
	function initSnooze() {
		// Toggle dropdown menu
		$(document).on('click', '.qa-btn-snooze-toggle', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $wrap = $(this).closest('.qa-snooze-wrap');
			$('.qa-snooze-wrap').not($wrap).removeClass('is-open');
			$wrap.toggleClass('is-open');
		});

		// Close dropdown when clicking outside
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.qa-snooze-wrap').length) {
				$('.qa-snooze-wrap').removeClass('is-open');
			}
		});

		// Preset options click (Tomorrow, 1 Week, 1 Month)
		$(document).on('click', '.qa-snooze-item', function (e) {
			e.preventDefault();
			var $item = $(this);
			var duration = $item.data('duration');
			var $card = $item.closest('.qa-notice-card');
			var noticeId = $card.data('notice-id');

			executeSnooze($card, noticeId, duration, '');
		});

		// Custom date pick
		$(document).on('click', '.qa-btn-snooze-custom', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $panel = $btn.closest('.qa-snooze-custom-panel');
			var customDate = $panel.find('.qa-snooze-date-picker').val();
			var $card = $btn.closest('.qa-notice-card');
			var noticeId = $card.data('notice-id');

			if (!customDate) {
				alert('Please select a date.');
				return;
			}

			executeSnooze($card, noticeId, 'custom', customDate);
		});
	}

	function executeSnooze($card, noticeId, duration, customDate) {
		if (!noticeId) return;

		$card.addClass('is-dismissing');
		$('.qa-snooze-wrap').removeClass('is-open');

		$.post(
			wpQuietAdminData.ajaxUrl,
			{
				action: 'wp_quiet_admin_snooze',
				notice_id: noticeId,
				duration: duration,
				custom_date: customDate,
				nonce: wpQuietAdminData.nonce,
			},
			function (response) {
				if (response && response.success) {
					var $cardClone = $card.clone(true);
					var $parentGrid = $card.parent();

					$card.slideUp(250, function () {
						$(this).remove();
						updateBadgeCounters(response.data.counts);
						checkEmptyState();
						showUndoToast(noticeId, $cardClone, $parentGrid, response.data.message || 'Notice snoozed.');
					});
				} else {
					$card.removeClass('is-dismissing');
					alert((response.data && response.data.message) || 'Error snoozing notice.');
				}
			}
		).fail(function () {
			$card.removeClass('is-dismissing');
		});
	}

	/**
	 * Handle Unsnooze / Wake Up Now from Archive Tab.
	 */
	function initUnsnooze() {
		$(document).on('click', '.qa-btn-unsnooze', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $card = $btn.closest('.qa-notice-card');
			var noticeId = $card.data('notice-id');

			if (!noticeId) return;

			$btn.prop('disabled', true).text('Waking up...');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_unsnooze',
					notice_id: noticeId,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$card.slideUp(200, function () {
							$(this).remove();
							updateBadgeCounters(response.data.counts);
							checkEmptyState();
						});
					} else {
						$btn.prop('disabled', false).text('Wake Up Now');
						alert((response.data && response.data.message) || 'Error waking notice.');
					}
				}
			);
		});
	}

	/**
	 * Show Undo Toast Notification upon notice dismissal or snooze.
	 */
	function showUndoToast(noticeId, $cardClone, $parentGrid, customMsg, batchIds) {
		lastDismissedItem = {
			id: noticeId || null,
			ids: batchIds || null,
			$card: $cardClone,
			$parent: $parentGrid
		};

		$('.qa-undo-toast').remove();
		if (undoToastTimer) {
			clearTimeout(undoToastTimer);
		}

		var labelText = customMsg || wpQuietAdminData.strings.dismissed || 'Notice dismissed.';

		var toastHtml =
			'<div class="qa-undo-toast">' +
			'  <span>' + labelText + '</span>' +
			'  <button type="button" class="qa-undo-toast-btn">Undo</button>' +
			'  <span class="qa-undo-toast-hint">Ctrl+Z</span>' +
			'  <button type="button" class="qa-undo-toast-close" title="Close">×</button>' +
			'</div>';

		$('body').append(toastHtml);

		undoToastTimer = setTimeout(function () {
			$('.qa-undo-toast').fadeOut(300, function () {
				$(this).remove();
			});
			lastDismissedItem = null;
		}, 9000);
	}

	/**
	 * Perform Undo action (restore the last dismissed notice or batch).
	 */
	function triggerUndo() {
		if (!lastDismissedItem) return;

		var item = lastDismissedItem;
		lastDismissedItem = null;
		$('.qa-undo-toast').remove();
		if (undoToastTimer) {
			clearTimeout(undoToastTimer);
		}

		if (item.ids && item.ids.length) {
			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_undo_dismiss',
					notice_ids: item.ids,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$('.qa-empty-state').remove();
						if ($('.qa-notices-grid').length === 0) {
							$('.qa-tab-content-area').append('<div class="qa-notices-grid"></div>');
						}
						var $restored = item.$card.removeClass('is-dismissing').hide();
						$('.qa-notices-grid').append($restored);
						$restored.slideDown(250);
						updateBadgeCounters(response.data.counts);
						$('.qa-btn-dismiss-tab').prop('disabled', false);
						$('.qa-btn-clear-all').prop('disabled', false);
					}
				}
			);
		} else if (item.id) {
			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_undo_dismiss',
					notice_id: item.id,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$('.qa-empty-state').remove();
						var $restored = item.$card.removeClass('is-dismissing').hide();
						item.$parent.prepend($restored);
						$restored.slideDown(250);
						updateBadgeCounters(response.data.counts);
						$('.qa-btn-dismiss-tab').prop('disabled', false);
						$('.qa-btn-clear-all').prop('disabled', false);
					}
				}
			);
		}
	}

	/**
	 * Initialize Undo Toast click and keyboard listeners (Ctrl+Z / Cmd+Z).
	 */
	function initUndoToast() {
		// Global Undo Dismiss Keyboard Listener (Ctrl+Z / Cmd+Z)
		$(document).on('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z') && !$(e.target).is('input, textarea, select')) {
				if (lastDismissedItem) {
					e.preventDefault();
					triggerUndo();
				}
			}
		});

		// Undo Toast click
		$(document).on('click', '.qa-undo-toast-btn', function (e) {
			e.preventDefault();
			triggerUndo();
		});

		$(document).on('click', '.qa-undo-toast-close', function (e) {
			e.preventDefault();
			$('.qa-undo-toast').fadeOut(200, function () {
				$(this).remove();
			});
			lastDismissedItem = null;
		});
	}

	/**
	 * Handle Restore Notice from Dismissed Archive Tab.
	 */
	function initRestoreNotice() {
		$(document).on('click', '.qa-btn-restore', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $card = $btn.closest('.qa-notice-card');
			var noticeId = $card.data('notice-id');

			if (!noticeId) return;

			$btn.prop('disabled', true).text('Restoring...');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_restore_notice',
					notice_id: noticeId,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$card.slideUp(200, function () {
							$(this).remove();
							updateBadgeCounters(response.data.counts);
							checkEmptyState();
						});
					} else {
						$btn.prop('disabled', false).text('Restore Notice');
					}
				}
			);
		});
	}

	/**
	 * Handle Purge Dismissed Archive from History Tab.
	 */
	function initPurgeHistory() {
		$(document).on('click', '.qa-btn-purge-history', function (e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to permanently clear all dismissed notices from the archive?')) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_purge_history',
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$('.qa-notices-grid').slideUp(200, function () {
							$(this).remove();
							checkEmptyState();
						});
						updateBadgeCounters(response.data.counts);
					} else {
						$btn.prop('disabled', false);
						alert((response.data && response.data.message) || 'Error purging archive.');
					}
				}
			).fail(function (xhr) {
				$btn.prop('disabled', false);
				alert((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Error purging archive.');
			});
		});
	}

	/**
	 * Handle Multi-Select Checkboxes & Floating Bulk Actions Bar.
	 */
	function initBulkActions() {
		// Card Checkbox Change
		$(document).on('change', '.qa-card-select-checkbox', function () {
			var $card = $(this).closest('.qa-notice-card');
			$card.toggleClass('is-selected', $(this).is(':checked'));
			syncBulkSelectionState();
		});

		// Master Select-All Checkbox
		$(document).on('change', '.qa-bulk-select-all-checkbox', function () {
			var isChecked = $(this).is(':checked');
			$('.qa-card-select-checkbox:visible').prop('checked', isChecked);
			$('.qa-notice-card:visible').toggleClass('is-selected', isChecked);
			syncBulkSelectionState();
		});

		// Deselect All link
		$(document).on('click', '.qa-bulk-deselect-btn', function (e) {
			e.preventDefault();
			$('.qa-card-select-checkbox').prop('checked', false);
			$('.qa-notice-card').removeClass('is-selected');
			syncBulkSelectionState();
		});

		// Toggle Bulk Snooze Dropdown
		$(document).on('click', '.qa-btn-bulk-snooze-toggle', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $wrap = $(this).closest('.qa-bulk-snooze-wrap');
			$wrap.toggleClass('is-open');
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('.qa-bulk-snooze-wrap').length) {
				$('.qa-bulk-snooze-wrap').removeClass('is-open');
			}
		});

		// Preset Bulk Snooze Options Click
		$(document).on('click', '.qa-bulk-snooze-item', function (e) {
			e.preventDefault();
			var duration = $(this).data('duration');
			executeBulkSnooze(duration, '');
		});

		// Custom Bulk Snooze Click
		$(document).on('click', '.qa-btn-bulk-snooze-custom', function (e) {
			e.preventDefault();
			var customDate = $('.qa-bulk-snooze-date-picker').val();
			if (!customDate) {
				alert('Please select a date.');
				return;
			}
			executeBulkSnooze('custom', customDate);
		});

		// Bulk Dismiss Button
		$(document).on('click', '.qa-btn-bulk-dismiss', function (e) {
			e.preventDefault();
			var selectedIds = getSelectedNoticeIds();
			if (selectedIds.length === 0) return;

			if (!confirm('Are you sure you want to dismiss the ' + selectedIds.length + ' selected notice(s)?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			var $selectedCards = getSelectedCards();
			var $cardsClone = $selectedCards.clone(true);
			var $parentGrid = $('.qa-notices-grid');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_bulk_dismiss',
					notice_ids: selectedIds,
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					$btn.prop('disabled', false);
					if (response && response.success) {
						$selectedCards.slideUp(250, function () {
							$(this).remove();
							checkEmptyState();
						});
						updateBadgeCounters(response.data.counts);
						resetBulkBar();
						showUndoToast(null, $cardsClone, $parentGrid, response.data.message || 'Selected notices dismissed.', response.data.dismissed_ids);
					} else {
						alert((response.data && response.data.message) || 'Error dismissing selected notices.');
					}
				}
			).fail(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	function syncBulkSelectionState() {
		var $visibleCheckboxes = $('.qa-card-select-checkbox:visible');
		var totalVisible = $visibleCheckboxes.length;
		var checkedCount = $visibleCheckboxes.filter(':checked').length;

		if (checkedCount > 0) {
			$('.qa-bulk-count').text(checkedCount);
			$('.qa-bulk-actions-bar').slideDown(150);
			if (checkedCount === totalVisible && totalVisible > 0) {
				$('.qa-bulk-select-all-checkbox').prop('checked', true).prop('indeterminate', false);
			} else {
				$('.qa-bulk-select-all-checkbox').prop('checked', false).prop('indeterminate', true);
			}
		} else {
			resetBulkBar();
		}
	}

	function resetBulkBar() {
		$('.qa-bulk-actions-bar').slideUp(150);
		$('.qa-bulk-select-all-checkbox').prop('checked', false).prop('indeterminate', false);
		$('.qa-bulk-snooze-wrap').removeClass('is-open');
	}

	function getSelectedNoticeIds() {
		var ids = [];
		$('.qa-card-select-checkbox:checked').each(function () {
			var val = $(this).val();
			if (val) ids.push(val);
		});
		return ids;
	}

	function getSelectedCards() {
		return $('.qa-card-select-checkbox:checked').closest('.qa-notice-card');
	}

	function executeBulkSnooze(duration, customDate) {
		var selectedIds = getSelectedNoticeIds();
		if (selectedIds.length === 0) return;

		$('.qa-bulk-snooze-wrap').removeClass('is-open');
		var $selectedCards = getSelectedCards();
		var $cardsClone = $selectedCards.clone(true);
		var $parentGrid = $('.qa-notices-grid');

		$.post(
			wpQuietAdminData.ajaxUrl,
			{
				action: 'wp_quiet_admin_bulk_snooze',
				notice_ids: selectedIds,
				duration: duration,
				custom_date: customDate,
				nonce: wpQuietAdminData.nonce,
			},
			function (response) {
				if (response && response.success) {
					$selectedCards.slideUp(250, function () {
						$(this).remove();
						checkEmptyState();
					});
					updateBadgeCounters(response.data.counts);
					resetBulkBar();
					showUndoToast(null, $cardsClone, $parentGrid, response.data.message || 'Notices snoozed.', response.data.snoozed_ids);
				} else {
					alert((response.data && response.data.message) || 'Error snoozing selected notices.');
				}
			}
		);
	}

	/**
	 * Handle Wake Up All in Snoozed Tab.
	 */
	function initWakeAll() {
		$(document).on('click', '.qa-btn-wake-all', function (e) {
			e.preventDefault();
			if (!confirm('Are you sure you want to wake up all snoozed notices immediately and return them to active tabs?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);
			var $cards = $('.qa-notices-grid .qa-notice-card');

			$.post(
				wpQuietAdminData.ajaxUrl,
				{
					action: 'wp_quiet_admin_wake_all',
					nonce: wpQuietAdminData.nonce,
				},
				function (response) {
					if (response && response.success) {
						$cards.slideUp(250, function () {
							$(this).remove();
							checkEmptyState();
						});
						updateBadgeCounters(response.data.counts);
					} else {
						$btn.prop('disabled', false);
						alert((response.data && response.data.message) || 'Error waking up snoozed notices.');
					}
				}
			).fail(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	/**
	 * Update badges across UI, Tabs, and Toolbar.
	 */
	function updateBadgeCounters(counts) {
		if (!counts) return;

		// Update Admin Bar multi-pill badges
		var $badgesGroup = $('#wpadminbar .qa-bar-badges-group');
		if ($badgesGroup.length) {
			var lowAndOther = (counts.low || 0) + (counts.other || 0);
			var html = '';

			if (counts.total === 0) {
				html = '<span class="qa-pill qa-pill-zero">0</span>';
				$('#wpadminbar .wp-quiet-admin-bar-root').removeClass('has-critical-alerts');
			} else {
				if (counts.high > 0) {
					html += '<span class="qa-pill qa-pill-high" title="' + counts.high + ' Critical Notices">' + counts.high + '</span>';
					$('#wpadminbar .wp-quiet-admin-bar-root').addClass('has-critical-alerts');
				} else {
					$('#wpadminbar .wp-quiet-admin-bar-root').removeClass('has-critical-alerts');
				}

				if (counts.medium > 0) {
					html += '<span class="qa-pill qa-pill-medium" title="' + counts.medium + ' Warnings">' + counts.medium + '</span>';
				}

				if (lowAndOther > 0) {
					html += '<span class="qa-pill qa-pill-low qa-pill-green" style="background-color:#00a32a !important;color:#ffffff !important;" title="' + lowAndOther + ' Muted Notices">' + lowAndOther + '</span>';
				}
			}

			$badgesGroup.html(html);
		}

		// Update sub-items in toolbar dropdown
		$('#wp-admin-bar-wp-quiet-admin-high .qa-sub-count').text(counts.high || 0);
		$('#wp-admin-bar-wp-quiet-admin-medium .qa-sub-count').text(counts.medium || 0);
		$('#wp-admin-bar-wp-quiet-admin-muted .qa-sub-count').text(lowAndOther);
		$('#wp-admin-bar-wp-quiet-admin-snoozed .qa-sub-count').text(counts.snoozed || 0);
		$('#wp-admin-bar-wp-quiet-admin-history .qa-sub-count').text(counts.dismissed || 0);

		// Update WordPress Core sub-item in toolbar dropdown
		if ($('#wp-admin-bar-wp-quiet-admin-wordpress').length) {
			var wpCount = counts.wordpress || 0;
			$('#wp-admin-bar-wp-quiet-admin-wordpress .qa-sub-count').text(wpCount);
			var $orb = $('#wp-admin-bar-wp-quiet-admin-wordpress .qa-sub-orb');
			$orb.removeClass('qa-orb-red qa-orb-yellow qa-orb-blue');
			if (counts.wp_high > 0) {
				$orb.addClass('qa-orb-red');
			} else if (counts.wp_medium > 0) {
				$orb.addClass('qa-orb-yellow');
			} else {
				$orb.addClass('qa-orb-blue');
			}
		}

		// Update Tab badges
		$('.qa-tab-high .qa-tab-badge').text(counts.high || 0).toggleClass('has-count', (counts.high || 0) > 0);
		$('.qa-tab-medium .qa-tab-badge').text(counts.medium || 0).toggleClass('has-count', (counts.medium || 0) > 0);
		$('.qa-tab-muted .qa-tab-badge, .qa-tab-promos .qa-tab-badge').text(lowAndOther).toggleClass('has-count', lowAndOther > 0);
		$('.qa-tab-snoozed .qa-tab-badge').text(counts.snoozed || 0).toggleClass('has-count', (counts.snoozed || 0) > 0);
		$('.qa-tab-history .qa-tab-badge').text(counts.dismissed || 0).toggleClass('has-count', (counts.dismissed || 0) > 0);

		// Update WordPress Core Tab multi-pills in tab header
		var $wpTabPills = $('.qa-tab-wordpress .qa-tab-pills-group');
		if ($wpTabPills.length) {
			var wpTotal = counts.wordpress || 0;
			var wpBlue = (counts.wp_other || 0) + (counts.wp_low || 0);
			var wpHtml = '';
			if (wpTotal === 0) {
				wpHtml = '<span class="qa-tab-pill pill-zero">0</span>';
			} else {
				if (counts.wp_high > 0) {
					wpHtml += '<span class="qa-tab-pill pill-red" title="' + counts.wp_high + ' Critical">' + counts.wp_high + '</span>';
				}
				if (counts.wp_medium > 0) {
					wpHtml += '<span class="qa-tab-pill pill-yellow" title="' + counts.wp_medium + ' Updates">' + counts.wp_medium + '</span>';
				}
				if (wpBlue > 0) {
					wpHtml += '<span class="qa-tab-pill pill-blue" title="' + wpBlue + ' Announcements">' + wpBlue + '</span>';
				}
			}
			$wpTabPills.html(wpHtml);
		}

		// Update WordPress subfilter counts
		$('.qa-wp-filter-btn[data-filter="all"] .qa-filter-count').text(counts.wordpress || 0);
		$('.qa-wp-filter-btn[data-filter="high"] .qa-filter-count').text(counts.wp_high || 0);
		$('.qa-wp-filter-btn[data-filter="medium"] .qa-filter-count').text(counts.wp_medium || 0);
		$('.qa-wp-filter-btn[data-filter="other"] .qa-filter-count').text((counts.wp_other || 0) + (counts.wp_low || 0));

		// Update Reviews & Promos subfilter counts
		$('.qa-muted-filter-btn[data-filter="all"] .qa-filter-count').text(lowAndOther);
		$('.qa-muted-filter-btn[data-filter="low"] .qa-filter-count').text(counts.low || 0);
		$('.qa-muted-filter-btn[data-filter="other"] .qa-filter-count').text(counts.other || 0);

		// Update Archive subfilter counts
		var archiveTotal = counts.dismissed || 0;
		$('.qa-history-filter-btn[data-filter="all"] .qa-filter-count').text(archiveTotal);
		if (archiveTotal === 0) {
			$('.qa-history-filter-btn .qa-filter-count').text(0);
		}

		// Update Left Sidebar Menu multi-pill badges (#adminmenu)
		var $sideGroup = $('#adminmenu a[href*="wp-quiet-admin"] .qa-sidebar-badge-group');
		if ($sideGroup.length) {
			var sideLowOther = (counts.low || 0) + (counts.other || 0);
			var sideHtml = '';
			if (counts.high > 0) {
				sideHtml += '<span class="qa-side-pill pill-red" style="background-color:#d63638;color:#ffffff;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;">' + counts.high + '</span>';
			}
			if (counts.medium > 0) {
				sideHtml += '<span class="qa-side-pill pill-yellow" style="background-color:#dba617;color:#1d2327;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;">' + counts.medium + '</span>';
			}
			if (sideLowOther > 0) {
				sideHtml += '<span class="qa-side-pill pill-green" style="background-color:#00a32a;color:#ffffff;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;">' + sideLowOther + '</span>';
			}
			$sideGroup.html(sideHtml);
			if (counts.total === 0) {
				$sideGroup.hide();
			} else {
				$sideGroup.show();
			}
		}
	}

	/**
	 * Check if current tab is empty and display empty state.
	 */
	function checkEmptyState() {
		if ($('.qa-notices-grid .qa-notice-card').length === 0 && $('.qa-empty-state').length === 0) {
			var emptyHtml =
				'<div class="qa-empty-state">' +
				'  <div class="qa-empty-icon"><span class="dashicons dashicons-yes-alt"></span></div>' +
				'  <h3>All Quiet Here!</h3>' +
				'  <p>No active notices found in this category. Enjoy the distraction-free dashboard.</p>' +
				'</div>';

			$('.qa-tab-content-area').append(emptyHtml);
			$('.qa-btn-clear-all').prop('disabled', true);
			$('.qa-btn-dismiss-tab').prop('disabled', true);
		}
	}
	/**
	 * Make each Rules & Settings section collapsible.
	 * First section is open by default; open/closed state persists in localStorage.
	 */
	function initCollapsibleSections() {
		var $sections = $('.qa-settings-section').not('.qa-support-section');
		if (!$sections.length) { return; }

		var storeKey = 'wpqaSettingsSections';
		var state = {};
		try { state = JSON.parse(window.localStorage.getItem(storeKey)) || {}; } catch (e) { state = {}; }

		function save() {
			try { window.localStorage.setItem(storeKey, JSON.stringify(state)); } catch (e) {}
		}

		function setOpen($section, open, persist) {
			$section.toggleClass('is-collapsed', !open);
			$section.find('.qa-section-toggle').attr('aria-expanded', open ? 'true' : 'false');
			$section.data('qaHidden').toggleClass('qa-section-hidden', !open);
			if (persist) { state[$section.data('qaKey')] = open ? 1 : 0; save(); }
		}

		$sections.each(function (i) {
			var $section = $(this);
			var $title = $section.find('h3').first();
			if (!$title.length) { return; }

			// Hide everything in the section except the path leading to the title.
			var $hidden = $();
			var node = $title[0];
			while (node && node !== this) {
				var $sibs = $(node).siblings();
				$hidden = $hidden.add($sibs);
				node = node.parentNode;
			}

			$section.addClass('qa-collapsible').data('qaHidden', $hidden).data('qaKey', i);
			$title.addClass('qa-section-toggle').attr({ role: 'button', tabindex: 0 })
				.append('<span class="qa-section-chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>');

			var open = (typeof state[i] !== 'undefined') ? !!state[i] : (i === 0);
			setOpen($section, open, false);

			$title.on('click keydown', function (e) {
				if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) { return; }
				e.preventDefault();
				setOpen($section, $section.hasClass('is-collapsed'), true);
			});
		});

		var $bar = $('<p class="qa-sections-toolbar"><a href="#" class="qa-expand-all">Expand all</a> | <a href="#" class="qa-collapse-all">Collapse all</a></p>');
		$sections.first().before($bar);
		$bar.on('click', 'a', function (e) {
			e.preventDefault();
			var open = $(this).hasClass('qa-expand-all');
			$sections.filter('.qa-collapsible').each(function () { setOpen($(this), open, true); });
		});
	}
})(jQuery);
