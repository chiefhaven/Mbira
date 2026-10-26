@if(count($schedule->invoices) > 0)
    <div class="col-md-12">
        <strong>
            <i class="fas fa-receipt margin-r-5"></i>
            @lang('lang_v1.invoices')
        </strong> <br>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>
                            @lang('sale.invoice_no')
                        </th>
                        <th>
                            @lang('messages.date')
                        </th>
                        <th>
                            @lang('sale.total_amount')
                        </th>
                        <th>
                            @lang('sale.total_paid')
                        </th>
                        <th>
                            @lang('sale.total_remaining')
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedule->invoices as $schedule_invoice)
                        <tr>
                            <td>
                                {{$schedule_invoice->invoice_no}}
                            </td>
                            <td>
                                {{@format_datetime($schedule_invoice->transaction_date)}}
                            </td>
                            <td>
                                @format_currency($schedule_invoice->final_total)
                            </td>
                            <td>
                                @php
                                    $paid = 0;
                                    foreach($schedule_invoice->payment_lines as $payment_line) {
                                        if ($payment_line->is_return) {
                                            $paid -= $payment_line->amount;
                                        } else {
                                            $paid += $payment_line->amount;
                                        }
                                    }
                                @endphp
                                @format_currency($paid)
                            </td>
                            <td>
                                @php
                                    $pending = $schedule_invoice->final_total - $paid;
                                @endphp
                                @format_currency($pending)
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif