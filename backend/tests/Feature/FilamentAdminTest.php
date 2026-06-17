<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Sub2Api\Models\Sub2ApiUpstreamAccount;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sub2Rebate');
    }

    public function test_admin_dashboard_requires_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_user_panel_access_allows_only_active_admin(): void
    {
        $panel = Filament::getPanel('admin');
        $admin = $this->user(1001, 'admin', 'active');
        $normal = $this->user(1002, 'user', 'active');
        $disabledAdmin = $this->user(1003, 'admin', 'disabled');

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($normal->canAccessPanel($panel));
        $this->assertFalse($disabledAdmin->canAccessPanel($panel));
    }

    public function test_admin_resource_pages_are_available_to_admin(): void
    {
        $admin = $this->user(1001, 'admin', 'active');
        Sub2ApiUpstreamAccount::query()->create([
            'sub2api_id' => 'upstream-1',
            'name' => '上游账号 1',
            'status' => 'active',
        ]);

        $paths = [
            '/admin/users',
            '/admin/config-items',
            '/admin/withdraw-records',
            '/admin/rebate-events',
            '/admin/rebate-records',
            '/admin/rebate-balances',
            '/admin/user-rebate-progresses',
            '/admin/risk-flags',
            '/admin/audit-logs',
            '/admin/sub2-api-upstream-accounts',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    private function user(int $id, string $role, string $status): User
    {
        return User::query()->create([
            'id' => $id,
            'username' => $role.$id,
            'email' => $role.$id.'@example.com',
            'role' => $role,
            'status' => $status,
        ]);
    }
}
