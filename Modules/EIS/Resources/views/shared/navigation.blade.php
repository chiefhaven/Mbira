<section class="no-print">
    @php
        $eisHomeRoute = auth()->user()->can('business_settings.access')
            ? 'eis.terminals.page'
            : (auth()->user()->can('stock_adjustment.view') ? 'eis.stock.index' : 'eis.stock.outbound.index');
    @endphp
    <nav class="navbar navbar-default bg-white m-4">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#eis-navbar-collapse" aria-expanded="false">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{ route($eisHomeRoute) }}"><i class="fa fas fa-file-invoice-dollar"></i> @lang('eis::lang.eis')</a>
            </div>
            <div class="collapse navbar-collapse" id="eis-navbar-collapse">
                <ul class="nav navbar-nav">
                    @if(auth()->user()->can('business_settings.access'))
                        <li @if(request()->routeIs('eis.terminals.*')) class="active" @endif>
                            <a href="{{ route('eis.terminals.page') }}">@lang('eis::lang.terminals')</a>
                        </li>
                        <li @if(request()->routeIs('eis.sales.*')) class="active" @endif>
                            <a href="{{ route('eis.sales.index') }}">@lang('eis::lang.fiscal_sales')</a>
                        </li>
                    @endif
                    @if(auth()->user()->can('business_settings.access') || auth()->user()->can('stock_adjustment.view'))
                        <li @if(request()->routeIs('eis.stock.index')) class="active" @endif>
                            <a href="{{ route('eis.stock.index') }}">@lang('eis::lang.stock_reconciliation')</a>
                        </li>
                    @endif
                    @if(auth()->user()->can('business_settings.access')
                        || auth()->user()->can('stock_adjustment.create')
                        || auth()->user()->can('stock_transfer.create')
                        || auth()->user()->can('purchase.create'))
                        <li @if(request()->routeIs('eis.stock.outbound.*')) class="active" @endif>
                            <a href="{{ route('eis.stock.outbound.index') }}">@lang('eis::lang.stock_outbound')</a>
                        </li>
                    @endif
                    @if(auth()->user()->can('business_settings.access'))
                        <li @if(request()->routeIs('eis.stock.tools.*')) class="active" @endif>
                            <a href="{{ route('eis.stock.tools.index') }}">@lang('eis::lang.stock_tools')</a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
</section>
