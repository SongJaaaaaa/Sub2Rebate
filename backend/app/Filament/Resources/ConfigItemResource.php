<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConfigItemResource\Pages;
use App\Modules\Config\Models\ConfigItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConfigItemResource extends Resource
{
    protected static ?string $model = ConfigItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = '系统';

    protected static ?string $modelLabel = '配置项';

    protected static ?string $pluralModelLabel = '配置中心';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')->label('配置键')->disabled(),
            Forms\Components\TextInput::make('group')->label('分组')->disabled(),
            Forms\Components\TextInput::make('name')->label('名称')->required(),
            Forms\Components\TextInput::make('type')->label('类型')->disabled(),
            Forms\Components\TextInput::make('value')->label('配置值')->required(),
            Forms\Components\Textarea::make('tips')->label('说明')->required()->columnSpanFull(),
            Forms\Components\Textarea::make('change_remark')
                ->label('修改备注')
                ->required()
                ->dehydrated(false)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sort')->label('排序')->numeric(),
            Forms\Components\Toggle::make('is_public')->label('公开可读'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group')->label('分组')->sortable(),
                Tables\Columns\TextColumn::make('key')->label('配置键')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable(),
                Tables\Columns\TextColumn::make('value')->label('值'),
                Tables\Columns\TextColumn::make('tips')->label('说明')->limit(40),
                Tables\Columns\TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('分组')
                    ->options([
                        'milestone' => '里程碑',
                        'rebate' => '返利',
                        'payment' => '支付',
                        'withdraw' => '提现',
                        'risk' => '风控',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConfigItems::route('/'),
            'edit' => Pages\EditConfigItem::route('/{record}/edit'),
        ];
    }
}
