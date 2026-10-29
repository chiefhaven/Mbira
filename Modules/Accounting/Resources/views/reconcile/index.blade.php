@extends('accounting::layouts.app')
@section('title')
    {{ trans_choice('accounting::lang.reconcile', 1) }}
@endsection

@section('content')

    @include('accounting::layouts.nav')
    <!-- Content Header (Page header) -->
    @component('accounting::components.section_header')
        @slot('title')
            {{ trans_choice('accounting::lang.reconcile', 1) }}
        @endslot
        @slot('subtitle')
            {{ trans('accounting::lang.reconcile_subtitle') }}
        @endslot
    @endcomponent

    <!-- Main content -->
    <section class="content no-print" id="vue-app">

        <div class="row">

            @component('accounting::components.box')
                @slot('body')
                    <section class="content">
                        @include('accounting::reconcile.partials.reconcile_form')
                        @include('accounting::reconcile.partials.undo_reconcile_form')
                    </section>
                @endslot
            @endcomponent

        </div>
    </section>
@stop

@section('javascript')
    <script>
        const app = new Vue({
            el: '#vue-app',
            data: {
                ending_balance: "{{ old('ending_balance') }}",
                ending_date: "{{ old('ending_date') }}",
                chart_of_accounts: {!! json_encode($chart_of_accounts) !!},
                chart_of_account_id: parseInt("{{ old('chart_of_account_id') }}"),
            },

            computed: {
                chart_of_account() {
                    return !isNaN(this.chart_of_account_id) ?
                        this.chart_of_accounts.find(account => account.id == this.chart_of_account_id) : {};
                }
            },

            methods: {
                storeUndoReconciliation() {
                    const form = document.getElementById('undo_last_reconcile_form');

                    swal({
                            title: `Undo last reconcilation for ${this.chart_of_account.name}?`,
                            text: 'This action may not be reversible',
                            icon: "warning",
                            buttons: true,
                            dangerMode: true,
                        })
                        .then((clickedOk) => {
                            if (clickedOk) {
                                form.submit();
                            }
                        });
                }
            }
        })
    </script>
@endsection
