<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    use FormatsApiPayloads;

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $action = trim($request->string('actionType', $request->string('action')->toString())->toString());

        $query = AuditLog::query();
        if ($action !== '') {
            $query->where('action', $action);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (AuditLog $log): array => $this->logPayload($log))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    private function logPayload(AuditLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'actorUserId' => $log->actor_user_id,
            'targetUserId' => $log->target_user_id,
            'module' => (string) $log->module,
            'action' => (string) $log->action,
            'subjectType' => $log->subject_type,
            'subjectId' => $log->subject_id,
            'beforeValues' => $log->before_values,
            'afterValues' => $log->after_values,
            'remark' => (string) ($log->remark ?: ''),
            'createdAt' => $this->time($log->created_at),
        ];
    }
}
