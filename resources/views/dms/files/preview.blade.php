@php use App\Enums\Core\ExtensionsEnum; @endphp
<div class="w-100">
    @if($service->isPrevieable())
        @if($service->type->value === ExtensionsEnum::Pdf->value)
            <div>
                {!! $service->preview('width="100%" height="100" style="min-height:70vh;"') !!}
            </div>
        @elseif($service->type->value === ExtensionsEnum::Txt->value)
            <div>
                {!! $service->preview('width="100%" height="100" style="min-height:70vh;"') !!}
            </div>
        @elseif($service->type->isImage())
            {!! $service->preview('class="img img-fluid"') !!}
        @elseif($service->type->isDocument())
            <div>
                {!! $service->preview('width="100%" height="100" style="min-height:70vh;"') !!}
            </div>
        @endif

    @else
        <div class="text-center py-5">
            <img src="{{ $service->type->getIcon('img') }}" alt="{{ $service->type->name }}" class="wid-75 mb-3">
            <h4 class="mb-1">No Preview Available</h4>
            <p class="text-muted mb-4">This file type cannot be previewed inline. Download it to view its contents.</p>
            <form action="{{ route('file-download.store', [$file->DocumentId]) }}" method="post"
                  id="previewDownloadForm_{{ $file->Id }}"> @csrf
                <input type="hidden" name="fetch_link" value="{{ $file->Name }}">
                <button type="submit" class="btn btn-primary btn-lg" id="previewDownloadBtn_{{ $file->Id }}">
                    <i class="fas fa-file-download me-2"></i> Download {{ $file->Name }}
                </button>
            </form>
        </div>
        <script>
            (function () {
                var formId = 'previewDownloadForm_{{ $file->Id }}';
                var btnId = 'previewDownloadBtn_{{ $file->Id }}';
                function attach() {
                    $('#' + formId).on('submit', async function (e) {
                        e.preventDefault();
                        var data = await saveForm($(this), $('#' + btnId), false, true, true);
                        if (data) {
                            window.open(data.route, '_blank', 'noopener,noreferrer');
                        }
                    });
                }
                if (typeof $ !== 'undefined' && typeof saveForm !== 'undefined') {
                    attach();
                } else {
                    document.addEventListener('DOMContentLoaded', attach);
                }
            })();
        </script>
    @endif
</div>
