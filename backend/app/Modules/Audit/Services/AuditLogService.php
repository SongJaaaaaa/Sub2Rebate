<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;

class AuditLogService
{
    public function record(string $module, string $action, array $data = []): AuditLog
    {
        $actor = $data['actor'] ?? null;
        $target = $data['target'] ?? null;
        $request = request();

        return AuditLog::query()->create([
            'actor_user_id' => $actor instanceof User ? $actor->id : ($data['actor_user_id'] ?? null),
            'target_user_id' => $target instanceof User ? $target->id : ($data['target_user_id'] ?? null),
            'module' => $module,
            'action' => $action,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'before_values' => $data['before_values'] ?? null,
            'after_values' => $data['after_values'] ?? null,
            'ip' => $data['ip'] ?? $request?->ip(),
            'user_agent' => $data['user_agent'] ?? $request?->userAgent(),
            'remark' => $data['remark'] ?? null,
        ]);
    }
}
