import type { InviteInfo, InviteRecord, InviteTreeNode, PromotionSummary, ConversionRecord, RebateRecord } from '@/types/rebate'

export const mockInviteInfo: InviteInfo = {
  inviteCode: 'ABCD12',
  inviteUrl: 'https://rebate.example.com/register?inviteCode=ABCD12',
  sub2ApiAffCode: 'SUB2AFF12',
  sub2ApiInviteUrl: 'https://api.sjiaa.cc.cd/register?aff=SUB2AFF12',
  sub2ApiAffiliatePageUrl: 'https://api.sjiaa.cc.cd/affiliate',
  parent: { id: 1000, username: 'parent', nickname: '上级用户' },
  depth: 2,
  directInviteCount: 18,
  teamInviteCount: 126,
}

export const mockInviteTree: InviteTreeNode = {
  id: 1001,
  username: 'demo',
  nickname: '演示用户',
  level: 0,
  children: [
    {
      id: 1008,
      username: 'userB',
      nickname: '下级用户B',
      level: 1,
      children: [
        { id: 1020, username: 'userE', nickname: '三级用户E', level: 2, children: [] },
      ],
    },
    { id: 1009, username: 'userC', nickname: '下级用户C', level: 1, children: [] },
    { id: 1010, username: 'userD', nickname: '下级用户D', level: 1, children: [] },
  ],
}

export const mockInviteRecords: InviteRecord[] = [
  { id: 1008, username: 'userB', nickname: '下级用户B', level: 1, totalPaidAmount: '300.00', totalRebateAmount: '30.00', boundAt: '2026-06-10 08:30:00' },
  { id: 1009, username: 'userC', nickname: '下级用户C', level: 1, totalPaidAmount: '150.00', totalRebateAmount: '15.00', boundAt: '2026-06-11 14:00:00' },
  { id: 1010, username: 'userD', nickname: '下级用户D', level: 1, totalPaidAmount: '200.00', totalRebateAmount: '18.50', boundAt: '2026-06-12 10:20:00' },
]

export const mockPromotionSummary: PromotionSummary = {
  inviteCode: 'ABCD12',
  inviteUrl: 'https://rebate.example.com/register?inviteCode=ABCD12',
  sub2ApiAffCode: 'SUB2AFF12',
  sub2ApiInviteUrl: 'https://api.sjiaa.cc.cd/register?aff=SUB2AFF12',
  sub2ApiAffiliatePageUrl: 'https://api.sjiaa.cc.cd/affiliate',
  directInviteCount: 18,
  teamInviteCount: 126,
  conversionRate: '0.320000',
  totalPaidUserCount: 48,
  totalRebateAmount: '15880.00',
}

export const mockConversions: ConversionRecord[] = [
  { id: 1, userId: 1008, username: 'userB', nickname: '下级用户B', level: 1, paidAmount: '100.00', rebateAmount: '15.00', type: 'milestone', createdAt: '2026-06-13 12:00:00' },
  { id: 2, userId: 1008, username: 'userB', nickname: '下级用户B', level: 1, paidAmount: '100.00', rebateAmount: '15.00', type: 'milestone', createdAt: '2026-06-12 16:00:00' },
  { id: 3, userId: 1008, username: 'userB', nickname: '下级用户B', level: 1, paidAmount: '100.00', rebateAmount: '9.62', type: 'decay', createdAt: '2026-06-12 10:00:00' },
  { id: 4, userId: 1009, username: 'userC', nickname: '下级用户C', level: 1, paidAmount: '150.00', rebateAmount: '15.00', type: 'milestone', createdAt: '2026-06-11 14:30:00' },
]

export const mockRebateRecords: RebateRecord[] = [
  { id: 9001, type: 'decay', status: 'available', sourceUserId: 1008, sourceUserName: '下级用户B', receiverUserId: 1001, level: 1, sourceAmount: '100.00', rebateAmount: '9.62', remark: '多级返利', createdAt: '2026-06-13 12:00:00' },
  { id: 9002, type: 'milestone', status: 'available', sourceUserId: 1009, sourceUserName: '下级用户C', receiverUserId: 1001, level: 1, sourceAmount: '150.00', rebateAmount: '15.00', remark: '里程碑奖励', createdAt: '2026-06-12 16:00:00' },
  { id: 9003, type: 'decay', status: 'frozen', sourceUserId: 1010, sourceUserName: '下级用户D', receiverUserId: 1001, level: 1, sourceAmount: '200.00', rebateAmount: '5.77', remark: '多级返利', createdAt: '2026-06-12 10:00:00' },
  { id: 9004, type: 'milestone', status: 'available', sourceUserId: 1008, sourceUserName: '下级用户B', receiverUserId: 1001, level: 1, sourceAmount: '100.00', rebateAmount: '15.00', remark: '里程碑奖励', createdAt: '2026-06-11 08:00:00' },
]
