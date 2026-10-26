@extends('layouts.app')

@section('title', __('crm::lang.crm'))

@section('content')

@include('crm::layouts.nav')

<section class="content no-print">
    <div class="row">
        <div class="col-md-4">
            @if(auth()->user()->can('crm.access_all_schedule') || auth()->user()->can('crm.access_own_schedule'))
                <div class="col-md-12">
                    <div class="info-box info-box-new-style">
                        <span class="info-box-icon bg-aqua"><i class="fas fa-calendar-check"></i></span>

                        <div class="info-box-content">
                          <span class="info-box-text">{{ __('crm::lang.todays_followups') }}</span>
                          <span class="info-box-number">{{$todays_followups}}</span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                </div>
            @endif
            @if(auth()->user()->can('crm.access_all_leads') || auth()->user()->can('crm.access_own_leads'))
                <div class="col-md-12">
                    <div class="info-box info-box-new-style">
                        <span class="info-box-icon bg-aqua"><i class="fas fa-user-check"></i></span>

                        <div class="info-box-content">
                          <span class="info-box-text">{{ __('crm::lang.my_leads') }}</span>
                          <span class="info-box-number">{{$my_leads}}</span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                </div>
            @endif
            <div class="col-md-12">
                <div class="info-box info-box-new-style">
                    <span class="info-box-icon bg-aqua"><i class="fas fa-exchange-alt"></i></span>

                    <div class="info-box-content">
                      <span class="info-box-text">{{ __('crm::lang.my_leads_to_customer_conversion') }}</span>
                      <span class="info-box-number">{{$my_conversion}}</span>
                    </div>
                    <!-- /.info-box-content -->
                </div>
            </div>
        </div>
        @if(auth()->user()->can('crm.access_all_schedule') || auth()->user()->can('crm.access_own_schedule'))
            <div class="col-md-4">
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title">@lang('crm::lang.my_followups')</h3>
                    </div>
                    <div class="box-body p-10">
                        <table class="table no-margin">

                            @foreach($statuses as $key => $value)
                                <tr>
                                    <th>{{$value}}</th>
                                    <td>
                                        @if(isset($my_follow_ups_arr[$key]))
                                            {{$my_follow_ups_arr[$key]}}
                                        @else
                                            0
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            @if(isset($my_follow_ups_arr['__other']))
                                <tr>
                                    <th>@lang('lang_v1.others')</th>
                                    <td>
                                        {{$my_follow_ups_arr['__other']}}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if(config('constants.enable_crm_call_log'))
            <div class="col-md-4">
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title">@lang('crm::lang.my_call_logs')</h3>
                    </div>
                    <div class="box-body p-10">
                        <table class="table no-margin">
                            <tr>
                                <th>@lang('crm::lang.calls_today')</th>
                                <td>{{$my_call_logs->calls_today ?? 0}}</td>
                            </tr>
                            <tr>
                                <th>@lang('crm::lang.calls_yesterday')</th>
                                <td>{{$my_call_logs->calls_yesterday ?? 0}}</td>
                            </tr>
                            <tr>
                                <th>@lang('crm::lang.calls_this_month')</th>
                                <td>{{$my_call_logs->calls_this_month ?? 0}}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
    
    @if($is_admin)
        <hr>
        <div class="row row-custom">
            <div class="col-md-3 col-sm-6 col-xs-12 col-custom">
              <div class="info-box info-box-new-style">
                <span class="info-box-icon bg-aqua"><i class="fas fa-user-friends"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">{{ __('lang_v1.customers') }}</span>
                  <span class="info-box-number">{{$total_customers}}</span>
                </div>
                <!-- /.info-box-content -->
              </div>
              <!-- /.info-box -->
            </div>
            <!-- /.col -->
            <div class="col-md-3 col-sm-6 col-xs-12 col-custom">
              <div class="info-box info-box-new-style">
                <span class="info-box-icon bg-aqua"><i class="fas fa-user-check"></i></span>

                <div class="info-box-content">
                  <span class="info-box-text">{{ __('crm::lang.leads') }}</span>
                  <span class="info-box-number">{{$total_leads}}</span>
                </div>
                <!-- /.info-box-content -->
              </div>
              <!-- /.info-box -->
            </div>
            <!-- /.col -->
            <div class="col-md-3 col-sm-6 col-xs-12 col-custom">
              <div class="info-box info-box-new-style">
                <span class="info-box-icon bg-yellow">
                    <i class="fas fa fa-search"></i>
                </span>

                <div class="info-box-content">
                    <span class="info-box-text">{{ __('crm::lang.sources') }}</span>
                  <span class="info-box-number">{{$total_sources}}</span>
                </div>
                <!-- /.info-box-content -->
              </div>
              <!-- /.info-box -->
            </div>
            <!-- /.col -->

            <!-- fix for small devices only -->
            <!-- <div class="clearfix visible-sm-block"></div> -->
            <div class="col-md-3 col-sm-6 col-xs-12 col-custom">
              <div class="info-box info-box-new-style">
                <span class="info-box-icon bg-yellow">
                    <i class="fas fa-life-ring"></i>
                </span>

                <div class="info-box-content">
                  <span class="info-box-text">{{ __('crm::lang.life_stages') }}</span>
                  <span class="info-box-number invoice_due">{{$total_life_stage}}</span>
                </div>
                <!-- /.info-box-content -->
              </div>
              <!-- /.info-box -->
            </div>
            <!-- /.col -->
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="box box-solid">
                    <div class="box-body p-10">
                        <table class="table no-margin">
                            <thead>
                                <tr>
                                    <th>{{ __('crm::lang.sources') }}</th>
                                    <th>{{ __('sale.total') }}</th>
                                    <th>{{ __('crm::lang.conversion') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sources as $source)
                                    <tr>
                                        <td>{{$source->name}}</td>
                                        <td>
                                            @if(!empty($leads_count_by_source[$source->id]))
                                                {{$leads_count_by_source[$source->id]['count']}}
                                            @else
                                                0
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($customers_count_by_source[$source->id]) && !empty($contacts_count_by_source[$source->id]))
                                                @php
                                                    $conversion = ($customers_count_by_source[$source->id]['count']/$contacts_count_by_source[$source->id]['count']) * 100;
                                                @endphp
                                                {{$conversion . '%'}}
                                            @else 
                                                {{'0 %'}}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center">@lang('lang_v1.no_data')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="box box-solid">
                    <div class="box-body p-10">
                        <table class="table no-margin">
                            <thead>
                                <tr>
                                    <th>{{ __('crm::lang.life_stages') }}</th>
                                    <th>{{ __('sale.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($life_stages as $life_stage)
                                    <tr>
                                        <td>{{$life_stage->name}}</td>
                                        <td>@if(!empty($leads_by_life_stage[$life_stage->id])){{count($leads_by_life_stage[$life_stage->id])}} @else 0 @endif</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center">@lang('lang_v1.no_data')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <i class="fas fa fa-birthday-cake"></i>
                        <h3 class="box-title">@lang('crm::lang.birthdays')</h3>
                        <a data-href="{{action([\Modules\Crm\Http\Controllers\CampaignController::class, 'create'])}}" class="btn btn-success btn-xs" id="wish_birthday">
                            <i class="fas fa-paper-plane"></i>
                            @lang('crm::lang.send_wishes')
                        </a>
                    </div>
                    <div class="box-body p-10">
                        <table class="table no-margin table-striped">
                            <caption>@lang('home.today')</caption>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('user.name')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($todays_birthdays as $key => $birthday)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="contat_id" name="contat_id[]" value="{{$birthday['id']}}" id="contat_id_{{$birthday['id']}}">
                                        </td>
                                        <td>
                                            <label for="contat_id_{{$birthday['id']}}" class="cursor-pointer fw-100">
                                                {{$birthday['name']}}
                                            </label>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center">@lang('lang_v1.no_data')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if(!empty($upcoming_birthdays))
                            <hr class="m-2">
                        @endif
                        <table class="table no-margin table-striped">
                            <caption>
                                @lang('crm::lang.upcoming')
                            </caption>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('user.name')</th>
                                    <th>@lang('crm::lang.birthday_on')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($upcoming_birthdays as $key => $birthday)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="contat_id" name="contat_id[]" value="{{$birthday['id']}}" id="contat_id_{{$birthday['id']}}">
                                        </td>
                                        <td>
                                            <label for="contat_id_{{$birthday['id']}}" class="cursor-pointer fw-100">
                                                {{$birthday['name']}}
                                            </label>
                                        </td>
                                        <td>
                                            {{Carbon::createFromFormat('m-d', $birthday['dob'])->format('jS M')}}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center">@lang('lang_v1.no_data')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-12">
                @component('components.widget', ['class' => 'box-solid', 'title' => __('crm::lang.follow_ups_by_user')])
                <div class="row">
                     <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('follow_up_user_date_range', __('report.date_range') . ':') !!}
                            {!! Form::text('follow_up_user_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('followup_category_id', __('crm::lang.followup_category') .':*') !!}
                            {!! Form::select('followup_category_id', $followup_category, null, ['class' => 'form-control select2', 'style' => 'width: 100%;', 'placeholder' => __('messages.all')]); !!}
                        </div>
                    </div>
                    <br/>
                </div>

                    <table class="table table-bordered table-striped" id="follow_ups_by_user_table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>@lang('role.user')</th>
                                @foreach($statuses as $key => $value)
                                    <th>
                                        {{$value}}
                                    </th>
                                @endforeach
                                <th>
                                    @lang('lang_v1.none')
                                </th>
                                <th>
                                    @lang('crm::lang.total_follow_ups')
                                </th>
                            </tr>
                        </thead>
                    </table>
                @endcomponent
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-solid', 'title' => __('crm::lang.lead_to_customer_conversion')])
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="lead_to_customer_conversion" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th>@lang('crm::lang.converted_by')</th>
                                    <th>@lang('sale.total')</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                @endcomponent
            </div>

            @if(config('constants.enable_crm_call_log'))
                <div class="col-md-6">
                    <div class="box box-solid">
                        <div class="box-header with-border">
                            <h3 class="box-title">@lang('crm::lang.all_users_call_log')</h3>
                        </div>
                        <div class="box-body p-10">
                            <div class="table-responsive">
                                <table class="table" id="all_users_call_log" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>
                                                @lang('role.user')
                                            </th>
                                            <th>
                                                @lang('crm::lang.calls_today')
                                            </th>
                                            <th>
                                                @lang('crm::lang.calls_this_month')
                                            </th>
                                            <th>
                                                @lang('lang_v1.all')
                                            </th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
@endsection
@section('css')
<style type="text/css">
    .fw-100 {
        font-weight: 100;
    }
    
</style>
@stop
@section('javascript')
	<script src="{{ asset('modules/crm/js/crm.js?v=' . $asset_v) }}"></script>
    @include('crm::reports.report_javascripts')
    <script type="text/javascript">
        $(document).ready(function () {

            $(document).on('click', '#wish_birthday', function () {
                var url = $(this).data('href');
                var contact_ids = [];
                $("input.contat_id").each(function(){
                    if ($(this).is(":checked")) {
                        contact_ids.push($(this).val());
                    }
                });

                if (_.isEmpty(contact_ids)) {
                    alert("{{__('crm::lang.plz_select_user')}}");
                } else {
                    location.href = url+'?contact_ids='+contact_ids;
                }
            });

            @if(config('constants.enable_crm_call_log'))
                all_users_call_log = $("#all_users_call_log").DataTable({
                            processing: true,
                            serverSide: true,
                            scrollY: "75vh",
                            scrollX: true,
                            scrollCollapse: true,
                            fixedHeader: false,
                            'ajax': {
                                url: "{{action([\Modules\Crm\Http\Controllers\CallLogController::class, 'allUsersCallLog'])}}"
                            },
                            columns: [
                                { data: 'username', name: 'u.username' },
                                { data: 'calls_today', searchable: false },
                                { data: 'calls_yesterday', searchable: false },
                                { data: 'all_calls', searchable: false }
                            ],
                        });
            @endif
        });
    </script>
@endsection