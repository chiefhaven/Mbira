<section class="no-print">
    <style type="text/css">
        #contacts_login_dropdown::after {
            display: inline-block;
            width: 0;
            height: 0;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }
    </style>
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
                <a class="navbar-brand" href="{{action([\Modules\Crm\Http\Controllers\CrmDashboardController::class, 'index'])}}"><i class="fas fa fa-broadcast-tower"></i> {{__('crm::lang.crm')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @if(auth()->user()->can('crm.access_all_leads') || auth()->user()->can('crm.access_own_leads'))
                    <li @if(request()->segment(2) == 'leads') class="active" @endif><a href="{{action([\Modules\Crm\Http\Controllers\LeadController::class, 'index']). '?lead_view=list_view'}}">@lang('crm::lang.leads')</a></li>
                    @endif
                    @if(auth()->user()->can('crm.access_all_schedule') || auth()->user()->can('crm.access_own_schedule'))
                    <li @if(request()->segment(2) == 'follow-ups') class="active" @endif><a href="{{action([\Modules\Crm\Http\Controllers\ScheduleController::class, 'index'])}}">@lang('crm::lang.follow_ups')</a></li>
                    @endif
                    @if(auth()->user()->can('crm.access_all_campaigns') || auth()->user()->can('crm.access_own_campaigns'))
                    <li @if(request()->segment(2) == 'campaigns') class="active" @endif><a href="{{action([\Modules\Crm\Http\Controllers\CampaignController::class, 'index'])}}">@lang('crm::lang.campaigns')</a></li>
                    @endif                    

                    @can('crm.access_contact_login')
                        <li class="nav-item @if(request()->segment(2) == 'all-contacts-login' || request()->segment(2) == 'commissions') active @endif">
                            <a class="nav-link dropdown-toggle" href="#" id="contacts_login_dropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                @lang('crm::lang.contacts_login')
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                              <a class="dropdown-item" href="{{action([\Modules\Crm\Http\Controllers\ContactLoginController::class, 'allContactsLoginList'])}}"> @lang('crm::lang.contacts_login')</a>
                              <a class="dropdown-item" href="{{action([\Modules\Crm\Http\Controllers\ContactLoginController::class, 'commissions'])}}">@lang('crm::lang.commissions')</a>
                            </div>
                        </li>
                    @endcan
                    @if((auth()->user()->can('crm.view_all_call_log') || auth()->user()->can('crm.view_own_call_log')) && config('constants.enable_crm_call_log'))
                    <li @if(request()->segment(2) == 'call-log') class="active" @endif><a href="{{action([\Modules\Crm\Http\Controllers\CallLogController::class, 'index'])}}">@lang('crm::lang.call_log')</a></li>
                    @endif

                    @can('crm.view_reports')
                    <li @if(request()->segment(2) == 'reports') class="active" @endif><a href="{{action([\Modules\Crm\Http\Controllers\ReportController::class, 'index'])}}">@lang('report.reports')</a></li>
                    @endcan
                    <li @if(request()->segment(2) == 'proposal-template') class="active" @endif>
                        <a href="{{action([\Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'index'])}}">
                            @lang('crm::lang.proposal_template')
                        </a>
                    </li>
                    <li @if(request()->segment(2) == 'proposals') class="active" @endif>
                        <a href="{{action([\Modules\Crm\Http\Controllers\ProposalController::class, 'index'])}}">
                            @lang('crm::lang.proposals')
                        </a>
                    </li>
                    @if(auth()->user()->can('crm.access_b2b_marketplace') && config('constants.enable_b2b_marketplace'))
                    <li @if(request()->segment(2) == 'b2b-marketplace') class="active" @endif>
                        <a href="{{action([\Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'index'])}}">
                            @lang('crm::lang.b2b_marketplace')
                        </a>
                    </li>
                    @endif

                    @can('crm.access_sources')
                        <li @if(request()->get('type') == 'source') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=source'}}">@lang('crm::lang.sources')</a></li>
                    @endcan

                    @can('crm.access_life_stage')
                        <li @if(request()->get('type') == 'life_stage') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=life_stage'}}">@lang('crm::lang.life_stage')</a></li>

                        <li @if(request()->get('type') == 'followup_category') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=followup_category'}}">@lang('crm::lang.followup_category')</a></li>
                    @endcan
                    <li @if(request()->segment(2) == 'settings') class="active" @endif>
                        <a href="{{action([\Modules\Crm\Http\Controllers\CrmSettingsController::class, 'index'])}}">
                            @lang('business.settings')
                        </a>
                    </li>
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>