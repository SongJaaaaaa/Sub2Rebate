<?php

namespace App\Filament\Resources\ConfigItemResource\Pages;

use App\Filament\Resources\ConfigItemResource;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Config\Services\ConfigService;
use Filament\Resources\Pages\EditRecord;

class EditConfigItem extends EditRecord
{
    protected static string $resource = ConfigItemResource::class;

    private array $before = [];

    protected function beforeSave(): void
    {
        $this->before = $this->record->toArray();
    }

    protected function afterSave(): void
    {
        app(ConfigService::class)->forget();
        app(AuditLogService::class)->record('config', 'config.update', [
            'actor' => auth()->user(),
            'subject_type' => $this->record::class,
            'subject_id' => $this->record->id,
            'before_values' => $this->before,
            'after_values' => $this->record->toArray(),
            'remark' => (string) ($this->data['change_remark'] ?? ''),
        ]);
    }
}
