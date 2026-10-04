<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Core\IntegrationsEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Models\Settings\APICredential;
use App\Models\Settings\OrganizationProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Handle the incoming request.
     * @throws AuthorizationException
     */
    public function lists(): View
    {
        $this->authorize('view', CodeDetail::class);

        return view('settings.lists');
    }

    /**
     * Handle the incoming request.
     * @throws AuthorizationException
     */
    public function users(): View
    {
        $this->authorize('settings', User::class);

        return view('settings.users');
    }

    public function integrations(): View
    {
        $this->authorize('viewAny', APICredential::class);
        $all = APICredential::all();

        $configuration = static function (IntegrationsEnum $integration, mixed $fallback = null) use ($all): mixed {
            $credential = $all->firstWhere('Integration', $integration->value);

            return $credential instanceof APICredential ? $credential->Configuration : $fallback;
        };

        return view('settings.integrations', [
            'twitterConfig' => $configuration(IntegrationsEnum::Twitter, new APICredential()),
            'facebookConfig' => $configuration(IntegrationsEnum::Facebook, new APICredential()),
            'smsConfig' => $configuration(IntegrationsEnum::SMS, new APICredential()),
            'cbsConfig' => $configuration(IntegrationsEnum::CoreBanking, new APICredential()),
            'emailConfig' => $configuration(IntegrationsEnum::Email, new APICredential()),
            'channelsConfig' => $configuration(IntegrationsEnum::Channels, new APICredential()),
            'llmConfig' => $configuration(IntegrationsEnum::LLM, new APICredential()),
            'infoBipConfig' => $configuration(IntegrationsEnum::InfoBip, new APICredential()),
            'srsConfig' => $configuration(IntegrationsEnum::ReportService, new APICredential()),
            'iTrackConfig' => $configuration(IntegrationsEnum::iTrack, new APICredential()),
            'crdbConfig' => $configuration(IntegrationsEnum::CRDB, new APICredential()),
            'gmaps' => $configuration(IntegrationsEnum::GoogleMaps),
            'dmsConfig' => $configuration(IntegrationsEnum::DMSCoreBanking),
            'organizationProfile' => OrganizationProfile::current(),
        ]);
    }

}
