import type { WithdrawConfig, WithdrawAccount, WithdrawRecord } from '@/types/withdraw'
import type { Balance } from '@/types/user'

export const mockWithdrawConfig: WithdrawConfig = {
  minAmount: '50.00',
  reviewMode: 'manual',
  dailyLimit: null,
  freezeDays: 0,
  toApiQuotaEnabled: true,
  toApiQuotaRate: '1.000000',
  tips: [
    '提现最低金额为 50.00 元',
    '返利余额可即时转入 Sub2API 额度',
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
  { id: 7001, type: 'alipay', amount: '100.00', status: 'paid', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', sub2ApiBalanceBefore: null, sub2ApiBalanceAfter: null, remark: '提现到支付宝', rejectReason: '', payoutTradeNo: '202606120001', payoutError: '', payoutTime: '2026-06-12 18:00:00', paidAt: '2026-06-12 18:00:00', createdAt: '2026-06-12 14:00:00' },
  { id: 7002, type: 'api_quota', amount: '80.00', status: 'paid', accountType: 'api_quota', accountNo: 'Sub2API 额度', realName: 'Sub2API', sub2ApiBalanceBefore: '300.00', sub2ApiBalanceAfter: '380.00', remark: '转入 API 额度', rejectReason: '', payoutTradeNo: '', payoutError: '', payoutTime: null, paidAt: '2026-06-13 10:20:00', createdAt: '2026-06-13 10:20:00' },
  { id: 7003, type: 'alipay', amount: '200.00', status: 'pending', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', sub2ApiBalanceBefore: null, sub2ApiBalanceAfter: null, remark: '', rejectReason: '', payoutTradeNo: '', payoutError: '', payoutTime: null, paidAt: null, createdAt: '2026-06-13 09:20:00' },
]

export const mockBalanceAfterApply: Balance = {
  availableAmount: '1180.00',
  frozenAmount: '300.00',
  totalAmount: '1480.00',
  withdrawnAmount: '500.00',
}
