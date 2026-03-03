/* OE Ambassador — Frontend JS */
jQuery(function ($) {

    // ── Application form submission ────────────────────────────────────────
    $('#oe-amb-apply-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#oe-amb-submit');
        var $msg     = $('#oe-amb-apply-msg');
        var origText = $btn.text();

        $btn.prop('disabled', true).text(oeAmbPub.i18n.submitting);
        $msg.removeClass('success error').hide();

        var data = $form.serializeArray();
        data.push({ name: 'action', value: 'oe_amb_submit_application' });
        data.push({ name: 'nonce',  value: oeAmbPub.nonce });

        $.post(oeAmbPub.ajaxUrl, data, function (res) {
            $btn.prop('disabled', false).text(origText);

            if (res.success) {
                $msg.addClass('success').text(res.data).show();
                $form.hide();
                $('html, body').animate({ scrollTop: $msg.offset().top - 80 }, 400);
            } else {
                $msg.addClass('error').text(res.data || 'An error occurred. Please try again.').show();
                $('html, body').animate({ scrollTop: $msg.offset().top - 80 }, 400);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(origText);
            $msg.addClass('error').text('Network error. Please try again.').show();
        });
    });

    // ── Copy code to clipboard ─────────────────────────────────────────────
    $(document).on('click', '.oe-amb-copy-btn', function () {
        var $btn    = $(this);
        var targetId = $btn.data('target');
        var text     = $('#' + targetId).text().trim();

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopied($btn);
            });
        } else {
            // Fallback
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopied($btn);
        }
    });

    function showCopied($btn) {
        var orig = $btn.text();
        $btn.text(oeAmbPub.i18n.copy_ok);
        setTimeout(function () { $btn.text(orig); }, 2000);
    }
});
