<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Sub2Api\Models\Sub2ApiUpstreamAccount;
use App\Modules\Sub2Api\Services\Sub2ApiAdminClient;
use App\Modules\Sub2Api\Services\Sub2ApiUpstreamAccountSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Sub2ApiUpstreamAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_client_uses_x_api_key_header(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);

        Http::fake([
            'https://sub2api.test/api/v1/admin/accounts' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [],
                ],
            ]),
        ]);

        app(Sub2ApiAdminClient::class)->accounts();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/accounts'
            && $request->hasHeader('x-api-key', 'secret-key'));
    }

    public function test_admin_client_falls_back_to_admin_jwt_when_api_key_missing(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => '',
            'sub2rebate.sub2api_admin_email' => 'admin@example.com',
            'sub2rebate.sub2api_admin_password' => 'pass',
        ]);

        Http::fake([
            'https://sub2api.test/api/v1/auth/login' => Http::response([
                'code' => 0,
                'data' => [
                    'access_token' => 'jwt-token',
                ],
            ]),
            'https://sub2api.test/api/v1/admin/accounts' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [],
                ],
            ]),
        ]);

        app(Sub2ApiAdminClient::class)->accounts();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/auth/login'
            && $request['email'] === 'admin@example.com'
            && $request['password'] === 'pass');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://sub2api.test/api/v1/admin/accounts'
            && $request->hasHeader('Authorization', 'Bearer jwt-token'));
    }

    public function test_sync_all_saves_upstream_account_snapshots_without_rebate_events(): void
    {
        $this->fakeConfig();

        Http::fake([
            'https://sub2api.test/api/v1/admin/accounts' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [
                        [
                            'id' => 101,
                            'name' => 'Claude 上游 1',
                            'provider' => 'claude',
                            'model' => 'claude-sonnet',
                            'status' => 'active',
                            'used_quota' => '12.5',
                            'total_quota' => '100',
                            'request_count' => 7,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncAll();

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['count']);
        $this->assertDatabaseHas('sub2api_upstream_accounts', [
            'sub2api_id' => '101',
            'name' => 'Claude 上游 1',
            'provider' => 'claude',
            'status' => 'active',
            'used_quota' => '12.5',
            'total_quota' => '100',
            'request_count' => 7,
        ]);
        $this->assertDatabaseCount('rebate_events', 0);
    }

    public function test_sync_details_reads_detail_usage_stats_and_today_stats(): void
    {
        $this->fakeConfig();

        $account = Sub2ApiUpstreamAccount::query()->create([
            'sub2api_id' => '101',
            'name' => 'old',
        ]);

        Http::fake([
            'https://sub2api.test/api/v1/admin/accounts/101' => Http::response([
                'code' => 0,
                'data' => [
                    'id' => 101,
                    'name' => 'Claude 上游 1',
                    'provider' => 'claude',
                    'status' => 'active',
                ],
            ]),
            'https://sub2api.test/api/v1/admin/accounts/101/usage' => Http::response(['data' => ['used' => 10]]),
            'https://sub2api.test/api/v1/admin/accounts/101/stats' => Http::response(['data' => ['requests' => 20]]),
            'https://sub2api.test/api/v1/admin/accounts/101/today-stats' => Http::response(['data' => ['requests' => 3]]),
        ]);

        $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncDetails($account);

        $this->assertTrue($result['ok']);
        $account->refresh();
        $this->assertSame('Claude 上游 1', $account->name);
        $this->assertSame(['data' => ['used' => 10]], $account->raw_usage);
        $this->assertSame(['data' => ['requests' => 20]], $account->raw_stats);
        $this->assertSame(['data' => ['requests' => 3]], $account->raw_today_stats);
        $this->assertNull($account->last_error);
    }

    public function test_sync_failure_writes_audit_log_and_does_not_throw(): void
    {
        $this->fakeConfig();
        $admin = $this->admin();

        Http::fake([
            'https://sub2api.test/api/v1/admin/accounts' => Http::response(['message' => 'bad'], 500),
        ]);

        $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncAll(false, $admin);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('HTTP 500', $result['message']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'module' => 'sub2api',
            'action' => 'upstream_account.sync_failed',
        ]);
        $this->assertDatabaseCount('rebate_events', 0);
    }

    private function fakeConfig(): void
    {
        config([
            'sub2rebate.sub2api_base_url' => 'https://sub2api.test',
            'sub2rebate.sub2api_admin_api_key' => 'secret-key',
        ]);
    }

    private function admin(): User
    {
        return User::query()->create([
            'id' => 9001,
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
