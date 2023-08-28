@extends('layouts.app')

@section('title', __('crm::lang.b2b_marketplace'))

@section('content')

@include('crm::layouts.nav')

<section class="content-header no-print">
   <h1>@lang('crm::lang.b2b_marketplace')</h1>
</section>

<section class="content no-print">
	<div class="row">
		<div class="col-md-12">
			@component('components.widget', ['class' => 'box-solid', 'title' => 'Exporters India'])
				<div class="row">
					<div class="col-md-8">
						{!! Form::open(['url' => action([\Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'save']), 'method' => 'post' ]) !!}
							<input type="hidden" name="marketplace" value="exportersindia">
							<div class="form-group row">
							    <label for="site_key" class="col-sm-2 col-form-label">@lang('crm::lang.key')*</label>
							    <div class="col-sm-10">
							      <input type="text"  class="form-control" id="site_key" 
							      value="{{$marketplace->site_key ?? ''}}" name="site_key" required>
							    </div>
							</div>
							<div class="form-group row">
							    <label for="site_id" class="col-sm-2 col-form-label">@lang('business.email')*</label>
							    <div class="col-sm-10">
							      <input type="email"  class="form-control" name="site_id" id="site_id" 
							      value="{{$marketplace->site_id ?? ''}}" required>
							    </div>
							</div>                            
                     <div class="form-group row">
							   <label for="assigned_users" class="col-sm-2 col-form-label">@lang('crm::lang.followup_assigned_to')*</label>
							   <div class="col-sm-10">
							      {!! Form::select('assigned_users[]', $users, $marketplace->assigned_users ?? [], ['class' => 'form-control select2', 'multiple', 'required', 'style' => 'width: 100%;', 'id' => 'assigned_users']); !!}
							   </div>
							</div>

							<div class="form-group row">
							   <label for="source_id" class="col-sm-2 col-form-label">@lang('crm::lang.source')*</label>
							   <div class="col-sm-10">
							      {!! Form::select('source_id', $sources, $marketplace->source_id ?? '', ['class' => 'form-control select2', 'required', 'style' => 'width: 100%;', 'id' => 'source_id']); !!}
							   </div>
							</div>

							<div class="form-group row">
								<button type="submit" class="btn btn-primary pull-right">@lang('messages.submit')</button>
							</div>
						{!! Form::close() !!}
					</div>
				</div>
				<hr>
				<br>
				<div class="row">
					<div class="col-md-4 col-md-offset-4"><a href="{{action([\Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'importLeads'])}}" class="btn btn-success btn-block">@lang('crm::lang.import_leads')</a></div>
				</div>
			@endcomponent
		</div>
	</div>
</section>
@endsection
@section('javascript')
<script type="text/javascript">
	$(document).ready( function() {
		$('#assigned_users').select2();
	});
</script>
@endsection