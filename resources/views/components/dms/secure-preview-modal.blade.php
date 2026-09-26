@props(['title' => 'Document preview'])

<div class="modal fade" id="dmsSecurePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dmsSecurePreviewModalTitle">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="dmsSecurePreviewModalContent"></div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function showError(message) {
                    if (typeof window.nError === 'function') {
                        window.nError(message);
                        return;
                    }
                    alert(message);
                }

                function setLoading() {
                    const container = document.getElementById('dmsSecurePreviewModalContent');
                    if (!container) return;
                    container.innerHTML = '<div class="text-center my-4"><div class="spinner-grow text-secondary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                }

                async function loadPreview(url) {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        let message = 'Failed to load document preview.';
                        const contentType = (response.headers.get('content-type') || '').toLowerCase();

                        if (contentType.includes('application/json')) {
                            try {
                                const data = await response.json();
                                if (data && data.message) {
                                    message = data.message;
                                }
                            } catch (e) {
                                // ignore
                            }
                        } else {
                            const text = await response.text();
                            if (text) {
                                message = text;
                            }
                        }

                        throw new Error(message);
                    }

                    return await response.text();
                }

                document.addEventListener('click', async function (event) {
                    const trigger = event.target.closest('.dms-secure-document-preview');
                    if (!trigger) return;

                    event.preventDefault();

                    const url = trigger.getAttribute('data-preview-url');
                    if (!url) return;

                    const name = trigger.getAttribute('data-document-name') || 'Document preview';
                    const modalEl = document.getElementById('dmsSecurePreviewModal');
                    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                        showError('Preview is not available (Bootstrap modal not loaded).');
                        return;
                    }

                    const titleEl = document.getElementById('dmsSecurePreviewModalTitle');
                    if (titleEl) titleEl.textContent = name;

                    setLoading();

                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();

                    try {
                        const html = await loadPreview(url);
                        const container = document.getElementById('dmsSecurePreviewModalContent');
                        if (container) container.innerHTML = html;
                    } catch (error) {
                        modal.hide();
                        showError(error && error.message ? error.message : 'Failed to load document preview.');
                    }
                });
            })();
        </script>
    @endpush
@endonce
