<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RebateRecordResource\Pages;
use App\Modules\Rebate\Models\RebateRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RebateRecordResource extends Resource
{
    protected static ?string $model = RebateRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = '返利';

    protected static ?string $modelLabel = '返利流水';

    protected static ?string $pluralModelLabel = '返利流水';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('event_id')->label('事件 ID')->disabled(),
            Forms\Components\TextInput::make('payer_user_id')->label('付款用户 ID')->disabled(),
            Forms\Components\TextInput::make('receiver_user_id')->label('收款用户 ID')->disabled(),
            Forms\Components\TextInput::make('type')->label('类型')->disabled(),
            Forms\Components\TextInput::make('level')->label('层级')->disabled(),
            Forms\Components\TextInput::make('source_amount')->label('来源金额')->disabled(),
            Forms\Components\TextInput::make('rebate_amount')->label('返利金额')->disabled(),
            Forms\Components\TextInput::make('status')->label('状态')->disabled(),
            Forms\Components\Textarea::make('remark')->label('备注')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('config_snapshot')
                ->label('配置快照')
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
                Tables\Columns\TextColumn::make('event_id')->label('事件 ID')->sortable(),
                Tables\Columns\TextColumn::make('payer_user_id')->label('付款用户')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('receiver_user_id')->label('收款用户')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')->label('类型')->badge(),
                Tables\Columns\TextColumn::make('level')->label('层级')->sortable(),
                Tables\Columns\TextColumn::make('rebate_amount')->label('返利金额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        RebateRecord::TYPE_MILESTONE => '里程碑',
                        RebateRecord::TYPE_DECAY => '衰减返利',
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
            'index' => Pages\ListRebateRecords::route('/'),
            'view' => Pages\ViewRebateRecord::route('/{record}'),
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
