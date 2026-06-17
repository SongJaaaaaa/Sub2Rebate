<?php

namespace App\Jobs;

use App\Modules\Milestone\Services\MilestoneService;
use App\Modules\Payment\Models\RebateEvent;
use App\Modules\Rebate\Services\DecayRebateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessRebateEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public RebateEvent $event)
    {
    }

    public function handle(MilestoneService $milestones, DecayRebateService $decayRebates): void
    {
        $event = $this->event->fresh();
        if (! $event instanceof RebateEvent) {
            return;
        }

        try {
            $event->status = RebateEvent::STATUS_PROCESSING;
            $event->error_message = null;
            $event->save();

            $milestone = $milestones->process($event);
            if (! ($milestone['ok'] ?? false)) {
                throw new \RuntimeException((string) ($milestone['message'] ?? '里程碑处理失败'));
            }

            $event->refresh();
            $decay = $decayRebates->process($event);
            if (! ($decay['ok'] ?? false)) {
                throw new \RuntimeException((string) ($decay['message'] ?? '衰减返利处理失败'));
            }

            $event->refresh();
            $event->status = RebateEvent::STATUS_PROCESSED;
            $event->processed_at = now();
            $event->save();
        } catch (Throwable $e) {
            $event->refresh();
            $event->status = RebateEvent::STATUS_FAILED;
            $event->error_message = $e->getMessage();
            $event->save();

            throw $e;
        }
    }
}
