var jQuery, aiwrc_globals;
(function($) {
	'use strict';

	var template_sortable_title_html =
		'<div class="aiwrc-sortable-title">' +
			'<div class="aiwrc-sortable-title-text"><input type="text" value="{title}" /></div>' +
			'<div class="aiwrc-sortable-title-icons">' +
				'<div class="aiwrc-sortable-title-icon icon-move"></div>' +
				'<div class="aiwrc-sortable-title-icon icon-trash"></div>' +
			'</div>' +
		'</div>';

	function disableForm($wrap) {
		$wrap.find('.aiwrc-content select, .aiwrc-content input, .aiwrc-content textarea, .aiwrc-foot button').attr('disabled', 'disabled');
		$wrap.find('.aiwrc-content').addClass('aiwrc-disabled');
	}

	function enableForm($wrap) {
		$wrap.find('.aiwrc-content select, .aiwrc-content input, .aiwrc-content textarea, .aiwrc-foot button').removeAttr('disabled');
		$wrap.find('.aiwrc-content').removeClass('aiwrc-disabled');
	}

	function spinnerShow($wrap) {
		$wrap.find('.aiwrc-spinner').show();
	}

	function spinnerHide($wrap) {
		$wrap.find('.aiwrc-spinner').hide();
	}

	function updateSortableTitlesClass($wrap) {
		var titlesDiv = $wrap.find('.aiwrc-sortable-titles').first();
		var titlesNum = titlesDiv.find('.aiwrc-sortable-title').length;
		titlesDiv.toggleClass('aiwrc-max-titles-reached', titlesNum === parseInt(titlesDiv.attr('data-max-titles')));
	}

	// renders the step 2, with the sortable titles
	function renderStep2($wrap) {
		$wrap.removeClass('aiwrc-step-1').addClass('aiwrc-step-2');

		// aiwrc_globals.step1response.result[i]
		var content_html = '<div class="aiwrc-content-blocker"></div>'; // to disable input
		content_html += '<div class="aiwrc-sortable-titles">';
		for (var i = 0; i < aiwrc_globals.step1response.result.length; i++) {
			content_html += template_sortable_title_html.replace('{title}', aiwrc_globals.step1response.result[i]);
		}
		content_html += '</div>';
		content_html +=
			'<div class="aiwrc-sortable-icons">' +
				'<div class="aiwrc-sortable-icon icon-add"></div>' +
			'</div>';
		$wrap.find('.aiwrc-content').html(content_html);
		$wrap.find('.aiwrc-sortable-titles').sortable({
			handle: ".aiwrc-sortable-title-icon.icon-move"
		});
		$wrap.find('.aiwrc-sortable-titles').attr('data-max-titles', aiwrc_globals.step1response.result.length);
		updateSortableTitlesClass($wrap);

		var foot_html = '<button class="button button-primary submit-step-2">' + 'Generate article' + ' &raquo;</button>';
		$wrap.find('.aiwrc-foot').html(foot_html);

		$wrap.find('.aiwrc-message').html('');
	}

	// renders the complete message and the button to go to edit the generated post
	function renderStep3($wrap) {
		$wrap.removeClass('aiwrc-step-2').addClass('aiwrc-step-3');
		$wrap.find('.aiwrc-content').html(
			'<div class="aiwrc-edit-it">' +
				'<p>The post is ready!</p>' +
				'<a href="' + aiwrc_globals.step2response.edit_post_link + '" class="button button-primary button-hero">' + 'Click to edit the draft' + '</a>' +
			'</div>'
		);
		$wrap.find('.aiwrc-foot').html('');
		$wrap.find('.aiwrc-message').html('');
	}

	// resets popup to step 1 after the completion of a generation
	function resetGutenbergPopup() {
		$('#aiwrc-editor-popup .aiwrc-wrap').html(
			$('#aiwrc-editor-popup-template-step-1 .aiwrc-wrap').html()
		).removeClass('aiwrc-step-2 aiwrc-step-3').addClass('aiwrc-step-1');
		delete aiwrc_globals.step1data;
		delete aiwrc_globals.step1response;
		delete aiwrc_globals.step2data;
		delete aiwrc_globals.step2response;
	}

	// clear error message if both title and instructions are filled in
	function maybeClearErrorMessage($wrap) {
		var title = $wrap.find('input#aiwrc-input-title').val().trim();
		var instructions = $wrap.find('textarea#aiwrc-input-instructions').val().trim();
		if ('' !== title && '' !== instructions) {
			$wrap.find('.aiwrc-message.message-error').html('');
		}
	}

	// jquery ready dom
	$(function(){

		// sortable test
		// $('.aiwrc-sortable-titles').sortable({
		// 	handle: ".aiwrc-sortable-title-icon.icon-move"
		// });

		var $wrap = $('.aiwrc-wrap');

		// step 1 submission
		$wrap.on('click', '.button.submit-step-1', function (e) {

			//window.console.log('click submit-step-1');
			e.preventDefault();

			var $this = $(this); // the button
			var $wrap = $this.closest('.aiwrc-wrap');

			$wrap.find('.aiwrc-message.message-error').html('');

			var language = $wrap.find('select[name="language"]').val();
			var title = $wrap.find('input[name="title"]').val().trim();
			var instructions = $wrap.find('textarea[name="instructions"]').val().trim();
			var length = $wrap.find('select[name="length"]').val();
			var tov = $wrap.find('select[name="tov"]').val();

			if ('' === title || '' === instructions) {
				$wrap.find('.aiwrc-message.message-error').html('Please fill in all fields.');
				$this.blur();
				return;
			}

			var data = {
				'action' : 'aiwrc_ajax_submission',
				'step' : '1',
				'language' : language,
				'title' : title,
				'instructions' : instructions,
				'length' : length,
				'tov' : tov
			};

			disableForm($wrap);
			spinnerShow($wrap);
			//console.log(data);

			// step 1 api request
			$.ajax({
				url: aiwrc_globals.ajaxurl,
				type: 'POST',
				data: data,
				dataType: 'json',
				success: function (resp) {
					spinnerHide($wrap);
					enableForm($wrap);

					if (typeof resp !== 'object') {
						window.console.log('unexpected server response:', resp);
						return;
					}

					//window.console.log('ajax response:', resp);

					if (false === resp[0]) {
						$wrap.find('.aiwrc-message.message-error').html(resp[1]);
						return;
					}

					aiwrc_globals.step1data = data;
					aiwrc_globals.step1response = resp[1];

					renderStep2($wrap);
				}
			});
		}); // end of - step 1 submission

		// step 2 submission
		$wrap.on('click', '.button.submit-step-2', function (e) {

			//window.console.log('click submit-step-2');
			e.preventDefault();

			var $this = $(this); // the button
			var $wrap = $this.closest('.aiwrc-wrap');

			//var titles = $('.aiwrc-sortable-titles .aiwrc-sortable-title-text').clone().append('|').text();
			//titles = titles.replace(/\|+$/g, '');

			var titles = [];
			$wrap.find('.aiwrc-sortable-titles .aiwrc-sortable-title-text input').each(function (i, DOMelem) {
				titles.push($(this).val());
			});
			titles = titles.join('|');

			var destination = $wrap.attr('data-destination');

			var data = {
				'action' : 'aiwrc_ajax_submission',
				'step' : '2',
				'title' : aiwrc_globals.step1data.title, // to be used in wp_insert_post()
				'paragraphs' : titles,
				'order_detail_id' : aiwrc_globals.step1response.order_detail_id,
				'destination' : destination
			};

			disableForm($wrap);
			spinnerShow($wrap);
			//console.log(data);

			// step 2 api request
			$.ajax({
				url: aiwrc_globals.ajaxurl,
				type: 'POST',
				data: data,
				dataType: 'json',
				success: function (resp) {
					spinnerHide($wrap);
					enableForm($wrap);

					if (typeof resp !== 'object') {
						window.console.log('unexpected server response:', resp);
						return;
					}

					//window.console.log('ajax response:', resp);

					if (false === resp[0]) {
						$wrap.find('.aiwrc-message.message-error').html(resp[1]);
						return;
					}

					aiwrc_globals.step2data = data;
					aiwrc_globals.step2response = resp[1];

					if ('new_post' === destination) {
						renderStep3($wrap);
					}

					if ('gutenberg' === destination) {
						var block;
						for (var i = 0; i < resp[1].length; i++) {
							var item = resp[1][i];
							block = false;
							if ('heading' === item.type) {
								block = wp.blocks.createBlock('core/heading', {level: item.level, content: item.text});
							}
							else if ('paragraph' === item.type ) {
								block = wp.blocks.createBlock('core/paragraph', {content: item.text});
							}
							if (false !== block) {
								wp.data.dispatch('core/block-editor').insertBlocks(block);
							}
						}
						// closing the popup if open
						$('#aiwrc-editor-popup-wrapper').removeClass('aiwrc-popup-visible');
						resetGutenbergPopup();
					}
				}
			});
		}); // end of - step 2 submission

		// title and instructions change handler to add a class (.aiwrc-empty-input) when they are empty
		$wrap.on('input', 'input#aiwrc-input-title,textarea#aiwrc-input-instructions', function (event) {
			var $input = $(this);
			var text = $input.val().trim();
		    $input.toggleClass('aiwrc-empty-input', '' === text);
			maybeClearErrorMessage($wrap);
		});
		$wrap.find('input#aiwrc-input-title,textarea#aiwrc-input-instructions').trigger('input');

		// delete a paragraph handler
		$wrap.on('click', '.aiwrc-sortable-title-icon.icon-trash', function (e) {
			e.preventDefault();
			var $this = $(this); // the button
			var $wrap = $this.closest('.aiwrc-wrap');
			var pars = $this.closest('.aiwrc-sortable-titles').find('.aiwrc-sortable-title').length;
			if (pars > 1) {
				$this.closest('.aiwrc-sortable-title').remove();
				updateSortableTitlesClass($wrap);
			}
		}); // end of - delete a paragraph handler

		// add a paragraph handler
		$wrap.on('click', '.aiwrc-sortable-icon.icon-add', function (e) {
			e.preventDefault();
			var max_allowed_titles = aiwrc_globals.step1response.result.length;
			var $this = $(this); // the button
			var $wrap = $this.closest('.aiwrc-wrap');
			if ($this.closest('.aiwrc-content').find('.aiwrc-sortable-titles .aiwrc-sortable-title').length >= max_allowed_titles) {
				return;
			}
			var html2add = template_sortable_title_html;
			html2add = html2add.replace('{title}', 'Lorem ipsum...');
			$this.closest('.aiwrc-content').find('.aiwrc-sortable-titles').append(html2add);
			updateSortableTitlesClass($wrap);
		}); // end of - add a paragraph handler
	}); // end of - jquery ready dom
})(jQuery);
