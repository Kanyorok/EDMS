{{-- Document Type & File Upload Partial --}}
{{-- Usage: @include('components.property-document-upload', ['inputName' => 'Document[]', 'required' => false, 'multiple' => true]) --}}

@php
    $inputName = $inputName ?? 'Document[]';
    $required = $required ?? false;
    $multiple = $multiple ?? true;
    $label = $label ?? 'Upload Documents';
@endphp

<div class="mb-3">
    <label class="form-label">Document Type @if($required)<span class="text-danger">*</span>@endif</label>
    <select name="DocumentType" class="form-select form-select-sm @error('DocumentType') is-invalid @enderror" {{ $required ? 'required' : '' }}>
        <option value="">-- Select Document Type --</option>
        @foreach($documentTypes as $dt)
            <option value="{{ $dt->ID }}" {{ old('DocumentType') == $dt->ID ? 'selected' : '' }}>{{ $dt->Description }}</option>
        @endforeach
    </select>
    @error('DocumentType') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
