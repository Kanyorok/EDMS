<div>
    <div class="mb-3">
        <label class="form-label" for="LockDocumentId">Document <span class="text-danger">*</span></label>
        <select class="form-control" id="LockDocumentId" style="width: 100%"></select>
        <p id="DocumentId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
    </div>

    <div id="documentHoldStatus" class="d-none">
        <div class="alert" id="documentHoldStatusAlert" role="alert"></div>

        <form action="{{ route('legal-hold.lock-document') }}" method="post" id="lockDocumentForm" class="d-none">
            @csrf
            <input type="hidden" name="DocumentId" id="lockDocumentIdField">
            <div class="mb-3">
                <label class="form-label" for="LockDescription">Reason</label>
                <textarea name="Description" id="LockDescription" rows="3" class="form-control"
                          maxlength="1000"></textarea>
                <p id="Description_error" class="invalid-feedback d-none error col-12" role="alert"></p>
            </div>
            <hr>
            <div class="mt-4">
                <button type="button" class="btn btn-secondary float-start"
                        onclick="window.bsOffcanvas.hide()">
                    cancel
                </button>
                <button class="btn btn-danger float-end" id="lockDocumentBtn" type="submit"><i
                        class="fas fa-lock"></i> lock document
                </button>
            </div>
        </form>

        <form action="{{ route('legal-hold.unlock-document') }}" method="post" id="unlockDocumentForm"
              class="d-none">
            @csrf
            <input type="hidden" name="DocumentId" id="unlockDocumentIdField">
            <hr>
            <div class="mt-4">
                <button type="button" class="btn btn-secondary float-start"
                        onclick="window.bsOffcanvas.hide()">
                    cancel
                </button>
                <button class="btn btn-success float-end" id="unlockDocumentBtn" type="submit"><i
                        class="fas fa-lock-open"></i> unlock document
                </button>
            </div>
        </form>
    </div>
</div>
<script>
    $(function () {
        const $status = $('#documentHoldStatus');
        const $alert = $('#documentHoldStatusAlert');
        const $lockForm = $('#lockDocumentForm');
        const $unlockForm = $('#unlockDocumentForm');

        $('#LockDocumentId').select2({
            placeholder: 'search a document by name',
            minimumInputLength: 2,
            dropdownParent: $("#offcanvasMain"),
            ajax: {
                url: '{!! route('documents.select2') !!}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {q: $.trim(params.term)};
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            return {text: item.Name, id: item.DocumentId};
                        })
                    };
                },
                cache: true
            }
        }).on('change', function () {
            const documentId = $(this).val();
            $status.addClass('d-none');
            $lockForm.addClass('d-none');
            $unlockForm.addClass('d-none');
            if (!documentId) {
                return;
            }

            $.ajax({
                url: '{{ route('legal-hold.document-status') }}',
                method: 'GET',
                data: {DocumentId: documentId},
                success: function (data) {
                    $status.removeClass('d-none');
                    if (data.isHold) {
                        const releasable = data.holds.some(function (h) {
                            return h.releasable;
                        });
                        if (releasable) {
                            $alert.removeClass('alert-success alert-warning').addClass('alert-danger')
                                .html('<i class="fas fa-lock"></i> <b>' + data.Name + '</b> is on hold.');
                            $('#unlockDocumentIdField').val(documentId);
                            $unlockForm.removeClass('d-none');
                        } else {
                            const holdNames = data.holds.map(function (h) {
                                return h.Name;
                            }).join(', ');
                            $alert.removeClass('alert-success alert-danger').addClass('alert-warning')
                                .html('<i class="fas fa-lock"></i> <b>' + data.Name + '</b> is on hold via a shared hold (' + holdNames + '). Release it from that hold\'s page instead.');
                        }
                    } else {
                        $alert.removeClass('alert-danger alert-warning').addClass('alert-success')
                            .html('<i class="fas fa-lock-open"></i> <b>' + data.Name + '</b> is not on hold.');
                        $('#lockDocumentIdField').val(documentId);
                        $lockForm.removeClass('d-none');
                    }
                },
                error: function (jqXHR) {
                    $status.addClass('d-none');
                    codeNotify(jqXHR.status);
                }
            });
        });

        $lockForm.submit(async function (e) {
            e.preventDefault();
            if (await saveForm($(this), $('#lockDocumentBtn'), false, true, true)) {
                window.bsOffcanvas.hide();
                if (typeof fetchLegalHoldsTable === "function") {
                    fetchLegalHoldsTable();
                }
            }
        });

        $unlockForm.submit(async function (e) {
            e.preventDefault();
            if (await saveForm($(this), $('#unlockDocumentBtn'), false, true, true)) {
                window.bsOffcanvas.hide();
                if (typeof fetchLegalHoldsTable === "function") {
                    fetchLegalHoldsTable();
                }
            }
        });
    });
</script>
