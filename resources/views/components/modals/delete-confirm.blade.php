<!-- 🧨 Custom Delete Confirmation Modal -->
<div class="modal fade" id="customDeleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 shadow-sm">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteItemName">this item</strong>? This action cannot be
                    undone.</p>
            </div>
            <div class="modal-footer">
                <form id="customDeleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"
                        onclick="return handleDelete(this)">
                        Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteBlockedModal" tabindex="-1" aria-labelledby="deleteBlockedModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3 shadow-sm">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="deleteBlockedModalLabel">Cannot Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong id="deleteBlockedItemName">This item</strong> is already in use.</p>
                <p class="mb-0 text-muted" id="deleteBlockedReason"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function initDeleteConfirmModal() {
        // Event delegation on document, guarded to run immediately if the DOM
        // is already parsed (instead of a one-time DOMContentLoaded binding
        // over whatever .custom-delete-btn elements happened to exist at that
        // moment). A plain querySelectorAll+forEach at DOMContentLoaded misses
        // buttons that don't exist yet at binding time and can silently no-op
        // if this script ever ends up evaluated after the event already fired
        // - delegation sidesteps both failure modes entirely, and this modal
        // partial is reused across many pages so it needs to be robust rather
        // than assume a particular load order.
        if (window.__deleteConfirmModalInitialized) return;
        window.__deleteConfirmModalInitialized = true;

        function bind() {
            document.addEventListener("click", function (e) {
                const deleteButton = e.target.closest(".custom-delete-btn");
                if (deleteButton) {
                    const deleteForm = document.getElementById("customDeleteForm");
                    const deleteItemName = document.getElementById("deleteItemName");
                    const route = deleteButton.getAttribute("data-route");

                    if (deleteItemName) {
                        deleteItemName.textContent = deleteButton.getAttribute("data-name") || "this item";
                    }
                    if (deleteForm && route) {
                        deleteForm.setAttribute("action", route);
                    }
                    return;
                }

                const blockedButton = e.target.closest(".delete-blocked-btn");
                if (blockedButton) {
                    const deleteBlockedItemName = document.getElementById("deleteBlockedItemName");
                    const deleteBlockedReason = document.getElementById("deleteBlockedReason");

                    if (deleteBlockedItemName) {
                        deleteBlockedItemName.textContent = blockedButton.getAttribute("data-name") || "This item";
                    }
                    if (deleteBlockedReason) {
                        deleteBlockedReason.textContent = blockedButton.getAttribute("data-reason") || "Remove the existing usage before deleting it.";
                    }
                }
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", bind, { once: true });
        } else {
            bind();
        }
    })();

    function handleDelete(button) {
        const form = button.closest('form');
        const submitBtn = button;

        // Guard against submitting to the current page URL (the collection
        // route, e.g. "/finance/journalentry") instead of the specific
        // item's delete route - this happens if the action never got set
        // to a real route, and would otherwise surface as a confusing
        // "DELETE method is not supported for route ..." error server-side
        // instead of a clear client-side message.
        const action = form.getAttribute('action') || '';
        if (!action || action === window.location.href || action === window.location.pathname) {
            showToast('error', 'Could not determine what to delete. Please close this dialog and try again.');
            return false;
        }

        // Disable button and show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deleting...';

        // Create XMLHttpRequest for better error handling
        const xhr = new XMLHttpRequest();
        const formData = new FormData(form);

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);

                        // Close modal and redirect on success
                        const modal = bootstrap.Modal.getInstance(document.getElementById('customDeleteConfirmModal'));
                        modal.hide();

                        if (response.success) {
                            // Show success message
                            showToast('success', response.message || 'Item deleted successfully. Refreshing page...');
                            // Redirect to index page after short delay
                            setTimeout(() => {
                                window.location.reload();
                                {{--window.location.href = '{{ route("glpostingmap.index") }}';--}}
                            }, 100);
                        } else {
                            // Show error message
                            showToast('error', response.message || 'Failed to delete item');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Yes, Delete';
                        }
                    } catch (e) {
                        // If response is not JSON, treat as success and redirect
                        const modal = bootstrap.Modal.getInstance(document.getElementById('customDeleteConfirmModal'));
                        modal.hide();
                        showToast('success', 'Item deleted successfully. Refreshing page...');
                        setTimeout(() => {
                            window.location.reload();
                            {{--window.location.href = '{{ route("glpostingmap.index") }}';--}}
                        }, 100);
                    }
                } else {
                    // Handle error response
                    try {
                        const response = JSON.parse(xhr.responseText);
                        showToast('error', response.message || 'Failed to delete item');
                    } catch (e) {
                        showToast('error', 'Failed to delete item. Please try again.');
                    }

                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Yes, Delete';
                }
            }
        };

        xhr.send(formData);

        return false; // Prevent default form submission
    }

    function showToast(type, message) {
        // Create a simple toast notification
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        const toastContainer = document.createElement('div');
        toastContainer.innerHTML = toastHtml;
        document.body.appendChild(toastContainer);

        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();

        // Remove toast after it's hidden
        toastContainer.querySelector('.toast').addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(toastContainer);
        });
    }
</script>
