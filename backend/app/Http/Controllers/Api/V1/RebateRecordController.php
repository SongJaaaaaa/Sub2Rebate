<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Modules\Rebate\Models\RebateRecord;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RebateRecordController extends Controller
{
    use FormatsApiPayloads;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::fail(ApiError::UNAUTHENTICATED, '未登录', null, 401);
        }

        [$page, $pageSize] = $this->pageParams((int) $request->integer('page', 1), (int) $request->integer('pageSize', 20));
        $type = trim($request->string('type')->toString());

        $query = RebateRecord::query()->where('receiver_user_id', $user->id);
        if ($type !== '') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return ApiResponse::ok([
            'list' => $rows->map(fn (RebateRecord $record): array => $this->recordPayload($record))->all(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ]);
    }

    private function recordPayload(RebateRecord $record): array
    {
        return [
            'id' => (int) $record->id,
            'eventId' => (int) $record->event_id,
            'payerUserId' => (int) $record->payer_user_id,
            'receiverUserId' => (int) $record->receiver_user_id,
            'type' => (string) $record->type,
            'level' => (int) $record->level,
            'sourceAmount' => $this->money($record->source_amount),
            'rebateAmount' => $this->money($record->rebate_amount),
            'status' => (string) $record->status,
            'remark' => (string) ($record->remark ?: ''),
            'createdAt' => $this->time($record->created_at),
        ];
    }
}
