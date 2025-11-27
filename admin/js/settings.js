(function ($) {
    $(function () {
        var $button = $('#gig-test-api');
        var $result = $('#gig-test-result');

        if (!$button.length) return;

        $button.on('click', function () {
            $result.removeClass('success error').css('color', '#666').text('Teste…');
            $button.prop('disabled', true);

            $.post(GIGSettings.ajaxUrl, {
                action: 'gig_test_api_connection',
                nonce: $button.data('nonce')
            }).done(function (response) {
                if (response.success) {
                    $result.css('color', '#00a32a').text('✓ ' + response.data);
                } else {
                    $result.css('color', '#d63638').text('✗ ' + (response.data || 'Fehler'));
                }
            }).fail(function () {
                $result.css('color', '#d63638').text('✗ Verbindungsfehler');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
