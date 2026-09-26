@extends('layouts.auth')

@section('title', __('ui.sign_in'))

@section('content')
    <div class="auth-page">
        <header class="auth-topbar" aria-label="{{ __('ui.login_header') }}">
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

        <main class="auth-shell" id="auth-content">
            <section class="auth-story" aria-labelledby="login-story-title">
                <div class="story-content">
                    <p class="story-eyebrow">{{ __('ui.login_eyebrow') }}</p>
                    <h1 id="login-story-title">{!! __('ui.login_headline') !!}</h1>
                    <p class="story-copy">{{ __('ui.login_story') }}</p>
                </div>

                {{-- <div class="trust-card">
                    <span class="trust-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <div>
                        <strong class="trust-card-title">{{ __('ui.secure_access') }}</strong>
                        <span class="trust-card-copy">{{ __('ui.secure_access_copy') }}</span>
                    </div>
                </div> --}}
            </section>

            <section class="auth-panel" aria-labelledby="login-title">
                <div class="auth-panel-inner">
                    {{-- <span class="workspace-badge">{{ __('ui.secure_workspace') }}</span> --}}
                    <h2 id="login-title">{{ __('ui.welcome_back') }}</h2>
                    <p class="panel-copy">{{ __('ui.login_subtitle') }}</p>

                    @php
                        $initialMessage = session('status')
                            ?? (request()->boolean('expired') ? __('ui.session_expired') : null);
                    @endphp

                    <div class="form-alert {{ session('status') ? 'form-alert--status' : '' }}"
                         id="formAlert"
                         role="alert"
                         {{ $initialMessage ? '' : 'hidden' }}>
                        <span class="alert-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/></svg>
                        </span>
                        <span id="formAlertMessage">{{ $initialMessage }}</span>
                    </div>

                    <form method="POST" action="{{ route('login') }}" id="LoginForm" novalidate>
                        @csrf

                        @php
                            $selectedBranchId = (string) old('branch', '');
                            $selectedBranch = $branches->first(
                                fn ($branch) => (string) $branch->BranchID === $selectedBranchId
                            );
                        @endphp

                        <div class="form-field @error('branch') has-error @enderror" data-field="branch">
                            <label for="branchControl" class="field-label">
                                {{ __('ui.login_branch') }} <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <div class="branch-picker" id="branchPicker">
                                <div class="input-wrap branch-native-wrap">
                                    <span class="field-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24"><path d="M3 21h18M6 21V5l6-3 6 3v16M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
                                    </span>
                                    <select class="auth-input @error('branch') is-invalid @enderror"
                                            id="branch"
                                            name="branch"
                                            required
                                            autofocus
                                            aria-describedby="branch_error"
                                            aria-invalid="{{ $errors->has('branch') ? 'true' : 'false' }}">
                                        <option value="" disabled {{ $selectedBranchId === '' ? 'selected' : '' }}>{{ __('ui.select_branch') }}</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->BranchID }}" {{ (string) $branch->BranchID === $selectedBranchId ? 'selected' : '' }}>
                                                {{ $branch->Name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="select-chevron" aria-hidden="true"></span>
                                </div>

                                <div class="branch-combobox" hidden>
                                    <button class="auth-input branch-trigger"
                                            type="button"
                                            id="branchControl"
                                            aria-haspopup="listbox"
                                            aria-expanded="false"
                                            aria-controls="branchListbox"
                                            aria-describedby="branch_error"
                                            aria-invalid="{{ $errors->has('branch') ? 'true' : 'false' }}">
                                        <span class="branch-trigger-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24"><path d="M3 21h18M6 21V5l6-3 6 3v16M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
                                        </span>
                                        <span class="branch-trigger-label {{ $selectedBranch ? '' : 'is-placeholder' }}">
                                            {{ $selectedBranch?->Name ?? __('ui.select_branch') }}
                                        </span>
                                        <span class="select-chevron" aria-hidden="true"></span>
                                    </button>

                                    <div class="branch-dropdown" id="branchDropdown" hidden>
                                        <div class="branch-search-wrap">
                                            <span class="branch-search-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                                            </span>
                                            <label class="visually-hidden" for="branchSearch">{{ __('ui.search_branches') }}</label>
                                            <input class="branch-search"
                                                   id="branchSearch"
                                                   type="search"
                                                   inputmode="search"
                                                   autocomplete="off"
                                                   placeholder="{{ __('ui.search_branches') }}"
                                                   aria-controls="branchListbox">
                                        </div>

                                        <div class="branch-options" id="branchListbox" role="listbox" aria-label="{{ __('ui.login_branch') }}">
                                            @foreach ($branches as $branch)
                                                <button class="branch-option"
                                                        type="button"
                                                        id="branch-option-{{ $loop->index }}"
                                                        role="option"
                                                        tabindex="-1"
                                                        data-value="{{ $branch->BranchID }}"
                                                        aria-selected="{{ (string) $branch->BranchID === $selectedBranchId ? 'true' : 'false' }}">
                                                    <span>{{ $branch->Name }}</span>
                                                    <svg class="branch-check" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                                </button>
                                            @endforeach
                                        </div>

                                        <p class="branch-empty" id="branchEmpty" hidden>{{ __('ui.no_branches_found') }}</p>
                                    </div>
                                </div>
                            </div>
                            <p id="branch_error" class="field-error" role="alert" aria-live="polite">@error('branch'){{ $message }}@enderror</p>
                        </div>

                        <div class="form-field @error('UserID') has-error @enderror" data-field="UserID">
                            <label for="UserID" class="field-label">
                                {{ __('ui.userid_or_email') }} <span class="required-mark" aria-hidden="true">*</span>
                            </label>
                            <div class="input-wrap">
                                <span class="field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                                </span>
                                <input class="auth-input @error('UserID') is-invalid @enderror"
                                       id="UserID"
                                       type="text"
                                       name="UserID"
                                       value="{{ old('UserID') }}"
                                       required
                                       autocomplete="username"
                                       autocapitalize="characters"
                                       spellcheck="false"
                                       placeholder="{{ __('ui.userid_placeholder') }}"
                                       aria-describedby="UserID_error"
                                       aria-invalid="{{ $errors->has('UserID') ? 'true' : 'false' }}">
                            </div>
                            <p id="UserID_error" class="field-error" role="alert" aria-live="polite">@error('UserID'){{ $message }}@enderror</p>
                        </div>

                        <div class="form-field @error('password') has-error @enderror" data-field="password">
                            <label for="password" class="field-label">
                                {{ __('ui.password') }} <span class="required-mark" aria-hidden="true">*</span>
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
                                       autocomplete="current-password"
                                       placeholder="{{ __('ui.password_placeholder') }}"
                                       aria-describedby="password_error"
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

                        <button type="submit" class="login-button" id="LoginBtn">
                            <span class="button-spinner" aria-hidden="true"></span>
                            <span class="button-success-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                            </span>
                            <span class="button-label" aria-live="polite">{{ __('ui.login_btn') }}</span>
                            <span class="button-arrow" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M5 12h14M14 7l5 5-5 5"/></svg>
                            </span>
                        </button>

                        <p class="form-footnote">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect width="16" height="11" x="4" y="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            {{ __('ui.authorized_access_only') }}
                        </p>
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

            const form = document.getElementById('LoginForm');
            const submitButton = document.getElementById('LoginBtn');
            const submitLabel = submitButton?.querySelector('.button-label');
            const alertBox = document.getElementById('formAlert');
            const alertMessage = document.getElementById('formAlertMessage');
            const password = document.getElementById('password');
            const passwordToggle = document.getElementById('togglePassword');
            const userId = document.getElementById('UserID');
            const branchPicker = document.getElementById('branchPicker');
            const branchSelect = document.getElementById('branch');
            const branchNativeWrap = branchPicker?.querySelector('.branch-native-wrap');
            const branchCombobox = branchPicker?.querySelector('.branch-combobox');
            const branchTrigger = document.getElementById('branchControl');
            const branchTriggerLabel = branchTrigger?.querySelector('.branch-trigger-label');
            const branchDropdown = document.getElementById('branchDropdown');
            const branchSearch = document.getElementById('branchSearch');
            const branchEmpty = document.getElementById('branchEmpty');
            const branchOptions = Array.from(document.querySelectorAll('.branch-option'));
            const defaultButtonLabel = @json(__('ui.login_btn'));
            let submitState = 'idle';

            const showAlert = (message) => {
                if (!alertBox || !alertMessage) return;
                alertBox.classList.remove('form-alert--status');
                alertMessage.textContent = message;
                alertBox.hidden = false;
            };

            const getFieldControl = (name) => {
                if (name === 'branch') return branchTrigger || branchSelect;
                return document.getElementById(name);
            };

            const clearFieldError = (name) => {
                const field = form.querySelector(`.form-field[data-field="${name}"]`);
                const control = getFieldControl(name);
                const error = document.getElementById(`${name}_error`);

                field?.classList.remove('has-error');
                control?.classList.remove('is-invalid');
                control?.setAttribute('aria-invalid', 'false');
                if (name === 'branch') {
                    branchSelect?.classList.remove('is-invalid');
                    branchSelect?.setAttribute('aria-invalid', 'false');
                }
                if (error) error.textContent = '';
            };

            const setFieldError = (name, message) => {
                const field = form.querySelector(`.form-field[data-field="${name}"]`);
                const control = getFieldControl(name);
                const error = document.getElementById(`${name}_error`);

                if (!field || !control) return null;

                field.classList.add('has-error');
                control.classList.add('is-invalid');
                control.setAttribute('aria-invalid', 'true');
                if (name === 'branch') {
                    branchSelect?.classList.add('is-invalid');
                    branchSelect?.setAttribute('aria-invalid', 'true');
                }
                if (error) error.textContent = Array.isArray(message) ? message[0] : message;
                return control;
            };

            const clearErrors = () => {
                form.querySelectorAll('.form-field[data-field]').forEach((field) => {
                    clearFieldError(field.dataset.field);
                });
                if (alertBox) alertBox.hidden = true;
            };

            const showFieldErrors = (errors, shouldFocus = true) => {
                let firstInvalidControl = null;
                let hasFieldErrors = false;

                Object.entries(errors || {}).forEach(([name, messages]) => {
                    const control = setFieldError(name, messages);
                    if (!control) return;
                    hasFieldErrors = true;
                    if (!firstInvalidControl) firstInvalidControl = control;
                });

                if (shouldFocus) firstInvalidControl?.focus();
                return hasFieldErrors;
            };

            const validateRequiredFields = () => {
                const errors = {};

                if (!branchSelect?.value) errors.branch = @json(__('ui.branch_required'));
                if (!userId?.value.trim()) errors.UserID = @json(__('ui.userid_required'));
                if (!password?.value) errors.password = @json(__('ui.password_required'));

                return Object.keys(errors).length === 0 || !showFieldErrors(errors);
            };

            const setSubmitState = (state, statusLabel = null) => {
                submitState = state;
                const isVerifying = state === 'verifying';
                const isOpening = state === 'opening';
                const isBusy = isVerifying || isOpening;

                submitButton.disabled = isBusy;
                submitButton.classList.toggle('is-loading', isVerifying);
                submitButton.classList.toggle('is-success', isOpening);
                submitButton.setAttribute('aria-busy', isBusy ? 'true' : 'false');
                submitButton.dataset.state = state;
                form?.setAttribute('aria-busy', isBusy ? 'true' : 'false');

                if (submitLabel) {
                    submitLabel.textContent = statusLabel || (isVerifying
                        ? @json(__('ui.signing_in'))
                        : isOpening
                            ? @json(__('ui.opening_workspace'))
                            : defaultButtonLabel);
                }
            };

            const enhanceBranchPicker = () => {
                if (!branchSelect || !branchNativeWrap || !branchCombobox || !branchTrigger || !branchDropdown || !branchSearch) {
                    return;
                }

                let activeOption = null;

                const visibleOptions = () => branchOptions.filter((option) => !option.hidden);

                const setActiveOption = (option) => {
                    branchOptions.forEach((item) => item.classList.remove('is-active'));
                    activeOption = option || null;
                    if (!activeOption) {
                        branchSearch.removeAttribute('aria-activedescendant');
                        return;
                    }

                    activeOption.classList.add('is-active');
                    branchSearch.setAttribute('aria-activedescendant', activeOption.id);
                    activeOption.scrollIntoView({ block: 'nearest' });
                };

                const filterOptions = () => {
                    const query = branchSearch.value.trim().toLocaleLowerCase();
                    branchOptions.forEach((option) => {
                        option.hidden = query !== '' && !option.textContent.toLocaleLowerCase().includes(query);
                    });

                    const visible = visibleOptions();
                    if (branchEmpty) branchEmpty.hidden = visible.length > 0;
                    setActiveOption(visible.find((option) => option.getAttribute('aria-selected') === 'true') || visible[0]);
                };

                const closeDropdown = (returnFocus = false) => {
                    branchDropdown.hidden = true;
                    branchCombobox.classList.remove('is-open');
                    branchTrigger.setAttribute('aria-expanded', 'false');
                    if (returnFocus) branchTrigger.focus();
                };

                const openDropdown = () => {
                    branchDropdown.hidden = false;
                    branchCombobox.classList.add('is-open');
                    branchTrigger.setAttribute('aria-expanded', 'true');
                    branchSearch.value = '';
                    filterOptions();
                    window.requestAnimationFrame(() => branchSearch.focus());
                };

                const selectOption = (option) => {
                    if (!option) return;

                    branchSelect.value = option.dataset.value;
                    branchTriggerLabel.textContent = option.querySelector('span')?.textContent.trim() || option.textContent.trim();
                    branchTriggerLabel.classList.remove('is-placeholder');
                    branchOptions.forEach((item) => {
                        item.setAttribute('aria-selected', item === option ? 'true' : 'false');
                    });
                    clearFieldError('branch');
                    branchSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    closeDropdown(true);
                };

                branchSelect.required = false;
                branchSelect.removeAttribute('autofocus');
                branchNativeWrap.hidden = true;
                branchCombobox.hidden = false;

                branchTrigger.addEventListener('click', () => {
                    if (branchDropdown.hidden) openDropdown();
                    else closeDropdown(false);
                });

                branchTrigger.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (branchDropdown.hidden) openDropdown();
                    } else if (event.key === 'Escape' && !branchDropdown.hidden) {
                        event.preventDefault();
                        closeDropdown(false);
                    }
                });

                branchSearch.addEventListener('input', filterOptions);
                branchSearch.addEventListener('keydown', (event) => {
                    const visible = visibleOptions();
                    const currentIndex = visible.indexOf(activeOption);

                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const direction = event.key === 'ArrowDown' ? 1 : -1;
                        const nextIndex = currentIndex < 0
                            ? 0
                            : (currentIndex + direction + visible.length) % visible.length;
                        setActiveOption(visible[nextIndex]);
                    } else if (event.key === 'Enter' && activeOption) {
                        event.preventDefault();
                        selectOption(activeOption);
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeDropdown(true);
                    }
                });

                branchOptions.forEach((option) => {
                    option.addEventListener('click', () => selectOption(option));
                    option.addEventListener('pointermove', () => setActiveOption(option));
                });

                document.addEventListener('pointerdown', (event) => {
                    if (!branchPicker.contains(event.target)) closeDropdown(false);
                });

                branchPicker.addEventListener('focusout', () => {
                    window.setTimeout(() => {
                        if (!branchPicker.contains(document.activeElement)) closeDropdown(false);
                    }, 0);
                });

                window.requestAnimationFrame(() => branchTrigger.focus({ preventScroll: true }));
            };

            enhanceBranchPicker();

            userId?.addEventListener('input', () => clearFieldError('UserID'));
            password?.addEventListener('input', () => clearFieldError('password'));
            branchSelect?.addEventListener('change', () => clearFieldError('branch'));

            passwordToggle?.addEventListener('click', () => {
                const showPassword = password.type === 'password';
                password.type = showPassword ? 'text' : 'password';
                passwordToggle.classList.toggle('is-visible', showPassword);
                passwordToggle.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
                passwordToggle.setAttribute('aria-label', showPassword
                    ? @json(__('ui.hide_password'))
                    : @json(__('ui.show_password'))
                );
            });

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (submitState !== 'idle') return;
                clearErrors();

                if (!validateRequiredFields()) return;

                setSubmitState('verifying');

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
                                showAlert(data.message || @json(__('ui.correct_login_errors')));
                            }
                        } else if (response.status === 419) {
                            showAlert(@json(__('ui.session_expired')));
                        } else {
                            showAlert(data.message || @json(__('ui.login_unavailable')));
                        }
                        setSubmitState('idle');
                        return;
                    }

                    if (data.route) {
                        const nextStatus = data.route === @json(route('two-factor.challenge'))
                            ? @json(__('ui.opening_security_check'))
                            : @json(__('ui.opening_workspace'));
                        setSubmitState('opening', nextStatus);
                        window.location.assign(data.route);
                        return;
                    }

                    showAlert(data.message || @json(__('ui.login_unavailable')));
                    setSubmitState('idle');
                } catch (error) {
                    showAlert(navigator.onLine
                        ? @json(__('ui.login_unavailable'))
                        : @json(__('ui.network_unavailable'))
                    );
                    setSubmitState('idle');
                }
            });

            window.addEventListener('pageshow', (event) => {
                if (event.persisted) setSubmitState('idle');
            });
        })();
    </script>
@endsection
