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
                <a class="navbar-brand" href="{{action([\Modules\Hms\Http\Controllers\HmsController::class, 'index'])}}"><i class="fas fa-hotel"></i>@lang('hms::lang.hms')</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                @can('hms.manage_rooms')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'rooms') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\RoomController::class, 'index'])}}">@lang('hms::lang.rooms')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_price')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'room' && request()->segment(3) == 'pricing') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\RoomController::class, 'pricing'])}}">@lang('hms::lang.prices')</a></li>
                    </ul>
                @endcan
                <ul class="nav navbar-nav">
                    <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'bookings') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])}}">@lang('hms::lang.bookings')</a></li>
                </ul>
                <ul class="nav navbar-nav">
                    <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'calendar') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsBookingController::class, 'calendar'])}}">@lang('hms::lang.calendar')</a></li>
                </ul>
                @can('hms.manage_extra')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'extras') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\ExtraController::class, 'index'])}}">@lang('hms::lang.extras')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_unavailable')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'unavailables') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])}}">@lang('hms::lang.unavailable')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_coupon')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'coupons') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])}}">@lang('hms::lang.coupons')</a></li>
                    </ul>
                @endcan
                <ul class="nav navbar-nav">
                    <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'reports') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsReportController::class, 'index'])}}">@lang('hms::lang.reports')</a></li>
                </ul>
                @can('hms.manage_amenities')
                    <ul class="nav navbar-nav">
                            <li @if(request()->get('type') == 'amenities') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=amenities'}}">@lang('hms::lang.amenities')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_settings')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'settings') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsSettingController::class, 'index'])}}">@lang('messages.settings')</a></li>
                    </ul>
                @endcan
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>