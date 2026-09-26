<div class="modal fade" id="sessionTimeoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sessionTimeoutLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark" id="sessionTimeoutLabel">
                    <i class="fas fa-exclamation-triangle"></i> Session Timeout Warning
                </h5>
            </div>
            <div class="modal-body">
                <p>Your session will expire in <span id="sessionTimeoutTimer" class="fw-bold text-danger"></span>.</p>
                <p>Enter your password to extend this session.</p>
                <form id="sessionTimeoutForm" autocomplete="off" class="mb-0">
                    <input
                        type="text"
                        name="session_extend_username"
                        autocomplete="username"
                        class="d-none"
                        aria-hidden="true"
                        tabindex="-1"
                        style="position: absolute; left: -9999px; top: -9999px; width: 0; height: 0; opacity: 0; overflow: hidden;"
                    >
                    <div class="mb-2">
                        <label for="sessionExtendPassword" class="form-label fw-semibold">Current Password</label>
                        <input
                            type="password"
                            name="session_extend_password"
                            class="form-control"
                            id="sessionExtendPassword"
                            autocomplete="off"
                            placeholder="Enter your password"
                        >
                        <div id="sessionExtendPasswordError" class="text-danger small mt-2 d-none"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('logout-form').submit();">Log Out</button>
                <button type="button" class="btn btn-primary" id="stayLoggedInBtn">Confirm &amp; Extend</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Configuration from backend (idle, independent of session lifetime)
    @php
        $orgParam = \App\Models\Settings\OrganizationProfile::current();
        $dbIdle = intval($orgParam?->IdleSessionTimeoutMinutes ?: config('idle.timeout_minutes', 20));
    @endphp
    const sessionLifetimeMinutes = {{ $dbIdle }};
    const warningSeconds = {{ config('idle.warning_seconds_before', 120) }}; 

    // Internal states
    let lastActivityTime = Date.now();
    let warningTimer;
    let logoutTimer;
    let countdownInterval;
    let isOffline = !navigator.onLine;
    let hasGoneOffline = false;
    let isWarningVisible = false;
    let lastServerHeartbeat = Date.now();
    let passwordChallengeRegistered = false;
    let reauthToken = null;
    let reauthCsrfToken = '{{ csrf_token() }}';

    const modalElement = document.getElementById('sessionTimeoutModal');
    const timerSpan = document.getElementById('sessionTimeoutTimer');
    const stayLoggedInBtn = document.getElementById('stayLoggedInBtn');
    const passwordInput = document.getElementById('sessionExtendPassword');
    const passwordError = document.getElementById('sessionExtendPasswordError');
    let modal;

    if (modalElement && typeof bootstrap !== 'undefined') {
        modal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });
    }

    function showPasswordError(message) {
        if (!passwordError) return;
        passwordError.textContent = message;
        passwordError.classList.remove('d-none');
    }

    function clearPasswordError() {
        if (!passwordError) return;
        passwordError.textContent = '';
        passwordError.classList.add('d-none');
    }

    function redirectToExpiredLogin() {
        window.location.href = "{{ route('login') }}?expired=1";
    }

    function requirePasswordForSession() {
        if (passwordChallengeRegistered || isOffline) {
            return;
        }

        passwordChallengeRegistered = true;

        fetch("{{ route('session.require-password') }}", {
            method: 'POST',
            keepalive: true,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                required: true,
                intended_url: window.location.href
            })
        }).then(async response => {
            if (response.status === 401 || response.status === 419) {
                redirectToExpiredLogin();
                return;
            }

            if (!response.ok) {
                showPasswordError('Could not secure this session. Please log in again.');
                return;
            }

            const payload = await response.json().catch(() => ({}));
            reauthToken = payload?.reauth_token || null;
            reauthCsrfToken = payload?.csrf_token || reauthCsrfToken;

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta && reauthCsrfToken) {
                csrfMeta.setAttribute('content', reauthCsrfToken);
            }
        }).catch(err => {
            console.warn('Could not mark session as requiring password confirmation', err);
        });
    }

    function resetActivity() {
        if (isOffline || isWarningVisible) return;
        lastActivityTime = Date.now();
        startTimers();
    }

    function timeRemaining() {
        const elapsed = Date.now() - lastActivityTime;
        const totalMs = sessionLifetimeMinutes * 60 * 1000;
        return totalMs - elapsed;
    }

    function startTimers() {
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownInterval);

        if (isOffline) return;

        const remainingMs = timeRemaining();
        const warningThresholdMs = warningSeconds * 1000;

        if (remainingMs <= 0) {
            handleTimeout();
            return;
        }

        if (remainingMs <= warningThresholdMs) {
            showWarningAndCountdown(Math.floor(remainingMs / 1000));
        } else {
            // Schedule warning
            warningTimer = setTimeout(() => {
                showWarningAndCountdown(warningSeconds);
            }, remainingMs - warningThresholdMs);
        }
    }

    function showWarningAndCountdown(secondsLeft) {
        isWarningVisible = true;
        requirePasswordForSession();
        clearPasswordError();
        if (passwordInput) {
            passwordInput.value = '';
        }
        if (modal && !modalElement.classList.contains('show')) {
            modal.show();
            setTimeout(() => passwordInput?.focus(), 150);
        }

        let remaining = secondsLeft;
        const renderCountdown = () => {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            timerSpan.textContent = `${mins}m ${secs}s`;
        };

        renderCountdown();
        
        countdownInterval = setInterval(() => {
            if (isOffline) {
                timerSpan.textContent = `PAUSED (Offline)`;
                return;
            }

            remaining--;
            renderCountdown();

            if (remaining <= 0) {
                clearInterval(countdownInterval);
                handleTimeout();
            }
        }, 1000);
    }

    function handleTimeout() {
        isWarningVisible = false;
        if (modal) modal.hide();
        // Redirect to login explicitly via backend route, let backend invalidate
        fetch("{{ route('timeout') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        }).finally(() => {
            redirectToExpiredLogin();
        });
    }

    function syncServerActivity() {
        if (isOffline || isWarningVisible) {
            return;
        }

        fetch("{{ route('session.touch') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).catch(err => console.warn('Background session sync failed', err));
    }

    function confirmSessionExtension(showLoaderOnElement = null) {
        clearPasswordError();

        const password = passwordInput?.value ?? '';
        if (password.trim() === '') {
            showPasswordError('Enter your password to extend the session.');
            passwordInput?.focus();
            if (showLoaderOnElement) {
                showLoaderOnElement.disabled = false;
                showLoaderOnElement.innerHTML = 'Confirm &amp; Extend';
            }
            return;
        }

        if (isOffline) {
            if (showLoaderOnElement) {
                showLoaderOnElement.disabled = false;
                showLoaderOnElement.innerHTML = 'Confirm &amp; Extend';
            }
            showPasswordError('Network offline. Reconnect, then enter your password again.');
            if (window.notyf) window.notyf.error('Network offline. Cannot renew session.');
            return;
        }

        if (!reauthToken) {
            if (showLoaderOnElement) {
                showLoaderOnElement.disabled = false;
                showLoaderOnElement.innerHTML = 'Confirm &amp; Extend';
            }
            showPasswordError('Session re-login is not ready. Please log in again.');
            return;
        }

        fetch("{{ route('session.relogin') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': reauthCsrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                password,
                reauth_token: reauthToken
            })
        })
        .then(async response => {
            if (response.ok) {
                const payload = await response.json().catch(() => ({}));
                window.location.href = payload?.route || window.location.href;
                return;
            }

            if (response.status === 422) {
                const payload = await response.json().catch(() => ({}));
                const message = payload?.errors?.password?.[0] || payload?.message || 'Password confirmation failed.';
                showPasswordError(message);
                passwordInput?.focus();
                return;
            }

            if (response.status === 401 || response.status === 419) {
                handleTimeout(); 
                return;
            }

            const payload = await response.json().catch(() => ({}));
            showPasswordError(payload?.message || 'Could not extend the session. Please log in again.');
        })
        .catch(err => {
            console.error('Session renew failed', err);
            showPasswordError('Unable to reach the server. Please try again.');
        })
        .finally(() => {
            if (showLoaderOnElement) {
                showLoaderOnElement.disabled = false;
                showLoaderOnElement.innerHTML = 'Confirm &amp; Extend';
            }
        });
    }

    // Attach activity listeners (throttle to avoid perf issues)
    let activityTimeout;
    const bodyEvents = ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'];
    bodyEvents.forEach(evt => {
        document.addEventListener(evt, () => {
            if (!activityTimeout && !isOffline && !isWarningVisible) {
                const now = Date.now();
                activityTimeout = setTimeout(() => { activityTimeout = null; }, 1000);

                resetActivity();

                const halfLifetimeMs = (sessionLifetimeMinutes * 60 * 1000) / 2;
                if (now - lastServerHeartbeat >= halfLifetimeMs) {
                    lastServerHeartbeat = now;
                    syncServerActivity();
                }
            }
        }, { passive: true });
    });

    if (stayLoggedInBtn) {
        stayLoggedInBtn.addEventListener('click', function () {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';
            confirmSessionExtension(this);
        });
    }

    passwordInput?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            stayLoggedInBtn?.click();
        }
    });

    // Network connection handling
    window.addEventListener('offline', function () {
        isOffline = true;
        hasGoneOffline = true;
        if (window.notyf) window.notyf.error('Network disconnected. Session timer frozen; will force re-login on reconnect.');
    });

    window.addEventListener('online', function () {
        isOffline = false;
        if (hasGoneOffline) {
            // Strictly enforce session drop on network reconnect per design spec
            if (window.notyf) window.notyf.error('Network restored. Refreshing session...');
            handleTimeout();
        }
    });

    // Start timers initially
    startTimers();
});
</script>
