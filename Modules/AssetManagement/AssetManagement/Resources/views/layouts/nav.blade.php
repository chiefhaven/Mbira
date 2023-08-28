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
                <a class="navbar-brand" href="{{action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard'])}}">
                		<i class="fas fa fa-boxes"></i>
                	{{__('assetmanagement::lang.asset_management')}}
                </a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @can('asset.view')
                    <li @if(request()->segment(2) == 'assets') class="active" @endif>
                        <a href="{{action([\Modules\AssetManagement\Http\Controllers\AssetController::class, 'index'])}}">
                            @lang('assetmanagement::lang.assets')
                        </a>
                    </li>
                    <li @if(request()->segment(2) == 'allocation') class="active" @endif>
                        <a href="{{action([\Modules\AssetManagement\Http\Controllers\AssetAllocationController::class, 'index'])}}">
                            @lang('assetmanagement::lang.asset_allocated')
                        </a>
                    </li>
                    <li @if(request()->segment(2) == 'revocation') class="active" @endif>
                        <a href="{{action([\Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class, 'index'])}}">
                            @lang('assetmanagement::lang.revoked_asset')
                        </a>
                    </li>
                    @endcan
                    @if(auth()->user()->can('asset.view_all_maintenance') || auth()->user()->can('asset.view_own_maintenance'))
                    <li @if(request()->segment(2) == 'maintenance') class="active" @endif>
                        <a href="{{action([\Modules\AssetManagement\Http\Controllers\AssetMaitenanceController::class, 'index'])}}">
                            @lang('assetmanagement::lang.asset_maintenance')
                        </a>
                    </li>
                    @endif
                    @can('only_admin')
                    <li @if(request()->get('type') == 'asset') class="active" @endif>
                    	<a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=asset'}}">
                    		@lang('assetmanagement::lang.asset_categories')
                    	</a>
                   	</li>
                    <li @if(request()->segment(2) == 'settings') class="active" @endif>
                        <a href="{{action([\Modules\AssetManagement\Http\Controllers\AssetSettingsController::class, 'index'])}}">
                            @lang('role.settings')
                        </a>
                    </li>
                    @endcan
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>