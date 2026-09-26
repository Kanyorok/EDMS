<div class="modal fade" id="previewDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewDocumentModalTitle">File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="onboarding-content with-gradient" id="previewDocumentContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.close') ?? 'Close' }}</button>
            </div>
        </div>
    </div>
</div>

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

            function ensureModalInBody() {
                const modals = document.querySelectorAll('#previewDocumentModal');
                if (!modals.length) return null;
                const modalEl = modals[0];
                for (let i = 1; i < modals.length; i++) {
                    modals[i].remove();
                }
                // If the modal was rendered inside a hidden container like #page-scripts or #page-stack-scripts,
                // or inside a card/container with its own stacking context, reparent it to <body>.
                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
                return modalEl;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ensureModalInBody);
            } else {
                ensureModalInBody();
            }

            function setLoading() {
                const container = document.getElementById('previewDocumentContent');
                if (!container) return;
                container.innerHTML = '<div class="text-center my-4"><div class="spinner-grow text-secondary me-2" role="status"><span class="visually-hidden">Loading...</span></div></div>';
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
                const trigger = event.target.closest('.modal-preview-document');
                if (!trigger) return;

                event.preventDefault();

                const url = trigger.getAttribute('data-url') || trigger.dataset.url;
                if (!url) return;

                const title = trigger.getAttribute('data-title') || trigger.getAttribute('data-name') || trigger.getAttribute('title') || 'File';
                const modalEl = ensureModalInBody();
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    showError('Preview is not available (Bootstrap modal not loaded).');
                    return;
                }

                const titleEl = document.getElementById('previewDocumentModalTitle');
                if (titleEl) {
                    titleEl.textContent = title.startsWith('File:') ? title : 'File: ' + title;
                }

                setLoading();

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();

                try {
                    const html = await loadPreview(url);
                    const container = document.getElementById('previewDocumentContent');
                    if (container) container.innerHTML = html;
                } catch (error) {
                    modal.hide();
                    showError(error && error.message ? error.message : 'Failed to load document preview.');
                }
            });
        })();
    </script>
@endpush
