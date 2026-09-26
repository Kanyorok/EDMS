<div class="modal fade" id="FileDocumentsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Add Document</h5>
                    <small class="text-muted" id="FileDocumentsFile"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-2 mb-3">
                    <div class="fw-semibold small mb-2">Already in this file</div>
                    <div id="ExistingDocuments" class="small"></div>
                </div>

                <form method="post" id="fileDocumentsForm" action=""> @csrf
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <h6 class="mb-0">New documents</h6>
                            <small class="text-muted">Each paper you are filing in now.</small>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm add-physical-document">
                            <i class="fas fa-plus"></i> Add Document
                        </button>
                    </div>

                    <div id="FileDocuments" class="physical-documents" data-next-index="0"></div>

                    <div class="d-flex justify-content-between border-top pt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveFileDocuments">
                            <i class="fas fa-save"></i> File Document(s)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
