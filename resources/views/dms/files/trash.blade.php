@php use App\Enums\Core\ExtensionsEnum; use App\Enums\Core\VisibilityEnum; @endphp
@extends('dms.layout')

@section('title', 'Trashed Files')

@section('styles')

@endsection

@section('content')
    <div class="row">
        <div class="col-12 ">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="trashTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="trash-tab" data-bs-toggle="tab" data-bs-target="#trashPane"
                                    type="button" role="tab"><i class="fas fa-trash-alt"></i> Trash</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archivedPane"
                                    type="button" role="tab"><i class="fas fa-archive"></i> Archived</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="trashPane" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-borderless w-100" id="DocumentsTable">
                                    <thead>
                                    <tr>
                                        <th scope="col">{{ __('common.name') }}</th>
                                        <th scope="col">{{ __('common.size') }}</th>
                                        <th scope="col">Deleted</th>
                                        <th scope="col">Deleted By</th>
                                        <th scope="col" class="text-end">{{ __('common.actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="archivedPane" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-borderless w-100" id="ArchivedTable">
                                    <thead>
                                    <tr>
                                        <th scope="col">{{ __('common.name') }}</th>
                                        <th scope="col">{{ __('common.size') }}</th>
                                        <th scope="col">Archived</th>
                                        <th scope="col">Archived By</th>
                                        <th scope="col" class="text-end">{{ __('common.actions') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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

                    <div class="onboarding-content with-gradient d-none modal-item text-center"
                         id="restoreFileModal">
                        <h4>
                            Restore Document <b class="rm-file-name"></b> ?
                        </h4>
                        <form id="restoreFileForm" method="post"> @csrf
                            <div class="mt-4">@method('put')
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-primary float-end" id="restoreFileBtn"
                                        type="submit"><i
                                        class="fas fa-trash-restore"></i> yes, restore
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="onboarding-content with-gradient d-none modal-item text-center"
                         id="unarchiveFileModal">
                        <h4>
                            Unarchive Document <b class="rm-file-name"></b> ?
                        </h4>
                        <form id="unarchiveFileForm" method="post"> @csrf
                            <div class="mt-4">@method('delete')
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-primary float-end" id="unarchiveFileBtn"
                                        type="submit"><i
                                        class="fas fa-box-open"></i> yes, unarchive
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
    <script>const $Modal = $('#dmsActionsModal');
        let DocumentsTable = null;
        let ArchivedTable = null;
        $(function () {
            $(document).on('click', '.file-actions-restore', function () {
                $(".modal-title").html('<b class="text-primary">Trash</b>  : ' + $(this).data('title'));
                $("#restoreFileForm").attr('action', $(this).data('url'));
                $(".rm-file-name").html($(this).data('title'));
                $(".modal-item").addClass('d-none');
                $('#restoreFileModal').removeClass('d-none');
                $Modal.modal('show');
            });

            $(document).on('click', '.file-actions-unarchive', function () {
                $(".modal-title").html('<b class="text-primary">Archive</b>  : ' + $(this).data('title'));
                $("#unarchiveFileForm").attr('action', $(this).data('url'));
                $(".rm-file-name").html($(this).data('title'));
                $(".modal-item").addClass('d-none');
                $('#unarchiveFileModal').removeClass('d-none');
                $Modal.modal('show');
            });

            $('form#restoreFileForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#restoreFileBtn'), false, true, true)) {
                    fetchDocumentsTable();
                    $Modal.modal('hide');
                }
            });

            $('form#unarchiveFileForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#unarchiveFileBtn'), false, true, true)) {
                    fetchArchivedTable();
                    $Modal.modal('hide');
                }
            });

            $('#archived-tab').on('shown.bs.tab', function () {
                fetchArchivedTable();
            });
            $('#trash-tab').on('shown.bs.tab', function () {
                if (DocumentsTable !== null) {
                    DocumentsTable.columns.adjust();
                }
            });

            fetchDocumentsTable();
        });

        function nameRender(data, type, row) {
            return '<div class="d-flex align-items-center"><img src="' + row.Icon + '" alt="file-icon" class="wid-35"><h6 class="mb-0 ms-2 text-truncate">' + row.Visibility + ' ' + row.Name + '</h6> </div></div>';
        }

        function buildTable(selector, extraParams, dateColumn, userRelation, emptyMessage, actionRender) {
            const table = $(selector).DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: getDocumentUrl().split('?')[0] + extraParams,
                    error: function (jqXHR) {
                        codeNotify(jqXHR.status);
                    }
                },
                dom: '<"row"<"col-12"r><"col-12 w-100 my-3"t><"col-6"i><"col-6"p>>',
                columnDefs: [
                    {"render": nameRender, "targets": 0},
                    {
                        "render": function (data, type, row) {
                            const by = row[userRelation] ? row[userRelation].Name : '';
                            return '<details><summary>' + data + '</summary><p>By : ' + by + '</p></details> ';
                        },
                        "targets": 2
                    },
                    {"render": actionRender, "targets": 4},
                    {"visible": false, "targets": [3]}
                ],
                "order": [[2, 'desc']],
                columns: [
                    {data: 'Name', name: 'Name'},
                    {data: 'current.Size', name: 'current.Size'},
                    {data: dateColumn, name: dateColumn},
                    {data: userRelation + '.Name', name: userRelation + '.Name'},
                    {data: 'action', name: 'action', orderable: false, searchable: false},
                ], "oLanguage": {
                    "sEmptyTable": "<div class='w-100 text-center my-4'><h4>" + emptyMessage + "</h4></div>"
                }
            });

            table.on('error', function (er) {
                nWarning("an issue occurred while loading the files.");
            });

            return table;
        }

        function fetchDocumentsTable() {
            if (DocumentsTable === null) {
                DocumentsTable = buildTable('#DocumentsTable', '', 'DeletedOn', 'deleter', 'Nothing to see here! Trash is clear.', function (data, type, row) {
                    return '<div class="btn-group" role="group" aria-label="Basic example"><button type="button" class="btn btn-danger btn-sm disabled"><i class="fas fa-trash-alt"></i> delete</button>' +
                        '<button type="button" class="btn btn-primary  btn-sm file-actions-restore" data-icon="' + row.Icon + '" data-title=" ' + row.Name + ' " data-url="' + row.action.restore + '"><i class="fas fa-trash-restore-alt"></i> restore</button></div>';
                });
            } else {
                DocumentsTable.ajax.reload();
            }
        }

        function fetchArchivedTable() {
            if (ArchivedTable === null) {
                ArchivedTable = buildTable('#ArchivedTable', '?tab=archived', 'ArchivedOn', 'archiver', 'Nothing archived yet.', function (data, type, row) {
                    return '<button type="button" class="btn btn-primary btn-sm file-actions-unarchive" data-icon="' + row.Icon + '" data-title=" ' + row.Name + ' " data-url="' + row.action.unarchive + '"><i class="fas fa-box-open"></i> unarchive</button>';
                });
            } else {
                ArchivedTable.ajax.reload();
            }
        }

    </script>
@endsection
