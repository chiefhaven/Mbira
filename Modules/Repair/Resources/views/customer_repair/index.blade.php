@extends('layouts.auth2')
@section('title', __('repair::lang.repair_status'))
@section('body_class', 'mbira-public brand-register-page brand-repair-page')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . $asset_v) }}">
@endsection
@section('content')
    <main class="brand-registration">
        <header class="brand-registration-heading">
            <h1>@lang('repair::lang.repair_status')</h1>
        </header>
        <div class="brand-registration-card brand-repair-search">
            @php
                $search_options = [
                    'job_sheet_no' => __('repair::lang.job_sheet_no'),
                    'invoice_no' => __('sale.invoice_no'),
                ];
                $placeholder = __('repair::lang.job_sheet_or_invoice_no');
                if (config('repair.enable_repair_check_using_mobile_num')) {
                    $search_options['mobile_num'] = __('lang_v1.mobile_number');
                    $placeholder .= ' / '.__('lang_v1.mobile_number');
                }
            @endphp
            <form method="POST" action="{{ route('post-repair-status', [], false) }}" id="check_repair_status" class="brand-verification-form">
                @csrf
                <label for="repair_search_type">@lang('lang_v1.search')</label>
                {!! Form::select('search_type', $search_options, null, ['class' => 'form-control', 'id' => 'repair_search_type']); !!}
                <label for="repair_search_number">{{ $placeholder }}</label>
                <input type="text" name="search_number" id="repair_search_number" required placeholder="{{ $placeholder }}">
                <label for="repair_serial_no">@lang('repair::lang.serial_no')</label>
                <input type="text" name="serial_no" id="repair_serial_no" placeholder="@lang('repair::lang.serial_no')">
                <button type="submit">@lang('lang_v1.search')</button>
                <p id="repair-search-feedback" role="status" aria-live="polite"></p>
            </form>
        </div>
        <div class="repair_status_details" aria-live="polite"></div>
    </main>
@endsection
@section('public_javascript')
<script>
    $(function () {
        $('#check_repair_status').on('submit', function (event) {
            event.preventDefault();
            var form = $(this);
            var button = form.find('button[type="submit"]');
            var feedback = $('#repair-search-feedback');
            button.prop('disabled', true);
            feedback.text('');
            $('.repair_status_details').empty().attr('aria-busy', 'true');
            $.ajax({
                method: 'POST',
                url: form.attr('action'),
                dataType: 'json',
                data: form.serialize(),
                success: function (result) {
                    if (result.success) {
                        $('.repair_status_details').html(result.repair_html);
                    } else {
                        feedback.text(result.msg || @json(__('messages.something_went_wrong')));
                    }
                },
                error: function () {
                    feedback.text(@json(__('messages.something_went_wrong')));
                },
                complete: function () {
                    button.prop('disabled', false);
                    $('.repair_status_details').attr('aria-busy', 'false');
                }
            });
        });
    });
</script>
@endsection
