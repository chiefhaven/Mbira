@php
    $is_mobile = isMobile();
@endphp

{{-- =====================================================================
     POS Form Actions Bar
     Restyled for improved UX: clear visual hierarchy, semantic button
     grouping, responsive mobile layout.
     ===================================================================== --}}

<style>
.pos-bar *{box-sizing:border-box}
.pos-bar{
    background:#fff;
    border-top:1px solid #e5e7eb;
    box-shadow:0 -4px 24px rgba(0,0,0,.07);
    border-radius:12px 12px 0 0;
    padding:8px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    flex-wrap:nowrap;
    overflow-x:auto;
}

/* ── Total block ── */
.pos-bar__total{
    display:flex;
    align-items:baseline;
    gap:8px;
}
.pos-bar__total-label{
    font-size:11px;
    font-weight:600;
    letter-spacing:.07em;
    text-transform:uppercase;
    color:#6b7280;
    white-space:nowrap;
}
.pos-bar__total-amount{
    font-size:20px;
    font-weight:700;
    color:#064e3b;
    font-variant-numeric:tabular-nums;
    line-height:1;
}

/* ── Divider ── */
.pos-bar__divider{
    width:1px;
    height:28px;
    background:#e5e7eb;
    flex-shrink:0;
}

/* ── Button base ── */
.pos-bar .pb{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    border:1px solid #d1d5db;
    border-radius:6px;
    background:#fff;
    color:#374151;
    font-size:12px;
    font-weight:600;
    padding:8px 10px;
    min-height:38px;
    flex-shrink:0;
    cursor:pointer;
    transition:background .15s, border-color .15s, transform .1s;
    white-space:nowrap;
    text-decoration:none;
    line-height:1;
}

.pos-bar .pb:hover{background:#f3f4f6;border-color:#9ca3af}
.pos-bar .pb:active{transform:scale(.97)}
.pos-bar .pb:disabled{opacity:.45;cursor:not-allowed;pointer-events:none}
.pos-bar .pb i{font-size:15px;line-height:1}

/* Ghost (icon + label stacked) */
.pos-bar .pb--ghost{
    border-color:transparent;
    background:transparent;
    flex-direction:column;
    gap:3px;
    padding:4px 6px;
    font-size:10px;
    letter-spacing:.04em;
    color:#6b7280;
    border-radius:8px;
}
.pos-bar .pb--ghost:hover{background:#f3f4f6;color:#111827;border-color:transparent}
.pos-bar .pb--ghost i{font-size:15px}

/* Solid variants */
.pos-bar .pb--dark{background:#001f3e;border-color:#001f3e;color:#fff}
.pos-bar .pb--dark:hover{background:#00162c;border-color:#00162c}

.pos-bar .pb--green{background:#1d9e75;border-color:#1d9e75;color:#fff}
.pos-bar .pb--green:hover{background:#0f6e56;border-color:#0f6e56}

.pos-bar .pb--indigo{
    background:#4f46e5;border-color:#4f46e5;color:#fff;
    border-radius:6px;
}
.pos-bar .pb--indigo:hover{background:#4338ca;border-color:#4338ca}

.pos-bar .pb--danger{background:transparent;border-color:#fca5a5;color:#dc2626}
.pos-bar .pb--danger:hover{background:#fef2f2;border-color:#f87171}

.pos-bar .pb--logout{background:transparent;border-color:#ff0000;color:#ffffff}
.pos-bar .pb--logout:hover{background:#fef2f2;border-color:#ffffff;color:#ffe7e7}

/* Icon accent colours */
.pos-bar .ic-draft{color:#009ce4}
.pos-bar .ic-quote{color:#e7a500}
.pos-bar .ic-suspend{color:#ef4b51}
.pos-bar .ic-card{color:#d61b60}

/* ── Section groups ── */
.pos-bar__left{display:flex;align-items:center;gap:8px;flex-shrink:0}
.pos-bar__middle{display:flex;align-items:center;gap:4px;flex-wrap:nowrap;flex-shrink:0}
.pos-bar__right{display:flex;align-items:center;gap:6px;flex-shrink:0}

/* ── Mobile overrides ── */
@media(max-width:1023.98px){
    .pos-bar{flex-direction:column;align-items:stretch;padding:0;gap:0;border-radius:12px 12px 0 0}
    .pos-bar__mobile-total{
        display:flex;align-items:center;justify-content:space-between;
        padding:12px 16px;border-bottom:1px solid #e5e7eb;
    }
    .pos-bar__mobile-total .pos-bar__total-amount{font-size:20px}
    .pos-bar__mobile-actions{
        display:flex;gap:6px;padding:8px;
        border-bottom:1px solid #e5e7eb;flex-wrap:nowrap;
    }
    .pos-bar__mobile-actions .pb{flex:1;min-width:0}
    .pos-bar__mobile-utilities{
        display:flex;gap:0;padding:4px 8px;justify-content:space-around;
    }
    .pos-bar__desktop{display:none!important}
}
@media(min-width:1024px){
    .pos-bar__mobile-total,
    .pos-bar__mobile-actions,
    .pos-bar__mobile-utilities{display:none!important}
}

</style>

<div class="row no-print">
<div class="pos-bar">

    {{-- ================================================================
         MOBILE LAYOUT
         ================================================================ --}}

    {{-- Mobile: total --}}
    <div class="pos-bar__mobile-total">
        <span class="pos-bar__total-label">@lang('sale.total_payable')</span>
        <input type="hidden" name="final_total" id="final_total_input" value="0.00">
        <span id="total_payable" class="pos-bar__total-amount number">0.00</span>
    </div>

    {{-- Mobile: primary checkout buttons --}}
    <div class="pos-bar__mobile-actions">
        @if (!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                id="pos-finalize"
                title="@lang('lang_v1.tooltip_checkout_multi_pay')"
                class="pb pb--dark @if($pos_settings['disable_pay_checkout'] != 0) hide @endif">
                <i class="fas fa-money-check-alt"></i>
                @lang('lang_v1.checkout_multi_pay')
            </button>
        @endif

        @if (!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                data-pay_method="cash"
                title="@lang('tooltip.express_checkout')"
                class="pb pb--green pos-express-finalize @if($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)) hide @endif">
                <i class="fas fa-money-bill-alt"></i>
                @lang('lang_v1.express_checkout_cash')
            </button>
        @endif

        @if (empty($edit))
            <button type="button" class="pb pb--danger" id="pos-cancel">
                <i class="fas fa-times"></i> @lang('sale.cancel')
            </button>
        @else
            <button type="button" class="pb pb--danger hide" id="pos-delete"
                @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-trash-alt"></i> @lang('messages.delete')
            </button>
        @endif
    </div>

    {{-- Mobile: utility ghost buttons --}}
    <div class="pos-bar__mobile-utilities">
        @if (!Gate::check('disable_draft') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button" class="pb pb--ghost @if($pos_settings['disable_draft'] != 0) hide @endif"
                id="pos-draft" @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-edit ic-draft"></i>@lang('sale.draft')
            </button>
        @endif

        @if (!Gate::check('disable_quotation') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button" class="pb pb--ghost" id="pos-quotation"
                @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-file-alt ic-quote"></i>@lang('lang_v1.quotation')
            </button>
        @endif

        @if (!Gate::check('disable_suspend_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            @if(empty($pos_settings['disable_suspend']))
                <button type="button"
                    class="pb pb--ghost pos-express-finalize"
                    data-pay_method="suspend"
                    title="@lang('lang_v1.tooltip_suspend')"
                    @if(!empty($only_payment)) disabled @endif>
                    <i class="fas fa-pause ic-suspend"></i>@lang('lang_v1.suspend')
                </button>
            @endif
        @endif

        @if (!Gate::check('disable_card') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                class="pb pb--ghost pos-express-finalize @if(!array_key_exists('card', $payment_types)) hide @endif"
                data-pay_method="card"
                title="@lang('lang_v1.tooltip_express_checkout_card')">
                <i class="fas fa-credit-card ic-card"></i>@lang('lang_v1.express_checkout_card')
            </button>
        @endif
    </div>

    {{-- ================================================================
         DESKTOP LAYOUT
         ================================================================ --}}

    {{-- Left: cancel / delete + total --}}
    <div class="pos-bar__left pos-bar__desktop">

        @if(empty($edit))
            <button type="button" class="pb pb--danger" id="pos-cancel">
                <i class="fas fa-times"></i> @lang('sale.cancel')
            </button>
        @else
            <button type="button" class="pb pb--danger hide" id="pos-delete"
                @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-trash-alt"></i> @lang('messages.delete')
            </button>
        @endif

        <div class="pos-bar__divider"></div>

        <div class="pos-bar__total">
            <span class="pos-bar__total-label">@lang('sale.total') @lang('lang_v1.payable')</span>
            <input type="hidden" name="final_total" id="final_total_input" value="0.00">
            <span id="total_payable" class="pos-bar__total-amount number">0.00</span>
        </div>
    </div>

    {{-- Middle: utility actions + checkout buttons --}}
    <div class="pos-bar__middle pos-bar__desktop">

        @if (!Gate::check('disable_draft') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                class="pb pb--ghost @if($pos_settings['disable_draft'] != 0) hide @endif"
                id="pos-draft"
                @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-edit ic-draft"></i>@lang('sale.draft')
            </button>
        @endif

        @if (!Gate::check('disable_quotation') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button" class="pb pb--ghost" id="pos-quotation"
                @if(!empty($only_payment)) disabled @endif>
                <i class="fas fa-file-alt ic-quote"></i>@lang('lang_v1.quotation')
            </button>
        @endif

        @if (!Gate::check('disable_suspend_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            @if(empty($pos_settings['disable_suspend']))
                <button type="button"
                    class="pb pb--ghost pos-express-finalize"
                    data-pay_method="suspend"
                    title="@lang('lang_v1.tooltip_suspend')"
                    @if(!empty($only_payment)) disabled @endif>
                    <i class="fas fa-pause ic-suspend"></i>@lang('lang_v1.suspend')
                </button>
            @endif
        @endif

        @if (!Gate::check('disable_card') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                class="pb pb--ghost pos-express-finalize @if(!array_key_exists('card', $payment_types)) hide @endif"
                data-pay_method="card"
                title="@lang('lang_v1.tooltip_express_checkout_card')">
                <i class="fas fa-credit-card ic-card"></i>@lang('lang_v1.express_checkout_card')
            </button>
        @endif

        <div class="pos-bar__divider"></div>

        @if (!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                id="pos-finalize"
                title="@lang('lang_v1.tooltip_checkout_multi_pay')"
                class="pb pb--dark @if($pos_settings['disable_pay_checkout'] != 0) hide @endif">
                <i class="fas fa-money-check-alt"></i>
                @lang('lang_v1.checkout_multi_pay')
            </button>
        @endif

        @if (!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            <button type="button"
                data-pay_method="cash"
                title="@lang('tooltip.express_checkout')"
                class="pb pb--green pos-express-finalize @if($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)) hide @endif">
                <i class="fas fa-money-bill-alt"></i>
                @lang('lang_v1.express_checkout_cash')
            </button>
        @endif

        @if (!Gate::check('disable_credit_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
            @if(empty($pos_settings['disable_credit_sale_button']))
                <input type="hidden" name="is_credit_sale" value="0" id="is_credit_sale">
                <button type="button"
                    data-pay_method="credit_sale"
                    title="@lang('lang_v1.tooltip_credit_sale')"
                    @if(!empty($only_payment)) disabled @endif
                    class="pb pb--indigo pos-express-finalize">
                    <i class="fas fa-shopping-cart"></i>
                    @lang('lang_v1.place_order')
                </button>
            @endif
        @endif
    </div>

    {{-- Right: recent transactions + logout --}}
    <div class="pos-bar__right pos-bar__desktop">
        @if(!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0)
            <button type="button"
                class="pb"
                data-toggle="modal"
                data-target="#recent_transactions_modal"
                id="recent-transactions">
                <i class="fas fa-clock"></i>
                @lang('lang_v1.recent_transactions')
            </button>
        @endif

        <a href="{{ route('logout') }}"
            class="pb pb--logout bg-danger">
            <i class="fas fa-sign-out-alt"></i> Exit
        </a>

        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>

</div>{{-- /.pos-bar --}}
</div>{{-- /.row --}}

{{-- ── Modals (unchanged) ────────────────────────────────────────────── --}}
@if(isset($transaction))
    @include('sale_pos.partials.edit_discount_modal', [
        'sales_discount'   => $transaction->discount_amount,
        'discount_type'    => $transaction->discount_type,
        'rp_redeemed'      => $transaction->rp_redeemed,
        'rp_redeemed_amount' => $transaction->rp_redeemed_amount,
        'max_available'    => !empty($redeem_details['points']) ? $redeem_details['points'] : 0,
    ])
@else
    @include('sale_pos.partials.edit_discount_modal', [
        'sales_discount'   => $business_details->default_sales_discount,
        'discount_type'    => 'percentage',
        'rp_redeemed'      => 0,
        'rp_redeemed_amount' => 0,
        'max_available'    => 0,
    ])
@endif

@if(isset($transaction))
    @include('sale_pos.partials.edit_order_tax_modal', ['selected_tax' => $transaction->tax_id])
@else
    @include('sale_pos.partials.edit_order_tax_modal', [
        'selected_tax' => $business_details->default_sales_tax,
    ])
@endif

@include('sale_pos.partials.edit_shipping_modal')