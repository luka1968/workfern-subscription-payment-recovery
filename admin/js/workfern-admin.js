/**
 * Admin JavaScript â€?Woo Stripe Recovery Pro
 *
 * @since   1.0.0
 * @package WooStripeRecoveryPro
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        /*
        |----------------------------------------------------------------------
        | Settings: Password Field Toggle (e.g. Webhook Secret)
        |----------------------------------------------------------------------
        */

        var $secretFields = $('input[type="password"]');

        $secretFields.each(function () {
            var $input = $(this);
            var $toggle = $('<button type="button" class="button button-small" style="margin-left:8px;vertical-align:middle;">Show</button>');

            $toggle.on('click', function () {
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $toggle.text('Hide');
                } else {
                    $input.attr('type', 'password');
                    $toggle.text('Show');
                }
            });

            $input.after($toggle);
        });

        /*
        |----------------------------------------------------------------------
        | Dashboard: Animate Stat Numbers
        |----------------------------------------------------------------------
        */

        $('.workfern-stat-number').each(function () {
            var $el = $(this);
            var text = $el.text().trim();
            var match = text.match(/([\d,.]+)/);

            if (!match) return;

            var raw = match[1].replace(/,/g, '');
            var target = parseFloat(raw);

            if (isNaN(target) || target === 0) return;

            var prefix = text.substring(0, text.indexOf(match[1]));
            var suffix = text.substring(text.indexOf(match[1]) + match[1].length);
            var isFloat = raw.indexOf('.') !== -1;
            var duration = 800;
            var start = 0;
            var startTime = null;

            function animate(timestamp) {
                if (!startTime) startTime = timestamp;

                var progress = Math.min((timestamp - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                var current = start + (target - start) * eased;

                if (isFloat) {
                    $el.text(prefix + current.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + suffix);
                } else {
                    $el.text(prefix + Math.round(current).toLocaleString() + suffix);
                }

                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            }

            requestAnimationFrame(animate);
        });

        /*
        |----------------------------------------------------------------------
        | Copy Recovery Link
        |----------------------------------------------------------------------
        */

        $(document).on('click', '.workfern-copy-link', function (e) {
            e.preventDefault();

            var url = $(this).data('url');

            if (!url) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    showNotice('Link copied to clipboard.');
                });
            } else {
                // Fallback.
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                document.execCommand('copy');
                $temp.remove();
                showNotice('Link copied to clipboard.');
            }
        });

        /*
        |----------------------------------------------------------------------
        | Helper: Admin Notice
        |----------------------------------------------------------------------
        */

        function showNotice(message) {
            var $notice = $('<div class="notice notice-success is-dismissible" style="position:fixed;top:40px;right:20px;z-index:99999;max-width:350px;"><p>' + $('<span>').text(message).html() + '</p></div>');

            $('body').append($notice);

            setTimeout(function () {
                $notice.fadeOut(300, function () {
                    $notice.remove();
                });
            }, 2500);
        }

    });

})(jQuery);
