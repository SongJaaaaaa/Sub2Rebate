<?php

namespace App\Filament\Resources\Sub2ApiUpstreamAccountResource\Pages;

use App\Filament\Resources\Sub2ApiUpstreamAccountResource;
use App\Modules\Sub2Api\Models\Sub2ApiUpstreamAccount;
use App\Modules\Sub2Api\Services\Sub2ApiUpstreamAccountSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSub2ApiUpstreamAccount extends ViewRecord
{
    protected static string $resource = Sub2ApiUpstreamAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncDetails')
                ->label('同步详情')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    /** @var Sub2ApiUpstreamAccount $record */
                    $record = $this->record;
                    $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncDetails($record, auth()->user());

                    if ($result['ok'] ?? false) {
                        Notification::make()->title('同步完成')->success()->send();
                        $this->record = $record->refresh();

                        return;
                    }

                    Notification::make()
                        ->title('同步失败')
                        ->body((string) ($result['message'] ?? '未知错误'))
                        ->danger()
                        ->send();
                }),
        ];
    }
}
