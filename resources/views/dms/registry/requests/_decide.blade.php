{{-- Answering a file request, handing the file over, and taking an approval
     back.

     The history reads in offcanvasMain; the answer is given here, so a remark
     is typed into a real field rather than a browser prompt. Shared by the
     registry workspace and a file's own page - a host may define
     afterRequestDecided() to refresh whatever it shows. --}}
<div class="modal fade" id="DecideRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="DecideRequestTitle">Decide request</h5>
                    <small class="text-muted" id="DecideRequestFile"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="decideRequestForm" action=""> @csrf
                    @method('put')
                    <input type="hidden" name="decision" id="DecideRequestDecision" value="">

                    <div class="mb-3">
                        <label class="form-label" for="remark">
                            <span id="DecideRequestRemarkLabel">Remark</span>
                            <span class="text-danger d-none" id="DecideRequestRemarkRequired">*</span>
                        </label>
                        <textarea class="form-control" id="remark" name="remark" rows="3"
                                  maxlength="1000"></textarea>
                        <p id="remark_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                    </div>

                    <div class="d-flex justify-content-between pt-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="decideRequestBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- The counter moment: the file leaves the shelf and the requester signs for
     it, so the remark is where any condition of the loan is written down. --}}
<div class="modal fade" id="HandOverRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Hand over this file</h5>
                    <small class="text-muted" id="HandOverRequestFile"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Custody passes to <b id="HandOverRequestRequester"></b>, who becomes answerable
                    for the file until it is returned to the registry.
                </p>
                <form method="post" id="handOverRequestForm" action=""> @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label" for="HandoverRemark">Handover remark (optional)</label>
                        <textarea class="form-control" id="HandoverRemark" name="HandoverRemark" rows="3"
                                  maxlength="1000"></textarea>
                        <p id="HandoverRemark_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                    </div>

                    <div class="d-flex justify-content-between pt-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="handOverRequestBtn">
                            <i class="fas fa-hand-holding"></i> hand over
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Taking an approval back. The requester was told the file was theirs to
     collect, so a reason is not optional here. --}}
<div class="modal fade" id="CancelRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Cancel this approval</h5>
                    <small class="text-muted" id="CancelRequestFile"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="cancelRequestForm" action=""> @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label" for="CancellationReason">
                            Reason for cancelling <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="CancellationReason" name="CancellationReason" rows="3"
                                  maxlength="1000" required></textarea>
                        <p id="CancellationReason_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                    </div>

                    <div class="d-flex justify-content-between pt-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">no, keep it</button>
                        <button type="submit" class="btn btn-danger" id="cancelRequestBtn">
                            <i class="fas fa-times"></i> cancel approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const requestHistoryUrlTemplate = '{{ route('registry.requests.history', ['physicalFileRequest' => '__ID__']) }}';
    const decideRequestUrlBase = '{{ route('registry.requests.index') }}';
    const handOverRequestUrlTemplate = '{{ route('registry.requests.handover', ['physicalFileRequest' => '__ID__']) }}';
    const cancelRequestUrlTemplate = '{{ route('registry.requests.cancel', ['physicalFileRequest' => '__ID__']) }}';

    $(function () {
        // The history opens in the shared offcanvas, from a table row or
        // anywhere else that renders the trigger.
        $(document).on('click', '.request-history', function () {
            showOffCanvasMain(
                'Request: ' + ($(this).data('file') || 'physical file'),
                requestHistoryUrlTemplate.replace('__ID__', $(this).data('id'))
            );
        });

        $(document).on('click', '.decide-request', function () {
            const decision = $(this).data('decision');
            const rejecting = decision === 'reject';
            const $form = resetRequestForm($('#decideRequestForm'));

            $form.attr('action', decideRequestUrlBase + '/' + $(this).data('id'));

            $('#DecideRequestDecision').val(decision);
            $('#DecideRequestTitle').text(rejecting ? 'Reject this request' : 'Approve this request');
            $('#DecideRequestFile').text($(this).data('file') || '');
            $('#DecideRequestRemarkLabel').text(rejecting ? 'Reason for rejection' : 'Approval remark (optional)');
            $('#DecideRequestRemarkRequired').toggleClass('d-none', ! rejecting);
            $('#remark').prop('required', rejecting);
            $('#decideRequestBtn')
                .toggleClass('btn-primary', ! rejecting)
                .toggleClass('btn-danger', rejecting)
                .html(rejecting ? '<i class="fas fa-times"></i> reject' : '<i class="fas fa-check"></i> approve');

            $('#DecideRequestModal').modal('show');
        });

        $(document).on('click', '.handover-request', function () {
            const $form = resetRequestForm($('#handOverRequestForm'));

            $form.attr('action', handOverRequestUrlTemplate.replace('__ID__', $(this).data('id')));
            $('#HandOverRequestFile').text($(this).data('file') || '');
            $('#HandOverRequestRequester').text($(this).data('requester') || 'the requester');
            $('#HandOverRequestModal').modal('show');
        });

        $(document).on('click', '.cancel-request', function () {
            const $form = resetRequestForm($('#cancelRequestForm'));

            $form.attr('action', cancelRequestUrlTemplate.replace('__ID__', $(this).data('id')));
            $('#CancelRequestFile').text($(this).data('file') || '');
            $('#CancelRequestModal').modal('show');
        });

        $('form#decideRequestForm').submit(async function (e) {
            e.preventDefault();

            if (await saveForm($(this), $('#decideRequestBtn'), false, false, true)) {
                closeAfterRequestAction($('#DecideRequestModal'));
            }
        });

        $('form#handOverRequestForm').submit(async function (e) {
            e.preventDefault();

            // A handover moves custody, which shows up well beyond the requests
            // table, so the page is reloaded rather than a table refreshed.
            if (await saveForm($(this), $('#handOverRequestBtn'), false, false, true)) {
                closeAfterRequestAction($('#HandOverRequestModal'), true);
            }
        });

        $('form#cancelRequestForm').submit(async function (e) {
            e.preventDefault();

            if (await saveForm($(this), $('#cancelRequestBtn'), false, false, true)) {
                closeAfterRequestAction($('#CancelRequestModal'));
            }
        });
    });

    /** A form opened afresh carries nothing over from the last request. */
    function resetRequestForm($form) {
        $form[0].reset();
        $form.find('.error').addClass('d-none').html('');
        $form.find('.is-invalid').removeClass('is-invalid');

        return $form;
    }

    function closeAfterRequestAction($modal, reload = false) {
        $modal.modal('hide');

        // The action may have been taken from the history panel.
        if (window.bsOffcanvas) window.bsOffcanvas.hide();

        if (reload) {
            window.location.reload();
        } else if (typeof afterRequestDecided === 'function') {
            afterRequestDecided();
        }
    }
</script>
