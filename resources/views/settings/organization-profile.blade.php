@extends('layouts.app')

@section('title', 'Organization Profile')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="javascript:void(0);">{{ __('common.settings') }}</a></li>
    <li class="breadcrumb-item"><a href="javascript:void(0);">Organization Profile</a></li>
@endsection

@section('content')
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Organization Profile</h2>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST"
                      action="{{ route(request()->routeIs('hr.*') ? 'hr.config.org.update' : 'settings.organization-profile.update') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Organization Name *</label>
                            <input type="text" name="BankName" class="form-control" value="{{ old('BankName', $profile->BankName ?? '') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Short Name</label>
                            <input type="text" name="ShortName" class="form-control" value="{{ old('ShortName', $profile->ShortName ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Registration No.</label>
                            <input type="text" name="BankRegNumber" class="form-control" value="{{ old('BankRegNumber', $profile->BankRegNumber ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Employer Tax PIN</label>
                            <input type="text" name="EmployerTaxPIN" class="form-control" value="{{ old('EmployerTaxPIN', $profile->EmployerTaxPIN ?? '') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Base Currency</label>
                            <select name="BaseCurrencyID" class="form-select" @disabled(! $canSetBaseCurrency)>
                                <option value="">-- Select Base Currency --</option>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->Id }}"
                                        {{ (string) old('BaseCurrencyID', $profile->BaseCurrencyID ?? '') === (string) $currency->Id ? 'selected' : '' }}>
                                        {{ $currency->Code }} - {{ $currency->Name }}
                                    </option>
                                @endforeach
                            </select>
                            @unless($canSetBaseCurrency)
                                <small class="text-muted">You can view the configured base currency, but only users with the `SetBaseCurrency` permission can change it.</small>
                            @endunless
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Organization Motto</label>
                            <input type="text" name="OrganizationMotto" class="form-control" value="{{ old('OrganizationMotto', $profile->OrganizationMotto ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Idle Session Timeout (Minutes)</label>
                            <input type="number" name="IdleSessionTimeoutMinutes" min="1" max="1440" class="form-control" value="{{ old('IdleSessionTimeoutMinutes', $profile->IdleSessionTimeoutMinutes ?? 20) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Bank Code</label>
                            <input type="text" name="BankCode" class="form-control" value="{{ old('BankCode', $profile->BankCode ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Swift Code</label>
                            <input type="text" name="SwiftCode" class="form-control" value="{{ old('SwiftCode', $profile->SwiftCode ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Clearing Code</label>
                            <input type="text" name="ClearingCode" class="form-control" value="{{ old('ClearingCode', $profile->ClearingCode ?? '') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Website</label>
                            <input type="text" name="Website" class="form-control" value="{{ old('Website', $profile->Website ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ __('common.address1') }}</label>
                            <input type="text" name="Address1" class="form-control" value="{{ old('Address1', $profile->Address1 ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('common.address2') }}</label>
                            <input type="text" name="Address2" class="form-control" value="{{ old('Address2', $profile->Address2 ?? '') }}">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Zip / Postal Code</label>
                            <input type="text" name="ZipCode" class="form-control" value="{{ old('ZipCode', $profile->ZipCode ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Phone 1</label>
                            <input type="text" name="Phone1" class="form-control" value="{{ old('Phone1', $profile->Phone1 ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Phone 2</label>
                            <input type="text" name="Phone2" class="form-control" value="{{ old('Phone2', $profile->Phone2 ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="Mobile" class="form-control" value="{{ old('Mobile', $profile->Mobile ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Fax</label>
                            <input type="text" name="Fax" class="form-control" value="{{ old('Fax', $profile->Fax ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ __('common.email') }}</label>
                            <input type="email" name="EmailID" class="form-control" value="{{ old('EmailID', $profile->EmailID ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Organization Logo</label>
                            <input type="file" name="OrganizationLogoFile" class="form-control" accept="image/*">
                            <small class="text-muted">Upload the primary logo used across shared screens and reports.</small>
                            @if (!empty($profile?->OrganizationLogo))
                                <div class="mt-3">
                                    <img src="{{ asset($profile->OrganizationLogo) }}" alt="Current organization logo" class="img-thumbnail" style="max-height: 96px;">
                                </div>
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="remove_organization_logo" name="remove_organization_logo" value="1">
                                    <label class="form-check-label" for="remove_organization_logo">Remove current logo</label>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-1">Color Scheme</h5>
                    <p class="text-muted small mb-3">
                        Each organization can brand the application with its own colors (e.g. matching its logo). These apply to the sidebar and accent color across the app for every user.
                    </p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="PrimaryColor">Primary / Accent Color</label>
                            <input type="color" id="PrimaryColor" name="PrimaryColor" class="form-control form-control-color w-100"
                                   value="{{ old('PrimaryColor', $profile->PrimaryColor ?? '#4680ff') }}">
                            <small class="text-muted">Buttons, links and the active menu highlight.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="SidebarBackgroundColor">Sidebar Background Color</label>
                            <input type="color" id="SidebarBackgroundColor" name="SidebarBackgroundColor" class="form-control form-control-color w-100"
                                   value="{{ old('SidebarBackgroundColor', $profile->SidebarBackgroundColor ?? '#ffffff') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="SidebarTextColor">Sidebar Text Color</label>
                            <input type="color" id="SidebarTextColor" name="SidebarTextColor" class="form-control form-control-color w-100"
                                   value="{{ old('SidebarTextColor', $profile->SidebarTextColor ?? '#5b6b79') }}">
                        </div>
                        <div class="col-md-3">
                            <div id="colorSchemePreview" class="rounded p-3 text-center" style="border:1px solid #dee2e6;">
                                <span id="colorSchemePreviewText" style="font-weight:600;">Sidebar Preview</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="reset_color_scheme" name="reset_color_scheme" value="1">
                                <label class="form-check-label" for="reset_color_scheme">Reset to the default theme colors on save (ignores the pickers above)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var bg = document.getElementById('SidebarBackgroundColor');
            var text = document.getElementById('SidebarTextColor');
            var preview = document.getElementById('colorSchemePreview');

            function updatePreview() {
                preview.style.backgroundColor = bg.value;
                preview.style.color = text.value;
            }

            [bg, text].forEach(function (el) {
                el.addEventListener('input', updatePreview);
            });
            updatePreview();
        })();
    </script>
@endsection
