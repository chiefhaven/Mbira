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
                <a class="navbar-brand" href="{{action('\Modules\Accounting\Http\Controllers\AccountingController@dashboard')}}"><i class="fas fa fa-broadcast-tower"></i> {{__('accounting::lang.accounting')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @if(auth()->user()->can('accounting.manage_accounts'))
                        <li @if(request()->segment(2) == 'chart-of-accounts') class="active" @endif><a href="{{action('\Modules\Accounting\Http\Controllers\CoaController@index')}}">@lang('accounting::lang.chart_of_accounts')</a></li>
                    @endif
                    
                    @if(auth()->user()->can('accounting.view_journal'))
                        <li @if(request()->segment(2) == 'journal-entry') class="active" @endif><a href="{{action('\Modules\Accounting\Http\Controllers\JournalEntryController@index')}}">@lang('accounting::lang.journal_entry')</a></li>
                    @endif

                    @if(auth()->user()->can('accounting.view_transfer'))
                        <li @if(request()->segment(2) == 'transfer') class="active" @endif>
                            <a href="{{action('\Modules\Accounting\Http\Controllers\TransferController@index')}}">
                                @lang('accounting::lang.transfer')
                            </a>
                        </li>
                    @endif

                    <li @if(request()->segment(2) == 'transactions') class="active" @endif><a href="{{action('\Modules\Accounting\Http\Controllers\TransactionController@index')}}">@lang('accounting::lang.transactions')</a></li>

                    @if(auth()->user()->can('accounting.manage_budget'))
                        <li @if(request()->segment(2) == 'budget') class="active" @endif>
                            <a href="{{action('\Modules\Accounting\Http\Controllers\BudgetController@index')}}">
                                @lang('accounting::lang.budget')
                            </a>
                        </li>
                    @endif
                    @if(auth()->user()->can('accounting.view_reports'))
                    <li @if(request()->segment(2) == 'reports') class="active" @endif><a href="{{action('\Modules\Accounting\Http\Controllers\ReportController@index')}}">
                        @lang('accounting::lang.reports')
                    </a></li>
                    @endif

                    <li @if(request()->segment(2) == 'settings') class="active" @endif><a href="{{action('\Modules\Accounting\Http\Controllers\SettingsController@index')}}">@lang('messages.settings')</a></li>
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>