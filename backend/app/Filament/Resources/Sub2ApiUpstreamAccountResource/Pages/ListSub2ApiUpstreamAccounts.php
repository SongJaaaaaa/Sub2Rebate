<?php

namespace App\Filament\Resources\Sub2ApiUpstreamAccountResource\Pages;

use App\Filament\Resources\Sub2ApiUpstreamAccountResource;
use App\Modules\Sub2Api\Services\Sub2ApiUpstreamAccountSyncService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSub2ApiUpstreamAccounts extends ListRecords
{
    protected static string $resource = Sub2ApiUpstreamAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('同步上游账号')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncAll(false, auth()->user());

                    if ($result['ok'] ?? false) {
                        Notification::make()
                            ->title('同步完成')
                            ->body('已同步 '.$result['count'].' 个上游账号')
                            ->success()
                            ->send();

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
