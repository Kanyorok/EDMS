@extends('dms.layout')

@section('title','Document Review')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="#">DMS</a></li>
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="documentReviewTable"
                               class="table table-striped dataTable no-footer dtr-inline w-100">
                            <thead>
                            <tr>
                                <th>No.</th>
                                <th>{{ __('common.document') }}</th>
                                <th>Role</th>
                                <th>Reviewer</th>
                                <th>Requester</th>
                                <th>{{ __('common.status') }}</th>
                                <th>{{ __('common.dated') }}</th>
                                <th>{{ __('common.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        let documentReviewTable = null;
        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchDocumentReviewTable();
        });

        function fetchDocumentReviewTable() {
            if (documentReviewTable === null) {
                documentReviewTable = $('#documentReviewTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    "order": [[6, 'desc']],
                    ajax: {
                        url: document.url,
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                        {data: 'Document', name: 'document.Name'},
                        {data: 'Role', name: 'Role', orderable: false, searchable: false},
                        {data: 'Reviewer', name: 'reviewer.Name'},
                        {data: 'Requester', name: 'creator.Name'},
                        {data: 'Status', name: 'Status'},
                        {data: 'CreatedOn', name: 'CreatedOn'},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ], "oLanguage": {
                        "sEmptyTable": "no document reviews found."
                    }
                });

                documentReviewTable.on('error', function () {
                    nWarning("an issue occurred while loading document reviews.");
                });
            } else {
                documentReviewTable.ajax.reload();
            }
        }
    </script>
@endsection
