<script type="text/javascript">
$(document).ready(function() {
    // Clear All Bills - Single event handler
    $(document).on('click', '.clear-all-bills-btn', function(e) {
        e.preventDefault();
        
        // Show loading state
        var btn = $(this);
        var originalHtml = btn.html();
        btn.html('<i class="fa fa-spinner fa-spin"></i> ' + '{{ __("lang_v1.checking") }}...');
        btn.prop('disabled', true);
        
        // First check if there are any unpaid bills
        $.ajax({
            url: "{{ route('sells.check-unpaid-bills') }}",
            type: 'GET',
            success: function(response) {
                // Reset button
                btn.html(originalHtml);
                btn.prop('disabled', false);
                
                if (response.count > 0) {
                    // Show confirmation dialog with count
                    swal({
                        title: "{{ __('lang_v1.are_you_sure') }}",
                        text: "{{ __('lang_v1.you_are_about_to_clear_all_unpaid_bills') }}\n\n" + 
                              "{{ __('lang_v1.total_unpaid_invoices') }}: " + response.count + "\n" +
                              "{{ __('lang_v1.total_due_amount') }}: " + response.total_due,
                        icon: "warning",
                        buttons: {
                            cancel: {
                                text: "{{ __('lang_v1.cancel') }}",
                                value: null,
                                visible: true,
                                className: "btn-default",
                                closeModal: true,
                            },
                            confirm: {
                                text: "{{ __('lang_v1.yes_clear_all') }}",
                                value: true,
                                visible: true,
                                className: "btn-danger",
                                closeModal: true
                            }
                        },
                        dangerMode: true,
                    }).then(function(isConfirm) {
                        if (isConfirm) {
                            // Show processing state
                            btn.html('<i class="fa fa-spinner fa-spin"></i> ' + '{{ __("lang_v1.processing") }}...');
                            btn.prop('disabled', true);
                            
                            // Perform the clear action
                            $.ajax({
                                url: "{{ route('sells.clear-all-bill') }}",
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    confirm: 'yes'
                                },
                                success: function(result) {
                                    // Reset button
                                    btn.html(originalHtml);
                                    btn.prop('disabled', false);
                                    
                                    if (result.success) {
                                        toastr.success(result.msg);
                                        // Reload the datatable if it exists
                                        if (typeof sell_table !== 'undefined') {
                                            sell_table.ajax.reload();
                                        }
                                        // Reload the page after 2 seconds to update summary cards
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    } else {
                                        toastr.error(result.msg);
                                    }
                                },
                                error: function(xhr) {
                                    // Reset button
                                    btn.html(originalHtml);
                                    btn.prop('disabled', false);
                                    
                                    var errorMsg = "{{ __('messages.something_went_wrong') }}";
                                    if (xhr.responseJSON && xhr.responseJSON.msg) {
                                        errorMsg = xhr.responseJSON.msg;
                                    }
                                    toastr.error(errorMsg);
                                }
                            });
                        }
                    });
                } else {
                    toastr.info("{{ __('lang_v1.no_unpaid_bills_found') }}");
                }
            },
            error: function() {
                // Reset button
                btn.html(originalHtml);
                btn.prop('disabled', false);
                toastr.error("{{ __('messages.something_went_wrong') }}");
            }
        });
    });
});
</script>