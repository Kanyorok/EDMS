<?php

namespace App\Services\DMS;

use App\Models\DMS\DocumentDashboardLayouts;
use App\Models\DMS\DMSTags;
use App\Models\DMS\Repository;
use App\Models\DMS\DocumentVersion;
use App\Models\DMS\DocumentLegalHold;

class DocumentDashboardLayoutsService
{
    /**
     * Return both built-in and persisted layouts keyed by layout key.
     */

    public function availableLayouts(): array
{
    return [

        'lead_overview' => [
            'key' => 'lead_overview',
            'name' => 'Lead Overview',
            'table' => 'Leads',
            'description' => 'Overview of lead generation, acquisition, and customer pipeline activity.',
            'cards' => [
                [
                    'title' => 'Total Leads',
                    'dataKey' => 'totalLeads',
                    'meta' => 'All generated leads'
                ],
                [
                    'title' => 'Scheduled Meetings',
                    'dataKey' => 'scheduledMeetings',
                    'meta' => 'Lead appointments'
                ],
                [
                    'title' => 'Email Campaigns',
                    'dataKey' => 'emailCampaigns',
                    'meta' => 'Marketing campaigns'
                ],
                [
                    'title' => 'Total Tickets',
                    'dataKey' => 'totalTickets',
                    'meta' => 'Customer issues'
                ],
            ],
        ],

        'customer_engagement' => [
            'key' => 'customer_engagement',
            'name' => 'Customer Engagement',
            'table' => 'Reviews',
            'description' => 'Measure customer interaction, satisfaction, and engagement activities.',
            'cards' => [
                [
                    'title' => 'Total Reviews',
                    'dataKey' => 'totalReviews',
                    'meta' => 'Customer feedback'
                ],
                [
                    'title' => 'Total Surveys',
                    'dataKey' => 'totalSurveys',
                    'meta' => 'Survey responses'
                ],
                [
                    'title' => 'Scheduled Meetings',
                    'dataKey' => 'scheduledMeetings',
                    'meta' => 'Customer meetings'
                ],
                [
                    'title' => 'Total Tickets',
                    'dataKey' => 'totalTickets',
                    'meta' => 'Support requests'
                ],
            ],
        ],

        'support_overview' => [
            'key' => 'support_overview',
            'name' => 'Support Overview',
            'table' => 'Tickets',
            'description' => 'Track support requests and customer service performance.',
            'cards' => [
                [
                    'title' => 'Total Tickets',
                    'dataKey' => 'totalTickets',
                    'meta' => 'Support requests'
                ],
                [
                    'title' => 'Total Reviews',
                    'dataKey' => 'totalReviews',
                    'meta' => 'Customer feedback'
                ],
                [
                    'title' => 'Total Surveys',
                    'dataKey' => 'totalSurveys',
                    'meta' => 'Survey responses'
                ],
                [
                    'title' => 'Scheduled Meetings',
                    'dataKey' => 'scheduledMeetings',
                    'meta' => 'Customer meetings'
                ],
            ],
        ],

        'campaign_overview' => [
            'key' => 'campaign_overview',
            'name' => 'Campaign Overview',
            'table' => 'Campaigns',
            'description' => 'Monitor marketing campaigns and lead generation activities.',
            'cards' => [
                [
                    'title' => 'Email Campaigns',
                    'dataKey' => 'emailCampaigns',
                    'meta' => 'Marketing campaigns'
                ],
                [
                    'title' => 'Total Leads',
                    'dataKey' => 'totalLeads',
                    'meta' => 'Generated leads'
                ],
                [
                    'title' => 'Scheduled Meetings',
                    'dataKey' => 'scheduledMeetings',
                    'meta' => 'Campaign appointments'
                ],
                [
                    'title' => 'Total Reviews',
                    'dataKey' => 'totalReviews',
                    'meta' => 'Customer responses'
                ],
            ],
        ],

        'meeting_overview' => [
            'key' => 'meeting_overview',
            'name' => 'Meeting Overview',
            'table' => 'Meetings',
            'description' => 'Track meetings, appointments, and customer follow-up activities.',
            'cards' => [
                [
                    'title' => 'Scheduled Meetings',
                    'dataKey' => 'scheduledMeetings',
                    'meta' => 'Planned meetings'
                ],
                [
                    'title' => 'Total Leads',
                    'dataKey' => 'totalLeads',
                    'meta' => 'Lead pipeline'
                ],
                [
                    'title' => 'Total Tickets',
                    'dataKey' => 'totalTickets',
                    'meta' => 'Support cases'
                ],
                [
                    'title' => 'Total Surveys',
                    'dataKey' => 'totalSurveys',
                    'meta' => 'Customer surveys'
                ],
            ],
        ],

    ];
}
    public function allLayouts(): array
    {
        $built = $this->availableLayouts();

        $persisted = DocumentDashboardLayouts::query()->get()->mapWithKeys(function ($item) {
            $cards = is_string($item->Cards) ? json_decode($item->Cards, true) : ($item->Cards ?? []);
            return [$item->Key => [
                'key' => $item->Key,
                'name' => $item->Name,
                'table' => $item->Name,
                'description' => $item->Description,
                'cards' => is_array($cards) ? $cards : [],
            ]];
        })->toArray();

        return array_merge( $built, $persisted);
    }

    public function getLayout(string $layoutKey): array
    {
        $layouts = $this->allLayouts();

        return $layouts[$layoutKey] ?? $layouts['lead_overview'];
    }

    public function getLayoutCards(string $layoutKey): array
    {
        $values = $this->getWidgetValues();

        $layout = $this->getLayout($layoutKey);

        return array_map(function ($card) use ($values) {
            $card['value'] = $values[$card['dataKey']] ?? 0;

            return $card;
        }, $layout['cards']);
    }

    /**
     * Available dashboard metrics/data sources.
     */
    public function getWidgetDisplayOptions(): array
  {
    return [

        // DMS Tags
        'totalDMSTags',

        // Repositories
        'totalRepositories',

        // Document Versions
        'totalDocumentVersions',

        // Document Legal Hold
        'totalDocumentLegalHolds',
    ];
}

/**
 * Actual values displayed on dashboard.
 */
public function getWidgetValues(): array
{
    return [

        // DMS Tags
        'totalDMSTags' => DMSTags::count(),

        // Repositories
        'totalRepositories' => Repository::count(),

        // Document Versions
        'totalDocumentVersions' => DocumentVersion::count(),

        // Document Legal Hold
        'totalDocumentLegalHolds' => DocumentLegalHold::count(),
    ];
  }
}

