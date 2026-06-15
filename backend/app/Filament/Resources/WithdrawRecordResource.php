<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawRecordResource\Pages;
use App\Modules\Admin\Services\AdminWithdrawService;
use App\Modules\Withdraw\Models\WithdrawRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WithdrawRecordResource extends Resource
{
    protected static ?string $model = WithdrawRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = '财务';

    protected static ?string $modelLabel = '提现记录';

    protected static ?string $pluralModelLabel = '提现审核';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user_id')->label('用户 ID')->disabled(),
            Forms\Components\TextInput::make('amount')->label('提现金额')->disabled(),
            Forms\Components\TextInput::make('status')->label('状态')->disabled(),
            Forms\Components\TextInput::make('account_type')->label('账号类型')->disabled(),
            Forms\Components\TextInput::make('account_no')->label('提现账号')->disabled(),
            Forms\Components\TextInput::make('real_name')->label('真实姓名')->disabled(),
            Forms\Components\Textarea::make('remark')->label('用户备注')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('reject_reason')->label('拒绝原因')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('用户 ID')->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('金额')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge(),
                Tables\Columns\TextColumn::make('account_no')->label('提现账号')->searchable(),
                Tables\Columns\TextColumn::make('real_name')->label('姓名')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('申请时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'pending' => '待审核',
                        'approved' => '已通过',
                        'paid' => '已打款',
                        'rejected' => '已拒绝',
                        'failed' => '失败',
                        'canceled' => '已取消',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('审核通过')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (WithdrawRecord $record): bool => $record->status === WithdrawRecord::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('remark')->label('审核备注')->required(),
                    ])
                    ->action(function (WithdrawRecord $record, array $data): void {
                        $result = app(AdminWithdrawService::class)->approve(auth()->user(), $record, (string) $data['remark']);
                        self::notifyResult($result, '已审核通过');
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('拒绝')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WithdrawRecord $record): bool => in_array($record->status, [
                        WithdrawRecord::STATUS_PENDING,
                        WithdrawRecord::STATUS_APPROVED,
                    ], true))
                    ->form([
                        Forms\Components\Textarea::make('remark')->label('拒绝原因')->required(),
                    ])
                    ->action(function (WithdrawRecord $record, array $data): void {
                        $result = app(AdminWithdrawService::class)->reject(auth()->user(), $record, (string) $data['remark']);
                        self::notifyResult($result, '已拒绝并解冻余额');
                    }),
                Tables\Actions\Action::make('markPaid')
                    ->label('标记打款')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (WithdrawRecord $record): bool => $record->status === WithdrawRecord::STATUS_APPROVED)
                    ->form([
                        Forms\Components\Textarea::make('remark')->label('打款备注')->required(),
                    ])
                    ->action(function (WithdrawRecord $record, array $data): void {
                        $result = app(AdminWithdrawService::class)->markPaid(auth()->user(), $record, (string) $data['remark']);
                        self::notifyResult($result, '已标记打款');
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    private static function notifyResult(array $result, string $successTitle): void
    {
        if ($result['ok'] ?? false) {
            Notification::make()->title($successTitle)->success()->send();

            return;
        }

        Notification::make()
            ->title('操作失败')
            ->body((string) ($result['message'] ?? '未知错误'))
            ->danger()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawRecords::route('/'),
            'view' => Pages\ViewWithdrawRecord::route('/{record}'),
        ];
    }
}
