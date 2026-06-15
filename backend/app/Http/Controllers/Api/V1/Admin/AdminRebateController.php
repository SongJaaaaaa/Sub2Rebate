<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Models\ConfigItem;
use App\Http\Controllers\Api\V1\Concerns\FormatsApiPayloads;
use App\Support\ApiError;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRebateController extends Controller
{
    use FormatsApiPayloads;

    public function __construct(private readonly AuditLogService $audits)
    {
    }

    public function overrides(): JsonResponse
    {
        $rows = ConfigItem::query()
            ->where('key', 'like', 'rebate.user_override.%')
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::ok([
            'list' => $rows->map(function (ConfigItem $item): array {
                $value = is_array($item->value) ? $item->value : [];
                $userId = (int) str_replace('rebate.user_override.', '', (string) $item->key);
                $user = User::query()->find($userId);

                return $this->overridePayload($userId, $user, $value, $item->updated_at);
            })->all(),
            'page' => 1,
            'pageSize' => 20,
            'total' => $rows->count(),
        ]);
    }

    public function showOverride(int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        $item = ConfigItem::query()->where('key', $this->overrideKey($user->id))->first();
        $value = $item instanceof ConfigItem && is_array($item->value) ? $item->value : [
            'enabled' => false,
            'customRates' => [],
        ];

        return ApiResponse::ok($this->overridePayload((int) $user->id, $user, $value, $item?->updated_at));
    }

    public function updateOverride(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);
        if (! $user instanceof User) {
            return ApiResponse::fail(ApiError::NOT_FOUND, '用户不存在', null, 404);
        }

        $rates = $request->input('customRates', $request->input('items', []));
        $enabled = (bool) $request->boolean('enabled', true);
        $value = [
            'enabled' => $enabled,
            'customRates' => is_array($rates) ? array_values($rates) : [],
        ];

        $key = $this->overrideKey($user->id);
        $item = ConfigItem::query()->firstOrNew(['key' => $key]);
        $before = $item->exists ? $item->toArray() : [];
        $item->fill([
            'group' => 'rebate',
            'name' => '用户返利层级 '.$user->id,
            'type' => 'json',
            'value' => $value,
            'tips' => '特定用户返利层级覆盖配置',
            'sort' => 900,
            'is_public' => false,
        ]);
        $item->save();

        $this->audits->record('rebate', 'rebate.override_update', [
            'actor' => $request->user(),
            'target' => $user,
            'subject_type' => ConfigItem::class,
            'subject_id' => $item->id,
            'before_values' => $before,
            'after_values' => $item->toArray(),
            'remark' => trim((string) $request->input('remark', '后台更新用户返利配置')),
        ]);

        return ApiResponse::ok($this->overridePayload((int) $user->id, $user, $value, $item->updated_at));
    }

    private function overridePayload(int $userId, ?User $user, array $value, mixed $updatedAt): array
    {
        $name = $user instanceof User
            ? (string) ($user->username ?: $user->email ?: 'user_'.$user->id)
            : 'user_'.$userId;

        return [
            'userId' => $userId,
            'username' => $name,
            'nickname' => $name,
            'customRates' => array_values($value['customRates'] ?? []),
            'enabled' => (bool) ($value['enabled'] ?? false),
            'updatedAt' => $this->time($updatedAt),
        ];
    }

    private function overrideKey(int $userId): string
    {
        return 'rebate.user_override.'.$userId;
    }
}
