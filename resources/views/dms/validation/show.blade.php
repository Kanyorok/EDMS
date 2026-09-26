@extends('dms.layout')

@section('title','Document Validation')
@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
@endsection
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-4 col-xxl-3">
            <div class="card">
                <div class="card-body">
                    <h3 class="text-center">{{ $documentValidation->Name }}</h3>
                    <h3 class="text-center">{{ $documentValidation->ValidationId }}</h3>
                    <ul class="list-group list-group-flush">
                        @foreach($documentValidation->properties as $property)
                            <li class="list-group-item ">{{ \Illuminate\Support\Str::of($property->Name)->replace('_',' ')->upper()->toString() }}
                                : <b
                                    class="float-end">{{ $property->formated_value }}</b></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Validation Status</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">STATUS <span
                                class="badge rounded-pill {{ $documentValidation->Status?->badge() ?? 'bg-warning' }} float-end">{{ $documentValidation->Status?->description() ?? 'Pending' }}</span>
                        </li>
                        @if(isset($mode))
                            <li class="list-group-item">MODE
                                <span class="float-end">
                                    <i class="{{ $mode->icon() }} me-1"></i>
                                    <b>{{ $mode->label() }}</b>
                                </span>
                            </li>
                        @endif
                        @if($hasWorkflow)
                            <li class="list-group-item">WORKFLOW <b
                                    class="float-end">{{ $documentValidation->type?->workflow?->Name ?? '-' }}</b></li>
                            @if($hasPendingWorkflow && data_get($workflowStatus, 'currentStage.name'))
                                <li class="list-group-item">CURRENT STAGE <b
                                        class="float-end">{{ data_get($workflowStatus, 'currentStage.name') }} <span class="badge bg-secondary ms-1">Approval Phase</span></b></li>
                            @elseif(! $documentValidation->isApproved() && ! $documentValidation->isRejected() && $mode === \App\Enums\DMS\DocumentValidationModeEnum::ApprovalThenSign)
                                <li class="list-group-item">CURRENT STAGE <b
                                        class="float-end text-info">Pending Validation &amp; Signature</b></li>
                            @endif
                        @endif
                        @if($documentValidation->isRejected())
                            <li class="list-group-item text-danger">REASON <b
                                    class="float-end">{{ $documentValidation->RejectionReason }}</b></li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    @include('snippets.behind_scenes',['model'=>$documentValidation])
                </div>
            </div>
        </div>
        <div class="col-md-8 col-xxl-9">
            @if(is_null($document))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Upload Document</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3 file-change d-none">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" id="progress-bar" style="width: 0" aria-valuenow="0"
                                 aria-valuemin="0" aria-valuemax="100"><small class="sr-only">0%
                                    Complete</small></div>
                        </div>
                        <form action="{{ route('dms.validation.update', $documentValidation->ValidationId) }}"
                              method="POST" enctype="multipart/form-data" id="documentUploadForm">@csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label for="file" class="form-label">Select Document</label>
                                <input type="file" class="form-control" id="file" name="file"
                                       accept="application/pdf" required>
                            </div>
                            <button type="submit" class="btn btn-primary float-end file-change d-none"
                                    id="documentUploadBtn">Upload Document
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card ">
                    <div class="card-body" style="min-height: 100px" id="FilePreviewPage">
                        <p class="text-center m-5"><i class="fas fa-spinner fa-spin fa-5x"></i><br>loading preview</p>
                    </div>
                    @if($canApprove)
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-sm-6 col-12">
                                    <button class="btn btn-primary w-100 approve-document-validation" type="button"><i
                                            class="fas fa-check-double me-1"></i>
                                        @if($signatureRequired)
                                            approve &amp; sign
                                        @elseif($hasPendingWorkflow)
                                            approve workflow stage
                                        @elseif($isFinalStage)
                                            approve
                                        @else
                                            approve stage
                                        @endif
                                    </button>
                                </div>

                                <div class="col-sm-6 col-12">
                                    <button class="btn btn-danger w-100 reject-document-validation" type="button"><i
                                            class="fas fa-times"></i> reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    @elseif($canResubmit)
                        <div class="card-footer">
                            <form method="post" id="resubmitValidationForm"
                                  action="{{ route('dms.validation.resubmit',[$documentValidation->ValidationId]) }}">
                                @csrf @method('PUT')
                                <button class="btn btn-primary w-100" id="resubmitValidationBtn" type="submit"><i
                                        class="fas fa-redo"></i> re-submit for validation
                                </button>
                            </form>
                        </div>
                    @elseif($documentValidation->isRejected() && $documentCheckedOut && $documentValidation->CreatedBy === auth()->id())
                        <div class="card-footer">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-file-upload"></i> Check the document back in before you can re-submit it for validation.
                            </div>
                        </div>
                    @endif
                </div>

                @if($hasWorkflow && $workflowHistory->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Approval History</h5>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                <tr>
                                    <th>Stage</th>
                                    <th>Status</th>
                                    <th>By</th>
                                    <th>Notes</th>
                                    <th>On</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($workflowHistory as $history)
                                    <tr>
                                        <td>{{ $history->stage?->StageName ?? '-' }}</td>
                                        <td>{{ $history->status?->Description ?? '-' }}</td>
                                        <td>{{ $history->creator?->Name ?? 'System' }}</td>
                                        <td>{{ $history->Notes ?: '-' }}</td>
                                        <td>{{ optional($history->CreatedOn)->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <div class="modal fade" id="DocumentValidationActionsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">..</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="onboarding-content d-none modal-item" id="rejectDocumentModal">
                        <h4 class="text-danger text-center">
                            Reject Document {{ $document->Name }} ?
                        </h4>
                        <div class="mt-2 mb-2 text-center">
                            The reason is recorded against the validation so the document can be corrected and
                            re-submitted.
                        </div>
                        <hr>
                        <form id="rejectDocumentForm" method="post"
                              action="{{ route('dms.validation.reject',[$documentValidation->ValidationId]) }}">
                            @csrf @method('PUT')
                            <div class="mb-3">
                                <label for="Reason" class="form-label">Reason <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="Reason" id="Reason" rows="3" maxlength="1000"
                                          required></textarea>
                                <p id="Reason_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                   cancel
                                </button>
                                <button class="btn btn-danger float-end" id="rejectDocumentBtn" type="submit"><i
                                        class="fas fa-times"></i> reject
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item" id="approveDocumentModal">
                        <form action="{{ route('dms.validation.approve',[$documentValidation->ValidationId]) }}" method="post" id="approveDocumentForm">
                            <div class="mb-3"> @csrf @method('PUT')
                                @if($signatureRequired)
                                    <div class="alert alert-info py-2 mb-3" role="alert">
                                        <i class="fas fa-file-signature me-1"></i>
                                        <small>A signature is required to complete document validation.</small>
                                    </div>
                                    <label for="Signature" class="form-label">Signature <span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" name="Signature" id="Signature" required>
                                        <option selected disabled>Select a Signature</option>
                                        @foreach($signatures as $signature)
                                            <option value="{{ $signature->SignatureId }}">{{ $signature->Name }}</option>
                                        @endforeach
                                    </select>
                                    <p id="Signature_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                                @elseif($isFinalStage)
                                    {{-- Final stage but no signature required (ApprovalOnly mode) --}}
                                    <div class="alert alert-success py-2" role="alert">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <small>This is the final approval stage. No signature is required for this submission type.</small>
                                    </div>
                                @else
                                    <p class="mb-0">This is an approval workflow stage. No signature is required at this stage. The validation will move to the next approver.</p>
                                @endif
                            </div>
                            <div class="mb-3">
                                <label for="Remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" name="Remarks" id="Remarks" rows="2"
                                          maxlength="1000"></textarea>
                                <p id="Remarks_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="approveDocumentBtn" type="submit"><i
                                        class="fas fa-save"></i>
                                    @if($signatureRequired ?? $isFinalStage)
                                        approve &amp; sign
                                    @elseif($isFinalStage)
                                        approve
                                    @else
                                        approve stage
                                    @endif
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{asset('assets/libs/jquery-form/jquery.form.min.js')}}"></script>
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script> const $Modal = $('#DocumentValidationActionsModal');
        $(function () {
            fetchFilePreview();

            $("#file").change(function () {
                $('.file-change').removeClass('d-none');
            });

            @if(is_null($document))
                $('#documentUploadForm').on('submit', function (e) {
                e.preventDefault();
                const btn = $("#documentUploadBtn");
                $(this).ajaxSubmit({
                    dataType: 'json', beforeSubmit: function () {
                        $("#progress-bar").width('0%');
                        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Uploading...');
                    },
                    uploadProgress: function (event, position, total, percentComplete) {
                        $("#progress-bar").width(percentComplete + '%').html('<small id="progress-status">' + percentComplete + ' % Complete</small>');

                    },
                    success: function (data) {
                        $('.avatar-change').addClass('d-none');
                        nSuccess(data.message);
                        if (data.route !== '') {
                            window.setTimeout(function () {
                                window.location.replace(data.route)
                            }, 3000)
                        }
                    },
                    error: function (request) {
                        formRequest(request, true)
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('Upload Document');
                    }, resetForm: true
                });
            });
            @else
                $(document).on('click', '.approve-document-validation', function () {
                    $(".modal-item").addClass('d-none');
                    $('#approveDocumentModal').removeClass('d-none');
                    $('.modal-title').html('Approve Document Validation.');
                    $('.modal-dialog').removeClass('modal-lg');
                    $Modal.modal('show');
                });
                $('form#approveDocumentForm').submit(async function (e) {
                    e.preventDefault();
                    if (await saveForm($(this), $('#approveDocumentBtn'), true, true, true)) {
                        $Modal.modal('hide');
                    }
                });
                $(document).on('click', '.reject-document-validation', function () {
                    $(".modal-item").addClass('d-none');
                    $('#rejectDocumentModal').removeClass('d-none');
                    $('.modal-title').html('Reject Document Validation.');
                    $('.modal-dialog').removeClass('modal-lg');
                    $Modal.modal('show');
                });
                $('form#rejectDocumentForm').submit(async function (e) {
                    e.preventDefault();
                    if (await saveForm($(this), $('#rejectDocumentBtn'), true, true, true)) {
                        $Modal.modal('hide');
                    }
                });
                $('form#resubmitValidationForm').submit(async function (e) {
                    e.preventDefault();
                    await saveForm($(this), $('#resubmitValidationBtn'), true, true, true);
                });
            @endif
        });

        function fetchFilePreview() {
            @if(!is_null($document))
            const previewContainer = document.getElementById('FilePreviewPage');
            $.ajax({
                url: "{{ route('file.preview', [$document->DocumentId]) }}",
                method: "GET",
                success: function (html) {
                    $(previewContainer).html(html);
                },
                error: function (jqXHR) {
                    $(previewContainer).html(`
                <div class="text-center m-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                    <p class="mt-2">Error loading preview: ${jqXHR.statusText}</p>
                </div>`);
                }
            });
            @endif
        }

    </script>
@endsection
