<div class="{{ !empty($standalone) ? 'box box-primary eis-terminal-management' : 'pos-tab-content' }}" id="eis-terminal-management">
    @if (empty($standalone))
        @include('eis::shared.navigation')
        <h4>MRA EIS terminals</h4>
        <p>Activate a terminal.</p>
    @else
        <div class="box-header with-border">
            <h2 class="box-title eis-terminals-section-title">Registered terminals</h2>
            <p class="eis-terminals-section-description">Review terminal status, location defaults, configurations, and credentials.</p>
        </div>
        <div class="box-body">
    @endif
    <div id="eis-management-message" role="status" aria-live="polite"></div>
    <div class="eis-terminal-activation">
        @if (!empty($standalone))
            <h3 class="eis-terminals-activation-heading">Activate a terminal</h3>
            <p class="eis-terminals-activation-help">Enter the activation code supplied for this EIS terminal.</p>
        @endif
        <div class="row">
            <div class="{{ !empty($standalone) ? 'col-md-5' : 'col-md-4' }} form-group">
                <label for="eis-activation-code">Activation code</label>
                <input type="text" id="eis-activation-code" class="form-control" autocomplete="off">
            </div>
            <div class="{{ !empty($standalone) ? 'col-md-7' : 'col-md-4' }} form-group eis-terminal-activation-actions">
                @if (empty($standalone))
                    <label>&nbsp;</label>
                @endif
                <div>
                    <button type="button" id="eis-activate-terminal" class="btn btn-success" disabled>Activate terminal</button>
                    <button type="button" id="eis-refresh-terminals" class="btn btn-default">Refresh</button>
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive eis-terminal-table-wrap">
        <table class="table table-bordered table-striped eis-terminal-table">
            <thead>
                <tr>
                    <th>Terminal</th>
                    <th>Location</th>
                    <th>Default for location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="eis-terminal-rows">
                <tr><td colspan="5">Loading terminals…</td></tr>
            </tbody>
        </table>
    </div>
    <p class="text-muted eis-management-note">Only terminals in your permitted locations are shown. Token renewal stores new credentials securely on the server.</p>
    <div class="box box-default" id="eis-configuration-panel" hidden>
        <div class="box-header with-border"><h3 class="box-title">Global, Terminal and Taxpayer configurations</h3></div>
        <div class="box-body table-responsive">
        <p>Saved configuration for the selected terminal. Use Latest config to fetch all three groups from EIS. Local enablement and terminal ownership are not overwritten. Credentials are excluded.</p>
        <div id="eis-configuration-data" aria-live="polite"></div>
        </div>
    </div>
    @if (!empty($standalone))
        </div>
    @endif
</div>
