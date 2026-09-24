(function ($) {
    'use strict';

    $(document).ready(function () {
        // State change -> update Councils
        $(document).on('change', '.sm-state-select', function () {
            var $form = $(this).closest('form');
            var stateId = $(this).val();
            var $councilSelect = $form.find('.sm-council-select');
            var $campSelect = $form.find('.sm-camp-select');
            var $lodgeSelect = $form.find('.sm-lodge-select');

            if ($councilSelect.length === 0) return;

            $councilSelect.html('<option value="">Loading Councils...</option>').prop('disabled', true);
            if ($campSelect.length) $campSelect.html('<option value="">Select a Council first...</option>').prop('disabled', true);
            if ($lodgeSelect.length) $lodgeSelect.html('<option value="">Select a Council first...</option>').prop('disabled', true);

            if (!stateId) {
                $councilSelect.html('<option value="">Select a State first...</option>').prop('disabled', false);
                return;
            }

            $.ajax({
                url: smAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sm_get_councils',
                    state_id: stateId,
                    security: smAjax.nonce
                },
                success: function (res) {
                    if (res.success && res.data) {
                        var opts = '<option value="">-- Select Council --</option>';
                        var targetVal = $councilSelect.data('selected') || '';
                        $.each(res.data, function (i, c) {
                            var sel = (c.id == targetVal || c.slug == targetVal) ? ' selected' : '';
                            opts += '<option value="' + c.id + '" data-slug="' + c.slug + '"' + sel + '>' + c.display_name + '</option>';
                        });
                        $councilSelect.html(opts).prop('disabled', false);

                        if (targetVal) {
                            $councilSelect.trigger('change');
                        }
                    } else {
                        $councilSelect.html('<option value="">No councils found</option>').prop('disabled', false);
                    }
                },
                error: function () {
                    $councilSelect.html('<option value="">Error loading councils</option>').prop('disabled', false);
                }
            });
        });

        // Council change -> update Camps and Lodges
        $(document).on('change', '.sm-council-select', function () {
            var $form = $(this).closest('form');
            var councilId = $(this).val();
            var stateId = $form.find('.sm-state-select').val() || '';
            var $campSelect = $form.find('.sm-camp-select');
            var $lodgeSelect = $form.find('.sm-lodge-select');

            if ($campSelect.length === 0 && $lodgeSelect.length === 0) return;

            if ($campSelect.length) $campSelect.html('<option value="">Loading Camps...</option>').prop('disabled', true);
            if ($lodgeSelect.length) $lodgeSelect.html('<option value="">Loading Lodges...</option>').prop('disabled', true);

            if (!councilId) {
                if ($campSelect.length) $campSelect.html('<option value="">Select a Council first...</option>');
                if ($lodgeSelect.length) $lodgeSelect.html('<option value="">Select a Council first...</option>');
                return;
            }

            $.ajax({
                url: smAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sm_get_camps_lodges',
                    council_id: councilId,
                    state_id: stateId,
                    security: smAjax.nonce
                },
                success: function (res) {
                    if (res.success && res.data) {
                        // Populate Camps
                        if ($campSelect.length) {
                            var campOpts = '<option value="">-- Select Camp (Optional) --</option>';
                            var targetCamp = $campSelect.data('selected') || '';
                            $.each(res.data.camps, function (i, camp) {
                                var sel = (camp.id == targetCamp || camp.slug == targetCamp) ? ' selected' : '';
                                campOpts += '<option value="' + camp.id + '" data-slug="' + camp.slug + '"' + sel + '>' + camp.name + '</option>';
                            });
                            $campSelect.html(campOpts).prop('disabled', false);
                        }

                        // Populate Lodges
                        if ($lodgeSelect.length) {
                            var lodgeOpts = '<option value="">-- Select Lodge (Optional) --</option>';
                            var targetLodge = $lodgeSelect.data('selected') || '';
                            $.each(res.data.lodges, function (i, lodge) {
                                var sel = (lodge.id == targetLodge || lodge.slug == targetLodge) ? ' selected' : '';
                                lodgeOpts += '<option value="' + lodge.id + '" data-slug="' + lodge.slug + '"' + sel + '>' + lodge.display_name + '</option>';
                            });
                            $lodgeSelect.html(lodgeOpts).prop('disabled', false);
                        }
                    }
                },
                error: function () {
                    if ($campSelect.length) $campSelect.html('<option value="">Error loading camps</option>').prop('disabled', false);
                    if ($lodgeSelect.length) $lodgeSelect.html('<option value="">Error loading lodges</option>').prop('disabled', false);
                }
            });
        });

        // Trigger on load if initial state is already selected
        $('.sm-state-select').each(function () {
            if ($(this).val()) {
                $(this).trigger('change');
            }
        });
    });

})(jQuery);
