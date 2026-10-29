@extends('layouts.app')
@section('title', __('lang_v1.clear_all_bills'))

@section('content')
<section class="content-header no-print">
    <h1>@lang('lang_v1.clear_all_bills')</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('lang_v1.confirm_clear_all_bills')</h3>
                </div>
                <div class="box-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        @lang('lang_v1.you_are_about_to_clear_all_unpaid_bills')
                    </div>
                    
                    <table class="table table-striped">
                        <tr>
                            <th>@lang('lang_v1.total_unpaid_invoices')</th>
                            <td>{{ $total_count }}</td>
                        </tr>
                        <tr>
                            <th>@lang('lang_v1.total_due_amount')</th>
                            <td>{{ $total_due }}</td>
                        </tr>
                    </table>
                    
                    <form action="{{ route('sells.clear-all-bill') }}" method="POST">
                        @csrf
                        <input type="hidden" name="confirm" value="yes">
                        <div class="form-group">
                            <label for="note">@lang('lang_v1.note') (Optional)</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="@lang('lang_v1.add_note_for_bulk_payment')"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-danger btn-block">
                                    <i class="fa fa-check-circle"></i> @lang('lang_v1.confirm_clear_all')
                                </button>
                            </div>
                            <div class="col-md-6">
                                <a href="{{ route('sells.unpaid-invoices') }}" class="btn btn-default btn-block">
                                    <i class="fa fa-times"></i> @lang('lang_v1.cancel')
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection