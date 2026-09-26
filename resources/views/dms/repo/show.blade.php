@php use App\Enums\Core\ExtensionsEnum; @endphp
@php use App\Enums\Core\VisibilityEnum; @endphp
@extends('dms.layout')

@section('title', 'Repositories')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('repo.index') }}">DMS</a></li>
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/dropzone/dropzone.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .icon-size {
            height: 30px !important;
            width: 30px !important;
        }

        #uploadCard .select2-container, #RepositoryPickerWrap .select2-container {
            width: 100% !important;
        }

        .repo-tabs .nav-link {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .repo-tabs .tab-close {
            cursor: pointer;
            color: #999;
            font-size: 1.1rem;
            line-height: 1;
        }

        .repo-tabs .tab-close:hover {
            color: #dc3545;
        }

        .toolbar-btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .repo-tree-row {
            padding: 2px 0;
        }

        .repo-tree-toggle {
            width: 16px;
            display: inline-flex;
            justify-content: center;
            color: #999;
        }

        .repo-tree-label {
            color: inherit;
            text-decoration: none;
            padding: 2px 4px;
            border-radius: 4px;
        }

        .repo-tree-label:hover {
            background: rgba(0, 0, 0, .05);
        }

        .repo-tree-label.active {
            font-weight: 600;
            color: var(--bs-primary, #0d6efd);
            background: rgba(13, 110, 253, .08);
        }

        .repo-tree-children {
            margin-left: 18px;
            border-left: 1px dashed #ddd;
            padding-left: 6px;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12 file-manger-wrapper">

            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-light btn-sm toolbar-btn" id="toolbar-upload" title="Import">
                                <i class="fas fa-file-import"></i> Import
                            </button>
                            <button type="button" class="btn btn-light btn-sm toolbar-btn" id="toolbar-scan" title="Scan - not available yet" disabled>
                                <i class="fas fa-camera"></i> Scan
                            </button>
                            <button type="button" class="btn btn-light btn-sm toolbar-btn create-new-repository" title="New repository">
                                <i class="fas fa-folder-plus"></i> New repository
                            </button>
                            <div class="btn-group btn-group-sm" role="group" aria-label="View">
                                <button type="button" class="btn btn-outline-secondary view-toggle active" data-view="list" title="List view">
                                    <i class="fas fa-list"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary view-toggle" data-view="grid" title="Grid view">
                                    <i class="fas fa-th-large"></i>
                                </button>
                            </div>
                            <button type="button" class="btn btn-light btn-sm toolbar-btn" id="toolbar-store" title="Store - not available yet" disabled>
                                <i class="fas fa-save"></i> Store
                            </button>
                        </div>
                        <div id="RepositoryPickerWrap" style="min-width: 280px;">
                            <select class="form-control" id="RepositoryPicker">
                                <option value="">Repositories — select to open</option>
                                @foreach($allRepositories as $repo)
                                    <option value="{{ $repo->RepositoryId }}" data-name="{{ $repo->Name }}">{{ $repo->Name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs repo-tabs mb-3" id="RepoTabs"></ul>

            <div class="row">
                <div class="col-lg-3 mb-3">
                    <div class="card mb-3">
                        <div class="card-header py-2"><strong>Repositories</strong></div>
                        <div class="card-body p-2" style="max-height: 320px; overflow-y: auto;">
                            <div id="RepoTree"></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header py-2"><strong>Filter files</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label mb-1" for="FilterLinkedToTypeId">Linked to</label>
                                <select class="form-control form-control-sm" id="FilterLinkedToTypeId">
                                    <option value="">Any</option>
                                    @foreach($linkedToTypes as $type)
                                        <option value="{{ $type->Id }}">{{ $type->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label mb-1" for="FilterTagId">Record classification</label>
                                <select class="form-control form-control-sm" id="FilterTagId">
                                    <option value="">Any</option>
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag->Id }}">{{ $tag->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label mb-1">Expiry date range</label>
                                <div class="d-flex gap-1">
                                    <input type="date" class="form-control form-control-sm" id="FilterExpiryFrom">
                                    <input type="date" class="form-control form-control-sm" id="FilterExpiryTo">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label mb-1" for="FilterEntityApplicability">Entity applicability</label>
                                <select class="form-control form-control-sm" id="FilterEntityApplicability">
                                    <option value="">Any</option>
                                    @foreach($entityApplicabilities as $entity)
                                        <option value="{{ $entity }}">{{ $entity }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="FilterIncludeArchived">
                                <label class="form-check-label" for="FilterIncludeArchived">Include archived</label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="ApplyFilters">Apply</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="ClearFilters">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="row my-2 align-items-center">
                        <div class="col">
                            <h5 class="mb-0" id="ActiveRepoLabel">{{ $repository->Name }}</h5>
                        </div>
                    </div>

                    <div id="repositoriesSection">
                        <div class="row" style="min-height: 10vh;" id="repositoriesContents"
                             data-url="{{ route('repo.show',[$repository->RepositoryId]) }}"></div>
                        <div class="d-grid text-center" id="repositoriesMessage"></div>

                        <hr class="my-3 border border-secondary-subtle">
                    </div>

                    <div class="row my-3">
                        <div class="col">
                            <div class="d-flex align-items-center"><h5 class="mb-0 me-2">Files</h5></div>
                        </div>
                    </div>

                    <div class="card d-none" id="uploadCard">
                        <div class="card-header pb-1 ">
                            <h6 class="card-title">Upload Files
                                <span class="float-end" style="cursor: pointer;" id="uploadCardClose"><i
                                        class="fas fa-times"></i></span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="LinkedToTypeId">Linked to <span class="text-danger">*</span></label>
                                    <select class="form-control" id="LinkedToTypeId" disabled>
                                        <option value="">Select a document type first</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block">Record <span class="text-danger">*</span></label>
                                    <button type="button" class="btn btn-outline-secondary btn-sm link-record-btn">
                                        <i class="fas fa-link"></i> Link Client, Lead or Employee
                                    </button>
                                    <div class="row g-2 align-items-end link-record-row d-none">
                                        <div class="col-md-4">
                                            <label class="form-label mb-1">Related to</label>
                                            <select class="form-control relation-type" id="Related">
                                                <option value="">— choose type —</option>
                                                @foreach(\App\Http\Controllers\DMS\Files\DocumentRelationOptionsController::RELATION_TYPES as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="form-label mb-1">Record</label>
                                            <select class="form-control relation-target" id="LinkedId"></select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger remove-link-record"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="DocumentTypeId">Document type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="DocumentTypeId">
                                        <option value="">Select document type</option>
                                        @foreach($documentTypes as $type)
                                            <option value="{{ $type->Id }}"
                                                    data-repository="{{ $type->repository->RepositoryId ?? $root->RepositoryId }}"
                                                    data-category="{{ $type->repository->Name ?? $root->Name }}"
                                                    data-tag="{{ $type->tag->Name ?? '' }}"
                                                    data-extensions="{{ $type->AllowedExtensions }}"
                                                    data-max-size="{{ $type->MaxSizeMB }}"
                                                    data-expiry-required="{{ $type->ExpiryRequired ? '1' : '0' }}">{{ $type->Code }} - {{ $type->Name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-primary d-none" id="DocumentTypeParams"></small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block">Folio (optional)</label>
                                    <div class="btn-group btn-group-sm mb-2" role="group" id="FolioMode">
                                        <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeNone" value="none" checked>
                                        <label class="btn btn-outline-secondary" for="FolioModeNone">Standalone</label>
                                        <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeNew" value="new">
                                        <label class="btn btn-outline-secondary" for="FolioModeNew">New case</label>
                                        <input type="radio" class="btn-check" name="FolioModeOption" id="FolioModeExisting" value="existing">
                                        <label class="btn btn-outline-secondary" for="FolioModeExisting">Existing case</label>
                                    </div>
                                    <input type="text" class="form-control d-none" id="FolioCode" readonly placeholder="Select a document type to generate a case reference">
                                    <select class="form-control d-none" id="FolioSelect"></select>
                                    <input type="hidden" id="FolioId" value="">
                                </div>
                            </div>
                            <div class="row expiry-row d-none">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="ExpiryDate">Expiry date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="ExpiryDate">
                                    <small class="text-muted">This document type requires an expiry date.</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="Notes">Notes (optional)</label>
                                <textarea class="form-control" id="Notes" rows="2" maxlength="2000"></textarea>
                            </div>
                        </div>
                        <div class="card-body p-0 border border-top">
                            <form action="{{ route('files.store',[$repository->RepositoryId]) }}" class="dropzone"
                                  id="upload-form">@csrf</form>
                            <div class="upload-controls mt-3 mx-3">
                                <button id="cancel-all-btn" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-times"></i> Cancel All
                                </button>
                                <button id="remove-all-btn" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-trash"></i> Remove All
                                </button>
                                <span id="file-count" class="badge badge-info ml-2">0 files</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive card bg-transparent border-0 shadow-none" style="min-height: 30vh" id="fileListView">
                        <table class="table table-borderless file-card">
                            <tbody id="fileContents" data-url="{{ route('files.index',[$repository->RepositoryId]) }}"></tbody>
                        </table>
                    </div>
                    <div class="row d-none" id="fileGridView"></div>
                    <div class="d-grid text-center" id="filesMessage"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="dmsActionsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">..</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="onboarding-content with-gradient d-none modal-item" id="createRepositoryModal">
                        <form action="{{ route('repo.store') }}" method="post"
                              id="createRepositoryForm">
                            @csrf
                            <input type="hidden" class="d-none" id="repository_parent" name="repository_parent"
                                   value="{{ $repository->RepositoryId }}">
                            <p id="repository_parent_error" class="invalid-feedback d-none error col-12"
                               role="alert"></p>
                            <div class="mb-3">
                                <label class="form-label" for="repository_name">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="repository_name" name="repository_name"
                                       placeholder="{{ __('common.name') }}">
                                <p id="repository_name_error" class="invalid-feedback d-none error col-12"
                                   role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="repository_description">{{ __('common.description') }}</label>
                                <textarea name="repository_description" id="repository_description" rows="3"
                                          class="form-control"></textarea>
                                <p id="repository_description_error" class="invalid-feedback d-none error col-12"
                                   role="alert"></p>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="createRepositoryBtn" type="submit">
                                    <i class="fas fa-save"></i> create folder
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item" id="updateRepositoryModal">
                        <form method="post" id="updateRepositoryForm">
                            @csrf
                            <div class="mb-3">@method('PUT')
                                <label class="form-label" for="e_repository_name">Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="e_repository_name" name="repository_name"
                                       placeholder="{{ __('common.name') }}">
                                <p id="e_repository_name_error" class="invalid-feedback d-none error col-12"
                                   role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="e_repository_description">{{ __('common.description') }}</label>
                                <textarea name="repository_description" id="e_repository_description" rows="3"
                                          class="form-control"></textarea>
                                <p id="e_repository_description_error" class="invalid-feedback d-none error col-12"
                                   role="alert"></p>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="updateRepositoryBtn" type="submit">
                                    <i class="fas fa-save"></i> rename
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item text-center"
                         id="trashRepositoryModal">
                        <h4 class="text-danger">
                            Trash Repository <b class="rm-repo-name"></b> ?
                        </h4>
                        <div class="alert alert-warning" role="alert">
                            <b>Note</b> by continuing, this will delete all the files (documents) and folders inside
                        </div>
                        <form id="trashRepositoryForm" method="post"> @csrf
                            <div class="mt-4">@method('delete')
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-danger float-end" id="trashRepositoryBtn"
                                        type="submit"><i
                                        class="fas fa-trash"></i> yes, trash
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item text-center" id="trashFileModal">
                        <h4 class="text-danger">
                            Trash Document <b class="rm-file-name"></b> ?
                        </h4>
                        <div class="alert alert-warning" role="alert">
                            <b>Note</b>This file will be deleted permanently
                        </div>
                        <form id="trashFileForm" method="post"> @csrf
                            <div class="mt-4">@method('delete')
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-danger float-end" id="trashFileBtn"
                                        type="submit"><i
                                        class="fas fa-trash"></i> yes, delete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="{{ asset('assets/libs/dropzone/dropzone.min.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script>const $Modal = $('#dmsActionsModal');
        let dropzoneInstance;
        let activeRepoCode = '{{ $repository->RepositoryId }}';
        let activeRepoName = '{{ $repository->Name }}';
        let openTabs = [{code: activeRepoCode, name: activeRepoName}];
        const rootRepositoryCode = '{{ $rootRepositoryCode }}';
        const filesStoreUrlTemplate = '{{ route('files.store', ['__REPO__']) }}';
        const repoShowUrlTemplate = '{{ route('repo.show', ['__REPO__']) }}';
        const filesIndexUrlTemplate = '{{ route('files.index', ['__REPO__']) }}';
        const folioCreateUrlTemplate = '{{ route('document-types.folios.store', ['documentType' => '__ID__']) }}';
        const linkedToTypesUrlTemplate = '{{ route('document-types.linked-to-types', ['documentType' => '__ID__']) }}';
        const folioOptionsUrl = '{{ route('dms.folios.options') }}';
        const documentRelationOptionsUrl = '{{ route('dms.document-relations.options') }}';
        const defaultMaxFilesize = parseInt('{{ config('app.dms.file_size') }}');
        const allRepositoriesData = {!! $allRepositories->map(fn ($r) => ['Id' => $r->Id, 'RepositoryId' => $r->RepositoryId, 'Name' => $r->Name, 'ParentId' => $r->ParentId])->toJson() !!};

        /**
         * The picker in the toolbar lists every repository flat - fine for
         * jumping straight to one by name, but it can't show which folders
         * live inside which. This renders the same data as an actual tree
         * so nested repositories are discoverable, entirely client-side
         * since the full list is already loaded.
         */
        function buildRepoTree() {
            const byParent = {};
            allRepositoriesData.forEach(function (repo) {
                const key = repo.ParentId === null ? 'root' : repo.ParentId;
                byParent[key] = byParent[key] || [];
                byParent[key].push(repo);
            });

            function renderNode(repo) {
                const children = byParent[repo.Id] || [];
                const hasChildren = children.length > 0;
                const $node = $('<div class="repo-tree-node"></div>');
                const $row = $('<div class="d-flex align-items-center repo-tree-row"></div>');
                const $toggle = $('<span class="repo-tree-toggle"></span>');

                if (hasChildren) {
                    $toggle.html('<i class="fas fa-caret-right"></i>').css('cursor', 'pointer');
                } else {
                    $toggle.html('&nbsp;');
                }

                const $label = $('<a href="javascript:void(0)" class="repo-tree-label"></a>')
                    .text(repo.Name)
                    .attr('data-code', repo.RepositoryId)
                    .attr('data-name', repo.Name);

                $row.append($toggle, $label);
                $node.append($row);

                if (hasChildren) {
                    const $children = $('<div class="repo-tree-children d-none"></div>');
                    children.forEach(function (child) {
                        $children.append(renderNode(child));
                    });
                    $node.append($children);

                    $toggle.on('click', function () {
                        $children.toggleClass('d-none');
                        $toggle.find('i').toggleClass('fa-caret-right fa-caret-down');
                    });
                }

                return $node;
            }

            const $tree = $('#RepoTree').empty();
            (byParent['root'] || []).forEach(function (repo) {
                $tree.append(renderNode(repo));
            });
        }

        /**
         * Expands every ancestor of the given repository so its node is
         * visible, and marks it active - keeps the tree in sync when a tab
         * is opened some other way (the picker, a folder card, a new tab).
         */
        function highlightRepoInTree(code) {
            $('.repo-tree-label').removeClass('active');
            const $label = $('.repo-tree-label[data-code="' + code + '"]').addClass('active');

            let repo = allRepositoriesData.find(r => r.RepositoryId === code);
            while (repo && repo.ParentId !== null) {
                const parent = allRepositoriesData.find(r => r.Id === repo.ParentId);
                if (!parent) {
                    break;
                }
                const $parentLabel = $('.repo-tree-label[data-code="' + parent.RepositoryId + '"]');
                const $parentNode = $parentLabel.closest('.repo-tree-node');
                $parentNode.children('.repo-tree-children').removeClass('d-none');
                $parentNode.children('.repo-tree-row').find('.repo-tree-toggle i').removeClass('fa-caret-right').addClass('fa-caret-down');
                repo = parent;
            }

            if ($label.length) {
                $label[0].scrollIntoView({block: 'nearest'});
            }
        }

        function initialiseRelationSelect() {
            const $target = $('#LinkedId');

            $target.select2({
                dropdownParent: $('#uploadCard'),
                placeholder: 'Search by name or number',
                minimumInputLength: 2,
                ajax: {
                    url: documentRelationOptionsUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({type: $('#Related').val(), q: params.term}),
                    processResults: data => data
                }
            });

            $('#Related').on('change', function () {
                $target.val(null).trigger('change');
            });
        }

        function initialiseFolioSelect() {
            $('#FolioSelect').select2({
                dropdownParent: $('#uploadCard'),
                placeholder: 'Search for an existing case by code or name',
                minimumInputLength: 2,
                ajax: {
                    url: folioOptionsUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => ({q: params.term}),
                    processResults: data => data
                }
            }).on('change', function () {
                $('#FolioId').val($(this).val() || '');
            });
        }

        /**
         * Select2 reads its option list straight off the underlying <select>
         * each time it opens, but a disabled/enabled toggle or a swapped
         * option list needs a fresh init to render correctly - simplest
         * reliable way is destroy, change the <select>, reinit.
         */
        function reinitSelect2($select, config) {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2(config);
        }

        function setLinkedToTypeOptions(html, disabled, placeholder) {
            const $select = $('#LinkedToTypeId');
            $select.html(html).prop('disabled', disabled);
            reinitSelect2($select, {dropdownParent: $('#uploadCard'), placeholder: placeholder, allowClear: true, width: '100%'});
        }

        function refreshLinkedToTypes() {
            const documentTypeId = $('#DocumentTypeId').val();
            const previous = $('#LinkedToTypeId').val();

            $('#Related').val('').trigger('change');
            $('.link-record-row').addClass('d-none');
            $('.link-record-btn').removeClass('d-none');

            if (!documentTypeId) {
                setLinkedToTypeOptions('<option></option>', true, 'Select a document type first');
                return;
            }

            setLinkedToTypeOptions('<option></option>', true, 'Loading…');

            $.get(linkedToTypesUrlTemplate.replace('__ID__', documentTypeId), function (res) {
                let options = '<option></option>';
                res.data.forEach(type => {
                    options += '<option value="' + type.Id + '" data-source-relation="' + (type.SourceRelation || '') + '">' + type.Name + '</option>';
                });
                setLinkedToTypeOptions(options, false, 'Select linked-to type');
                if (previous && $('#LinkedToTypeId').find('option[value="' + previous + '"]').length) {
                    $('#LinkedToTypeId').val(previous).trigger('change');
                }
            });
        }

        function applySourceRelation() {
            const sourceRelation = $('#LinkedToTypeId').find(':selected').data('source-relation') || '';

            if (!sourceRelation) {
                $('#Related').prop('disabled', false);
                return;
            }

            $('.link-record-btn').addClass('d-none');
            $('.link-record-row').removeClass('d-none');
            $('#Related').val(sourceRelation).prop('disabled', true).trigger('change');
        }

        function expiryRequired() {
            return $('#DocumentTypeId').find(':selected').data('expiry-required') == '1';
        }

        function uploadRules() {
            const mode = $('#FolioMode input:checked').val();
            const folioId = $('#FolioId').val();
            return {
                LinkedToTypeId: $('#LinkedToTypeId').val(),
                Related: $('#Related').val(),
                LinkedId: $('#LinkedId').val(),
                DocumentTypeId: $('#DocumentTypeId').val(),
                FolioId: (mode !== 'none' && folioId && folioId !== 'null' && !isNaN(folioId)) ? folioId : null,
                ExpiryDate: $('#ExpiryDate').val(),
                Notes: $.trim($('#Notes').val()),
            };
        }

        function missingUploadFields() {
            const rules = uploadRules();
            const missing = [];
            if (!rules.LinkedToTypeId) missing.push('Linked to');
            if (!rules.Related || !rules.LinkedId) missing.push('Record (click "Link Client, Lead or Employee")');
            if (!rules.DocumentTypeId) missing.push('Document type');
            if (expiryRequired() && !rules.ExpiryDate) missing.push('Expiry date');
            if ($('#FolioMode input:checked').val() !== 'none' && !rules.FolioId) missing.push('Folio (case)');
            return missing;
        }

        function isReady() {
            return missingUploadFields().length === 0;
        }

        function reevaluateDropzoneFiles() {
            if (!dropzoneInstance) return;
            if (isReady()) {
                dropzoneInstance.files.forEach(file => {
                    if (file.status === Dropzone.REJECTED || file.previewElement?.classList.contains('dz-error')) {
                        file.status = Dropzone.QUEUED;
                        file.accepted = true;
                        file.previewElement?.classList.remove('dz-error', 'dz-complete');
                        const errMsg = file.previewElement?.querySelector('.dz-error-message');
                        if (errMsg) errMsg.remove();
                    }
                });
                if (dropzoneInstance.submitButton) {
                    dropzoneInstance.submitButton.disabled = false;
                }
            }
        }

        function refreshFolio() {
            const mode = $('#FolioMode input:checked').val();
            const documentTypeId = $('#DocumentTypeId').val();

            if (mode !== 'new' || !documentTypeId) {
                return;
            }
            $('#FolioId').val('');
            $('#FolioCode').val('generating…');
            $.post(folioCreateUrlTemplate.replace('__ID__', documentTypeId), {_token: '{{ csrf_token() }}'}, function (res) {
                $('#FolioId').val(res.data.id);
                $('#FolioCode').val(res.data.code);
            });
        }

        let documentTypeOptionsHtml = '';

        /**
         * Only the document types that file into the active tab's own
         * repository make sense to offer there - the rest would silently
         * upload somewhere the user isn't looking at.
         */
        function filterDocumentTypesForActiveRepo() {
            const $select = $('#DocumentTypeId');
            const currentVal = $select.val();

            $select.html(documentTypeOptionsHtml);
            $select.find('option[data-repository]').each(function () {
                if ($(this).attr('data-repository') !== activeRepoCode) {
                    $(this).remove();
                }
            });
            reinitSelect2($select, {dropdownParent: $('#uploadCard'), placeholder: 'Select document type', allowClear: true, width: '100%'});

            if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
                $select.val(currentVal);
            } else {
                $select.val('').trigger('change');
            }
        }

        function updateUploadTarget() {
            const docTypeRepo = $('#DocumentTypeId').find(':selected').data('repository');
            const action = filesStoreUrlTemplate.replace('__REPO__', docTypeRepo || activeRepoCode);
            $('#upload-form').attr('action', action);
            if (dropzoneInstance && dropzoneInstance.options) {
                dropzoneInstance.options.url = action;
            }
        }

        Dropzone.options.uploadForm = {
            autoProcessQueue: false, // Fill in the form, attach files, then click Upload
            url: filesStoreUrlTemplate.replace('__REPO__', activeRepoCode),
            maxFilesize: defaultMaxFilesize,//Mb
            maxFiles: 30,
            parallelUploads: 3,
            uploadMultiple: false,
            addRemoveLinks: true,
            dictRemoveFile: "Remove",
            dictCancelUpload: "Cancel",
            acceptedFiles: "{{ implode(", ",ExtensionsEnum::getAllMimeTypes()) }}",
            dictDefaultMessage: `
                <div class="dropzone-msg dz-message needsclick">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                    <h3>Drop files here or click to upload.</h3>
                    <span class="note needsclick">Fill in Linked to, the linked record and Document type above first - they decide what's accepted and where files are filed.</span>
                </div>`,
            params: function () {
                return uploadRules();
            },
            accept: function (file, done) {
                const missing = missingUploadFields();
                if (missing.length) {
                    done('Missing: ' + missing.join(', '));
                    return;
                }
                done();
            },
            init: function () {
                dropzoneInstance = this;

                const submitButton = document.createElement("button");
                submitButton.id = "upload-all-btn";
                submitButton.className = "btn btn-primary float-end";
                submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';
                submitButton.disabled = true;
                $('.upload-controls').append(submitButton);
                this.submitButton = submitButton;

                submitButton.addEventListener("click", () => {
                    const missing = missingUploadFields();
                    if (missing.length) {
                        nWarning('Missing before uploading: ' + missing.join(', ') + '.');
                        return;
                    }
                    this.processQueue();
                });

                this.on("addedfile", () => {
                    if (this.files.length > 0) {
                        this.submitButton.disabled = false;
                        this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                    }
                });

                this.on("removedfile", () => {
                    if (this.files.length === 0) {
                        this.submitButton.disabled = true;
                        this.submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';
                    } else {
                        this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                    }
                });

                this.on("sendingmultiple", () => {
                    $('.upload-controls').addClass('d-none');
                    this.submitButton.disabled = true;
                    this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                });

                this.on("successmultiple", (files, response) => {
                    nSuccess(response.message || 'All files uploaded successfully!');
                    appendFiles(response.data, true);
                    this.submitButton.disabled = true;
                    this.submitButton.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Upload All Files';
                    this.removeAllFiles(true);
                });

                this.on("errormultiple", () => {
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                });

                this.on("complete", (file) => {
                    if (!file.previewElement.classList.contains("dz-error")) {
                        setTimeout(() => this.removeFile(file), 2000);
                    }
                    if (dropzoneInstance.getQueuedFiles().length > 0) {
                        setTimeout(() => dropzoneInstance.processQueue(), 100);
                    }
                });

                this.on("queuecomplete", () => {
                    $('.upload-controls').removeClass('d-none');
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Upload ${this.files.length} File(s)`;
                });

                this.on("addedfile", updateFileCount);
                this.on("removedfile", updateFileCount);
                this.on("queuecomplete", updateFileCount);
            },
            success: function (file, response) {
                file.previewElement.classList.add("dz-success");
                appendFiles(response.data, true);
            },
            error: function (file, message) {
                msg = (typeof message === 'string') ? message : message.message

                nWarning(msg + ' : ' + file.name);
                file.previewElement.classList.add("dz-error");
            },
        };

        function updateFileCount() {
            const badge = document.getElementById('file-count');
            if (badge && dropzoneInstance) {
                const fileCount = dropzoneInstance.files.length;
                badge.textContent = fileCount + ' file' + (fileCount !== 1 ? 's' : '');
            }
        }

        // ---- Tabs ----

        function renderTabs() {
            const $tabs = $('#RepoTabs').empty();
            openTabs.forEach(function (tab) {
                const isActive = tab.code === activeRepoCode;
                const $li = $('<li class="nav-item"></li>');
                const $link = $('<a href="javascript:void(0)" class="nav-link repo-tab"></a>')
                    .toggleClass('active', isActive)
                    .attr('data-code', tab.code)
                    .append($('<span></span>').text(tab.name));

                if (openTabs.length > 1) {
                    $link.append($('<span class="tab-close" title="Close">&times;</span>').attr('data-code', tab.code));
                }

                $li.append($link);
                $tabs.append($li);
            });
        }

        function buildFilesUrl(code) {
            const query = $.param(filterParams());
            return filesIndexUrlTemplate.replace('__REPO__', code) + (query ? '?' + query : '');
        }

        function filterParams() {
            const params = {};
            const linkedToTypeId = $('#FilterLinkedToTypeId').val();
            const tagId = $('#FilterTagId').val();
            const expiryFrom = $('#FilterExpiryFrom').val();
            const expiryTo = $('#FilterExpiryTo').val();
            const entity = $.trim($('#FilterEntityApplicability').val() || '');
            const includeArchived = $('#FilterIncludeArchived').is(':checked');

            if (linkedToTypeId) params.LinkedToTypeId = linkedToTypeId;
            if (tagId) params.TagId = tagId;
            if (expiryFrom) params.ExpiryFrom = expiryFrom;
            if (expiryTo) params.ExpiryTo = expiryTo;
            if (entity) params.EntityApplicability = entity;
            if (includeArchived) params.IncludeArchived = 1;

            return params;
        }

        function switchToTab(code, name) {
            activeRepoCode = code;
            activeRepoName = name;
            $('#ActiveRepoLabel').text(name);
            $('#repository_parent').val(code);

            if (code === rootRepositoryCode) {
                $('#repositoriesSection').removeClass('d-none');
                $('#repositoriesContents').empty().data('url', repoShowUrlTemplate.replace('__REPO__', code));
                $('#repositoriesMessage').html('');
                fetchRepositories();
            } else {
                $('#repositoriesSection').addClass('d-none');
                $('#repositoriesContents').empty().data('url', null);
                $('#repositoriesMessage').html('');
            }

            $('#fileContents').empty();
            $('#fileGridView').empty();
            $('#fileContents').data('url', buildFilesUrl(code));
            $('#filesMessage').html('');
            fetchFiles();

            filterDocumentTypesForActiveRepo();
            updateUploadTarget();
            renderTabs();
            highlightRepoInTree(code);
        }

        function openRepoTab(code, name) {
            if (!openTabs.find(t => t.code === code)) {
                openTabs.push({code: code, name: name});
            }
            switchToTab(code, name);
        }

        function closeTab(code) {
            if (openTabs.length <= 1) {
                return;
            }
            const wasActive = code === activeRepoCode;
            openTabs = openTabs.filter(t => t.code !== code);

            if (wasActive) {
                const next = openTabs[openTabs.length - 1];
                switchToTab(next.code, next.name);
            } else {
                renderTabs();
            }
        }

        $(function () {
            documentTypeOptionsHtml = $('#DocumentTypeId').html();
            initialiseRelationSelect();
            initialiseFolioSelect();
            reinitSelect2($('#DocumentTypeId'), {dropdownParent: $('#uploadCard'), placeholder: 'Select document type', allowClear: true, width: '100%'});
            setLinkedToTypeOptions('<option></option>', true, 'Select a document type first');
            renderTabs();
            buildRepoTree();

            $(document).on('click', '.repo-tree-label', function () {
                openRepoTab($(this).data('code'), $(this).data('name'));
            });

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            $('#ExpiryDate').attr('min', tomorrow.toISOString().slice(0, 10));

            $('#RepositoryPicker').select2({placeholder: 'Repositories — select to open'});
            $('#FilterEntityApplicability').select2({placeholder: 'Any', allowClear: true, width: '100%'});
            $('#FilterLinkedToTypeId').select2({placeholder: 'Any', allowClear: true, width: '100%'});
            $('#FilterTagId').select2({placeholder: 'Any', allowClear: true, width: '100%'});
            $('#RepositoryPicker').on('change', function () {
                const code = $(this).val();
                if (!code) {
                    return;
                }
                const name = $(this).find(':selected').data('name');
                openRepoTab(code, name);
                $(this).val('').trigger('change.select2');
            });

            $(document).on('click', '.repo-tab', function () {
                const tab = openTabs.find(t => t.code === $(this).data('code'));
                if (tab) {
                    switchToTab(tab.code, tab.name);
                }
            });

            $(document).on('click', '.tab-close', function (e) {
                e.stopPropagation();
                closeTab($(this).data('code'));
            });

            $('#ApplyFilters').on('click', function () {
                switchToTab(activeRepoCode, activeRepoName);
            });

            $('#ClearFilters').on('click', function () {
                $('#FilterLinkedToTypeId').val('');
                $('#FilterTagId').val('');
                $('#FilterExpiryFrom').val('');
                $('#FilterExpiryTo').val('');
                $('#FilterEntityApplicability').val('');
                $('#FilterIncludeArchived').prop('checked', false);
                switchToTab(activeRepoCode, activeRepoName);
            });

            $('.view-toggle').on('click', function () {
                $('.view-toggle').removeClass('active');
                $(this).addClass('active');
                if ($(this).data('view') === 'grid') {
                    $('#fileListView').addClass('d-none');
                    $('#fileGridView').removeClass('d-none');
                } else {
                    $('#fileGridView').addClass('d-none');
                    $('#fileListView').removeClass('d-none');
                }
            });

            $(document).on('click', '#toolbar-upload', function () {
                $("#uploadCard").removeClass('d-none');
            });
            $(document).on('click', '#uploadCardClose', function () {
                $("#uploadCard").addClass('d-none');
            });

            document.getElementById('cancel-all-btn')?.addEventListener('click', function () {
                if (dropzoneInstance && confirm('Cancel all uploads?')) {
                    dropzoneInstance.removeAllFiles(true);
                }
            });

            document.getElementById('remove-all-btn')?.addEventListener('click', function () {
                if (dropzoneInstance && confirm('Remove all files?')) {
                    dropzoneInstance.removeAllFiles();
                }
            });

            $(document).on('click', '.link-record-btn', function () {
                $(this).addClass('d-none');
                $('.link-record-row').removeClass('d-none');
            });

            $(document).on('click', '.remove-link-record', function () {
                $('#Related').prop('disabled', false).val('').trigger('change');
                $('#LinkedId').val(null).trigger('change');
                $('.link-record-row').addClass('d-none');
                $('.link-record-btn').removeClass('d-none');
                applySourceRelation();
            });

            $(document).on('change', '#LinkedToTypeId, #LinkedId, #ExpiryDate', function () {
                reevaluateDropzoneFiles();
            });

            $(document).on('change', '#LinkedToTypeId', applySourceRelation);

            $(document).on('change', '#DocumentTypeId', function () {
                const $opt = $(this).find(':selected');
                const category = $opt.data('category') || '';
                const tag = $opt.data('tag') || '';
                const extensions = ($opt.data('extensions') || '').toString();
                const maxSize = parseFloat($opt.data('max-size')) || defaultMaxFilesize;

                updateUploadTarget();

                if (dropzoneInstance && dropzoneInstance.options) {
                    dropzoneInstance.options.acceptedFiles = extensions
                        ? extensions.split(',').map(ext => '.' + $.trim(ext)).join(',')
                        : null;
                    dropzoneInstance.options.maxFilesize = maxSize;
                }

                if (category) {
                    let text = 'Files into: ' + category;
                    text += tag ? ' · Tagged: ' + tag : ' · No tag applied';
                    text += ' · Max ' + maxSize + 'MB';
                    text += extensions ? ' · Allowed: ' + extensions.split(',').map(ext => '.' + $.trim(ext)).join(', ') : ' · Any extension';
                    $('#DocumentTypeParams').text(text).removeClass('d-none');
                } else {
                    $('#DocumentTypeParams').addClass('d-none');
                }

                if (expiryRequired()) {
                    $('.expiry-row').removeClass('d-none');
                    $('#ExpiryDate').prop('required', true);
                } else {
                    $('.expiry-row').addClass('d-none');
                    $('#ExpiryDate').prop('required', false).val('');
                }

                refreshLinkedToTypes();
                refreshFolio();
                reevaluateDropzoneFiles();
            });

            $(document).on('change', 'input[name="FolioModeOption"]', function () {
                const mode = $(this).val();
                $('#FolioId').val('');
                $('#FolioCode').addClass('d-none').val('');
                $('#FolioSelect').addClass('d-none').val(null).trigger('change.select2');

                if (mode === 'new') {
                    $('#FolioCode').removeClass('d-none');
                    refreshFolio();
                } else if (mode === 'existing') {
                    $('#FolioSelect').removeClass('d-none');
                }
                reevaluateDropzoneFiles();
            });

            $(document).on('click', '.create-new-repository', function () {
                $(".modal-item").addClass('d-none');
                $('#createRepositoryModal').removeClass('d-none');
                $('.modal-title').html('<b class="text-info">CREATE</b> a repository (folder)');
                $Modal.children().first().removeClass('modal-lg');
                $Modal.modal('show');
            });
            $('form#createRepositoryForm').submit(async function (e) {
                e.preventDefault();
                const response = await saveForm($(this), $('#createRepositoryBtn'), false, true, true)
                if (response) {
                    $Modal.modal('hide');
                    _appendRepository(response.data);
                }
            });

            $(document).on('click', '.repo-action-update', function () {
                const data = $(this).data('info').split('~');
                $(".modal-title").html('Update Repo  : ' + data[1]);
                $("#e_repository_name").val(data[1]);
                $("#e_repository_description").html(data[2]);
                $("#updateRepositoryForm").attr('action', data[3]);
                $(".modal-item").addClass('d-none');
                $('#updateRepositoryModal').removeClass('d-none');
                $Modal.modal('show');
            });
            $('form#updateRepositoryForm').submit(async function (e) {
                e.preventDefault();
                const response = await saveForm($(this), $('#updateRepositoryBtn'), false, true, true, true)
                if (response) {
                    $Modal.modal('hide');
                    $('#' + response.data.id).remove();
                    _appendRepository(response.data);
                }
            });

            $(document).on('click', '.repo-action-trash', function () {
                const data = $(this).data('info').split('~');
                $(".modal-title").html('<b class="text-danger">Trash</b> Repository : ' + data[1]);
                $("#trashRepositoryForm").attr('action', data[2]);
                $(".rm-repo-name").html(data[1]);
                $(".modal-item").addClass('d-none');
                $('#trashRepositoryModal').removeClass('d-none');
                $Modal.modal('show');
            });
            $('form#trashRepositoryForm').submit(async function (e) {
                e.preventDefault();
                const response = await saveForm($(this), $('#trashRepositoryBtn'), false, true, true);
                if (response) {
                    $('#' + response.data.id).remove();
                    $Modal.modal('hide');
                }
            });

            $(document).on('click', '.repo-action-open', function () {
                openRepoTab($(this).data('code'), $(this).data('name'));
            });

            $(document).on('dblclick', '.repo-card-open', function () {
                openRepoTab($(this).data('code'), $(this).data('name'));
            });

            $(document).on('click', '.file-action-trash', function () {
                $(".modal-title").html('<b class="text-danger">Trash</b>  : ' + $(this).data('title'));
                $("#trashFileForm").attr('action', $(this).data('url'));
                $(".rm-repo-name").html($(this).data('title'));
                $(".modal-item").addClass('d-none');
                $('#trashFileModal').removeClass('d-none');
                $Modal.modal('show');
            });

            $('form#trashFileForm').submit(async function (e) {
                e.preventDefault();
                const response = await saveForm($(this), $('#trashFileBtn'), false, true, true);
                if (response) {
                    $('#' + response.data.id).remove();
                    $('#grid-' + response.data.id).remove();
                    $Modal.modal('hide');
                }
            });

            switchToTab(activeRepoCode, activeRepoName);
        });

        async function fetchRepositories() {
            const parent = $('#repositoriesContents'), cmtMsg = $('#repositoriesMessage');
            let url = parent.data('url');
            if (url === null || url === undefined) {
                cmtMsg.html('');
                return;
            }
            cmtMsg.html('<p class="mt-3"><i class="fas fa-spinner fa-spin fa-5x"></i> please wait</p>');
            await $.get(url, function (data) {
                $.map(data.data, function (repo) {
                    _appendRepository(repo);
                });
                url = data.links.next;
                parent.data('url', url);
                cmtMsg.html(url ? '<button type="button" class="btn btn-primary" onclick="fetchRepositories()">Load more</button>' : '');
            }).fail(function (e) {
                formRequest(e)
            });
        }

        function _appendRepository(repo) {
            let content = '<div class="col-md-6 col-xl-3 repo-card-open" id="' + repo.id + '" data-code="' + repo.id + '" data-name="' + repo.name + '"> ' +
                '<div class="card"> <div class="card-body"> <div class="d-flex"> <div class="flex-shrink-0"> <svg class="pc-icon wid-40 hei-40 ';
            content += (repo.visibility.value === '{{ VisibilityEnum::Private->value }}') ? ' text-warning' : ' text-primary';
            content += '"> <use xlink:href="#custom-folder-open"></use> </svg> </div> <div class="flex-grow-1 mx-3">' +
                '<h5 class="mb-1 d-grid"><span class="text-truncate w-100">' + repo.name + '</span></h5> <p class="mb-0"><small>';
            content += (repo.files.count >= 1) ? repo.files.string + ' file(s)' : 'empty';
            content += '</small></p></div>' +
                ' <div class="dropdown"><a class="avtar avtar-xs btn-link-secondary dropdown-toggle arrow-none" href="javascript:void(0)" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="material-icons-two-tone f-18">more_vert</i></a><div class="dropdown-menu dropdown-menu-end">' +
                '<a class="dropdown-item py-2 f-w-600 repo-action-open" data-code="' + repo.id + '" data-name="' + repo.name + '" href="javascript:void(0)">Open</a> ' +
                '<a class="dropdown-item py-2 click-summary-data" href="javascript:void(0)" data-summary_title="Move ' + repo.name + '" data-click_url="' + repo.links.move + '">Move </a>' +
                '<a class="dropdown-item py-2 repo-action-update" data-info="' + repo.id + '~' + repo.name + '~' + repo.description + '~' + repo.links.route + '" href="javascript:void(0)">Rename</a>' +
                '<a class="dropdown-item py-2 click-summary-data" href="javascript:void(0)" data-summary_title="<i class=\'fas fa-folder-open\'></i> ' + repo.name + ' " data-click_url="' + repo.links.summary + '">Share</a> ' +
                '<a class="dropdown-item py-2 repo-action-trash" href="javascript:void(0)" data-info="' + repo.id + '~' + repo.name + '~' + repo.links.route + '">Trash</a></div> </div></div> </div> </div> </div>';
            $('#repositoriesContents').append(content).fadeIn(500);
        }

        async function fetchFiles() {
            const parent = $('#fileContents'), cmtMsg = $('#filesMessage');
            let url = parent.data('url');
            if (url === null || url === undefined) {
                cmtMsg.html('');
                return;
            }
            cmtMsg.html('<p class="mt-3"><i class="fas fa-spinner fa-spin fa-5x"></i> please wait</p>');
            await $.get(url, function (data) {
                $.map(data.data, function (document) {
                    appendFiles(document);
                });
                url = data.links.next;
                parent.data('url', url);
                cmtMsg.html(url ? '<button type="button" class="btn btn-primary" onclick="fetchFiles()">Load more</button>' : '');
            }).fail(function (e) {
                formRequest(e)
            });
        }

        function appendFiles(file, prepend = false) {
            let usersContent = '';
            file.users.data.data.forEach(function (user) {
                usersContent += user.image;
            });
            if (file.users.hasMorePages) {
                usersContent += ' <span class="avtar avtar-xs bg-light-primary text-primary">+' + file.users.total + '</span>';
            }
            let tagsContent = '';
            file.tags.data.data.forEach(function (tag) {
                if (tag.visibility.value === '{{ VisibilityEnum::Private->value }}') {
                    tagsContent += '<span class="badge rounded-pill text-bg-primary">' + tag.Name + '</span>'
                } else {
                    tagsContent += '<span class="badge rounded-pill text-bg-danger">' + tag.Name + '</span>'
                }
            });

            let content = '<tr id="' + file.id + '" class="dbl-click-redirect-data" data-dbl_click_url="' + file.links.detail + '"> <td> <div class="d-flex align-items-center"><img src="' + file.type.img + '" alt="file-icon" class="wid-35">' +
                '<h6 class="mb-0 ms-2 text-truncate">' + file.name + '</h6> </div> </td> <td>' + file.size.string + '</td> <td>' + file.dated.datetime + '</td>' +
                '<td> <div class="user-group p-1">' + usersContent + '  </div> </td>' +
                '<td> <div class="d-flex flex-wrap gap-2">' + tagsContent + ' </div> </td>' +
                '<td> <ul class="list-inline text-end"> <li class="list-inline-item mx-2"> ' + file.visibility.icon + ' </li>' +
                '<li class="list-inline-item"><div class="dropdown"><a class="avtar avtar-xs btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="material-icons-two-tone f-18">more_vert</i></a><div class="dropdown-menu dropdown-menu-end" style="">' +
                '<a class="dropdown-item f-w-600" href="' + file.links.detail + '">Details</a> ' +
                '<a class="dropdown-item click-summary-data" href="javascript:void(0)" data-summary_title=" ' + file.type.icon + ' ' + file.name + ' Move " data-click_url="' + file.links.move + '"> Move </a>' +
                '<a class="dropdown-item click-summary-data" href="javascript:void(0)" data-summary_title=" ' + file.type.icon + ' ' + file.name + ' " data-click_url="' + file.links.summary + '"> Share </a>' +
                '<a class="dropdown-item file-action-trash" data-title=" ' + file.type.icon + ' ' + file.name + ' " data-url="' + file.links.detail + '" href="#">Delete</a></div></div></li> </ul> </td> </tr>';

            let gridContent = '<div class="col-md-4 col-lg-3 mb-3" id="grid-' + file.id + '">' +
                '<div class="card file-card h-100 dbl-click-redirect-data" data-dbl_click_url="' + file.links.detail + '">' +
                '<div class="card-body text-center"><img src="' + file.type.img + '" alt="file-icon" class="wid-40 mb-2">' +
                '<h6 class="text-truncate mb-1">' + file.name + '</h6>' +
                '<small class="text-muted d-block">' + file.size.string + '</small>' +
                '<div class="d-flex flex-wrap gap-1 justify-content-center mt-2">' + tagsContent + '</div>' +
                '</div></div></div>';

            if (prepend) {
                $('#fileContents').prepend(content);
                $('#fileGridView').prepend(gridContent);
            } else {
                $('#fileContents').append(content);
                $('#fileGridView').append(gridContent);
            }
        }
    </script>
@endsection
