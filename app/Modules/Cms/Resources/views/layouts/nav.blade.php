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
                <a class="navbar-brand" href="{{action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'page'])}}"><i class="fas fa-window-restore"></i> {{__('cms::lang.cms')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <li @if(request()->segment(2) == 'site-details') class="active" @endif>
                        <a href="{{action([\Modules\Cms\Http\Controllers\SettingsController::class, 'index'])}}">
                            @lang('cms::lang.site_details')
                        </a>
                    </li>
                </ul>
                <ul class="nav navbar-nav">
                    <li @if(request()->type == 'page') class="active" @endif>
                        <a href="{{action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'page'])}}">
                            @lang('cms::lang.page')
                        </a>
                    </li>
                </ul>
                <ul class="nav navbar-nav">
                    <li @if(request()->type == 'blog') class="active" @endif>
                        <a href="{{action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'blog'])}}">
                            @lang('cms::lang.blog')
                        </a>
                    </li>
                </ul>
                <ul class="nav navbar-nav">
                    <li @if(request()->type == 'testimonial') class="active" @endif>
                        <a href="{{action([\Modules\Cms\Http\Controllers\CmsPageController::class, 'index'], ['type' => 'testimonial'])}}">
                            @lang('cms::lang.testimonial')
                        </a>
                    </li>
                </ul>
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>