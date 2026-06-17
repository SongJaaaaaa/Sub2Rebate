<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Config\Services\ConfigService;
use App\Modules\Rebate\Services\RebateEligibilityService;
use App\Modules\Sub2Api\Repositories\Sub2ApiUserRepository;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Throwable;

class CheckLieFlatUsersCommand extends Command
{
    protected $signature = 'rebate:check-lie-flat-users {--limit=500}';

    protected $description = 'Check inactive rebate users and update rebate eligibility.';

    public function __construct(
        private readonly ConfigService $configs,
        private readonly Sub2ApiUserRepository $sub2Users,
        private readonly RebateEligibilityService $eligibility,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) $this->configs->get('risk.lie_flat_enabled', true)) {
            $this->info('防躺平检测未启用');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->configs->get('risk.lie_flat_days', 7));
        $limit = max(1, min((int) $this->option('limit'), 2000));
        $checked = 0;
        $disabled = 0;
        $restored = 0;

        User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->limit($limit)
            ->each(function (User $user) use ($days, &$checked, &$disabled, &$restored): void {
                $checked++;

                try {
                    $sub2User = $this->sub2Users->findById((int) $user->id);
                } catch (Throwable $e) {
                    $this->warn('读取 Sub2API 用户失败：'.$user->id.' '.$e->getMessage());

                    return;
                }

                if ($sub2User === null) {
                    return;
                }

                $balance = $this->amount($sub2User->balance);
                $total = $this->amount($sub2User->totalRecharged);
                $now = now();
                $oldTotal = $user->last_sub2api_total_recharged;
                $oldBalance = $user->last_sub2api_balance;

                if ($oldBalance !== null && $balance < $this->amount($oldBalance)) {
                    $this->eligibility->markBalanceDecrease($user, $now);
                }

                if ($oldTotal !== null && $total > $this->amount($oldTotal)) {
                    $wasDisabled = ! $this->eligibility->eligible($user);
                    $this->eligibility->markRecharge($user, $now);
                    if ($wasDisabled) {
                        $restored++;
                    }
                }

                $user->forceFill([
                    'last_sub2api_balance' => $this->money($balance),
                    'last_sub2api_total_recharged' => $this->money($total),
                    'last_balance_checked_at' => $now,
                ])->save();

                if ($this->eligibility->eligible($user) && $this->inactive($user->refresh(), $days, $now)) {
                    if ($this->eligibility->disable($user, 'lie_flat', null, '防躺平：连续 '.$days.' 天无活跃')) {
                        $disabled++;
                    }
                }
            });

        $this->info("已检查 {$checked} 个用户，置灰 {$disabled} 个，恢复 {$restored} 个");

        return self::SUCCESS;
    }

    private function inactive(User $user, int $days, CarbonInterface $now): bool
    {
        $threshold = $now->copy()->subDays($days);
        $lastActive = collect([
            $user->last_recharge_at,
            $user->last_balance_decreased_at,
            $user->last_invited_at,
            $user->created_at,
        ])->filter()->max();

        return $lastActive === null || $lastActive->lessThanOrEqualTo($threshold);
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 6) : 0.0;
    }

    private function money(float|int|string $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }
}
