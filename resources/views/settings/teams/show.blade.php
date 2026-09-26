@php use App\Models\Auth\User; @endphp
@extends('layouts.app')

@section('title')
    Team details
@endsection
@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/libs/select2/css/select2.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
        }
    </style>
@endsection
@section('content')
    <div class="row">
        <div class="col-md-4 col-xl-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <h5 class=" mb-0 h3">{{ $team->Name }}</h5>
                    <div class="text-muted mb-2">{{ $team->Notes }}</div>
                    <div class="text-muted"> {{ $team->Email }}</div>

                </div>
                <div class=" border-top card-body">
                    <h5 class="h6 card-title">Team Lead</h5>
                    @if($team->lead instanceof User)
                        @include('snippets.user_summary', ['user'=>$team->lead])
                    @else
                        <h3 class="h3 text-center">No Lead</h3>
                    @endif
                </div>
                <div class=" border-top card-body">
                    @include('snippets.behind_scenes',['model'=>$team])
                </div>
                <div class=" border-top card-body row">
                    <div class="col-6">
                        <button type="button" class="btn btn-primary w-100 modal-update-team">
                            <i class="fas fa-edit"></i></button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-danger w-100 modal-trash-team">
                            <i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8 col-xl-9">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Users
                        <span class="float-end">
                            <button type="button" class="btn btn-primary modal-update-team-users">
                                <i class="fas fa-plus-circle"></i> add team members</button>
                        </span>
                    </h3>
                </div>
                <div class="card-body">
                    <table id="usersTable"
                           class="table table-striped dataTable no-footer dtr-inline w-100 table-responsive">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('common.name') }}</th>
                            <th>{{ __('common.email') }}</th>
                            <th>Added On</th>
                            <th>action</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="TeamActionsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">..</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="onboarding-content with-gradient d-none modal-item" id="updateTeamModal">
                        <form action="{{ route('teams.update',[$team->TeamID]) }}" method="post"
                              id="updateTeamForm"> @csrf
                            <div class="mb-3">@method('put')
                                <label class="form-label" for="Name">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Name" name="Name" required
                                       placeholder="{{ __('common.name') }}" value="{{ $team->Name }}">
                                <p id="Name_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="Email">Email <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="Email" name="Email" required
                                       placeholder="{{ __('common.email') }}" value="{{ $team->Email }}">
                                <p id="Email_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label for="team_lead" class="form-label">Team Lead</label>
                                <select class="form-control " name="team_lead" id="team_lead">
                                    @if($team->lead instanceof User)
                                        <option value="{{ $team->lead->UserID }}">{{ $team->lead->Name }}</option>
                                    @endif
                                </select>
                                <p id="team_lead_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="Notes">{{ __('common.notes') }}</label>
                                <textarea name="Notes" id="Notes" rows="3" class="form-control"
                                          maxlength="1000">{{ $team->Notes }}</textarea>
                                <p id="Notes_error" class="invalid-feedback d-none error col-12"
                                   role="alert"></p>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="updateTeamBtn" type="submit"><i
                                        class="fas fa-save"></i>
                                    update {{ \Illuminate\Support\Str::limit($team->Name ,20) }}
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item text-center" id="trashTeamModal">
                        <h4 class="text-danger">
                            You are about to delete team <b>{{ $team->Name }}</b> ?
                        </h4>
                        <p class="text-center"> are you sure about this?</p>
                        <form id="trashTeamForm" method="post"
                              action="{{ route('teams.destroy',[$team->TeamID]) }}"> @csrf @method('delete')
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    no, cancel
                                </button>
                                <button class="btn btn-danger float-end" id="trashTeamBtn"
                                        type="submit"><i
                                        class="fas fa-trash"></i> yes, trash
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content with-gradient d-none modal-item" id="addTeamUserModal">
                        <form action="{{ route('team-users.store',[$team->TeamID]) }}" method="post"
                              id="addTeamUserForm">
                            @csrf
                            <div class="mb-3">
                                <label for="users" class="form-label">Team Members(s)</label>
                                <select class="form-control " name="users[]" id="users" multiple required>
                                </select>
                                <p id="users_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                            </div>
                            <hr>
                            <div class="mt-4">
                                <button type="button" class="btn btn-secondary float-start"
                                        data-bs-dismiss="modal">
                                    cancel
                                </button>
                                <button class="btn btn-primary float-end" id="addTeamUserBtn" type="submit"><i
                                        class="fas fa-save"></i> add members
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="onboarding-content d-none modal-item text-center" id="removeTeamUserModal">
                        <h4 class="text-danger">
                            Remove <b id="removeTeamUserName"></b> from Team {{ $team->name }}
                        </h4>
                        <div class="mt-2 mb-2">
                            You are about to delete this item, confirm below ?
                        </div>
                        <hr>
                        <form id="removeTeamUserForm" method="post"> @csrf @method('delete')
                            <div class="mt-4">
                                <button type="button" class="btn btn-success float-start"
                                        data-bs-dismiss="modal">
                                    no, keep
                                </button>
                                <button class="btn btn-danger float-end" id="removeTeamUserBtn" type="submit"><i
                                        class="fas fa-trash"></i> yes, remove
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
    <script src="{{ asset('assets/js/datatables.js') }}"></script>
    <script src="{{ asset('assets/libs/select2/js/select2.full.min.js') }}"></script>
    <script> const $Modal = $('#TeamActionsModal');
        let usersTable = null;
        $(function () {
            $.fn.dataTable.ext.errMode = 'none';
            fetchUsersTable();

            $(document).on('click', '.modal-trash-team-users', function () {
                $(".modal-title").html('<b class="text-danger">Remove</b> Team Member :' + $(this).data('name'));
                $(".modal-item").addClass('d-none');
                $('#removeTeamUserModal').removeClass('d-none');
                $("#removeTeamUserName").html($(this).data('name'));
                $('#removeTeamUserForm').attr('action', $(this).data('action'));
                $Modal.modal('show');
            });

            $('form#removeTeamUserForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#removeTeamUserBtn'), false, true, true)) {
                    $Modal.modal('hide');
                    fetchUsersTable();
                }
            });

            $(document).on('click', '.modal-update-team-users', function () {
                $(".modal-title").html('Add Users Team : {{ $team->Name }}');
                $(".modal-item").addClass('d-none');
                $('#addTeamUserModal').removeClass('d-none');
                $('#users').val([]).change();
                $Modal.modal('show');
            });

            $('form#addTeamUserForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#addTeamUserBtn'), false, true, true)) {
                    $Modal.modal('hide');
                    fetchUsersTable();
                }
            });

            $(document).on('click', '.modal-trash-team', function () {
                $(".modal-title").html('Trash Team : {{ $team->Name }}');
                $(".modal-item").addClass('d-none');
                $('#trashTeamModal').removeClass('d-none');
                $Modal.modal('show');
            });
            $('form#trashTeamForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#trashTeamBtn'), true, true, true)) {
                    $Modal.modal('hide');
                }
            });

            $(document).on('click', '.modal-update-team', function () {
                $(".modal-title").html('Update Team : {{ $team->Name }}');
                $(".modal-item").addClass('d-none');
                $('#updateTeamModal').removeClass('d-none');
                $Modal.modal('show');
            });
            $('form#updateTeamForm').submit(async function (e) {
                e.preventDefault();
                if (await saveForm($(this), $('#updateTeamBtn'), true, true, true)) {
                    $Modal.modal('hide');
                }
            });

            $('#users').select2({
                placeholder: "Choose users ...", minimumInputLength: 2,
                dropdownParent: $Modal,
                ajax: {
                    url: '{{route('users.select2',['filter_team'=>$team->TeamID])}}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {q: $.trim(params.term)};
                    },
                    processResults: function (data) {
                        return {
                            results: $.map(data, function (item) {
                                return {text: item.Name + ' - ' + item.UserID, id: item.UserID}
                            })
                        };
                    },
                    cache: true
                }
            });
            $('#team_lead').select2({
                placeholder: "Choose team members ...", minimumInputLength: 2,
                dropdownParent: $Modal,
                ajax: {
                    url: '{{route('users.select2',['filter_team_only'=>$team->TeamID])}}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {q: $.trim(params.term)};
                    },
                    processResults: function (data) {
                        return {
                            results: $.map(data, function (item) {
                                return {text: item.Name + ' - ' + item.UserID, id: item.UserID}
                            })
                        };
                    },
                    cache: true
                }
            });

        });


        function fetchUsersTable() {
            if (usersTable === null) {
                usersTable = $('#usersTable').DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: true,
                    //"order": [[6, 'desc']],

                    ajax: {
                        url: '{{ route('team-users.index',[$team->TeamID]) }}',
                        error: function (jqXHR) {
                            codeNotify(jqXHR.status);
                        }
                    },
                    columns: [
                        {
                            data: {
                                _: "photo",
                                sort: "UserID",
                            }, name: 'UserID', searchable: false
                        },
                        {data: 'Name', name: 'Name'},
                        {data: 'Email', name: 'Email'},
                        {data: 'pivot', name: 'pivot.CreatedOn'},
                        {data: 'action', name: 'action', orderable: false, searchable: false},
                    ], "oLanguage": {
                        "sEmptyTable": "no users staged here"
                    }
                });

                usersTable.on('error', function (er) {
                    nWarning("an issue occurred while loading users.");
                });
            } else {
                usersTable.ajax.reload();
            }
        }
    </script>
@endsection
