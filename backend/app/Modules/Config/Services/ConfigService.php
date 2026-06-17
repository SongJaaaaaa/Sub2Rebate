<?php

namespace App\Modules\Config\Services;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Models\ConfigItem;
use App\Modules\Config\Support\DefaultConfig;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ConfigService
{
    private const CACHE_KEY = 'sub2rebate.config.items';

    public function __construct(private readonly AuditLogService $audits)
    {
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            $this->ensureDefaults();

            $rows = ConfigItem::query()
                ->orderBy('sort')
                ->orderBy('key')
                ->get();

            return $rows->map(fn (ConfigItem $item): array => $this->payload($item))->all();
        });
    }

    public function values(): array
    {
        $values = [];

        foreach ($this->all() as $item) {
            Arr::set($values, $item['key'], $item['value']);
        }

        return $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->values(), $key, $default);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function updateBatch(array $items, ?User $operator = null): void
    {
        $this->ensureDefaults();

        foreach ($items as $key => $value) {
            if (is_array($value) && array_key_exists('key', $value)) {
                $key = (string) $value['key'];
                $value = $value['value'] ?? null;
            }

            $item = ConfigItem::query()->where('key', (string) $key)->first();
            if (! $item instanceof ConfigItem) {
                continue;
            }

            $before = $item->toArray();
            $item->value = $value;
            $item->save();

            $this->audits->record('config', 'config.update', [
                'actor' => $operator,
                'subject_type' => ConfigItem::class,
                'subject_id' => $item->id,
                'before_values' => $before,
                'after_values' => $item->toArray(),
                'remark' => '后台更新配置',
            ]);
        }

        $this->forget();
    }

    public function ensureDefaults(): void
    {
        foreach (DefaultConfig::items() as $item) {
            ConfigItem::query()->firstOrCreate(
                ['key' => $item['key']],
                [
                    'group' => $item['group'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'value' => $item['value'],
                    'tips' => $item['tips'],
                    'sort' => $item['sort'],
                    'is_public' => true,
                ]
            );
        }
    }

    private function payload(ConfigItem $item): array
    {
        return [
            'key' => $item->key,
            'group' => $item->group,
            'name' => $item->name,
            'type' => $item->type,
            'value' => $item->value,
            'tips' => $item->tips,
            'sort' => $item->sort,
        ];
    }
}
