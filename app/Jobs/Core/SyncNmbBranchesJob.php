<?php

namespace App\Jobs\Core;

use App\Models\Core\BranchSyncRun;
use App\Models\Core\SyncedBranch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SyncNmbBranchesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CANCELLATION_EXCEPTION_PREFIX = '__BRANCH_SYNC_CANCELLED__:';

    public int $timeout = 1800;

    public function __construct(public int $syncRunId)
    {
    }

    public function handle(): void
    {
        $syncRun = BranchSyncRun::find($this->syncRunId);
        if (! $syncRun) {
            return;
        }

        $userId = $syncRun->CreatedBy ?? 1;

        try {
            if ($this->syncWasCancelled()) {
                return;
            }

            $syncRun->update([
                'Status' => 'running',
                'Message' => 'Starting Nimble branch sync.',
                'SyncError' => null,
                'StartedAt' => $syncRun->StartedAt ?? now(),
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);

            [
                'login_url' => $loginUrl,
                'base_url' => $baseUrl,
                'bank_id' => $bankId,
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret,
            ] = $this->getNimbleConfiguration();

            $accessToken = $this->getNmbAccessToken($loginUrl, $consumerKey, $consumerSecret);
            $endpoint = rtrim($baseUrl, '/') . '/Branch/SyncBranches';

            $pageSize = max(1, min(5000, (int) ($syncRun->PageSize ?: 1000)));
            $nextCursor = null;
            $nextPageUrl = null;
            $page = 0;
            $recordsSynced = 0;
            $totalRecords = null;
            $seenCursors = [];

            DB::table('t_SyncedBranchesStaging')->truncate();

            while (true) {
                $this->abortIfCancelled($userId, 'Branch sync cancelled before fetching the next page.', true);
                $page++;

                $response = $this->fetchBranchesPage(
                    endpoint: $endpoint,
                    accessToken: $accessToken,
                    bankId: $bankId,
                    pageSize: $pageSize,
                    nextCursor: $nextCursor,
                    nextPageUrl: $nextPageUrl
                );

                if (! $response->successful()) {
                    throw new RuntimeException('Nimble branch sync API failed with HTTP ' . $response->status() . '.');
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    throw new RuntimeException('Nimble branch sync API returned invalid JSON payload.');
                }

                $status = strtoupper((string) Arr::get($payload, 'Status', ''));
                $code = (int) Arr::get($payload, 'Code', 0);
                if (! in_array($status, ['00', '000', 'OK'], true) || $code !== 200) {
                    throw new RuntimeException('Nimble branch sync API returned non-success status payload.');
                }

                $rows = Arr::get($payload, 'Data', []);
                if (! is_array($rows)) {
                    $rows = [];
                }

                $now = now();
                $preparedRows = collect($rows)
                    ->map(fn ($row) => $this->mapBranchRow(is_array($row) ? $row : (array) $row, $userId, $now))
                    ->filter()
                    ->unique('BranchID')
                    ->values()
                    ->all();

                if (! empty($preparedRows)) {
                    // SQL Server has a strict parameter limit; write in smaller batches.
                    foreach (array_chunk($preparedRows, 50) as $batch) {
                        DB::table('t_SyncedBranchesStaging')->upsert(
                            $batch,
                            ['BranchID'],
                            [
                                'Name',
                                'Address',
                                'Address2',
                                'City',
                                'State',
                                'Zip',
                                'Country',
                                'Phone',
                                'Fax',
                                'Email',
                                'IsHQ',
                                'IsActive',
                                'Source',
                                'SourceTable',
                                'IsSynced',
                                'ModifiedBy',
                                'ModifiedOn',
                            ]
                        );
                    }
                }

                $recordsSynced += count($preparedRows);
                $totalRecordsValue = Arr::get($payload, 'TotalRecords');
                $totalRecords = is_numeric($totalRecordsValue) ? (int) $totalRecordsValue : $totalRecords;

                $nextCursorValue = trim((string) Arr::get($payload, 'NextCursor', ''));
                $nextPageUrlValue = trim((string) Arr::get($payload, 'NextPageUrl', ''));

                $this->abortIfCancelled($userId, 'Branch sync cancelled while processing staged records.', true);

                $syncRun->update([
                    'Status' => 'running',
                    'Message' => 'Staged page ' . $page . '.',
                    'RecordsSynced' => $recordsSynced,
                    'TotalRecords' => $totalRecords,
                    'CurrentPage' => $page,
                    'PageSize' => $pageSize,
                    'LastCursor' => $nextCursorValue !== '' ? $nextCursorValue : null,
                    'ModifiedBy' => $userId,
                    'ModifiedOn' => now(),
                ]);

                if ($nextCursorValue === '' && $nextPageUrlValue === '') {
                    break;
                }

                if ($nextCursorValue !== '') {
                    if (isset($seenCursors[$nextCursorValue])) {
                        throw new RuntimeException('Pagination loop detected. Cursor repeated: ' . $nextCursorValue);
                    }
                    $seenCursors[$nextCursorValue] = true;
                }

                if (empty($rows)) {
                    break;
                }

                if ($totalRecords !== null && $recordsSynced >= $totalRecords) {
                    break;
                }

                $nextCursor = $nextCursorValue !== '' ? $nextCursorValue : null;
                $nextPageUrl = $nextPageUrlValue !== '' ? $nextPageUrlValue : null;
            }

            $this->abortIfCancelled($userId, 'Branch sync cancelled before applying the staged snapshot.', true);

            $syncRun->update([
                'Status' => 'running',
                'Message' => 'Applying staged snapshot.',
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);

            DB::transaction(function () {
                SyncedBranch::truncate();

                DB::table('t_SyncedBranches')->insertUsing(
                    [
                        'BranchID',
                        'Name',
                        'Address',
                        'Address2',
                        'City',
                        'State',
                        'Zip',
                        'Country',
                        'Phone',
                        'Fax',
                        'Email',
                        'IsHQ',
                        'IsActive',
                        'Source',
                        'SourceTable',
                        'IsSynced',
                        'CreatedBy',
                        'CreatedOn',
                        'ModifiedBy',
                        'ModifiedOn',
                        'DeletedBy',
                        'DeletedOn',
                    ],
                    DB::table('t_SyncedBranchesStaging')->select(
                        'BranchID',
                        'Name',
                        'Address',
                        'Address2',
                        'City',
                        'State',
                        'Zip',
                        'Country',
                        'Phone',
                        'Fax',
                        'Email',
                        'IsHQ',
                        'IsActive',
                        'Source',
                        'SourceTable',
                        'IsSynced',
                        'CreatedBy',
                        'CreatedOn',
                        'ModifiedBy',
                        'ModifiedOn',
                        'DeletedBy',
                        'DeletedOn'
                    )
                );
            });

            $appliedCount = SyncedBranch::count();

            $this->abortIfCancelled($userId, 'Branch sync cancelled before finalizing the synced snapshot.', true);

            DB::table('t_SyncedBranchesStaging')->truncate();

            $syncRun->update([
                'Status' => 'completed',
                'Message' => 'Nimble branch sync completed successfully. Snapshot applied.',
                'CompletedAt' => now(),
                'RecordsSynced' => $appliedCount,
                'TotalRecords' => $totalRecords ?? $recordsSynced,
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isCancellationException($e)) {
                Log::info('Nimble branch sync job cancelled', [
                    'syncRunId' => $this->syncRunId,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            Log::error('Nimble branch sync job failed', [
                'syncRunId' => $this->syncRunId,
                'message' => $e->getMessage(),
            ]);

            $syncRun->refresh();
            if ($syncRun->Status === 'cancelled') {
                return;
            }

            $syncRun->update([
                'Status' => 'failed',
                'Message' => 'Nimble branch sync failed: ' . mb_substr($e->getMessage(), 0, 300),
                'SyncError' => $e->getMessage(),
                'CompletedAt' => now(),
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);
        }
    }

    private function syncWasCancelled(): bool
    {
        return BranchSyncRun::query()
            ->whereKey($this->syncRunId)
            ->where('Status', 'cancelled')
            ->exists();
    }

    private function abortIfCancelled(int $userId, string $message, bool $truncateStaging = false): void
    {
        if (! $this->syncWasCancelled()) {
            return;
        }

        if ($truncateStaging) {
            DB::table('t_SyncedBranchesStaging')->truncate();
        }

        BranchSyncRun::query()
            ->whereKey($this->syncRunId)
            ->update([
                'Status' => 'cancelled',
                'Message' => $message,
                'SyncError' => null,
                'CompletedAt' => now(),
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);

        throw new RuntimeException(self::CANCELLATION_EXCEPTION_PREFIX . $message);
    }

    private function isCancellationException(\Throwable $e): bool
    {
        return str_starts_with($e->getMessage(), self::CANCELLATION_EXCEPTION_PREFIX);
    }

    private function getNimbleConfiguration(): array
    {
        $config = [
            'login_url' => trim((string) config('services.nimble.login_url')),
            'base_url' => trim((string) config('services.nimble.base_url')),
            'bank_id' => trim((string) config('services.nimble.bank_id', '00')),
            'consumer_key' => trim((string) config('services.nimble.consumer_key')),
            'consumer_secret' => trim((string) config('services.nimble.consumer_secret')),
        ];

        $missing = [];
        foreach (['login_url', 'base_url', 'consumer_key', 'consumer_secret', 'bank_id'] as $key) {
            if ($config[$key] === '') {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Missing Nimble configuration: ' . implode(', ', $missing) . '.');
        }

        return $config;
    }

    private function getNmbAccessToken(string $loginUrl, string $consumerKey, string $consumerSecret): string
    {
        $response = Http::withoutVerifying()
            ->timeout(60)
            ->asJson()
            ->post($loginUrl, [
                'ConsumerKey' => $consumerKey,
                'ConsumerSecret' => $consumerSecret,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Nimble login failed with HTTP ' . $response->status() . '.');
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Nimble login returned invalid JSON payload.');
        }

        $accessToken = Arr::get($body, 'AccessToken')
            ?? Arr::get($body, 'accessToken')
            ?? Arr::get($body, 'access_token')
            ?? Arr::get($body, 'token');

        $statusCode = strtoupper((string) Arr::get($body, 'StatusCode', ''));
        $success = Arr::get($body, 'Success', true);
        $statusOk = in_array($statusCode, ['000', '00', 'OK'], true);

        if (! $accessToken || ! $success || ! $statusOk) {
            throw new RuntimeException('Nimble login did not return a valid access token.');
        }

        return (string) $accessToken;
    }

    private function fetchBranchesPage(
        string $endpoint,
        string $accessToken,
        string $bankId,
        int $pageSize,
        ?string $nextCursor,
        ?string $nextPageUrl
    ): \Illuminate\Http\Client\Response {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'token' => $accessToken,
        ];

        if ($nextPageUrl) {
            $getResponse = Http::withoutVerifying()
                ->timeout(180)
                ->withHeaders($headers)
                ->get($nextPageUrl);

            if ($getResponse->successful()) {
                return $getResponse;
            }
        }

        $payload = [
            'BankID' => $bankId,
            'Source' => 'NIMBLE',
            'PageSize' => $pageSize,
        ];

        if ($nextCursor) {
            $payload['LastCursor'] = $nextCursor;
            $payload['lastCursor'] = $nextCursor;
        }

        return Http::withoutVerifying()
            ->timeout(180)
            ->withHeaders($headers)
            ->post($endpoint, $payload);
    }

    private function mapBranchRow(array $row, int $userId, $now): ?array
    {
        $branchId = $this->pick($row, ['branchID', 'BranchID', 'branchId', 'OurBranchID', 'ourBranchID']);
        if (! $branchId) {
            return null;
        }

        $hqValue = $this->pick($row, ['isHQ', 'IsHQ', 'isHq']);
        $isHQ = filter_var($hqValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isHQ === null) {
            $isHQ = false;
        }

        $activeValue = $this->pick($row, ['isActive', 'IsActive', 'active', 'Active']);
        $isActive = filter_var($activeValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            $isActive = true;
        }

        return [
            'BranchID' => (string) $branchId,
            'Name' => (string) ($this->pick($row, ['branchName', 'BranchName', 'name', 'Name']) ?? ''),
            'Address' => $this->pick($row, ['address', 'Address', 'address1', 'Address1']),
            'Address2' => $this->pick($row, ['address2', 'Address2']),
            'City' => $this->pick($row, ['city', 'City']),
            'State' => $this->pick($row, ['state', 'State']),
            'Zip' => $this->pick($row, ['zip', 'Zip', 'postalCode', 'PostalCode']),
            'Country' => $this->pick($row, ['country', 'Country']),
            'Phone' => $this->pick($row, ['phone', 'Phone']),
            'Fax' => $this->pick($row, ['fax', 'Fax']),
            'Email' => $this->pick($row, ['email', 'Email']),
            'IsHQ' => $isHQ ? 1 : 0,
            'IsActive' => $isActive ? 1 : 0,
            'Source' => (string) ($this->pick($row, ['source', 'Source']) ?? 'NIMBLE'),
            'SourceTable' => (string) ($this->pick($row, ['sourceTable', 'SourceTable']) ?? ''),
            'IsSynced' => 1,
            'CreatedBy' => $userId,
            'CreatedOn' => $now,
            'ModifiedBy' => $userId,
            'ModifiedOn' => $now,
            'DeletedBy' => null,
            'DeletedOn' => null,
        ];
    }

    private function pick(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }
}
