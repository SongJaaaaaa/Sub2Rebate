<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserRebateProgressResource\Pages;
use App\Modules\Rebate\Models\UserRebateProgress;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserRebateProgressResource extends Resource
{
    protected static ?string $model = UserRebateProgress::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = '返利';

    protected static ?string $modelLabel = '里程碑进度';

    protected static ?string $pluralModelLabel = '里程碑进度';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user_id')->label('用户 ID')->disabled(),
            Forms\Components\TextInput::make('total_recharge_amount')->label('累计充值')->disabled(),
            Forms\Components\TextInput::make('milestone_times')->label('已触发次数')->disabled(),
            Forms\Components\TextInput::make('last_event_id')->label('最后事件 ID')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')->label('用户 ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('total_recharge_amount')->label('累计充值')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('milestone_times')->label('触发次数')->sortable(),
                Tables\Columns\TextColumn::make('last_event_id')->label('最后事件')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserRebateProgress::route('/'),
            'view' => Pages\ViewUserRebateProgress::route('/{record}'),
        ];
    }
}
