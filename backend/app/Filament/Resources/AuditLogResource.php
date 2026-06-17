<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Modules\Audit\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = '风控审计';

    protected static ?string $modelLabel = '审计日志';

    protected static ?string $pluralModelLabel = '审计日志';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('actor_user_id')->label('操作者 ID')->disabled(),
            Forms\Components\TextInput::make('target_user_id')->label('目标用户 ID')->disabled(),
            Forms\Components\TextInput::make('module')->label('模块')->disabled(),
            Forms\Components\TextInput::make('action')->label('动作')->disabled(),
            Forms\Components\TextInput::make('subject_type')->label('对象类型')->disabled(),
            Forms\Components\TextInput::make('subject_id')->label('对象 ID')->disabled(),
            Forms\Components\Textarea::make('remark')->label('备注')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('before_values')
                ->label('变更前')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('after_values')
                ->label('变更后')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('actor_user_id')->label('操作者')->sortable(),
                Tables\Columns\TextColumn::make('target_user_id')->label('目标用户')->sortable(),
                Tables\Columns\TextColumn::make('module')->label('模块')->badge()->searchable(),
                Tables\Columns\TextColumn::make('action')->label('动作')->searchable(),
                Tables\Columns\TextColumn::make('remark')->label('备注')->limit(36),
                Tables\Columns\TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->label('模块')
                    ->options([
                        'withdraw' => '提现',
                        'rebate' => '返利',
                        'sub2api' => 'Sub2API',
                        'config' => '配置',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
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
