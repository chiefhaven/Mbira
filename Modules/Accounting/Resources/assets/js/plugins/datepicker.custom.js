$(function () {
    $('.datepicker')
        .datepicker({
            format: 'yyyy-mm-dd',
            defaultDate: new Date()
        }).attr('readonly', '');

    $('.datepicker-min-today')
        .datepicker({
            format: 'yyyy-mm-dd',
            defaultDate: new Date(),
            minDate: new Date(),
        }).attr('readonly', '');

    $(".year-datepicker").datepicker({
        format: "yyyy",
        viewMode: "years",
        minViewMode: "years"
    }).attr('readonly', '');
});