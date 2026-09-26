@extends('layouts.auth')

@section('title', __('ui.password_recovery'))

@section('content')
    <div class="auth-page">
        <header class="auth-topbar" aria-label="{{ __('ui.reset_header') }}">
            <a class="auth-brand" href="{{ route('login') }}" aria-label="{{ $authOrganizationName }}">
                <img src="{{ asset($authOrganizationLogo) }}" width="609" height="352" alt="{{ $authOrganizationName }} logo">
            </a>

            <nav class="language-switcher" aria-label="{{ __('ui.language') }}">
                <a href="{{ route('lang.switch', 'en') }}"
                   lang="en"
                   aria-label="{{ __('ui.english') }}"
                   aria-current="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">EN</a>
                <a href="{{ route('lang.switch', 'fr') }}"
                   lang="fr"
                   aria-label="{{ __('ui.french') }}"
                   aria-current="{{ app()->getLocale() === 'fr' ? 'true' : 'false' }}">FR</a>
            </nav>
        </header>

        <main class="auth-shell auth-shell--reset" id="auth-content">
            <section class="auth-story auth-story--reset" aria-labelledby="reset-story-title">
                <div class="story-content">
                    <p class="story-eyebrow">{{ __('ui.reset_eyebrow') }}</p>
                    <h1 id="reset-story-title">{!! __('ui.reset_headline') !!}</h1>
                    <p class="story-copy">{{ __('ui.reset_story') }}</p>
                </div>

                <div class="trust-card">
                    <span class="trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect width="16" height="11" x="4" y="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                    </span>
                    <div>
                        <strong class="trust-card-title">{{ __('ui.reset_secure_title') }}</strong>
                        <span class="trust-card-copy">{{ __('ui.reset_secure_copy') }}</span>
                    </div>
                </div>
            </section>

            <section class="auth-panel auth-panel--reset" aria-labelledby="reset-title">
                <div class="auth-panel-inner auth-panel-inner--reset">
                    <span class="workspace-badge">{{ __('ui.account_security') }}</span>
                    <h2 id="reset-title">{{ __('ui.reset_password_title') }}</h2>
                    <p class="panel-copy">{{ __('ui.reset_password_subtitle') }}</p>

                    <div class="form-alert" id="formAlert" role="alert" hidden>
                        <span class="alert-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
                        </span>
                        <span id="formAlertMessage"></span>
                    </div>

                    <form method="POST" action="{{ route('password.store') }}" id="recoverAccountForm" novalidate>
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="form-field @error('email') has-error @enderror" data-field="email">
                            <label for="email" class="field-label">
                                {{ __('ui.email_address') }} <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <div class="input-wrap">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><rect width="18" height="14" x="3" y="5" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                                </span>
                                <input class="auth-input auth-input--readonly @error('email') is-invalid @enderror"
                                       id="email"
                                       type="email"
                                       name="email"
                                       value="{{ old('email', $request->email) }}"
                                       required
                                       readonly
                                       autocomplete="email"
                                       aria-describedby="email_help email_error"
                                       aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                            </div>
                            <p class="field-help" id="email_help">{{ __('ui.reset_email_help') }}</p>
                            <p id="email_error" class="field-error" role="alert" aria-live="polite">@error('email'){{ $message }}@enderror</p>
                        </div>

                        <div class="form-field @error('password') has-error @enderror" data-field="password">
                            <label for="password" class="field-label">
                                {{ __('ui.new_password') }} <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <div class="input-wrap">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><rect width="16" height="11" x="4" y="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                                </span>
                                <input class="auth-input @error('password') is-invalid @enderror"
                                       id="password"
                                       type="password"
                                       name="password"
                                       required
                                       autofocus
                                       autocomplete="new-password"
                                       placeholder="{{ __('ui.new_password_placeholder') }}"
                                       aria-describedby="password_error passwordRequirements"
                                       aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                                <button type="button"
                                        id="togglePassword"
                                        class="password-toggle"
                                        aria-label="{{ __('ui.show_password') }}"
                                        aria-pressed="false">
                                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.15A8.7 8.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.1 2.75M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.35 0 2.57-.3 3.65-.78M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                                </button>
                            </div>
                            <p id="password_error" class="field-error" role="alert" aria-live="polite">@error('password'){{ $message }}@enderror</p>
                        </div>

                        <div class="form-field @error('password_confirmation') has-error @enderror" data-field="password_confirmation">
                            <label for="password_confirmation" class="field-label">
                                {{ __('ui.confirm_password') }} <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <div class="input-wrap">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><rect width="16" height="11" x="4" y="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
                                </span>
                                <input class="auth-input @error('password_confirmation') is-invalid @enderror"
                                       id="password_confirmation"
                                       type="password"
                                       name="password_confirmation"
                                       required
                                       autocomplete="new-password"
                                       placeholder="{{ __('ui.confirm_password_placeholder') }}"
                                       aria-describedby="password_confirmation_error"
                                       aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}">
                                <button type="button"
                                        id="togglePasswordConfirmation"
                                        class="password-toggle"
                                        aria-label="{{ __('ui.show_confirm_password') }}"
                                        aria-pressed="false">
                                    <svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    <svg class="eye-off-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.15A8.7 8.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16 16 0 0 1-2.1 2.75M6.2 6.2C3.8 7.7 2.5 12 2.5 12s3.5 6 9.5 6c1.35 0 2.57-.3 3.65-.78M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                                </button>
                            </div>
                            <p id="password_confirmation_error" class="field-error" role="alert" aria-live="polite">@error('password_confirmation'){{ $message }}@enderror</p>
                        </div>

                        <div class="password-requirements" id="passwordRequirements" aria-live="polite">
                            <div class="requirements-header">
                                <strong>{{ __('ui.password_requirements') }}</strong>
                                <span class="strength-label" id="strengthLabel">{{ __('ui.strength_waiting') }}</span>
                            </div>
                            <div class="strength-meter" id="strengthMeter" aria-hidden="true">
                                <span class="strength-segment"></span><span class="strength-segment"></span><span class="strength-segment"></span><span class="strength-segment"></span>
                            </div>
                            <ul class="requirements-grid">
                                <li class="requirement-item" id="req-length">{{ __('ui.requirement_length') }}</li>
                                <li class="requirement-item" id="req-lower">{{ __('ui.requirement_lower') }}</li>
                                <li class="requirement-item" id="req-upper">{{ __('ui.requirement_upper') }}</li>
                                <li class="requirement-item" id="req-number">{{ __('ui.requirement_number') }}</li>
                                <li class="requirement-item" id="req-symbol">{{ __('ui.requirement_symbol') }}</li>
                                <li class="requirement-item" id="req-match">{{ __('ui.requirement_match') }}</li>
                            </ul>
                        </div>

                        <button type="submit" class="login-button" id="recoverAccountBtn">
                            <span class="button-spinner" aria-hidden="true"></span>
                            <span class="button-label">{{ __('ui.reset_password_btn') }}</span>
                            <span class="button-arrow" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                            </span>
                        </button>

                        <a class="form-secondary-link" href="{{ route('login') }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M10 17l-5-5 5-5"/></svg>
                            {{ __('ui.back_to_sign_in') }}
                        </a>
                    </form>
                </div>
            </section>
        </main>

        <footer class="auth-footer">
            &copy; {{ date('Y') }} {{ $authOrganizationName }}. {{ __('ui.all_rights_reserved') }}
        </footer>
    </div>
@endsection

@section('scripts')
    <script>
        (() => {
            'use strict';

            const form = document.getElementById('recoverAccountForm');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const confirmation = document.getElementById('password_confirmation');
            const submitButton = document.getElementById('recoverAccountBtn');
            const submitLabel = submitButton?.querySelector('.button-label');
            const alertBox = document.getElementById('formAlert');
            const alertMessage = document.getElementById('formAlertMessage');
            const requirementsBox = document.getElementById('passwordRequirements');
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthLabel = document.getElementById('strengthLabel');
            const defaultButtonLabel = @json(__('ui.reset_password_btn'));
            const requirementElements = {
                length: document.getElementById('req-length'),
                lower: document.getElementById('req-lower'),
                upper: document.getElementById('req-upper'),
                number: document.getElementById('req-number'),
                symbol: document.getElementById('req-symbol'),
                match: document.getElementById('req-match'),
            };

            const patterns = {
                lower: /[a-z]/,
                upper: /[A-Z]/,
                number: /\d/,
                symbol: /[^A-Za-z0-9]/,
            };

            const showAlert = (message) => {
                if (!alertBox || !alertMessage) return;
                alertMessage.textContent = message;
                alertBox.hidden = false;
            };

            const clearFieldError = (name) => {
                const field = form.querySelector(`.form-field[data-field="${name}"]`);
                const control = document.getElementById(name);
                const error = document.getElementById(`${name}_error`);

                field?.classList.remove('has-error');
                control?.classList.remove('is-invalid');
                control?.setAttribute('aria-invalid', 'false');
                if (error) error.textContent = '';
            };

            const setFieldError = (name, message) => {
                const field = form.querySelector(`.form-field[data-field="${name}"]`);
                const control = document.getElementById(name);
                const error = document.getElementById(`${name}_error`);
                if (!field || !control) return null;

                field.classList.add('has-error');
                control.classList.add('is-invalid');
                control.setAttribute('aria-invalid', 'true');
                if (error) error.textContent = Array.isArray(message) ? message[0] : message;
                return control;
            };

            const clearErrors = () => {
                form.querySelectorAll('.form-field[data-field]').forEach((field) => {
                    clearFieldError(field.dataset.field);
                });
                if (alertBox) alertBox.hidden = true;
            };

            const showFieldErrors = (errors) => {
                let firstInvalidControl = null;
                let hasFieldErrors = false;

                Object.entries(errors || {}).forEach(([name, messages]) => {
                    const control = setFieldError(name, messages);
                    if (!control) return;
                    hasFieldErrors = true;
                    if (!firstInvalidControl) firstInvalidControl = control;
                });

                firstInvalidControl?.focus();
                return hasFieldErrors;
            };

            const passwordState = () => {
                const value = password?.value || '';
                const confirmationValue = confirmation?.value || '';
                return {
                    length: value.length >= 8,
                    lower: patterns.lower.test(value),
                    upper: patterns.upper.test(value),
                    number: patterns.number.test(value),
                    symbol: patterns.symbol.test(value),
                    match: value !== '' && value === confirmationValue,
                };
            };

            const updateRequirements = (activate = false) => {
                const state = passwordState();
                const value = password?.value || '';
                if (activate || value !== '') requirementsBox?.classList.add('is-active');

                Object.entries(state).forEach(([name, valid]) => {
                    requirementElements[name]?.classList.toggle('is-met', valid);
                    requirementElements[name]?.classList.toggle('is-unmet', !valid && requirementsBox?.classList.contains('is-active'));
                });

                const strengthScore = ['length', 'lower', 'upper', 'number', 'symbol']
                    .filter((name) => state[name]).length;
                const filledSegments = value === '' ? 0 : Math.max(1, Math.min(4, strengthScore - 1));
                const strengthClass = filledSegments <= 1 ? 'weak' : filledSegments === 2 ? 'fair' : filledSegments === 3 ? 'good' : 'strong';
                const strengthLabels = {
                    weak: @json(__('ui.strength_weak')),
                    fair: @json(__('ui.strength_fair')),
                    good: @json(__('ui.strength_good')),
                    strong: @json(__('ui.strength_strong')),
                };

                strengthMeter?.setAttribute('data-strength', value === '' ? 'waiting' : strengthClass);
                strengthMeter?.querySelectorAll('.strength-segment').forEach((segment, index) => {
                    segment.classList.toggle('is-filled', index < filledSegments);
                });
                if (strengthLabel) {
                    strengthLabel.textContent = value === '' ? @json(__('ui.strength_waiting')) : strengthLabels[strengthClass];
                }

                return state;
            };

            const validateForm = () => {
                const errors = {};
                const state = updateRequirements(true);

                if (!email?.value.trim()) errors.email = @json(__('ui.reset_email_required'));
                if (!password?.value) {
                    errors.password = @json(__('ui.password_required'));
                } else if (!state.length || !state.lower || !state.upper || !state.number || !state.symbol) {
                    errors.password = @json(__('ui.password_rules_error'));
                }

                if (!confirmation?.value) {
                    errors.password_confirmation = @json(__('ui.confirmation_required'));
                } else if (!state.match) {
                    errors.password_confirmation = @json(__('ui.password_mismatch_error'));
                }

                if (Object.keys(errors).length === 0) return true;
                showFieldErrors(errors);
                return false;
            };

            const setLoading = (loading) => {
                submitButton.disabled = loading;
                submitButton.classList.toggle('is-loading', loading);
                submitButton.setAttribute('aria-busy', loading ? 'true' : 'false');
                if (submitLabel) submitLabel.textContent = loading ? @json(__('ui.resetting_password')) : defaultButtonLabel;
            };

            const wirePasswordToggle = (input, button, showLabel, hideLabel) => {
                button?.addEventListener('click', () => {
                    const showPassword = input.type === 'password';
                    input.type = showPassword ? 'text' : 'password';
                    button.classList.toggle('is-visible', showPassword);
                    button.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                    button.setAttribute('aria-label', showPassword ? hideLabel : showLabel);
                });
            };

            password?.addEventListener('input', () => {
                clearFieldError('password');
                updateRequirements(true);
            });
            confirmation?.addEventListener('input', () => {
                clearFieldError('password_confirmation');
                updateRequirements(true);
            });

            wirePasswordToggle(
                password,
                document.getElementById('togglePassword'),
                @json(__('ui.show_password')),
                @json(__('ui.hide_password'))
            );
            wirePasswordToggle(
                confirmation,
                document.getElementById('togglePasswordConfirmation'),
                @json(__('ui.show_confirm_password')),
                @json(__('ui.hide_confirm_password'))
            );

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearErrors();
                if (!validateForm()) return;

                setLoading(true);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form),
                        credentials: 'same-origin',
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        if (response.status === 422 && data.errors) {
                            if (!showFieldErrors(data.errors)) {
                                showAlert(data.message || @json(__('ui.reset_unavailable')));
                            }
                        } else if (response.status === 419) {
                            showAlert(@json(__('ui.session_expired')));
                        } else {
                            showAlert(data.message || @json(__('ui.reset_unavailable')));
                        }
                        return;
                    }

                    if (data.route) {
                        if (submitLabel) submitLabel.textContent = @json(__('ui.opening_sign_in'));
                        window.location.assign(data.route);
                        return;
                    }

                    showAlert(data.message || @json(__('ui.reset_unavailable')));
                } catch (error) {
                    showAlert(navigator.onLine
                        ? @json(__('ui.reset_unavailable'))
                        : @json(__('ui.network_unavailable'))
                    );
                } finally {
                    if (!document.hidden) setLoading(false);
                }
            });

            updateRequirements(false);
        })();
    </script>
@endsection
