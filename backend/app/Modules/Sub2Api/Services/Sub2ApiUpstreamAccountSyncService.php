<?php

namespace App\Modules\Sub2Api\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Sub2Api\Models\Sub2ApiUpstreamAccount;
use Throwable;

class Sub2ApiUpstreamAccountSyncService
{
    public function __construct(
        private readonly Sub2ApiAdminClient $client,
        private readonly AuditLogService $audits,
    ) {
    }

    public function syncAll(bool $withDetails = false, ?User $actor = null): array
    {
        try {
            $payload = $this->client->accounts();
            $rows = $this->listRows($payload);
            $count = 0;

            foreach ($rows as $row) {
                $account = $this->saveAccount($row);
                if ($withDetails) {
                    $this->syncDetails($account, $actor);
                }
                $count++;
            }

            return [
                'ok' => true,
                'count' => $count,
            ];
        } catch (Throwable $e) {
            $this->auditFailure($actor, null, $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncDetails(Sub2ApiUpstreamAccount $account, ?User $actor = null): array
    {
        $id = $account->sub2api_id;

        try {
            $raw = $this->client->account($id);
            $usage = $this->client->usage($id);
            $stats = $this->client->stats($id);
            $todayStats = $this->client->todayStats($id);

            $row = $this->firstRow($raw);
            $account->fill($this->mapAccount($row ?: $account->raw_account ?: [], $account->sub2api_id));
            $account->raw_account = $raw;
            $account->raw_usage = $usage;
            $account->raw_stats = $stats;
            $account->raw_today_stats = $todayStats;
            $account->last_synced_at = now();
            $account->last_error = null;
            $account->save();

            return [
                'ok' => true,
                'account' => $account,
            ];
        } catch (Throwable $e) {
            $account->last_error = $e->getMessage();
            $account->last_synced_at = now();
            $account->save();
            $this->auditFailure($actor, $account, $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function auditFailure(?User $actor, ?Sub2ApiUpstreamAccount $account, string $message): void
    {
        $this->audits->record('sub2api', 'upstream_account.sync_failed', [
            'actor' => $actor,
            'subject_type' => $account instanceof Sub2ApiUpstreamAccount ? Sub2ApiUpstreamAccount::class : null,
            'subject_id' => $account?->id,
            'after_values' => [
                'sub2api_id' => $account?->sub2api_id,
                'message' => $message,
            ],
            'remark' => $message,
        ]);
    }

    private function saveAccount(array $row): Sub2ApiUpstreamAccount
    {
        $mapped = $this->mapAccount($row);
        $account = Sub2ApiUpstreamAccount::query()->updateOrCreate(
            ['sub2api_id' => $mapped['sub2api_id']],
            $mapped + [
                'raw_account' => $row,
                'last_synced_at' => now(),
                'last_error' => null,
            ]
        );

        return $account;
    }

    private function listRows(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        if (isset($data['list']) && is_array($data['list'])) {
            return array_values(array_filter($data['list'], 'is_array'));
        }

        if (isset($data['items']) && is_array($data['items'])) {
            return array_values(array_filter($data['items'], 'is_array'));
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }

    private function firstRow(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        if (is_array($data) && ! array_is_list($data)) {
            return $data;
        }

        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return [];
    }

    private function mapAccount(array $row, ?string $fallbackId = null): array
    {
        $id = $row['id'] ?? $row['account_id'] ?? $row['uuid'] ?? $fallbackId;

        return [
            'sub2api_id' => (string) ($id ?: sha1(json_encode($row, JSON_UNESCAPED_UNICODE))),
            'name' => $this->str($row, ['name', 'account_name', 'display_name', 'email']),
            'provider' => $this->str($row, ['provider', 'platform', 'channel', 'type']),
            'model' => $this->str($row, ['model', 'model_name']),
            'status' => $this->str($row, ['status', 'state']),
            'used_quota' => $this->num($row, ['used_quota', 'usedQuota', 'used', 'usage']),
            'total_quota' => $this->num($row, ['total_quota', 'totalQuota', 'quota', 'limit']),
            'request_count' => (int) $this->num($row, ['request_count', 'requestCount', 'requests', 'total_requests']),
            'last_used_at' => $this->time($row, ['last_used_at', 'lastUsedAt', 'last_used']),
        ];
    }

    private function str(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }

    private function num(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return number_format((float) $row[$key], 6, '.', '');
            }
        }

        return '0.000000';
    }

    private function time(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }
}
