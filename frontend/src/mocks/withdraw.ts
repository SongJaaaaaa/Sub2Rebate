import type { WithdrawConfig, WithdrawAccount, WithdrawRecord } from '@/types/withdraw'
import type { Balance } from '@/types/user'

export const mockWithdrawConfig: WithdrawConfig = {
  minAmount: '50.00',
  reviewMode: 'manual',
  dailyLimit: null,
  freezeDays: 0,
  tips: [
    '提现最低金额为 50.00 元',
    '第一版采用人工审核和人工打款',
  ],
}

export const mockWithdrawAccount: WithdrawAccount = {
  id: 1,
  type: 'alipay',
  realName: '张三',
  accountNo: 'demo@example.com',
  createdAt: '2026-06-13 12:00:00',
  updatedAt: '2026-06-13 12:00:00',
}

export const mockWithdrawRecords: WithdrawRecord[] = [
  { id: 7001, amount: '100.00', status: 'paid', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', remark: '提现到支付宝', rejectReason: '', paidAt: '2026-06-12 18:00:00', createdAt: '2026-06-12 14:00:00' },
  { id: 7002, amount: '200.00', status: 'pending', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', remark: '', rejectReason: '', paidAt: null, createdAt: '2026-06-13 09:20:00' },
]

export const mockBalanceAfterApply: Balance = {
  availableAmount: '1180.00',
  frozenAmount: '300.00',
  totalAmount: '1480.00',
  withdrawnAmount: '500.00',
}
