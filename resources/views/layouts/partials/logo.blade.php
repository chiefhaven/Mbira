<div class="row text-center">
	@if(config('branding.logo') || file_exists(public_path('uploads/logo.png')))
		<div class="col-xs-12">
			<img src="{{ config('branding.logo') ?: asset('uploads/logo.png') }}" class="img-rounded" alt="{{ config('app.name') }}" width="150" style="margin-bottom: 30px;">
		</div>
	@else
    	<h1 class="text-center page-header">{{ config('app.name', 'ultimatePOS') }}</h1>
    @endif
</div>