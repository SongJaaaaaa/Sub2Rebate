<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RiskFlagResource\Pages;
use App\Modules\Risk\Models\RiskFlag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RiskFlagResource extends Resource
{
    protected static ?string $model = RiskFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = '风控审计';

    protected static ?string $modelLabel = '风控标记';

    protected static ?string $pluralModelLabel = '风控标记';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user_id')->label('用户 ID')->numeric()->required(),
            Forms\Components\Select::make('type')
                ->label('类型')
                ->options([
                    RiskFlag::TYPE_BLACKLIST => '黑名单',
                    RiskFlag::TYPE_WITHDRAW_FREEZE => '提现冻结',
                ])
                ->required(),
            Forms\Components\Select::make('status')
                ->label('状态')
                ->options([
                    RiskFlag::STATUS_ACTIVE => '生效中',
                    RiskFlag::STATUS_RESOLVED => '已解除',
                ])
                ->default(RiskFlag::STATUS_ACTIVE)
                ->required(),
            Forms\Components\TextInput::make('created_by')->label('创建人 ID')->numeric(),
            Forms\Components\DateTimePicker::make('expires_at')->label('过期时间'),
            Forms\Components\Textarea::make('reason')->label('原因')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('用户 ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')->label('类型')->badge(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge(),
                Tables\Columns\TextColumn::make('reason')->label('原因')->limit(36),
                Tables\Columns\TextColumn::make('expires_at')->label('过期时间')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('类型')
                    ->options([
                        RiskFlag::TYPE_BLACKLIST => '黑名单',
                        RiskFlag::TYPE_WITHDRAW_FREEZE => '提现冻结',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        RiskFlag::STATUS_ACTIVE => '生效中',
                        RiskFlag::STATUS_RESOLVED => '已解除',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRiskFlags::route('/'),
            'create' => Pages\CreateRiskFlag::route('/create'),
            'edit' => Pages\EditRiskFlag::route('/{record}/edit'),
        ];
    }
}
