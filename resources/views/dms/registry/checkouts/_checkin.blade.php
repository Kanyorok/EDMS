{{-- Taking a lent file back.

     Shared by the registry workspace and a file's own page. The shelf the file
     goes back to is asked for here rather than assumed to be its home: a file
     often lands in a returns tray first, and the shelving has to say so. A host
     may define afterFileCheckedIn() to refresh whatever it shows; otherwise the
     page is reloaded, since a return changes custody, status and counts. --}}
<div class="modal fade" id="CheckInFileModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Check this file back in</h5>
                    <small class="text-muted" id="CheckInFileNumber"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Returned by <b id="CheckInFileBorrower"></b>. Custody passes back to the registry
                    the moment this is confirmed.
                </p>
                <form method="post" id="checkInFileForm" action=""> @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label" for="ReturnedToLocationId">
                            Shelved at <span class="text-danger">*</span>
                        </label>
                        {{-- Filled from storage-locations.parents when the modal opens. --}}
                        <select class="form-control" id="ReturnedToLocationId" name="ReturnedToLocationId" required>
                            <option value="">loading locations&hellip;</option>
                        </select>
                        <p id="ReturnedToLocationId_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="CheckInRemark">
                            Condition on return (optional)
                        </label>
                        <textarea class="form-control" id="CheckInRemark" name="CheckInRemark" rows="3"
                                  maxlength="1000"
                                  placeholder="Anything wrong with the file, or missing from it"></textarea>
                        <p id="CheckInRemark_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                    </div>

                    <div class="d-flex justify-content-between pt-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="checkInFileBtn">
                            <i class="fas fa-inbox"></i> check in
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const checkInUrlTemplate = '{{ route('registry.checkouts.checkin', ['physicalFileCheckout' => '__ID__']) }}';
    const checkInLocationsUrl = '{{ route('storage-locations.parents') }}';

    $(function () {
        $(document).on('click', '.checkin-file', function () {
            const $trigger = $(this);
            const $form = $('#checkInFileForm');

            $form[0].reset();
            $form.find('.error').addClass('d-none').html('');
            $form.find('.is-invalid').removeClass('is-invalid');
            $form.attr('action', checkInUrlTemplate.replace('__ID__', $trigger.data('id')));

            $('#CheckInFileNumber').text($trigger.data('file') || '');
            $('#CheckInFileBorrower').text($trigger.data('borrower') || 'the borrower');

            // The file goes back into the registry that keeps it, so those are
            // the only locations offered - its home shelf first.
            loadCheckInLocations($trigger.data('registry'), $trigger.data('home-location'));

            $('#CheckInFileModal').modal('show');
        });

        $('form#checkInFileForm').submit(async function (e) {
            e.preventDefault();

            if (await saveForm($(this), $('#checkInFileBtn'), false, false, true)) {
                $('#CheckInFileModal').modal('hide');

                // Close the request history if it was left open behind this.
                if (window.bsOffcanvas) window.bsOffcanvas.hide();

                if (typeof afterFileCheckedIn === 'function') {
                    afterFileCheckedIn();
                } else {
                    window.location.reload();
                }
            }
        });
    });

    function loadCheckInLocations(registry, homeLocationId) {
        const $select = $('#ReturnedToLocationId');
        $select.html('<option value="">loading locations…</option>');

        return $.getJSON(checkInLocationsUrl, {registry: registry})
            .done(function (locations) {
                $select.html('<option value="">— select a location —</option>');

                $.each(locations, function (_, location) {
                    $select.append(
                        $('<option>').val(location.Id).text(location.Label)
                    );
                });

                if (homeLocationId) $select.val(homeLocationId);
            })
            .fail(function () {
                $select.html('<option value="">loading locations failed</option>');
                nWarning('loading the storage locations failed');
            });
    }
</script>
