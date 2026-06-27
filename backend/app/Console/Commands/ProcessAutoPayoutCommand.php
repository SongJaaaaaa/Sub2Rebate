<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\AdminWithdrawService;
use App\Modules\Payment\Services\AlipayTransferService;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Illuminate\Console\Command;

class ProcessAutoPayoutCommand extends Command
{
    protected $signature = 'withdraw:process-auto-payout {--limit=}';

    protected $description = 'Retry approved Alipay withdraw payouts.';

    public function __construct(
        private readonly AdminWithdrawService $withdraws,
        private readonly AlipayTransferService $transfers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->transfers->retryEnabled()) {
            $this->info('支付宝打款自动重试未启用');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: $this->transfers->retryBatchSize());
        $limit = max(1, min($limit, 500));
        $threshold = now()->subMinutes($this->transfers->retryIntervalMinutes());
        $done = 0;
        $failed = 0;

        $records = WithdrawRecord::query()
            ->where('type', WithdrawRecord::TYPE_ALIPAY)
            ->where('status', WithdrawRecord::STATUS_APPROVED)
            ->whereNull('payout_trade_no')
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('payout_error')
                    ->orWhere('updated_at', '<=', $threshold);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($records as $record) {
            $result = $this->withdraws->autoPayout($record, '系统自动打款重试');
            if ($result['ok'] ?? false) {
                $done++;
            } else {
                $failed++;
                $this->warn('提现 #'.$record->id.' 自动打款失败：'.(string) ($result['message'] ?? '未知错误'));
            }
        }

        $this->info("已处理 {$records->count()} 笔提现，成功 {$done} 笔，失败 {$failed} 笔");

        return self::SUCCESS;
    }
}
