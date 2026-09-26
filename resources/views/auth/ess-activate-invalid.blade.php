@extends('layouts.blanks')

@section('title', 'Invalid ESS Activation Link')

@section('content')
    <div class="card">
        <div class="card-header text-center pb-0">
            <h1 class="h3 mb-1">ESS Activation</h1>
            <p class="mb-0 text-muted">Unable to activate account.</p>
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-0">
                {{ $message ?? 'Activation link is invalid or has expired.' }}
            </div>
        </div>
    </div>
@endsection

