@extends('accounting::layouts.transactions_layout')
@section('tab-title')
    @yield('sub-tab-title')
@endsection

@section('tab-content')

    <div class="row">
        <div class="col-md-12">

            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs nav-justified">
                    <li class="@if (Request::get('type') == 'purchase_order') active @endif">
                        <a href="{{ request()->type != 'purchase_order' ? url('accounting/transactions/purchases?type=purchase_order') : '#' }}" aria-expanded="true">
                            <i class="fas fa-credit-card" aria-hidden="true"></i>
                            {{ trans_choice('accounting::general.purchase', 1) }} {{ trans_choice('accounting::general.order', 1) }}
                        </a>
                    </li>
                    <li class="@if (Request::get('type') == 'purchase_payment') active @endif">
                        <a href="{{ request()->type != 'purchase_payment' ? url('accounting/transactions/purchases?type=purchase_payment') : '#' }}" aria-expanded="true">
                            <i class="fas fa-file" aria-hidden="true"></i>
                            {{ trans_choice('accounting::general.purchase', 1) }} {{ trans_choice('accounting::general.payment', 1) }}
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane active">
                        @yield('sub-tab-content')
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('tab-javascript')
   @yield('sub-tab-javascript')
@endsection
