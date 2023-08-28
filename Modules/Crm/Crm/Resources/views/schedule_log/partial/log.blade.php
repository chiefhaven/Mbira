@if($modal_content)
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    Follow Up Log <span class="text-info">{{$schedule->title}}</span> <br/>
                    <span class="text-info">{{$schedule->customer->full_name_with_business}}</span>
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-2"></div>
                    <div class="col-md-10">
@endif

<div class="col-md-12" style="margin-bottom: 5px;">
    @include('crm::schedule.partial.schedule_info_invoices')
</div>

<!-- if call log enabled get the log with this user. -->
@if(config('constants.enable_crm_call_log'))
    <table class="table table-bordered">
        <tr>
            <th>@lang('restaurant.start_time')</th>
            <th>@lang('restaurant.end_time')</th>
            <th>@lang('crm::lang.call_duration')</th>
            <th>@lang('crm::lang.call_type')</th>
            <th>@lang('lang_v1.contact_no')</th>
            <th>@lang('crm::lang.call_log_created_by')</th>
        </tr>

        @foreach($call_logs as $call_log)
            <tr>
                <td>{{$call_log->start_time}}</td>
                <td>{{$call_log->end_time}}</td>
                <td>
                    @if(empty($call_log->duration))
                        {{Carbon\CarbonInterval::seconds($call_log->duration)->cascade()->forHumans()}}
                    @endif
                </td>
                <td>{{$call_log->call_type}}</td>
                <td>{{$call_log->mobile_number}} <br/> {{$call_log->mobile_name}}</td>
                <td>{{$call_log->created_user_name}}</td>
            </tr>
        @endforeach
        @if(count($call_logs) == 0)
            <tr>
                <td colspan="6" class="text-center">
                    @lang('lang_v1.no_data')
                </td>
            </tr>
        @endif
    </table>
@endif

<div class="col-md-12">
<ul class="timeline">
@if($schedule_logs->count() > 0)
    @foreach($schedule_logs as $schedule_log)
        <!-- timeline time label -->
        <li class="time-label">
            <span class="bg-red">
                {{@format_datetime($schedule_log->created_at)}}
            </span>
        </li>
        <!-- /.timeline-label -->

        <!-- timeline item -->
        <li>
            <!-- timeline icon -->
            <i class="
                @if($schedule_log->log_type == 'email')
                    fa fa-envelope
                @elseif($schedule_log->log_type == 'call')
                    fas fa fa-phone-alt
                @elseif($schedule_log->log_type == 'sms')
                    fas fa fa-sms
                @elseif($schedule_log->log_type == 'meeting')
                    fas fa fa-handshake
                @endif
                @if($schedule_log->created_at == $schedule_log->updated_at)
                    bg-green
                @else
                    bg-blue
                @endif
                " data-toggle="tooltip" title="@lang('crm::lang.'.$schedule_log->log_type)">
            </i>
            <div class="timeline-item">
                <span class="time pa-0">
                    <span>
                        <i class="fas fa-pen"></i>
                        {{$schedule_log->createdBy->user_full_name}}
                    </span><br>
                    <i class="fas fa-clock"></i>
                    {{@format_datetime($schedule_log->start_datetime)}} ~ {{@format_datetime($schedule_log->end_datetime)}}
                </span>

                <h3 class="timeline-header">
                    <a class="cursor-pointer view_a_schedule_log" data-href="{{action([\Modules\Crm\Http\Controllers\ScheduleLogController::class, 'show'], ['id' => $schedule_log->id, 'schedule_id' => $schedule_log->schedule_id])}}">
                        {{$schedule_log->subject}}
                    </a>
                </h3>

                <div class="timeline-body">
                    {!!$schedule_log->description!!}
                </div>

                <div class="timeline-footer">
                    
                    <i class="fa fa-eye cursor-pointer m-5 text-info view_a_schedule_log" data-href="{{action([\Modules\Crm\Http\Controllers\ScheduleLogController::class, 'show'], ['id' => $schedule_log->id, 'schedule_id' => $schedule_log->schedule_id])}}"></i>
                
                
                    <i class="fa fa-edit cursor-pointer m-5 text-primary edit_schedule_log" data-href="{{action([\Modules\Crm\Http\Controllers\ScheduleLogController::class, 'edit'], ['id' => $schedule_log->id, 'schedule_id' => $schedule_log->schedule_id])}}"></i>
            
              
                    <i class="fas fa-trash cursor-pointer m-5 text-danger delete_schedule_log" data-href="{{action([\Modules\Crm\Http\Controllers\ScheduleLogController::class, 'destroy'], ['id' => $schedule_log->id, 'schedule_id' => $schedule_log->schedule_id])}}"></i>
                    
                </div>
            </div>
        </li>
        <!-- END timeline item -->
    @endforeach
    {{--
        @if($schedule_logs->nextPageUrl())
        <li class="timeline-lode-more-btn">
            <a data-href="{{$schedule_logs->nextPageUrl()}}" class="btn btn-block btn-sm btn-info load_more_log">
                @lang('project::lang.load_more')
            </a>
        </li>
    @endif
    --}}
@else
    <li>
        <div class="timeline-item">
            <div class="timeline-body">
                <span class="text-info">@lang('crm::lang.no_log_found')</span>
            </div>
        </div>
    </li>
@endif
</ul>
</div>

@if($modal_content)
            </div>
        </div>
    </div>
</div></div>
@endif