@php
    $go_back_url = action([\App\Http\Controllers\SellPosController::class, 'index']);
    $transaction_sub_type = '';
    $view_suspended_sell_url = action([\App\Http\Controllers\SellController::class, 'index']) . '?suspended=1';
    $pos_redirect_url = action([\App\Http\Controllers\SellPosController::class, 'create']);
@endphp

@if (!empty($pos_module_data))
    @foreach ($pos_module_data as $key => $value)
        @php
            if (!empty($value['go_back_url'])) {
                $go_back_url = $value['go_back_url'];
            }
            if (!empty($value['transaction_sub_type'])) {
                $transaction_sub_type = $value['transaction_sub_type'];
                $view_suspended_sell_url .= '&transaction_sub_type=' . $transaction_sub_type;
                $pos_redirect_url .= '?sub_type=' . $transaction_sub_type;
            }
        @endphp
    @endforeach
@endif

<input type="hidden" name="transaction_sub_type" id="transaction_sub_type" value="{{ $transaction_sub_type }}">
@inject('request', 'Illuminate\Http\Request')

<style>
.ph *{box-sizing:border-box}
.ph{
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 16px rgba(17,17,26,.08);
    margin:4px 0 0;
    padding:10px 16px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

/* ── Location block ── */
.ph__location{
    display:flex;
    align-items:center;
    gap:8px;
    flex-shrink:0;
}
.ph__location-label{
    font-size:11px;
    font-weight:600;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:#6b7280;
    white-space:nowrap;
}
.ph__location select.form-control{
    height:30px;
    font-size:13px;
    padding:0 8px;
    border-radius:6px;
    border:1px solid #d1d5db;
    min-width:120px;
}
.ph__location-name{
    font-size:13px;
    font-weight:600;
    color:#111827;
}

/* ── Datetime chip ── */
.ph__datetime{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:#646EE4;
    color:#fff;
    font-size:12px;
    font-weight:600;
    padding:5px 10px;
    border-radius:6px;
    white-space:nowrap;
}
.ph__datetime i{cursor:pointer;opacity:.8}
.ph__datetime i:hover{opacity:1}

/* ── Toolbar ── */
.ph__toolbar{
    display:flex;
    align-items:center;
    gap:4px;
    flex-wrap:wrap;
}

/* ── Icon button base ── */
.ph__btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    width:34px;
    height:34px;
    border:1px solid #e5e7eb;
    border-radius:8px;
    background:#fff;
    color:#374151;
    cursor:pointer;
    transition:background .15s, border-color .15s, color .15s;
    font-size:14px;
    text-decoration:none;
    flex-shrink:0;
}
.ph__btn:hover{background:#f3f4f6;border-color:#9ca3af;color:#111827}
.ph__btn:active{transform:scale(.96)}

/* ── Text button (labelled) ── */
.ph__btn--text{
    width:auto;
    padding:0 10px;
    font-size:12px;
    font-weight:600;
    gap:5px;
}

/* ── Colour accents on icons ── */
.ic-blue{color:#009EE4}
.ic-indigo{color:#646EE4}
.ic-green{color:#00935F}
.ic-red{color:#EF4B53}
.ic-gray{color:#A5ADBB}

/* ── Divider ── */
.ph__sep{
    width:1px;height:22px;
    background:#e5e7eb;
    flex-shrink:0;
    margin:0 2px;
}

/* ── Mobile hamburger ── */
.ph__hamburger{
    display:none;
    align-items:center;
    gap:8px;
    margin-left:auto;
}
.ph__hamburger .ph__btn{border:none;background:transparent;font-size:18px}

@media(max-width:767px){
    .ph{flex-wrap:wrap;padding:10px 12px;gap:8px}
    .ph__hamburger{display:flex}
    .ph__toolbar{
        display:none;
        width:100%;
        flex-direction:column;
        align-items:stretch;
        border-top:1px solid #e5e7eb;
        padding-top:8px;
        gap:6px;
    }
    .ph__toolbar.is-open{display:flex}
    .ph__btn{width:100%;height:36px;justify-content:flex-start;padding:0 10px;border-radius:8px}
    .ph__btn--text{width:100%}
    .ph__sep{display:none}
    .ph__datetime{display:none}
    .ph__mobile-products{display:inline-flex!important}
}
.ph__mobile-products{display:none}
</style>

<div class="col-md-12 no-print pos-header">
    <input type="hidden" id="pos_redirect_url" value="{{ $pos_redirect_url }}">

    <div class="ph">

        {{-- ── Left: location + datetime ── --}}
        <div class="ph__location">
            <span class="ph__location-label">@lang('sale.location')</span>

            @if (empty($transaction->location_id))
                @if (count($business_locations) > 1)
                    {!! Form::select(
                        'select_location_id',
                        $business_locations,
                        $default_location->id ?? null,
                        ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required', 'autofocus'],
                        $bl_attributes,
                    ) !!}
                @else
                    <span class="ph__location-name">{{ $default_location->name }}</span>
                @endif
            @else
                <span class="ph__location-name">{{ $transaction->location->name }}</span>
            @endif

            <span class="ph__datetime">
                <span class="curr_datetime">{{ @format_datetime('now') }}</span>
                <i class="fa fa-keyboard hover-q" aria-hidden="true"
                   data-container="body" data-toggle="popover" data-placement="bottom"
                   data-content="@include('sale_pos.partials.keyboard_shortcuts_details')"
                   data-html="true" data-trigger="hover" title=""></i>
            </span>
        </div>

        {{-- ── Mobile: product suggestion + hamburger ── --}}
        <div class="ph__hamburger">
            @if (empty($pos_settings['hide_product_suggestion']))
                <button type="button"
                    title="{{ __('lang_v1.view_products') }}"
                    class="ph__btn ph__mobile-products btn-modal"
                    data-toggle="modal" data-target="#mobile_product_suggestion_modal">
                    <i class="fa fa-cubes ic-green"></i>
                </button>
            @endif

            <button type="button" class="ph__btn" aria-label="More options"
                onclick="document.getElementById('pos_header_more_options').classList.toggle('is-open')">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        {{-- ── Toolbar ── --}}
        <div class="ph__toolbar" id="pos_header_more_options">

            {{-- Go back --}}
            <a href="{{ $go_back_url }}" title="{{ __('lang_v1.go_back') }}" class="ph__btn ph__btn--text">
                <i class="fa fa-arrow-left ic-blue"></i>
                <span class="ph-label">{{ __('lang_v1.go_back') }}</span>
            </a>

            <div class="ph__sep"></div>

            {{-- Recent transactions (mobile only row) --}}
            @if (!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0)
                <button type="button"
                    class="ph__btn ph__btn--text d-md-none"
                    data-toggle="modal" data-target="#recent_transactions_modal" id="recent-transactions-header">
                    <i class="fa fa-clock ic-indigo"></i>
                    <span class="ph-label">{{ __('lang_v1.recent_transactions') }}</span>
                </button>
            @endif

            {{-- Service staff availability --}}
            @if (!empty($pos_settings['inline_service_staff']))
                <button type="button"
                    id="show_service_staff_availability"
                    title="{{ __('lang_v1.service_staff_availability') }}"
                    class="ph__btn ph__btn--text"
                    data-container=".view_modal"
                    data-href="{{ action([\App\Http\Controllers\SellPosController::class, 'showServiceStaffAvailibility']) }}">
                    <i class="fa fa-users ic-indigo"></i>
                    <span class="ph-label">{{ __('lang_v1.service_staff_availability') }}</span>
                </button>
            @endif

            {{-- Close register --}}
            @can('close_cash_register')
                <button type="button"
                    id="close_register"
                    title="{{ __('cash_register.close_register') }}"
                    class="ph__btn ph__btn--text btn-modal"
                    data-container=".close_register_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getCloseRegister']) }}">
                    <i class="fa fa-window-close ic-red"></i>
                    <span class="ph-label">{{ __('cash_register.close_register') }}</span>
                </button>
            @endcan

            {{-- Service staff replacement --}}
            @if (!empty($pos_settings['inline_service_staff']) || (in_array('tables', $enabled_modules) || in_array('service_staff', $enabled_modules)))
                <button type="button"
                    id="service_staff_replacement"
                    title="{{ __('restaurant.service_staff_replacement') }}"
                    class="ph__btn ph__btn--text popover-default"
                    data-toggle="popover" data-trigger="click" data-html="true" data-placement="bottom"
                    data-content='<div class="m-8"><input type="text" class="form-control" placeholder="@lang('sale.invoice_no')" id="send_for_sell_service_staff_invoice_no"></div><div class="w-100 text-center"><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error" id="send_for_sercice_staff_replacement">@lang('lang_v1.send')</button></div>'>
                    <i class="fa fa-user-plus ic-indigo"></i>
                    <span class="ph-label">{{ __('restaurant.service_staff_replacement') }}</span>
                </button>
            @endif

            {{-- Register details --}}
            @can('view_cash_register')
                <button type="button"
                    id="register_details"
                    title="{{ __('cash_register.register_details') }}"
                    class="ph__btn ph__btn--text btn-modal"
                    data-container=".register_details_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getRegisterDetails']) }}">
                    <i class="fa fa-briefcase ic-green"></i>
                    <span class="ph-label">{{ __('cash_register.register_details') }}</span>
                </button>
            @endcan

            <div class="ph__sep"></div>

            {{-- Calculator --}}
            <button type="button"
                id="btnCalculator"
                title="@lang('lang_v1.calculator')"
                class="ph__btn ph__btn--text popover-default"
                data-toggle="popover" data-trigger="click" data-html="true" data-placement="bottom"
                data-content='@include('layouts.partials.calculator')'>
                <i class="fa fa-calculator ic-green"></i>
                <span class="ph-label">{{ __('lang_v1.calculator') }}</span>
            </button>

            {{-- Sell return --}}
            <button type="button"
                id="return_sale"
                title="@lang('lang_v1.sell_return')"
                class="ph__btn ph__btn--text popover-default"
                data-toggle="popover" data-trigger="click" data-html="true" data-placement="bottom"
                data-content='<div class="m-8"><input type="text" class="form-control" placeholder="@lang('sale.invoice_no')" id="send_for_sell_return_invoice_no"></div><div class="w-100 text-center"><button type="button" class="tw-dw-btn tw-dw-btn-error tw-text-white tw-dw-btn-sm" id="send_for_sell_return">@lang('lang_v1.send')</button></div>'>
                <i class="fas fa-undo ic-red"></i>
                <span class="ph-label">{{ __('lang_v1.sell_return') }}</span>
            </button>

            {{-- Full screen --}}
            <button type="button"
                id="full_screen"
                title="{{ __('lang_v1.full_screen') }}"
                class="ph__btn ph__btn--text">
                <i class="fa fa-window-maximize ic-indigo"></i>
                <span class="ph-label">Full screen</span>
            </button>

            {{-- View suspended sales --}}
            <button type="button"
                id="view_suspended_sales"
                title="{{ __('lang_v1.view_suspended_sales') }}"
                class="ph__btn ph__btn--text btn-modal"
                data-container=".view_modal"
                data-href="{{ $view_suspended_sell_url }}">
                <i class="fa fa-pause-circle ic-gray"></i>
                <span class="ph-label">{{ __('lang_v1.view_suspended_sales') }}</span>
            </button>

            {{-- Customer display screen --}}
            @if (!empty($pos_settings['customer_display_screen']))
                <a href="{{ route('pos_display') }}"
                    id="customer_display_screen"
                    title="{{ __('lang_v1.customer_display_screen') }}"
                    onclick="window.open(this.href,'customer_display','width='+screen.width+',height='+screen.height+',top=0,left=0'); return false;"
                    class="ph__btn ph__btn--text">
                    <i class="fa fa-tv ic-indigo"></i>
                    <span class="ph-label">{{ __('lang_v1.customer_display_screen') }}</span>
                </a>
            @endif

            {{-- Repair module --}}
            @if (Module::has('Repair') && $transaction_sub_type != 'repair')
                @include('repair::layouts.partials.pos_header')
            @endif

            @if (in_array('pos_sale', $enabled_modules) && !empty($transaction_sub_type))
                @can('sell.create')
                    <div class="ph__sep"></div>
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}"
                        title="@lang('sale.pos_sale')"
                        class="ph__btn ph__btn--text">
                        <i class="fa fa-th-large ic-green"></i>
                        <span class="ph-label">@lang('sale.pos_sale')</span>
                    </a>
                @endcan
            @endif

            {{-- Add expense --}}
            @can('expense.add')
                <button type="button"
                    id="add_expense"
                    title="{{ __('expense.add_expense') }}"
                    class="ph__btn ph__btn--text btn-modal">
                    <i class="fa fa-minus-circle ic-red"></i>
                    <span class="ph-label">@lang('expense.add_expense')</span>
                </button>
            @endcan

        </div>{{-- /.ph__toolbar --}}

    </div>{{-- /.ph --}}
</div>

<div class="modal fade" id="service_staff_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>