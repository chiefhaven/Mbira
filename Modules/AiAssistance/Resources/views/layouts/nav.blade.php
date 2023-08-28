<section class="no-print">
    <nav class="navbar navbar-default bg-white m-4">
        <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{action([\Modules\AiAssistance\Http\Controllers\AiAssistanceController::class, 'index'])}}"><i class="fas fa-robot"></i> {{__('aiassistance::lang.aiassistance')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @if(auth()->user()->can('aiassistance.access_aiassistance_module'))
                        <li @if(request()->segment(2) == 'aiassistance') class="active" @endif><a href="{{action([\Modules\AiAssistance\Http\Controllers\AiAssistanceController::class, 'index'])}}">@lang('aiassistance::lang.aiassistance')</a></li>
                    @endif
                    
                    @if(auth()->user()->can('aiassistance.access_aiassistance_module'))
                        <li @if(request()->segment(2) == 'aiassistance') class="active" @endif><a href="{{action([\Modules\AiAssistance\Http\Controllers\AiAssistanceController::class, 'history'])}}">@lang('aiassistance::lang.history')</a></li>
                    @endif
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>