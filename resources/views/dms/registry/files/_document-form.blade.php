{{-- Filing paper into a physical file.

     Shared by the registry workspace and a file's own page: both build the same
     document cards. Hosts that render the modal in visible page content pass
     documentModalMarkup=false here. A host page may define
     afterDocumentsFiled() to refresh whatever it shows. --}}
@if($documentModalMarkup ?? true)
    @include('dms.registry.files._document-modal')
@endif

<script>
    const physicalFilesBaseUrl = '{{ route('registry.physical-files.index') }}';
    const documentRelationOptionsUrl = '{{ route('registry.physical-files.relation-options') }}';

    $(function () {
        // The handlers work off the container the click came from, so the same
        // cards serve this modal and the register-a-file form.
        $(document).on('click', '.add-physical-document', function () {
            addDocumentCard($(this).closest('.modal').find('.physical-documents'));
        });

        $(document).on('click', '.remove-physical-document', function () {
            const $container = $(this).closest('.physical-documents');
            $(this).closest('.physical-document-card').remove();
            refreshDocumentNumbers($container);
        });

        $(document).on('click', '.add-document-relation', function () {
            const $card = $(this).closest('.physical-document-card');
            const documentIndex = $card.data('document-index');
            const relationIndex = Number($card.attr('data-next-relation') || 0);

            $card.attr('data-next-relation', relationIndex + 1);

            const $row = $(relationTemplate(documentIndex, relationIndex));
            $card.find('.document-relations').append($row);
            initialiseRelationSelect($row);
        });

        $(document).on('click', '.remove-document-relation', function () {
            $(this).closest('.document-relation-row').remove();
        });

        $(document).on('click', '.add-file-document', function () {
            openFileDocuments($(this).data('id'), $(this).data('file'));
        });

        $('form#fileDocumentsForm').submit(async function (e) {
            e.preventDefault();

            if (await saveForm($(this), $('#saveFileDocuments'), false, false, true)) {
                $('#FileDocumentsModal').modal('hide');

                if (typeof afterDocumentsFiled === 'function') {
                    afterDocumentsFiled();
                }
            }
        });
    });

    function fileDocumentsUrl(fileId) {
        return physicalFilesBaseUrl + '/' + encodeURIComponent(fileId) + '/documents';
    }

    /**
     * Opens the "file another paper into this file" form, showing what the
     * file already holds so the same document is not filed twice.
     */
    function openFileDocuments(fileId, label) {
        const $form = $('#fileDocumentsForm');
        $form.attr('action', fileDocumentsUrl(fileId));
        $form.find('.error').addClass('d-none').html('');
        $form.find('.is-invalid').removeClass('is-invalid');

        $('#FileDocumentsFile').text(label || '');
        $('#ExistingDocuments').html('<span class="spinner-border spinner-border-sm me-2"></span>loading…');

        addDocumentCard($('#FileDocuments').empty().attr('data-next-index', 0));
        $('#FileDocumentsModal').modal('show');

        $.getJSON(fileDocumentsUrl(fileId))
            .done(renderExistingDocuments)
            .fail(function (jqXHR) {
                $('#ExistingDocuments').html('<span class="text-muted">could not load the file contents</span>');
                nWarning(jqXHR.responseJSON?.message || 'loading the file contents failed');
            });
    }

    function renderExistingDocuments(payload) {
        const documents = (payload && payload.documents) || [];
        const $list = $('#ExistingDocuments').empty();

        if (!documents.length) {
            $list.html('<span class="text-muted">No documents filed yet.</span>');
            return;
        }

        documents.forEach(function (document, position) {
            const $row = $('<div>').addClass('d-flex justify-content-between gap-3 py-1 border-bottom');
            const $left = $('<div>').appendTo($row);

            $('<span>').addClass('fw-semibold').text((position + 1) + '. ' + document.title).appendTo($left);

            if (document.description) {
                $left.append('<br>');
                $('<span>').addClass('text-muted').text(document.description).appendTo($left);
            }

            $('<span>').addClass('text-muted text-nowrap').text(document.documentDate || '—').appendTo($row);
            $list.append($row);
        });
    }

    function addDocumentCard($container) {
        const index = Number($container.attr('data-next-index') || 0);

        $container.attr('data-next-index', index + 1);
        $container.append(physicalDocumentTemplate(index));
        refreshDocumentNumbers($container);
    }

    function refreshDocumentNumbers($container) {
        const $cards = $container.find('.physical-document-card');

        $cards.each(function (position) {
            $(this).find('.document-number').first().text('Document ' + (position + 1));
        });

        $cards.find('.remove-physical-document').toggleClass('d-none', $cards.length === 1);
    }

    function physicalDocumentTemplate(index) {
        return `<div class="card physical-document-card mb-3" data-document-index="${index}" data-next-relation="0">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <strong class="document-number"></strong>
                    <button type="button" class="btn btn-sm btn-link text-danger remove-physical-document">Remove</button>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input class="form-control" name="Documents[${index}][Title]" maxlength="255" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Document date</label>
                        <input type="date" class="form-control" name="Documents[${index}][DocumentDate]">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="Documents[${index}][Description]" rows="2" maxlength="1000" required></textarea>
                </div>
                <div class="document-relations"></div>
                <button type="button" class="btn btn-outline-secondary btn-sm add-document-relation">
                    <i class="fas fa-link"></i> Link Client, Lead or Employee
                </button>
            </div>
        </div>`;
    }

    function relationTemplate(documentIndex, relationIndex) {
        return `<div class="row g-2 align-items-end document-relation-row mb-2">
            <div class="col-md-4">
                <label class="form-label mb-1">Related to</label>
                <select class="form-control relation-type"
                        name="Documents[${documentIndex}][Relations][${relationIndex}][Related]" required>
                    <option value="">— choose type —</option>
                    <option value="ClientID">Client</option>
                    <option value="LeadID">Lead</option>
                    <option value="hr_employees">Employee</option>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label mb-1">Record</label>
                <select class="form-control relation-target"
                        name="Documents[${documentIndex}][Relations][${relationIndex}][RelatedID]" required></select>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger remove-document-relation"><i class="fas fa-times"></i></button>
            </div>
        </div>`;
    }

    function initialiseRelationSelect($row) {
        const $target = $row.find('.relation-target');

        $target.select2({
            dropdownParent: $row.closest('.modal'),
            placeholder: 'Search by name or number',
            minimumInputLength: 2,
            ajax: {
                url: documentRelationOptionsUrl,
                dataType: 'json',
                delay: 250,
                data: params => ({
                    type: $row.find('.relation-type').val(),
                    q: params.term
                }),
                processResults: data => data
            }
        });

        $row.find('.relation-type').on('change', function () {
            $target.val(null).trigger('change');
        });
    }
</script>
