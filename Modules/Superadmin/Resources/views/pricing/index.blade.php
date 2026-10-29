@extends('layouts.auth')
@section('title', __('superadmin::lang.pricing'))
@php $no_header = true; @endphp
@section('body_class', 'mbira-public brand-pricing')
@section('public_css')
    <link rel="stylesheet" href="{{ asset('brand-public.css?v=' . $asset_v) }}">
@endsection

@section('content')

@include('layouts.partials.public-navigation')
<main class="container brand-pricing-content">
    @include('superadmin::layouts.partials.currency')
    <header class="brand-page-heading">
        <p class="brand-eyebrow">Room to grow</p>
        <h1>Find the right plan for your business</h1>
        <p>Explore {{ config('app.name') }} packages and choose the tools that fit the way you work.</p>
    </header>
    <div class="row">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title text-center">@lang('superadmin::lang.packages')</h3>
            </div>

            <div class="box-body brand-package-grid">
                @include('superadmin::subscription.partials.packages', ['action_type' => 'register', 'compact' => true])
            </div>
        </div>
    </div>
    <div class="brand-pricing-help">
        <h2>Let's find your fit.</h2>
        <p>Have questions about the right package for your business?</p>
        @if(Route::has('cms.contact.us'))
            <a href="{{ route('cms.contact.us') }}">Talk to our team <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>
</main>
<div class="modal fade brand-features-modal" id="plan-features-modal" tabindex="-1" role="dialog"
    aria-labelledby="plan-features-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="@lang('messages.close')">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h2 class="modal-title" id="plan-features-title"></h2>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</div>
@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function(){
        $('#plan-features-modal').off('show.bs.modal.planFeatures').on('show.bs.modal.planFeatures', function(event) {
            var trigger = event.relatedTarget;
            var features = document.getElementById(trigger.getAttribute('data-features-id'));
            $(this).find('.modal-title').text(trigger.getAttribute('data-plan-name') + ' - Plan features');
            $(this).find('.modal-body').html(features.innerHTML);
        });
        $('#change_lang').change( function(){
            window.location = "{{ route('pricing') }}?lang=" + $(this).val();
        });
    })
</script>
@endsection
