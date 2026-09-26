@extends('layouts.blanks')

@section('title', 'Two-Factor Authentication')

@section('content')
    <div class="auth-wrapper v2">
        <div class="auth-form">
            <div class="card my-5 border border-primary border-1 shadow-lg">
                <div class="card-body" id="TwoFactorChallenge">
                    @php
                        $org      = \App\Models\Settings\OrganizationProfile::current();
                        $branding = $org?->Configuration;
                        $logo     = is_object($branding) && isset($branding->logo) ? $branding->logo : 'assets/img/BRERP_Logo_small.png';
                    @endphp

                    <div class="text-center mb-4">
                        <img src="{{ asset($logo) }}" alt="Logo"
                             style="max-width: 100%; height: auto; max-height: 120px; object-fit: contain; margin-bottom: 12px;">
                    </div>

                    {{-- Shield / lock icon --}}
                    <div class="text-center mb-3">
                        <span style="font-size: 3rem; color: var(--bs-primary, #0d6efd);">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                    </div>

                    <h4 class="text-center f-w-500 mb-1">Two-Factor Authentication</h4>
                    <p class="text-center text-muted mb-4" style="font-size: 0.9rem;">
                        Open <strong>Google Authenticator</strong> (or any TOTP app) and enter the 6-digit code for
                        <strong>{{ config('app.name') }}</strong>.
                    </p>

                    <form id="TwoFactorForm">
                        @csrf
                        <div class="mb-4">
                            <label for="code" class="form-label">Verification Code <span class="text-danger">*</span></label>
                            <input id="code"
                                   type="text"
                                   inputmode="numeric"
                                   pattern="[0-9 ]*"
                                   maxlength="7"
                                   class="form-control form-control-lg text-center"
                                   name="code"
                                   autocomplete="one-time-code"
                                   autofocus
                                   placeholder="000000"
                                   style="letter-spacing: 0.4em; font-size: 1.6rem; font-weight: 600;">
                            <p id="code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="VerifyBtn">
                                <i class="fas fa-check-circle me-1"></i> Verify &amp; Sign In
                            </button>
                        </div>
                    </form>

                    {{-- Cancel link --}}
                    <div class="text-center">
                        <form method="POST" action="{{ route('two-factor.cancel') }}" class="d-inline"
                              id="CancelForm">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn btn-link btn-sm text-muted p-0"
                                    style="text-decoration: underline; font-size: 0.85rem;">
                                Cancel &amp; return to login
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Auto-format: strip non-digits, limit to 6
            const codeInput = document.getElementById('code');
            if (codeInput) {
                codeInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').substring(0, 6);
                });
            }

            // Submit via saveForm helper (AJAX)
            $('form#TwoFactorForm').submit(async function (e) {
                e.preventDefault();
                const btn    = $('#VerifyBtn');
                const form   = $(this);
                const code   = $('#code').val().trim();

                // POST to verify endpoint
                await $.ajax({
                    url:    '{{ route("two-factor.verify") }}',
                    method: 'POST',
                    data: {
                        _token: $('input[name=_csrf]').val() || '{{ csrf_token() }}',
                        code:   code,
                    },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    beforeSend() {
                        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Verifying…');
                        $('#code_error').addClass('d-none').text('');
                    },
                    success(res) {
                        if (res && res.route) {
                            window.location.href = res.route;
                        } else {
                            window.location.href = '/';
                        }
                    },
                    error(xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i> Verify &amp; Sign In');
                        let msg = 'An unexpected error occurred.';
                        try {
                            const body = JSON.parse(xhr.responseText);
                            if (body.errors && body.errors.code) {
                                msg = body.errors.code[0] ?? msg;
                            } else if (body.message) {
                                msg = body.message;
                            }
                        } catch (_) {}
                        $('#code_error').removeClass('d-none').text(msg);
                        codeInput?.focus();
                        codeInput?.select();
                    },
                });
            });

            // Allow Enter key in code field
            codeInput?.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('form#TwoFactorForm').trigger('submit');
                }
            });
        });
    </script>
@endsection
