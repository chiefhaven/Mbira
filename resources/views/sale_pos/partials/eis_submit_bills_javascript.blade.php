<script type="text/javascript">
$(document).ready(function() {
    // Submit All Bills - Single event handler
    $(document).on('click', '.submit-all-bills-btn', function(e) {
        e.preventDefault();
        
        // Show loading state
        var btn = $(this);
        var originalHtml = btn.html();
        var isProcessing = false;
    
        if (isProcessing) {
            return;
        }
        
        isProcessing = true;
        btn.html('<i class="fa fa-spinner fa-spin"></i> ' + '{{ __("lang_v1.checking") }}...');
        btn.prop('disabled', true);
        
        // First check if there are any unsubmitted bills
        $.ajax({
            url: "{{ route('sells.check-unsubmitted-bills') }}",
            type: 'GET',
            success: function(response) {
                // Reset button
                btn.html(originalHtml);
                btn.prop('disabled', false);
                
                if (response.count > 0) {

                    var message = "{{ __('lang_v1.you_are_about_to_submit_all_bills') }}\n\n" + 
                    "{{ __('lang_v1.total_unsubmitted_bills') }}: " + response.count + "\n";
    
                    // Add additional info if available
                    if (response.total_amount) {
                        message += "{{ __('lang_v1.total_amount') }}: " + response.total_amount + "\n";
                    }
                    // Show confirmation dialog with count
                    swal({
                        title: "{{ __('lang_v1.are_you_sure') }}",
                        text: message,
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
                                text: "{{ __('lang_v1.yes_submit_all') }}",
                                value: true,
                                visible: true,
                                className: "btn-success",
                                closeModal: true
                            }
                        },
                        dangerMode: true,
                    }).then(function(isConfirm) {
                        if (isConfirm) {
                            // Show processing state
                            btn.html('<i class="fa fa-spinner fa-spin"></i> ' + '{{ __("lang_v1.processing") }}...');
                            btn.prop('disabled', true);
                            
                            // Perform the submit action
                            $.ajax({
                                url: "{{ route('sells.eis-submit-all-bills') }}",
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function(result) {
                                    // Reset button
                                    btn.html(originalHtml);
                                    btn.prop('disabled', false);
                                    
                                    if (result.success) {
                                        toastr.success(result.message || '{{ __("lang_v1.bills_submitted_successfully") }}');
                                        // Reload the datatable if it exists
                                        if (typeof sell_table !== 'undefined') {
                                            sell_table.ajax.reload();
                                        }
                                        // Reload the page after 2 seconds to update summary cards
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    } else {
                                        var details = (result.failed || []).map(function(item) {
                                            return item.invoice_no + ': ' + item.error;
                                        }).join('\n');
                                        toastr.error([result.message || result.error, details].filter(Boolean).join('\n') || '{{ __("messages.something_went_wrong") }}');
                                    }
                                },
                                error: function(xhr) {
                                    // Reset button
                                    btn.html(originalHtml);
                                    btn.prop('disabled', false);
                                    
                                    var errorMsg = "{{ __('messages.something_went_wrong') }}";
                                    if (xhr.responseJSON && xhr.responseJSON.error) {
                                        errorMsg = xhr.responseJSON.error;
                                    }
                                    toastr.error(errorMsg);
                                }
                            });
                        }
                    });
                } else {
                    toastr.info("{{ __('lang_v1.no_unsubmitted_bills_found') }}");
                }
            },
            error: function(xhr) {
                // Reset button
                btn.html(originalHtml);
                btn.prop('disabled', false);
                var errorMsg = "{{ __('messages.something_went_wrong') }}";
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                toastr.error(errorMsg);
            }
        });

        isProcessing = false;
    });
});
</script>