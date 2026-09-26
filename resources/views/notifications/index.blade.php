@extends('layouts.app')
@section('title','Notifications')

@section('content')
<div class="container my-3">
  <div class="card shadow-sm rounded-3">
    <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
      <h6 class="mb-0 text-muted">Notifications</h6>
      <div class="d-flex gap-2">
        <form method="post" action="{{ route('notifications.read-all') }}">
          @csrf
          <button class="btn btn-outline-primary btn-sm">Mark All Read</button>
        </form>
        <form method="post" action="{{ route('notifications.clear') }}" onsubmit="return confirm('Clear all notifications?')">
          @csrf
          <button class="btn btn-outline-danger btn-sm">{{ __('common.clear') }}</button>
        </form>
      </div>
    </div>
    <div class="card-body p-3">
      @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>{{ __('common.title') }}</th>
              <th>Body</th>
              <th>{{ __('common.status') }}</th>
              <th>Created</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $i => $n)
              <tr>
                <td>{{ $rows->firstItem() + $i }}</td>
                <td class="fw-semibold">{{ $n->Title }}</td>
                <td>{{ \Illuminate\Support\Str::limit((string) $n->Body, 140) }}</td>
                <td>{{ $n->ReadOn ? 'Read' : 'New' }}</td>
                <td>{{ $n->CreatedOn }}</td>
                <td class="text-end">
                  @if(is_null($n->ReadOn))
                    <form method="post" action="{{ route('notifications.read', $n->Id) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-outline-secondary btn-sm">Mark Read</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">No notifications.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      {{ $rows->links() }}
    </div>
  </div>
</div>
@endsection

