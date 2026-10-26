<script type="text/javascript">
    $(document).ready(function(){

        if($('#follow_ups_by_user_table').length > 0){

            $('#follow_up_user_date_range').daterangepicker(
                dateRangeSettings,
                function (start, end) {
                    $('#follow_up_user_date_range').val(start.format(moment_date_format) + ' - ' + end.format(moment_date_format));
                    //pass parameter in ajax call
                    follow_ups_by_user_table.ajax.reload();
                }
            );
            $('#follow_up_user_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#follow_up_user_date_range').val('');
                //pass parameter in ajax call
                follow_ups_by_user_table.ajax.reload();
            });

            $('#followup_category_id').change(function(){
                follow_ups_by_user_table.ajax.reload();
            });
        
            var follow_ups_by_user_table = 
            $("#follow_ups_by_user_table").DataTable({
                processing: true,
                serverSide: true,
                scrollY: "75vh",
                scrollX: true,
                scrollCollapse: true,
                fixedHeader: false,
                'ajax': {
                    url: "{{action([\Modules\Crm\Http\Controllers\ReportController::class, 'followUpsByUser'])}}",

                    data: function(d) {
                        var start = '';
                        var end = '';
                        if ($('#follow_up_user_date_range').val()) {
                            start = $('input#follow_up_user_date_range')
                                .data('daterangepicker')
                                .startDate.format('YYYY-MM-DD');
                            end = $('input#follow_up_user_date_range')
                                .data('daterangepicker')
                                .endDate.format('YYYY-MM-DD');
                        }

                        d.start_date = start;
                        d.end_date = end;
                        d.followup_category_id = $('#followup_category_id').val();
                    },

                },
                columns: [
                    { data: 'full_name', name: 'full_name' },
                    @foreach($statuses as $key => $value)
                        { data: 'count_{{$key}}', searchable: false },
                    @endforeach
                    { data: 'count_nulled', searchable: false },
                    { data: 'total_follow_ups', searchable: false }
                ],
            });
        }

        var follow_ups_by_contact_table = 
        $("#follow_ups_by_contact_table").DataTable({
            processing: true,
            serverSide: true,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            'ajax': {
                url: "{{action([\Modules\Crm\Http\Controllers\ReportController::class, 'followUpsContact'])}}"
            },
            columns: [
                { data: 'contact_name', name: 'contact_name' },
                @foreach($statuses as $key => $value)
                    { data: 'count_{{$key}}', searchable: false },
                @endforeach
                { data: 'count_nulled', searchable: false },
                { data: 'total_follow_ups', searchable: false }
            ],
        });

        var lead_to_customer_conversion = 
        $("#lead_to_customer_conversion").DataTable({
            processing: true,
            serverSide: true,
            scrollY: "75vh",
            scrollX: true,
            scrollCollapse: true,
            fixedHeader: false,
            aaSorting: [[1, 'desc']],
            'ajax': {
                url: "{{action([\Modules\Crm\Http\Controllers\ReportController::class, 'leadToCustomerConversion'])}}"
            },
            columns: [
                {
                    orderable: false,
                    searchable: false,
                    data: null,
                    defaultContent: '',
                },
                { data: 'full_name', name: 'full_name' },
                { data: 'total_conversions', searchable: false }
            ],
            createdRow: function(row, data, dataIndex) {
                $(row).find('td:eq(0)')
                    .addClass('details-control');
            },
        });

        // Array to track the ids of the details displayed rows
        var ltc_detail_rows = [];

        $('#lead_to_customer_conversion tbody').on('click', 'tr td.details-control', function() {
            var tr = $(this).closest('tr');
            var row = lead_to_customer_conversion.row(tr);
            var idx = $.inArray(tr.attr('id'), ltc_detail_rows);

            if (row.child.isShown()) {
                tr.removeClass('details');
                row.child.hide();

                // Remove from the 'open' array
                ltc_detail_rows.splice(idx, 1);
            } else {
                tr.addClass('details');

                row.child(show_lead_to_customer_details(row.data())).show();

                // Add to the 'open' array
                if (idx === -1) {
                    ltc_detail_rows.push(tr.attr('id'));
                }
            }
        });

        // On each draw, loop over the `detailRows` array and show any child rows
        lead_to_customer_conversion.on('draw', function() {
            $.each(ltc_detail_rows, function(i, id) {
                $('#' + id + ' td.details-control').trigger('click');
            });
        });

        function show_lead_to_customer_details(rowData) {
            var div = $('<div/>')
                .addClass('loading')
                .text('Loading...');
            $.ajax({
                url: '/crm/lead-to-customer-details/' + rowData.DT_RowId,
                dataType: 'html',
                success: function(data) {
                    div.html(data).removeClass('loading');
                },
            });

            return div;
        }
    });
</script>