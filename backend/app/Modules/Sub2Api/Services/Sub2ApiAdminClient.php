<?php

namespace App\Modules\Sub2Api\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Sub2ApiAdminClient
{
    public function user(string|int $id): array
    {
        return $this->get('/api/v1/admin/users/'.$id);
    }

    public function updateUserBalance(string|int $id, float $balance, string $operation, string $notes, string $idempotencyKey): array
    {
        $res = $this->http()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/api/v1/admin/users/'.$id.'/balance', [
                'balance' => $balance,
                'operation' => $operation,
                'notes' => $notes,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Sub2API 用户余额调整失败：HTTP '.$res->status());
        }

        return $res->json() ?? [];
    }

    public function userBalanceHistory(string|int $id, int $page = 1, int $pageSize = 20): array
    {
        return $this->get('/api/v1/admin/users/'.$id.'/balance-history?page='.$page.'&page_size='.$pageSize);
    }

    public function accounts(): array
    {
        return $this->get('/api/v1/admin/accounts');
    }

    public function account(string|int $id): array
    {
        return $this->get('/api/v1/admin/accounts/'.$id);
    }

    public function usage(string|int $id): array
    {
        return $this->get('/api/v1/admin/accounts/'.$id.'/usage');
    }

    public function stats(string|int $id): array
    {
        return $this->get('/api/v1/admin/accounts/'.$id.'/stats');
    }

    public function todayStats(string|int $id): array
    {
        return $this->get('/api/v1/admin/accounts/'.$id.'/today-stats');
    }

    private function get(string $path): array
    {
        $res = $this->http()->get($path);

        if (! $res->successful()) {
            throw new RuntimeException('Sub2API Admin API 调用失败：HTTP '.$res->status());
        }

        return $res->json() ?? [];
    }

    private function http(): PendingRequest
    {
        $baseUrl = rtrim((string) config('sub2rebate.sub2api_base_url'), '/');
        $apiKey = (string) config('sub2rebate.sub2api_admin_api_key');

        if ($baseUrl === '') {
            throw new RuntimeException('缺少 SUB2API_BASE_URL');
        }

        if ($apiKey === '') {
            $token = $this->adminToken($baseUrl);
            if ($token === '') {
                throw new RuntimeException('缺少 SUB2API_ADMIN_API_KEY 或 SUB2API_ADMIN_EMAIL/SUB2API_ADMIN_PASSWORD');
            }

            return Http::baseUrl($baseUrl)
                ->timeout((int) config('sub2rebate.sub2api_admin_timeout', 10))
                ->acceptJson()
                ->withToken($token);
        }

        return Http::baseUrl($baseUrl)
            ->timeout((int) config('sub2rebate.sub2api_admin_timeout', 10))
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => $apiKey,
            ]);
    }

    private function adminToken(string $baseUrl): string
    {
        $email = (string) config('sub2rebate.sub2api_admin_email');
        $password = (string) config('sub2rebate.sub2api_admin_password');

        if ($email === '' || $password === '') {
            return '';
        }

        $res = Http::baseUrl($baseUrl)
            ->timeout((int) config('sub2rebate.sub2api_admin_timeout', 10))
            ->acceptJson()
            ->post('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('Sub2API 管理员登录失败：HTTP '.$res->status());
        }

        return (string) data_get($res->json(), 'data.access_token', '');
    }
}
