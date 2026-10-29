$(function () {
    $(document).on('click', '.send-notification', function () {
        const url = $(this).attr('action');
        console.log(url);
        $.ajax({
            url: url,
            dataType: 'html',
            success: function (result) {
                $('.view_modal')
                    .html(result)
                    .modal('show');
            },
        });
    })
});