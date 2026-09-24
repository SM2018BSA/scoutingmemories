/**
 * Scouting Forms & Archives - Backend Guide & Walkthrough Interactivity
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // 1. Step Navigation & Stepper Controller
        var $stepItems = $('.sm-step-item');
        var $stepPanels = $('.sm-step-panel');
        var $btnPrev = $('#sm-btn-prev');
        var $btnNext = $('#sm-btn-next');
        var currentStep = 1;
        var totalSteps = $stepItems.length;

        function goToStep(stepNum) {
            if (stepNum < 1 || stepNum > totalSteps) return;
            currentStep = stepNum;

            $stepItems.removeClass('active');
            $stepItems.filter('[data-step="' + currentStep + '"]').addClass('active');

            $stepPanels.removeClass('active');
            $('#sm-step-' + currentStep).addClass('active');

            // Button visibility & states
            if (currentStep === 1) {
                $btnPrev.hide();
            } else {
                $btnPrev.show();
            }

            if (currentStep === totalSteps) {
                $btnNext.text('Back to Step 1').data('action', 'restart');
            } else {
                $btnNext.text('Next Step ›').data('action', 'next');
            }

            // Smooth scroll up to step header if below viewport
            var containerTop = $('.sm-step-content-container').offset().top - 40;
            if ($(window).scrollTop() > containerTop) {
                $('html, body').animate({ scrollTop: containerTop }, 200);
            }
        }

        $stepItems.on('click', function(e) {
            e.preventDefault();
            var step = parseInt($(this).data('step'), 10);
            goToStep(step);
        });

        $btnPrev.on('click', function(e) {
            e.preventDefault();
            goToStep(currentStep - 1);
        });

        $btnNext.on('click', function(e) {
            e.preventDefault();
            if ($(this).data('action') === 'restart') {
                goToStep(1);
            } else {
                goToStep(currentStep + 1);
            }
        });

        // 2. Shortcode Copy to Clipboard
        $('.sm-copy-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var textToCopy = $btn.data('clipboard');

            if (!textToCopy) {
                textToCopy = $btn.closest('.sm-shortcode-card').find('.sm-sc-code').text().trim();
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showCopiedFeedback($btn);
                }).catch(function() {
                    fallbackCopy(textToCopy, $btn);
                });
            } else {
                fallbackCopy(textToCopy, $btn);
            }
        });

        function fallbackCopy(text, $btn) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopiedFeedback($btn);
        }

        function showCopiedFeedback($btn) {
            var origText = $btn.text();
            $btn.addClass('copied').text('✓ Copied!');
            setTimeout(function() {
                $btn.removeClass('copied').text(origText);
            }, 2000);
        }

        // 3. Live AJAX Cascading Playground inside Guide
        var $playState = $('#sm-test-state');
        var $playCouncil = $('#sm-test-council');
        var $playCamp = $('#sm-test-camp');
        var $playLodge = $('#sm-test-lodge');
        var $playStatus = $('#sm-test-status');

        if (typeof smAjax !== 'undefined' && $playState.length) {
            $playState.on('change', function() {
                var stateId = $(this).val();

                $playCouncil.empty().append('<option value="">— Select Council —</option>').prop('disabled', true);
                $playCamp.empty().append('<option value="">— Select Camp —</option>').prop('disabled', true);
                $playLodge.empty().append('<option value="">— Select Lodge —</option>').prop('disabled', true);

                if (!stateId) {
                    $playStatus.text('Select a state to test AJAX lookups.');
                    return;
                }

                $playStatus.text('Fetching councils via AJAX...');

                $.ajax({
                    url: smAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sm_get_councils',
                        security: smAjax.nonce,
                        state_id: stateId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var councils = response.data;
                            if (councils.length === 0) {
                                $playCouncil.append('<option value="">No councils found</option>');
                                $playStatus.text('No historical councils found for selected state.');
                            } else {
                                councils.forEach(function(c) {
                                    $playCouncil.append(
                                        $('<option>', {
                                            value: c.id,
                                            text: c.display_name + (c.active === 'Yes' ? ' [Active]' : ' [Historic]')
                                        })
                                    );
                                });
                                $playCouncil.prop('disabled', false);
                                $playStatus.html('<strong>✓ Success!</strong> Loaded <strong>' + councils.length + '</strong> councils via AJAX.');
                            }
                        } else {
                            $playStatus.text('Error querying councils.');
                        }
                    },
                    error: function() {
                        $playStatus.text('AJAX server connection failed.');
                    }
                });
            });

            $playCouncil.on('change', function() {
                var councilId = $(this).val();
                var stateId = $playState.val();

                $playCamp.empty().append('<option value="">— Select Camp —</option>').prop('disabled', true);
                $playLodge.empty().append('<option value="">— Select Lodge —</option>').prop('disabled', true);

                if (!councilId) return;

                $playStatus.text('Fetching camps and lodges for selected council...');

                $.ajax({
                    url: smAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sm_get_camps_lodges',
                        security: smAjax.nonce,
                        council_id: councilId,
                        state_id: stateId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var camps = response.data.camps || [];
                            var lodges = response.data.lodges || [];

                            if (camps.length > 0) {
                                camps.forEach(function(cp) {
                                    $playCamp.append($('<option>', { value: cp.id, text: cp.name }));
                                });
                                $playCamp.prop('disabled', false);
                            } else {
                                $playCamp.append('<option value="">No camps found for this council</option>');
                            }

                            if (lodges.length > 0) {
                                lodges.forEach(function(ld) {
                                    $playLodge.append($('<option>', { value: ld.id, text: ld.display_name }));
                                });
                                $playLodge.prop('disabled', false);
                            } else {
                                $playLodge.append('<option value="">No lodges found for this council</option>');
                            }

                            $playStatus.html('<strong>✓ Success!</strong> Loaded <strong>' + camps.length + '</strong> camps and <strong>' + lodges.length + '</strong> lodges via AJAX.');
                        }
                    }
                });
            });
        }

    });
})(jQuery);
