<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RebateEventResource\Pages;
use App\Modules\Payment\Models\RebateEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RebateEventResource extends Resource
{
    protected static ?string $model = RebateEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = '返利';

    protected static ?string $modelLabel = '返利事件';

    protected static ?string $pluralModelLabel = '返利事件';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user_id')->label('用户 ID')->disabled(),
            Forms\Components\TextInput::make('source_type')->label('来源类型')->disabled(),
            Forms\Components\TextInput::make('source_id')->label('来源 ID')->disabled(),
            Forms\Components\TextInput::make('event_type')->label('事件类型')->disabled(),
            Forms\Components\TextInput::make('status')->label('状态')->disabled(),
            Forms\Components\TextInput::make('source_amount')->label('原始金额')->disabled(),
            Forms\Components\TextInput::make('standard_amount')->label('标准金额')->disabled(),
            Forms\Components\TextInput::make('credit_amount')->label('Sub2API 额度')->disabled(),
            Forms\Components\Textarea::make('remark')->label('备注')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('config_snapshot')
                ->label('配置快照')
                ->formatStateUsing(fn (mixed $state): string => self::jsonText($state))
                ->disabled()
                ->columnSpanFull(),
            Forms\Components\Textarea::make('error_message')->label('错误信息')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('用户 ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('source_type')->label('来源')->searchable(),
                Tables\Columns\TextColumn::make('source_id')->label('来源 ID')->limit(24)->searchable(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge(),
                Tables\Columns\TextColumn::make('standard_amount')->label('标准金额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('credit_amount')->label('额度')->sortable(),
                Tables\Columns\TextColumn::make('occurred_at')->label('发生时间')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('入库时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        RebateEvent::STATUS_PENDING => '待处理',
                        RebateEvent::STATUS_PROCESSING => '处理中',
                        RebateEvent::STATUS_PROCESSED => '已处理',
                        RebateEvent::STATUS_FAILED => '失败',
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
            'index' => Pages\ListRebateEvents::route('/'),
            'view' => Pages\ViewRebateEvent::route('/{record}'),
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
