@extends('dms.layout')

@section('title')
    Review: {{ $file->Name }}
@endsection
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dms.review.index') }}">Document Review</a></li>
@endsection
@section('styles')
    <style>
        #reviewPages {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .review-page {
            position: relative;
            margin: 0 auto 16px;
            max-width: 100%;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .15);
            background: #fff;
        }

        .review-page canvas, .review-page img {
            display: block;
            width: 100%;
            height: auto;
        }

        .review-pin-layer {
            position: absolute;
            inset: 0;
        }

        .review-page.placing-mode {
            cursor: crosshair;
        }

        /* The annotation is "burned" onto the page: its text is always visible at the
           point it was placed, not hidden behind a hover/click on a bare marker. */
        .review-pin {
            position: absolute;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            max-width: 220px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #fd7e14;
            border-radius: .375rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .3);
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.3;
            cursor: pointer;
        }

        .review-pin.mine {
            background: #cfe2ff;
            border-color: #9ec5fe;
            border-left-color: #0d6efd;
        }

        .review-pin .review-pin-badge {
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fd7e14;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .review-pin.mine .review-pin-badge {
            background: #0d6efd;
        }

        .review-pin .review-pin-text {
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .review-pin-form {
            position: absolute;
            z-index: 20;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .2);
            padding: .5rem;
            width: 260px;
        }

        .review-annotation-list .list-group-item.active-pin {
            background: #fff3cd;
        }
    </style>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-4 col-xxl-3">
            <div class="card">
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">Document : <b class="float-end">{{ $file->Name }}</b></li>
                        <li class="list-group-item">Requested By : <b class="float-end">{{ $review->creator->Name ?? '' }}</b></li>
                        <li class="list-group-item">Reviewer : <b class="float-end">{{ $review->reviewerName() }}</b></li>
                        <li class="list-group-item">Status : <b class="float-end"><span class="badge {{ $review->Status->badge() }}">{{ $review->Status->description() }}</span></b></li>
                        <li class="list-group-item">Requested On : <b class="float-end">{{ $review->CreatedOn?->format('d M Y, H:i') }}</b></li>
                    </ul>
                </div>
                @if($review->RequestNote)
                    <div class="card-body border-top">
                        <b>Request note</b>
                        <p class="mb-0">{{ $review->RequestNote }}</p>
                    </div>
                @endif
                @if($review->isReviewed())
                    <div class="card-body border-top">
                        <b class="text-success"><i class="fas fa-check-circle"></i> Reviewed</b>
                        <p class="mb-1 text-muted">{{ $review->ReviewedOn?->format('d M Y, H:i') }}</p>
                        @if($review->ReviewComment)
                            <p class="mb-0">{{ $review->ReviewComment }}</p>
                        @endif
                    </div>
                @elseif($review->isReviewer(auth()->user()))
                    <div class="card-footer">
                        <form id="markReviewedForm" action="{{ route('dms.review.update', $review->Id) }}" method="post">
                            @csrf
                            @method('put')
                            <div class="mb-2">
                                <label class="form-label" for="ReviewComment">Final comment</label>
                                <textarea class="form-control" id="ReviewComment" name="ReviewComment" rows="2"
                                          maxlength="2000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100" id="markReviewedBtn">
                                <i class="fas fa-check"></i> mark as reviewed
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header py-3">
                    <h5 class="mb-0">Annotations (<span id="annotationCount">{{ $review->annotations->count() }}</span>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush review-annotation-list" id="annotationList"
                         style="max-height:50vh; overflow-y:auto;">
                        @forelse($review->annotations as $annotation)
                            <div class="list-group-item" data-annotation-id="{{ $annotation->Id }}" data-page="{{ $annotation->Page }}">
                                <div class="d-flex justify-content-between">
                                    <b>page {{ $annotation->Page }}</b>
                                    <small class="text-muted">{{ $annotation->CreatedOn?->format('d M, H:i') }}</small>
                                </div>
                                <div class="text-muted small mb-1">{{ $annotation->creator->Name ?? 'Unknown' }}</div>
                                <div>{{ $annotation->Comment }}</div>
                            </div>
                        @empty
                            <div class="list-group-item text-muted text-center" id="annotationListEmpty">
                                No annotations yet. Use <b>Add Comment</b> to pin one on the document.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @php $canParticipate = auth()->user()->can('participate', $review); @endphp
        <div class="col-md-8 col-xxl-9">
            <div class="card">
                @if($previewUrl && $previewKind)
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small" id="placingHint">
                            @if($canParticipate)
                                Click <b>Add Comment</b>, then click anywhere on the document to pin it there.
                            @else
                                <i class="fas fa-lock"></i> Only <b>{{ $review->creator->Name ?? 'the requester' }}</b> (requester) and
                                <b>{{ $review->reviewerName() ?: 'the reviewer' }}</b> (reviewer) can add comments here.
                            @endif
                        </span>
                        @if($canParticipate)
                            <button type="button" class="btn btn-primary btn-sm" id="addAnnotationBtn">
                                <i class="fas fa-comment-medical"></i> Add Comment
                            </button>
                        @endif
                    </div>
                @endif
                <div class="card-body">
                    @if($previewUrl && $previewKind)
                        <div id="reviewViewer" data-kind="{{ $previewKind }}" data-src="{{ $previewUrl }}"
                             data-store-url="{{ route('dms.review.annotations.store', $review->Id) }}"
                             data-can-participate="{{ $canParticipate ? '1' : '0' }}"></div>
                        <div id="reviewPages" style="min-height: 100px;">
                            <p class="text-center m-5"><i class="fas fa-spinner fa-spin fa-3x"></i><br>loading preview</p>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <h4 class="mb-1">No Preview Available</h4>
                            <p class="text-muted mb-0">This file type cannot be annotated inline.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    @php
        // Computed here rather than inline inside @json() below: Blade's @json() directive
        // splits its argument on every comma to separate the value/options/depth arguments,
        // which corrupts any multi-key array literal passed directly to it.
        $initialAnnotationsData = $review->annotations->map(fn ($a) => [
            'Id' => $a->Id,
            'Page' => $a->Page,
            'X' => (float) $a->X,
            'Y' => (float) $a->Y,
            'Comment' => $a->Comment,
            'Author' => $a->creator->Name ?? 'Unknown',
            'CreatedOn' => optional($a->CreatedOn)->format('d M Y, H:i'),
            'IsMine' => $a->CreatedBy === auth()->id(),
        ]);
    @endphp
    <script>
        $(function () {
            const viewerEl = document.getElementById('reviewViewer');
            if (!viewerEl) {
                return;
            }

            const kind = viewerEl.dataset.kind;
            const src = viewerEl.dataset.src;
            const storeUrl = viewerEl.dataset.storeUrl;
            const canParticipate = viewerEl.dataset.canParticipate === '1';
            const container = document.getElementById('reviewPages');

            const initialAnnotations = @json($initialAnnotationsData);

            let pinSeq = 0;
            let placingMode = false;
            const pageShells = [];

            function annotationsForPage(page) {
                return initialAnnotations.filter(a => a.Page === page);
            }

            function setPlacingMode(on) {
                placingMode = on;
                pageShells.forEach(el => el.classList.toggle('placing-mode', on));
                const btn = document.getElementById('addAnnotationBtn');
                const hint = document.getElementById('placingHint');
                if (btn) {
                    btn.classList.toggle('btn-primary', !on);
                    btn.classList.toggle('btn-warning', on);
                    btn.innerHTML = on
                        ? '<i class="fas fa-times"></i> Cancel'
                        : '<i class="fas fa-comment-medical"></i> Add Comment';
                }
                if (hint) {
                    hint.innerHTML = on
                        ? '<b>Click anywhere on the document</b> to place your comment there.'
                        : 'Click <b>Add Comment</b>, then click anywhere on the document to pin it there.';
                }
            }

            const addAnnotationBtn = document.getElementById('addAnnotationBtn');
            if (addAnnotationBtn) {
                addAnnotationBtn.addEventListener('click', function () {
                    setPlacingMode(!placingMode);
                });
            }

            function addPinToPage(pageEl, annotation) {
                const layer = pageEl.querySelector('.review-pin-layer');
                const pin = document.createElement('div');
                pinSeq += 1;
                pin.className = 'review-pin' + (annotation.IsMine ? ' mine' : '');
                pin.style.left = (annotation.X * 100) + '%';
                pin.style.top = (annotation.Y * 100) + '%';
                pin.dataset.annotationId = annotation.Id;
                pin.title = annotation.Author + ' - ' + annotation.CreatedOn;

                const badge = document.createElement('span');
                badge.className = 'review-pin-badge';
                badge.textContent = pinSeq;

                const text = document.createElement('span');
                text.className = 'review-pin-text';
                text.textContent = annotation.Comment;

                pin.appendChild(badge);
                pin.appendChild(text);
                layer.appendChild(pin);

                pin.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const row = document.querySelector('.review-annotation-list [data-annotation-id="' + annotation.Id + '"]');
                    if (row) {
                        row.scrollIntoView({behavior: 'smooth', block: 'center'});
                        row.classList.add('active-pin');
                        setTimeout(() => row.classList.remove('active-pin'), 1500);
                    }
                });
            }

            function appendAnnotationToList(annotation) {
                const list = document.getElementById('annotationList');
                const empty = document.getElementById('annotationListEmpty');
                if (empty) {
                    empty.remove();
                }
                const item = document.createElement('div');
                item.className = 'list-group-item';
                item.dataset.annotationId = annotation.Id;
                item.dataset.page = annotation.Page;
                item.innerHTML = '<div class="d-flex justify-content-between">'
                    + '<b>page ' + annotation.Page + '</b>'
                    + '<small class="text-muted">' + annotation.CreatedOn + '</small>'
                    + '</div>'
                    + '<div class="text-muted small mb-1">' + annotation.Author + '</div>'
                    + '<div></div>';
                item.querySelector('div:last-child').textContent = annotation.Comment;
                list.appendChild(item);
                document.getElementById('annotationCount').textContent = list.querySelectorAll('[data-annotation-id]').length;
            }

            function openPinForm(pageEl, pageNum, x, y, clickX, clickY) {
                document.querySelectorAll('.review-pin-form').forEach(f => f.remove());
                const form = document.createElement('form');
                form.className = 'review-pin-form';
                form.setAttribute('action', storeUrl);
                form.setAttribute('method', 'post');
                form.style.left = Math.max(0, clickX - 10) + 'px';
                form.style.top = Math.max(0, clickY - 10) + 'px';
                form.innerHTML = '@csrf'
                    + '<input type="hidden" name="Page" value="' + pageNum + '">'
                    + '<input type="hidden" name="X" value="' + x + '">'
                    + '<input type="hidden" name="Y" value="' + y + '">'
                    + '<textarea name="Comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Add a comment..." required></textarea>'
                    + '<div class="d-flex justify-content-end gap-1">'
                    + '<button type="button" class="btn btn-secondary btn-sm cancel-pin">cancel</button>'
                    + '<button type="submit" class="btn btn-primary btn-sm">save</button>'
                    + '</div>';
                pageEl.appendChild(form);
                form.querySelector('textarea').focus();
                form.querySelector('.cancel-pin').addEventListener('click', () => form.remove());

                $(form).submit(async function (e) {
                    e.preventDefault();
                    const btn = $(form).find('button[type="submit"]');
                    const data = await saveForm($(form), btn, false, false, false);
                    if (data && data.data) {
                        addPinToPage(pageEl, data.data);
                        appendAnnotationToList(data.data);
                        form.remove();
                        setPlacingMode(false);
                    }
                });
            }

            function attachClickToPin(pageEl, pageNum) {
                if (!canParticipate) {
                    return;
                }
                pageEl.addEventListener('click', function (e) {
                    if (!placingMode) {
                        return;
                    }
                    if (e.target.closest('.review-pin') || e.target.closest('.review-pin-form')) {
                        return;
                    }
                    const rect = pageEl.getBoundingClientRect();
                    const x = ((e.clientX - rect.left) / rect.width).toFixed(4);
                    const y = ((e.clientY - rect.top) / rect.height).toFixed(4);
                    openPinForm(pageEl, pageNum, x, y, e.clientX - rect.left, e.clientY - rect.top);
                });
            }

            function buildPageShell(pageNum) {
                const pageEl = document.createElement('div');
                pageEl.className = 'review-page' + (placingMode ? ' placing-mode' : '');
                pageEl.dataset.page = pageNum;
                const layer = document.createElement('div');
                layer.className = 'review-pin-layer';
                pageEl.appendChild(layer);
                container.appendChild(pageEl);
                attachClickToPin(pageEl, pageNum);
                pageShells.push(pageEl);
                return pageEl;
            }

            function renderPinsForPage(pageEl, pageNum) {
                annotationsForPage(pageNum).forEach(a => addPinToPage(pageEl, a));
            }

            // Fit the page/canvas to the actual available width of its card, instead of a
            // fixed pixel scale, so the preview never overflows its container on narrower
            // screens or when the sidebar cards take up more of the row.
            function fittedWidth() {
                return Math.max(280, Math.min(container.clientWidth || 800, 900));
            }

            if (kind === 'image') {
                container.innerHTML = '';
                const pageEl = buildPageShell(1);
                const img = document.createElement('img');
                img.src = src;
                img.addEventListener('load', function () {
                    renderPinsForPage(pageEl, 1);
                });
                pageEl.insertBefore(img, pageEl.firstChild);
            } else if (kind === 'pdf') {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                pdfjsLib.getDocument(src).promise.then(async function (pdf) {
                    container.innerHTML = '';
                    const dpr = window.devicePixelRatio || 1;
                    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const unscaled = page.getViewport({scale: 1});
                        const displayWidth = fittedWidth();
                        const scale = (displayWidth / unscaled.width) * dpr;
                        const viewport = page.getViewport({scale});

                        const pageEl = buildPageShell(pageNum);
                        pageEl.style.width = (viewport.width / dpr) + 'px';
                        pageEl.style.height = (viewport.height / dpr) + 'px';

                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;
                        pageEl.insertBefore(canvas, pageEl.firstChild);
                        const ctx = canvas.getContext('2d');
                        await page.render({canvasContext: ctx, viewport}).promise;

                        renderPinsForPage(pageEl, pageNum);
                    }
                }).catch(function (err) {
                    container.innerHTML = '<div class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-3x"></i><p class="mt-2">Failed to load document preview.</p></div>';
                    console.error(err);
                });
            }

            document.getElementById('annotationList').addEventListener('click', function (e) {
                const row = e.target.closest('[data-annotation-id]');
                if (!row) {
                    return;
                }
                const pin = document.querySelector('.review-pin[data-annotation-id="' + row.dataset.annotationId + '"]');
                if (pin) {
                    pin.scrollIntoView({behavior: 'smooth', block: 'center'});
                    pin.style.outline = '3px solid #ffc107';
                    setTimeout(() => pin.style.outline = '', 1500);
                }
            });

            $('#markReviewedForm').submit(async function (e) {
                e.preventDefault();
                await saveForm($(this), $('#markReviewedBtn'), true, false, true);
            });
        });
    </script>
@endsection
