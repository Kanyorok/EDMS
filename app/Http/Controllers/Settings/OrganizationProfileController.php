<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Core\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Core\Currency;
use App\Models\Settings\OrganizationProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationProfileController extends Controller
{
    public function index(): View
    {
        $this->authorizeAccess();

        $profile = OrganizationProfile::current();
        $canSetBaseCurrency = auth()->user()?->can(PermissionEnum::SetBaseCurrency->value) ?? false;
        $currencies = Currency::query()
            ->orderBy('Name')
            ->get(['Id', 'Code', 'Name', 'Symbol']);

        return view('settings.organization-profile', compact('profile', 'currencies', 'canSetBaseCurrency'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAccess();
        $canSetBaseCurrency = $request->user()?->can(PermissionEnum::SetBaseCurrency->value) ?? false;

        $baseCurrencyRules = ['nullable', 'integer'];
        if (Schema::hasTable('t_Currencies')) {
            $baseCurrencyRules[] = Rule::exists('t_Currencies', 'Id');
        }

        $data = $request->validate([
            'BankName' => ['required', 'string', 'max:150'],
            'ShortName' => ['nullable', 'string', 'max:50'],
            'BankCode' => ['nullable', 'string', 'max:50'],
            'SwiftCode' => ['nullable', 'string', 'max:50'],
            'ClearingCode' => ['nullable', 'string', 'max:50'],
            'Address1' => ['nullable', 'string', 'max:255'],
            'Address2' => ['nullable', 'string', 'max:255'],
            'ZipCode' => ['nullable', 'string', 'max:50'],
            'Phone1' => ['nullable', 'string', 'max:50'],
            'Phone2' => ['nullable', 'string', 'max:50'],
            'Mobile' => ['nullable', 'string', 'max:50'],
            'Fax' => ['nullable', 'string', 'max:50'],
            'EmailID' => ['nullable', 'email', 'max:150'],
            'Website' => ['nullable', 'string', 'max:150'],
            'BankRegNumber' => ['nullable', 'string', 'max:100'],
            'EmployerTaxPIN' => ['nullable', 'string', 'max:50'],
            'OrganizationMotto' => ['nullable', 'string', 'max:200'],
            'BaseCurrencyID' => [Rule::excludeIf(! $canSetBaseCurrency), ...$baseCurrencyRules],
            'OrganizationLogoFile' => ['nullable', 'image', 'max:2048'],
            'remove_organization_logo' => ['nullable', 'boolean'],
            'IdleSessionTimeoutMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'PrimaryColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'SidebarBackgroundColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'SidebarTextColor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reset_color_scheme' => ['nullable', 'boolean'],
        ]);

        $profile = OrganizationProfile::current() ?? new OrganizationProfile();
        $actorId = $request->user()->Id ?? $request->user()->id ?? null;
        $currentLogo = $profile->OrganizationLogo;

        if ($request->boolean('remove_organization_logo')) {
            $this->deleteManagedLogo($currentLogo);
            $data['OrganizationLogo'] = null;
        }

        if ($request->hasFile('OrganizationLogoFile')) {
            $this->deleteManagedLogo($currentLogo);
            $path = $request->file('OrganizationLogoFile')->store('branding/organization', 'public');
            $data['OrganizationLogo'] = 'storage/' . $path;
        }

        if ($request->boolean('reset_color_scheme')) {
            $data['PrimaryColor'] = null;
            $data['SidebarBackgroundColor'] = null;
            $data['SidebarTextColor'] = null;
        }

        unset($data['OrganizationLogoFile'], $data['remove_organization_logo'], $data['reset_color_scheme']);

        $data['ModifiedBy'] = $actorId;
        $data['ModifiedOn'] = now();

        if ($profile->exists) {
            $profile->update($data);
        } else {
            $data['CreatedBy'] = $actorId;
            $data['CreatedOn'] = now();
            $data['IsActive'] = 1;
            OrganizationProfile::create($data);
        }

        return redirect()->back()->with('success', 'Organization profile saved.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can(PermissionEnum::OrganizationProfile->value), 403);
    }

    private function deleteManagedLogo(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $relativePath = ltrim($path, '/');
        if (! str_starts_with($relativePath, 'storage/branding/organization/')) {
            return;
        }

        Storage::disk('public')->delete(substr($relativePath, strlen('storage/')));
    }
}
