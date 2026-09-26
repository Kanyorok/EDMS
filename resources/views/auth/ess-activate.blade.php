@extends('layouts.blanks')

@section('title', 'Activate ESS Account')

@section('content')
    <div class="card">
        <div class="card-header text-center pb-0">
            <h1 class="h3 mb-1">{{ config('app.name') }} - ESS Activation</h1>
            <p class="mb-0 text-muted">
                Set your password to activate your Employee Self Service account.
            </p>
        </div>

        <div class="card-body">
            <div class="alert alert-light border mb-3">
                <div><strong>Employee:</strong> {{ optional($essUser->employee)->full_name ?? $essUser->LoginID }}</div>
                <div><strong>Staff No:</strong> {{ optional($essUser->employee)->EmployeeNo ?? '-' }}</div>
                <div><strong>Email:</strong> {{ $essUser->Email ?? '-' }}</div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('ess.activation.store', ['essUser' => $essUser->Id]) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    >
                    @error('password')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input
                        type="password"
                        class="form-control"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">Activate Account</button>
            </form>
        </div>
    </div>
@endsection

