@foreach($kitchen_tickets as $ticket)
    <div class="col-md-3 col-xs-6 order_div">
        <div class="small-box bg-gray">
            <div class="inner">
                <h4 class="text-center">#{{ $ticket['invoice_no'] }}</h4>
                <p><strong>@lang('restaurant.table'):</strong> {{ $ticket['table_name'] }}</p>
                <p><strong>@lang('restaurant.dispatch_round'):</strong> #{{ $ticket['id'] }}</p>
                <p>{{ $ticket['product_name'] }}</p>
                <p><strong>@lang('sale.qty'):</strong> {{ $ticket['quantity'] }}</p>
                <p><span class="label bg-light-blue">@lang('restaurant.order_statuses.received')</span></p>
            </div>
            <a href="#" class="btn btn-flat small-box-footer bg-yellow mark_ticket_as_cooked_btn"
               data-href="{{ action([\App\Http\Controllers\Restaurant\KitchenController::class, 'markTicketAsCooked'], [$ticket['transaction_id']]) }}"
               data-ticket-id="{{ $ticket['id'] }}" data-operation-id="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <i class="fa fa-check-square-o"></i> @lang('restaurant.mark_as_cooked')
            </a>
        </div>
    </div>
@endforeach
@if(count($kitchen_tickets))
    <div class="clearfix"></div>
@endif
