<style>
    table,
    th,
    td {
        border: 1px solid #000;
        border-collapse: collapse;
    }

    .text-center {
        text-align: center;
    }

    .bg-grand-total {
        background-color: #d1f9ff;
    }

    .bg-subtotal {
        background-color: #dff0d8;
    }

</style>
<h3 class="text-center">{{ get_business_name() }}</h3>
<h3 class="text-center">{{ $page_title }}</h3>
@switch(Request::get('view'))
    @case('monthly')
        <table style="font-size: 11px;">
            @include('accounting::report.budget.partials.monthly_view_table')
        </table>
    @break

    @case('quarterly')
        <table>
            @include('accounting::report.budget.partials.quarterly_view_table')
        </table>
    @break

    @default
        <div class="alert alert-info">
            {{ trans('accounting::lang.an_error_occurred') }}
        </div>
@endswitch
