import type { DashboardSummary, RebateTrendItem, RecentActivity } from '@/types/rebate'

export const mockDashboardSummary: DashboardSummary = {
  todayRebateAmount: '128.00',
  monthRebateAmount: '2380.00',
  totalRebateAmount: '15880.00',
  availableAmount: '1280.00',
  directInviteCount: 18,
  teamInviteCount: 126,
  pendingWithdrawAmount: '200.00',
}

export const mockRebateTrends: RebateTrendItem[] = [
  { date: '2026-06-07', rebateAmount: '86.00', inviteCount: 2 },
  { date: '2026-06-08', rebateAmount: '142.50', inviteCount: 4 },
  { date: '2026-06-09', rebateAmount: '98.00', inviteCount: 1 },
  { date: '2026-06-10', rebateAmount: '215.30', inviteCount: 5 },
  { date: '2026-06-11', rebateAmount: '176.80', inviteCount: 3 },
  { date: '2026-06-12', rebateAmount: '192.40', inviteCount: 2 },
  { date: '2026-06-13', rebateAmount: '128.00', inviteCount: 3 },
]

export const mockRecentActivities: RecentActivity[] = [
  { id: 1, type: 'rebate', title: '获得多级返利', content: '来自用户 userB 的充值返利', amount: '9.62', createdAt: '2026-06-13 12:00:00' },
  { id: 2, type: 'rebate', title: '获得里程碑奖励', content: '来自用户 userC 的首充奖励', amount: '15.00', createdAt: '2026-06-13 10:30:00' },
  { id: 3, type: 'withdraw', title: '提现审核通过', content: '提现 ¥100.00 已打款', amount: '-100.00', createdAt: '2026-06-12 18:00:00' },
  { id: 4, type: 'rebate', title: '获得多级返利', content: '来自用户 userD 的充值返利', amount: '5.77', createdAt: '2026-06-12 14:20:00' },
  { id: 5, type: 'invite', title: '新用户加入', content: 'userE 通过你的推广链接注册', amount: '0.00', createdAt: '2026-06-12 09:15:00' },
]
