@extends('accounting::layouts.app')
@section('title')
    {{ $page_title }}
@endsection

@section('css')
    @include('accounting::report.partials.report_css')
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ $page_title }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">
        <div class="row">
            @component('accounting::components.box')
                @slot('header')
                    <div class="box-tools">
                        @component('accounting::components.download_action_button', [
                            'url' => Request::url(),
                            'filters' => http_build_query($filters),
                            'options' => ['pdf'],
                            ])
                        @endcomponent
                    </div>

                    <div class="col-md-4">
                        <form action="{{ Request::url() }}" id="financial_year_form">
                            <input type="hidden" name="view" value="{{ Request::get('view') }}">
                            <div class="form-group">
                                <label for="year">{{ trans_choice('accounting::general.financial_year', 1) }}</label>
                                <input type="text" class="form-control year-datepicker" name="year" id="financial_year" v-model="financial_year">
                            </div>
                        </form>
                    </div>
                @endslot

                @slot('body')
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs nav-justified">
                            <li class="@if (Request::get('view') == 'monthly') active @endif">
                                <a href="{{ request()->view != 'monthly' ? $url->monthly : '#' }}" aria-expanded="true">
                                    <i class="fas fa-calendar" aria-hidden="true"></i>
                                    {{ trans_choice('accounting::lang.monthly', 1) }}
                                </a>
                            </li>
                            <li class="@if (Request::get('view') == 'quarterly') active @endif">
                                <a href="{{ request()->view != 'quarterly' ? $url->quarterly : '#' }}" aria-expanded="true">
                                    <i class="fas fa-calendar" aria-hidden="true"></i>
                                    {{ trans_choice('accounting::lang.quarterly', 1) }}
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active">

                                {{-- Conditional views --}}

                                @switch(Request::get('view'))
                                    @case('monthly')
                                        @include(
                                            'accounting::report.budget.partials.monthly_view'
                                        )
                                    @break

                                    @case('quarterly')
                                        @include(
                                            'accounting::report.budget.partials.quarterly_view'
                                        )
                                    @break

                                    @default
                                        <div class="alert alert-info">
                                            {{ trans('accounting::lang.an_error_occurred') }}
                                        </div>
                                @endswitch

                            </div>
                        </div>
                    </div>
                @endslot
            @endcomponent
        </div>
    </section>
@stop

@section('javascript')
    @include('accounting::report.partials.report_js')
    <script>
        $(function() {
            $('#financial_year').on('change', function() {
                document.getElementById('financial_year_form').submit();
            })
        });

        new Vue({
            el: '#vue-app',
            data() {
                return {
                    financial_year_start: "{{ $financial_year_start }}",
                    financial_year: "{{ $financial_year }}",
                    business_id: "{{ session('business.id') }}",
                    view: "{{ Request::get('view') }}",
                }
            },
        });
    </script>
@endsection
