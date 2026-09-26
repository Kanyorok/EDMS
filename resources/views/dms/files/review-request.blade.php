<link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
<script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
<style>
    .select2-container {
        width: 100% !important;
    }
</style>
<div class="d-flex flex-column" style="height: 85%; overflow-y: auto;">
    <h2 class="text-center">{{ $file->Name }}</h2>
    <div class="alert alert-primary d-flex align-items-center" role="alert">
        <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Info:">
            <use xlink:href="#info-fill"/>
        </svg>
        <div>
            The reviewer can leave pinned comments directly on the document and mark it reviewed once satisfied.
        </div>
    </div>
    <form action="{{ route('document-review.store',[$file->DocumentId]) }}" method="post"
          id="requestReviewForm"> @csrf
        <div class="mb-3">
            <label class="form-label" for="ReviewerId">Reviewer <span class="text-danger">*</span></label>
            <select class="form-control" name="ReviewerId" id="ReviewerId" required></select>
            <p id="ReviewerId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
        </div>
        <div class="mb-3">
            <label class="form-label" for="RequestNote">Note </label>
            <textarea name="RequestNote" id="RequestNote" rows="3" class="form-control"
                      maxlength="2000" placeholder="What should the reviewer focus on?"></textarea>
            <p id="RequestNote_error" class="invalid-feedback d-none error" role="alert"></p>
        </div>
        <hr>
        <div class="mt-4">
            <button type="button" class="btn btn-secondary float-start" onclick="window.bsOffcanvas.hide();">
                cancel
            </button>
            <button class="btn btn-primary float-end" id="requestReviewBtn" type="submit">
                <i class="fas fa-comment-dots"></i> request review
            </button>
        </div>
    </form>
</div>
<script>
    $(function () {
        $('#ReviewerId').select2({
            placeholder: "Search a user to review this document",
            minimumInputLength: 2,
            dropdownParent: $("#offcanvasMain"),
            ajax: {
                url: '{!! route('users.select2',['filter_current' => 'yes']) !!}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {q: $.trim(params.term)};
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            return {text: item.Name, id: item.UserID}
                        })
                    };
                },
                cache: true
            }
        });

        $('form#requestReviewForm').submit(async function (e) {
            e.preventDefault();
            if (await saveForm($(this), $('#requestReviewBtn'), true, true, true)) {
                window.bsOffcanvas.hide();
            }
        });
    });
</script>
