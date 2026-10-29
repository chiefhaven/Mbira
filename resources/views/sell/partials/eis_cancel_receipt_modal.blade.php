<div class="modal fade" id="eis_cancel_receipt_modal" tabindex="-1" role="dialog" aria-labelledby="eis_cancel_receipt_title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="eis_cancel_receipt_title">Request EIS receipt cancellation</h4>
            </div>
            <div class="modal-body">
                <div id="eis-cancel-message" role="status" aria-live="polite"></div>
                <p class="text-warning">This sends a void request to EIS; it does not cancel the POS sale or change local stock. MRA approval is separate.</p>
                <p>Fiscal invoice: <strong id="eis-cancel-invoice"></strong></p>
                <div id="eis-cancel-form">
                    <label for="eis-cancel-reason">Reason</label>
                    <textarea id="eis-cancel-reason" class="form-control" maxlength="1000" required></textarea>
                    <button type="button" id="eis-cancel-prepare" class="btn btn-primary">Review cancellation request</button>
                </div>
                <div id="eis-cancel-review" hidden>
                    <div id="eis-cancel-request-details" class="table-responsive"></div>
                    <label><input id="eis-cancel-confirm" type="checkbox"> I reviewed this exact void request and understand that approval is separate.</label>
                    <button type="button" id="eis-cancel-submit" class="btn btn-danger">Send void request once</button>
                </div>
            </div>
        </div>
    </div>
</div>
