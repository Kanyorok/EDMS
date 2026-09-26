{{--
    2FA setup partial — loaded via HTMX or direct GET on the profile page.

    Variables passed in:
        $enabled   (bool)   — whether 2FA is already confirmed
        $qrCodeUrl (string) — otpauth:// URL for QR rendering (when !$enabled)
        $secret    (string) — raw base32 secret for manual entry (when !$enabled)
        $user      (User)   — current user (when !$enabled)
--}}

@if($enabled)
    {{-- ===========================
         2FA IS ALREADY ENABLED
         =========================== --}}
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="badge bg-success fs-6 px-3 py-2">
            <i class="fas fa-shield-alt me-1"></i> 2FA Enabled
        </span>
        <span class="text-muted small">Your account is protected with Google Authenticator.</span>
    </div>

    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
        <i class="fas fa-exclamation-triangle mt-1"></i>
        <div>
            Disabling 2FA will remove this protection layer. You will need to enter your current password to confirm.
        </div>
    </div>

    <form id="Disable2FAForm">
        @csrf
        @method('DELETE')
        <div class="mb-3" style="max-width: 360px;">
            <label for="disable_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input id="disable_password" type="password" name="password"
                       class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary" id="toggleDisablePwd" aria-label="Show password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <p id="disable_password_error" class="invalid-feedback d-none error col-12" role="alert"></p>
        </div>
        <button type="submit" class="btn btn-outline-danger" id="Disable2FABtn">
            <i class="fas fa-lock-open me-1"></i> Disable Two-Factor Authentication
        </button>
    </form>

@else
    {{-- ===========================
         SET UP 2FA — QR CODE VIEW
         =========================== --}}
    <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="fas fa-info-circle mt-1"></i>
        <div>
            Scan the QR code below with <strong>Google Authenticator</strong> (or any TOTP app like Authy or 1Password),
            then enter the 6-digit code to confirm.
        </div>
    </div>

    {{-- QR code rendered via browser JS (qrcode.js CDN) --}}
    <div class="text-center mb-3">
        <div id="qrcode" style="display: inline-block; padding: 12px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.12);"></div>
    </div>

    {{-- Manual secret entry --}}
    <div class="mb-4">
        <label class="form-label text-muted small">
            Can't scan? Enter this key manually in your app:
        </label>
        <div class="input-group" style="max-width: 400px;">
            <input type="text" class="form-control form-control-sm font-monospace"
                   id="tfSecret" value="{{ $secret }}" readonly
                   style="letter-spacing: 0.15em; background: #f8f9fa;">
            <button class="btn btn-outline-secondary btn-sm" type="button" id="copySecret" title="{{ __('common.copy') }}">
                <i class="fas fa-copy"></i>
            </button>
        </div>
    </div>

    <form id="Enable2FAForm" style="max-width: 360px;">
        @csrf
        <div class="mb-3">
            <label for="enable_code" class="form-label">Verification Code <span class="text-danger">*</span></label>
            <input id="enable_code"
                   type="text"
                   inputmode="numeric"
                   pattern="[0-9]*"
                   maxlength="6"
                   name="code"
                   class="form-control text-center"
                   autocomplete="one-time-code"
                   placeholder="000000"
                   style="letter-spacing: 0.4em; font-size: 1.4rem; font-weight: 600;">
            <p id="enable_code_error" class="invalid-feedback d-none error col-12" role="alert"></p>
        </div>
        <button type="submit" class="btn btn-primary" id="Enable2FABtn">
            <i class="fas fa-check-circle me-1"></i> Confirm &amp; Enable 2FA
        </button>
        <button type="button" class="btn btn-link text-muted ms-2" id="Cancel2FASetupBtn">{{ __('common.cancel') }}</button>
    </form>
@endif

{{-- ===============================================================
     SCRIPTS — split into separate blocks so Blade @if directives
     are never inside a <script> tag (avoids JS decorator errors).
     =============================================================== --}}

@if(!$enabled)
<script>
(function () {
    // Render QR code using qrcode.js (loaded via CDN if not already present)
    function renderQR() {
        const el = document.getElementById('qrcode');
        if (!el) return;
        if (typeof QRCode !== 'undefined') {
            new QRCode(el, {
                text:         @json($qrCodeUrl),
                width:        192,
                height:       192,
                colorDark:    '#000000',
                colorLight:   '#ffffff',
                correctLevel: QRCode.CorrectLevel.M,
            });
        } else {
            el.innerHTML = '<p class="text-muted small">Unable to render QR code.<br>Use the key below.</p>';
        }
    }

    if (typeof QRCode === 'undefined') {
        const s  = document.createElement('script');
        s.src    = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        s.onload = renderQR;
        document.head.appendChild(s);
    } else {
        renderQR();
    }

    // Copy secret to clipboard
    document.getElementById('copySecret')?.addEventListener('click', function () {
        const val = document.getElementById('tfSecret')?.value;
        if (val) navigator.clipboard.writeText(val).then(() => {
            this.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(() => this.innerHTML = '<i class="fas fa-copy"></i>', 1800);
        });
    });

    // Strip non-digits from code input
    document.getElementById('enable_code')?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);
    });

    // Enable 2FA form submit
    const enableForm = document.getElementById('Enable2FAForm');
    if (enableForm) {
        enableForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('Enable2FABtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Confirming…';
            
            const errEl = document.getElementById('enable_code_error');
            errEl.classList.add('d-none');
            errEl.textContent = '';

            try {
                const response = await fetch('{{ route("two-factor.enable") }}', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        code: document.getElementById('enable_code').value
                    })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    const body = await response.json();
                    let msg = 'An error occurred. Please try again.';
                    if (body.errors?.code) msg = body.errors.code[0];
                    else if (body.message) msg = body.message;
                    errEl.classList.remove('d-none');
                    errEl.textContent = msg;
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (err) {
                errEl.classList.remove('d-none');
                errEl.textContent = 'A network error occurred. Please try again.';
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        });
    }

    // Cancel setup button
    document.getElementById('Cancel2FASetupBtn')?.addEventListener('click', function () {
        const card = this.closest('.card, [id$="2FASetupArea"]');
        if (card) card.style.display = 'none';
        else window.location.reload();
    });
})();
</script>
@endif

@if($enabled)
<script>
(function () {
    // Toggle password visibility
    document.getElementById('toggleDisablePwd')?.addEventListener('click', function () {
        const inp  = document.getElementById('disable_password');
        const icon = this.querySelector('i');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    });

    // Disable 2FA form submit
    const disableForm = document.getElementById('Disable2FAForm');
    if (disableForm) {
        disableForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('Disable2FABtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Disabling…';
            
            const errEl = document.getElementById('disable_password_error');
            errEl.classList.add('d-none');
            errEl.textContent = '';

            try {
                const response = await fetch('{{ route("two-factor.disable") }}', {
                    method: 'DELETE',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        password: document.getElementById('disable_password').value
                    })
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    const body = await response.json();
                    let msg = 'An error occurred. Please try again.';
                    if (body.errors?.password) msg = body.errors.password[0];
                    else if (body.message) msg = body.message;
                    errEl.classList.remove('d-none');
                    errEl.textContent = msg;
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (err) {
                errEl.classList.remove('d-none');
                errEl.textContent = 'A network error occurred. Please try again.';
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        });
    }
})();
</script>
@endif
