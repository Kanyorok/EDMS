<link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
<script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
<style>
    .select2-container {
        width: 100% !important;
    }

    .submission-mode-card {
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 14px 16px;
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 10px;
        position: relative;
    }

    .submission-mode-card:hover {
        border-color: #6c757d;
        background-color: #f8f9fa;
    }

    .submission-mode-card.selected {
        border-color: #0d6efd;
        background-color: #eef4ff;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
    }

    .submission-mode-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .submission-mode-card .mode-icon {
        font-size: 1.4rem;
        margin-right: 12px;
        color: #6c757d;
        transition: color 0.2s ease;
    }

    .submission-mode-card.selected .mode-icon {
        color: #0d6efd;
    }

    .submission-mode-card .mode-title {
        font-weight: 600;
        font-size: 0.92rem;
        color: #212529;
        display: block;
        line-height: 1.2;
    }

    .submission-mode-card .mode-desc {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 3px;
        display: block;
    }

    .submission-mode-card.selected .mode-title {
        color: #0d6efd;
    }

    /* Transition for validation type field show/hide */
    #validationTypeWrapper {
        transition: opacity 0.25s ease, max-height 0.3s ease;
        overflow: hidden;
    }
    #validationTypeWrapper.hidden-field {
        opacity: 0;
        max-height: 0;
        pointer-events: none;
    }
    #validationTypeWrapper.visible-field {
        opacity: 1;
        max-height: 200px;
    }
</style>
<div class="d-flex flex-column" style="height: 85%; overflow-y: auto;">
    <h4 class="text-center">{{ $file->Name }}</h4>
    <form action="{{ route('file-validation.store', [$file->DocumentId])  }}" method="post"
          id="requestDocumentValidationForm"> @csrf
        {{-- Step 1: choose submission type first so we know whether to show the type field --}}
        <div class="mb-3">
            <label class="form-label fw-semibold">Submission Type <span class="text-danger">*</span></label>
            <p id="SubmissionMode_error" class="invalid-feedback d-none error col-12" role="alert"></p>

            {{-- Option 1: Approval Only --}}
            <label class="submission-mode-card d-flex align-items-center" id="mode-card-ap" for="mode_ap">
                <input type="radio" name="SubmissionMode" id="mode_ap" value="ap" required>
                <span class="mode-icon"><i class="fas fa-check-circle"></i></span>
                <span>
                    <span class="mode-title">Submit For Approval</span>
                    <span class="mode-desc">Routes through the approval workflow only. No signature required.</span>
                </span>
            </label>

            {{-- Option 2: Validation (approve + sign) --}}
            <label class="submission-mode-card d-flex align-items-center" id="mode-card-va" for="mode_va">
                <input type="radio" name="SubmissionMode" id="mode_va" value="va">
                <span class="mode-icon"><i class="fas fa-file-signature"></i></span>
                <span>
                    <span class="mode-title">Submit for Validation</span>
                    <span class="mode-desc">Requires approval and a signature from the assigned approvers.</span>
                </span>
            </label>

            {{-- Option 3: Approval then Validation (sign) --}}
            <label class="submission-mode-card d-flex align-items-center" id="mode-card-av" for="mode_av">
                <input type="radio" name="SubmissionMode" id="mode_av" value="av">
                <span class="mode-icon"><i class="fas fa-tasks"></i></span>
                <span>
                    <span class="mode-title">Submit For Approval and Validation</span>
                    <span class="mode-desc">First approved through the workflow, then signed by the added approvers.</span>
                </span>
            </label>
        </div>

        {{-- Validation Type: hidden for ApprovalOnly, required otherwise --}}
        <div id="validationTypeWrapper" class="mb-3 visible-field">
            <label class="form-label" for="ValidationType">
                Validation Type <span class="text-danger" id="validationTypeRequired">*</span>
            </label>
            <select name="ValidationType" id="ValidationType" class="form-control">
                <option value="" selected disabled>-- Select a type --</option>
                @foreach($types as $type)
                    <option value="{{ $type->ValidationTypeId }}">{{ $type->Name }}</option>
                @endforeach
            </select>
            <p id="ValidationType_error" class="invalid-feedback d-none error col-12" role="alert"></p>
        </div>

        <hr>
        <div class="mt-4">
            <button type="button" class="btn btn-secondary float-start" onclick="window.bsOffcanvas.hide();">
                cancel
            </button>
            <button class="btn btn-primary float-end" id="requestDocumentValidationBtn" type="submit" disabled>
                <i class="fas fa-paper-plane"></i> submit request
            </button>
        </div>
    </form>
</div>
<script>
    $(function () {
        const $typeWrapper   = $('#validationTypeWrapper');
        const $typeSelect    = $('#ValidationType');
        const $submitBtn     = $('#requestDocumentValidationBtn');
        const $modeCards     = $('.submission-mode-card');
        const $modeRadios    = $('input[name="SubmissionMode"]');

        // Initialise Select2
        $typeSelect.select2({
            placeholder: "Select Validation Types",
            dropdownParent: $("#offcanvasMain"),
        });

        // Show/hide Validation Type field and toggle its required-ness based on mode
        function applyMode(mode) {
            if (mode === 'ap') {
                // Approval Only – no validation type needed
                $typeWrapper.addClass('hidden-field').removeClass('visible-field');
                $typeSelect.removeAttr('required').val('').trigger('change');
            } else {
                // Validation / ApprovalThenSign – type is required
                $typeWrapper.removeClass('hidden-field').addClass('visible-field');
                $typeSelect.attr('required', 'required');
            }
            // Enable submit once a mode is chosen
            $submitBtn.prop('disabled', false);
        }

        // Highlight selected card & apply mode
        $modeRadios.on('change', function () {
            $modeCards.removeClass('selected');
            $(this).closest('.submission-mode-card').addClass('selected');
            applyMode($(this).val());
        });

        // Allow clicking anywhere on the card
        $modeCards.on('click', function () {
            const radio = $(this).find('input[type="radio"]');
            radio.prop('checked', true).trigger('change');
        });

        $('form#requestDocumentValidationForm').submit(async function (e) {
            e.preventDefault();
            const response = await saveForm($(this), $submitBtn, false, true, true);
            if (response) {
                window.bsOffcanvas.hide();
            }
        });
    });
</script>
