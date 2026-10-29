$(function () {
    //Sweet alert confirm delete
    $(document).on('click', '.confirm', function (e) {
        e.preventDefault();
        var url = $(this).attr('href');
        swal({
            title: "Are you sure?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
            .then((willDelete) => {
                if (willDelete) {
                    location.href = url;
                }
            });
    });

    //Sweet alert confirm update
    $(document).on('click', '.confirm-update', function (e) {
        e.preventDefault();
        sweetAlertForUpdate($(this));
    });
    
    //Sweet alert confirm delete
    $(document).on('click', '.confirm-delete', function (e) {
        e.preventDefault();
        sweetAlertForDelete($(this));
    });

    function sweetAlertForUpdate(element) {
        const formId = element.attr('form_id');
        const form = document.getElementById(formId);

        swal({
            title: "Are you sure?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
            .then((willDelete) => {
                if (willDelete) {
                    form.submit();
                }
            });
    }

    function sweetAlertForDelete(element) {
        const action = element.attr('action');
        const formId = element.attr('form_id');
        const form = document.getElementById(formId);

        swal({
            title: "Are you sure?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        })
            .then((willDelete) => {
                if (willDelete) {
                    form.setAttribute('action', action);
                    form.submit();
                }
            });
    }
});