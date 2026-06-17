<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = '用户';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '用户管理';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('id')->label('Sub2API 用户 ID')->disabled(),
            Forms\Components\TextInput::make('username')->label('用户名')->disabled(),
            Forms\Components\TextInput::make('email')->label('邮箱')->disabled(),
            Forms\Components\TextInput::make('role')->label('角色')->disabled(),
            Forms\Components\TextInput::make('status')->label('状态')->disabled(),
            Forms\Components\TextInput::make('sub2api_aff_code')->label('Sub2API 邀请码')->disabled(),
            Forms\Components\TextInput::make('sub2api_inviter_id')->label('Sub2API 邀请人 ID')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('username')->label('用户名')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('邮箱')->searchable(),
                Tables\Columns\TextColumn::make('role')->label('角色')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge()->sortable(),
                Tables\Columns\TextColumn::make('sub2api_aff_code')->label('Sub2API 邀请码')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('同步时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('角色')
                    ->options([
                        'admin' => '管理员',
                        'user' => '普通用户',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '正常',
                        'disabled' => '禁用',
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
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
