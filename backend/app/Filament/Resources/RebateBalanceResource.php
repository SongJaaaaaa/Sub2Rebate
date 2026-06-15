<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RebateBalanceResource\Pages;
use App\Modules\Rebate\Models\RebateBalance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RebateBalanceResource extends Resource
{
    protected static ?string $model = RebateBalance::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = '财务';

    protected static ?string $modelLabel = '返利余额';

    protected static ?string $pluralModelLabel = '返利余额';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user_id')->label('用户 ID')->disabled(),
            Forms\Components\TextInput::make('available_amount')->label('可提现余额')->disabled(),
            Forms\Components\TextInput::make('frozen_amount')->label('冻结余额')->disabled(),
            Forms\Components\TextInput::make('withdrawn_amount')->label('已提现')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')->label('用户 ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('available_amount')->label('可提现')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('frozen_amount')->label('冻结')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('withdrawn_amount')->label('已提现')->money('CNY')->sortable(),
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
            'index' => Pages\ListRebateBalances::route('/'),
            'view' => Pages\ViewRebateBalance::route('/{record}'),
        ];
    }
}
