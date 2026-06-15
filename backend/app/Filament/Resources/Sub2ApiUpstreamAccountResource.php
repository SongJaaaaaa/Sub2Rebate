<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Sub2ApiUpstreamAccountResource\Pages;
use App\Modules\Sub2Api\Models\Sub2ApiUpstreamAccount;
use App\Modules\Sub2Api\Services\Sub2ApiUpstreamAccountSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class Sub2ApiUpstreamAccountResource extends Resource
{
    protected static ?string $model = Sub2ApiUpstreamAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Sub2API';

    protected static ?string $modelLabel = '上游账号监控';

    protected static ?string $pluralModelLabel = '上游账号监控';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('notice')
                ->label('说明')
                ->content('这里的 accounts 是 Sub2API 上游模型账号/渠道账号，不是用户账号，不参与返利发放和用户账务计算。')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sub2api_id')->label('Sub2API Account ID')->disabled(),
            Forms\Components\TextInput::make('name')->label('名称')->disabled(),
            Forms\Components\TextInput::make('provider')->label('渠道')->disabled(),
            Forms\Components\TextInput::make('model')->label('模型')->disabled(),
            Forms\Components\TextInput::make('status')->label('状态')->disabled(),
            Forms\Components\TextInput::make('used_quota')->label('已用额度')->disabled(),
            Forms\Components\TextInput::make('total_quota')->label('总额度')->disabled(),
            Forms\Components\TextInput::make('request_count')->label('请求数')->disabled(),
            Forms\Components\TextInput::make('last_used_at')->label('最后使用')->disabled(),
            Forms\Components\TextInput::make('last_synced_at')->label('同步时间')->disabled(),
            Forms\Components\Textarea::make('last_error')->label('最近错误')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('raw_account')
                ->label('账号原始数据')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('raw_usage')
                ->label('用量原始数据')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('raw_stats')
                ->label('统计原始数据')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('raw_today_stats')
                ->label('今日统计原始数据')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sub2api_id')->label('Account ID')->searchable()->limit(18),
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable()->limit(24),
                Tables\Columns\TextColumn::make('provider')->label('渠道')->badge()->searchable(),
                Tables\Columns\TextColumn::make('model')->label('模型')->searchable()->limit(20),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge(),
                Tables\Columns\TextColumn::make('used_quota')->label('已用')->sortable(),
                Tables\Columns\TextColumn::make('total_quota')->label('总量')->sortable(),
                Tables\Columns\TextColumn::make('request_count')->label('请求数')->sortable(),
                Tables\Columns\IconColumn::make('last_error')->label('错误')->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->state(fn (Sub2ApiUpstreamAccount $record): bool => filled($record->last_error)),
                Tables\Columns\TextColumn::make('last_synced_at')->label('同步时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态'),
                Tables\Filters\SelectFilter::make('provider')->label('渠道'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('syncDetails')
                    ->label('同步详情')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Sub2ApiUpstreamAccount $record): void {
                        $result = app(Sub2ApiUpstreamAccountSyncService::class)->syncDetails($record, auth()->user());
                        if ($result['ok'] ?? false) {
                            Notification::make()->title('同步完成')->success()->send();

                            return;
                        }

                        Notification::make()
                            ->title('同步失败')
                            ->body((string) ($result['message'] ?? '未知错误'))
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSub2ApiUpstreamAccounts::route('/'),
            'view' => Pages\ViewSub2ApiUpstreamAccount::route('/{record}'),
        ];
    }

    private static function jsonText(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '';
        }

        if (is_string($state)) {
            return $state;
        }

        return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }
}
