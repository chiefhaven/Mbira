<div class="row">
	<div class="col-md-12">
		<button type="button" class="btn btn-sm btn-primary schedule_log_add pull-right m-5" data-href="{{action([\Modules\Crm\Http\Controllers\ScheduleLogController::class, 'create'], ['schedule_id' => $schedule->id])}}">
		    <i class="fas fa-plus"></i>
		    @lang('messages.add')
		</button>
	</div>
</div>
<div class="table-responsive followup_timeline">

</div>