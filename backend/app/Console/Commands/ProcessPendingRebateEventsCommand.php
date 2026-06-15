<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRebateEventJob;
use App\Modules\Payment\Models\RebateEvent;
use Illuminate\Console\Command;

class ProcessPendingRebateEventsCommand extends Command
{
    protected $signature = 'rebate:process-pending {--limit=100}';

    protected $description = 'Dispatch pending rebate events.';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $events = RebateEvent::query()
            ->where('status', RebateEvent::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            ProcessRebateEventJob::dispatch($event);
        }

        $this->info('已派发 '.$events->count().' 个返利事件');

        return self::SUCCESS;
    }
}
