/* OE Ambassador Admin JS */
jQuery(function ($) {
    var nonce   = oeAmb.nonce;
    var ajaxUrl = oeAmb.ajaxUrl;

    // ── Approve commission (inline) ────────────────────────────────────────
    $(document).on('click', '.oe-approve-commission', function () {
        var $btn = $(this);
        var id   = parseInt($btn.data('id'));
        if (!id) return;

        $btn.prop('disabled', true).text('...');

        $.post(ajaxUrl, {
            action:        'oe_amb_approve_commission',
            nonce:         nonce,
            commission_id: id
        }, function (res) {
            if (res.success) {
                $btn.closest('tr').find('span').css({'background':'#e3f2fd','color':'#1565c0'}).text('Approved');
                $btn.remove();
            } else {
                alert('Error approving commission.');
                $btn.prop('disabled', false).text('Approve');
            }
        });
    });

    // ── Create payout ──────────────────────────────────────────────────────
    $('#oe-payout-btn').on('click', function () {
        var $btn   = $(this);
        var ambId  = parseInt($btn.data('amb'));
        var from   = $('#oe-payout-from').val();
        var to     = $('#oe-payout-to').val();
        var notes  = $('#oe-payout-notes').val();

        if (!from || !to) {
            alert('Please set a date range.');
            return;
        }
        if (!confirm(oeAmb.i18n.confirm_payout)) return;

        $btn.prop('disabled', true).text('Processing...');
        $('#oe-payout-result').html('');

        $.post(ajaxUrl, {
            action:        'oe_amb_create_payout',
            nonce:         nonce,
            ambassador_id: ambId,
            date_from:     from,
            date_to:       to,
            notes:         notes
        }, function (res) {
            $btn.prop('disabled', false).text('Create Payout');
            if (res.success) {
                $('#oe-payout-result').html(
                    '<div class="notice notice-success"><p>✓ Payout created! Amount: <strong>' +
                    parseFloat(res.data.amount).toFixed(2) + '</strong>. Ambassador notified.</p></div>'
                );
                setTimeout(function () { location.reload(); }, 2000);
            } else {
                $('#oe-payout-result').html(
                    '<div class="notice notice-error"><p>' + (res.data || 'Error creating payout.') + '</p></div>'
                );
            }
        });
    });

    // ── Tier management ────────────────────────────────────────────────────
    var tierTemplate = '<tr>' +
        '<td class="tier-num"></td>' +
        '<td><input type="number" name="tier_min[]" value="0" min="0" style="width:80px"></td>' +
        '<td><input type="number" name="tier_max[]" value="-1" min="-1" style="width:80px"></td>' +
        '<td><input type="number" name="tier_pct[]" value="10" min="0.1" max="100" step="0.1" style="width:80px"> %</td>' +
        '<td><button type="button" class="button oe-remove-tier" style="color:#c62828">Remove</button></td>' +
        '</tr>';

    function renumberTiers() {
        $('#oe-tiers-body tr').each(function (i) {
            $(this).find('.tier-num').text(i + 1);
        });
    }

    $('#oe-add-tier').on('click', function () {
        $('#oe-tiers-body').append(tierTemplate);
        renumberTiers();
    });

    $(document).on('click', '.oe-remove-tier', function () {
        if ($('#oe-tiers-body tr').length <= 1) {
            alert('You must have at least one tier.');
            return;
        }
        $(this).closest('tr').remove();
        renumberTiers();
    });

    renumberTiers();
});
