@extends('layouts.app')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
    @yield('breadcrumb')
@endsection
@section('search-form')
    <style>
        .tt-menu {
            width: 100% !important;
            padding: .5rem 1.5rem !important;
            opacity: 0.98;
        }

        .dropdown-item {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
    </style>
    @php
        // Paper files are only offered to users who may open the registry
        // workspace at all; the endpoint applies the same check, plus the
        // branch rule and each file's own visibility.
        $searchesPhysicalFiles = auth()->user()?->can(\App\Enums\Core\PermissionEnum::DMSView->value);
    @endphp
    <div class="form-search" action="" method="get">
        <i class="search-icon">
            <svg class="pc-icon">
                <use xlink:href="#custom-search-normal-1"></use>
            </svg>
        </i>
        <input type="search" name="q" class="form-control typeahead" id="SearchInput" style="width: 50vw;"
               placeholder="{{ $searchesPhysicalFiles ? 'Search files, folders & physical files' : 'Search Files & Folders' }}"
               data-url="{{ route('dms.search') }}"
               @if($searchesPhysicalFiles) data-physical-files="1" @endif>
    </div>
@endsection
@section('script')
    <script src="{{ asset('assets/libs/typeahead/typeahead.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/search/dms.js') }}"></script>
@endsection
