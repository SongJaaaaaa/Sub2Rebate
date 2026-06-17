import type { AdminUser, AdminWithdrawRecord, RebateConfig, AdminDashboardStats, AdminTrendItem, FullRebateConfig, RelationshipNode } from '@/types/admin'

export const mockAdminUsers: AdminUser[] = [
  { id: 1001, username: 'u1', nickname: '测试用户1', email: 'u1@example.com', avatar: '', role: 'user', createdAt: '2026-06-01 10:00:00', status: 'active', parentNickname: '上级用户', directInviteCount: 4, totalRebateAmount: '15880.00', totalPaidAmount: '5000.00' },
  { id: 1002, username: 'u2', nickname: '测试用户2', email: 'u2@example.com', avatar: '', role: 'user', createdAt: '2026-06-10 08:30:00', status: 'active', parentNickname: '测试用户1', directInviteCount: 3, totalRebateAmount: '300.00', totalPaidAmount: '300.00' },
  { id: 1003, username: 'u3', nickname: '测试用户3', email: 'u3@example.com', avatar: '', role: 'user', createdAt: '2026-06-11 14:00:00', status: 'active', rebateStatus: 'disabled', rebateDisabledReason: 'lie_flat', parentNickname: '测试用户1', directInviteCount: 2, totalRebateAmount: '0.00', totalPaidAmount: '150.00' },
  { id: 1004, username: 'u4', nickname: '测试用户4', email: 'u4@example.com', avatar: '', role: 'user', createdAt: '2026-06-12 10:20:00', status: 'banned', parentNickname: '测试用户1', directInviteCount: 1, totalRebateAmount: '50.00', totalPaidAmount: '200.00' },
  { id: 1005, username: 'u5', nickname: '测试用户5', email: 'u5@example.com', avatar: '', role: 'user', createdAt: '2026-06-12 16:00:00', status: 'active', parentNickname: '测试用户2', directInviteCount: 2, totalRebateAmount: '0.00', totalPaidAmount: '80.00' },
  { id: 1006, username: 'u6', nickname: '测试用户6', email: 'u6@example.com', avatar: '', role: 'user', createdAt: '2026-06-12 17:00:00', status: 'active', parentNickname: '测试用户2', directInviteCount: 1, totalRebateAmount: '0.00', totalPaidAmount: '60.00' },
  { id: 1007, username: 'u7', nickname: '测试用户7', email: 'u7@example.com', avatar: '', role: 'user', createdAt: '2026-06-12 18:00:00', status: 'active', parentNickname: '测试用户3', directInviteCount: 1, totalRebateAmount: '0.00', totalPaidAmount: '90.00' },
  { id: 1008, username: 'u8', nickname: '测试用户8', email: 'u8@example.com', avatar: '', role: 'user', createdAt: '2026-06-13 08:00:00', status: 'active', parentNickname: '测试用户3', directInviteCount: 0, totalRebateAmount: '0.00', totalPaidAmount: '30.00' },
  { id: 1009, username: 'u9', nickname: '测试用户9', email: 'u9@example.com', avatar: '', role: 'user', createdAt: '2026-06-13 09:00:00', status: 'active', parentNickname: '测试用户5', directInviteCount: 1, totalRebateAmount: '0.00', totalPaidAmount: '20.00' },
  { id: 1010, username: 'u10', nickname: '测试用户10', email: 'u10@example.com', avatar: '', role: 'user', createdAt: '2026-06-13 10:00:00', status: 'active', parentNickname: '测试用户5', directInviteCount: 0, totalRebateAmount: '0.00', totalPaidAmount: '40.00' },
  { id: 1011, username: 'u11', nickname: '测试用户11', email: 'u11@example.com', avatar: '', role: 'user', createdAt: '2026-06-13 11:00:00', status: 'active', parentNickname: '测试用户9', directInviteCount: 0, totalRebateAmount: '0.00', totalPaidAmount: '10.00' },
]

export const mockAdminWithdrawals: AdminWithdrawRecord[] = [
  { id: 7001, userId: 1001, username: 'demo', nickname: '演示用户', amount: '100.00', status: 'paid', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', remark: '', rejectReason: '', paidAt: '2026-06-12 18:00:00', createdAt: '2026-06-12 14:00:00' },
  { id: 7002, userId: 1001, username: 'demo', nickname: '演示用户', amount: '200.00', status: 'pending', accountType: 'alipay', accountNo: 'demo@example.com', realName: '张三', remark: '', rejectReason: '', paidAt: null, createdAt: '2026-06-13 09:20:00' },
  { id: 7003, userId: 1008, username: 'userB', nickname: '下级用户B', amount: '80.00', status: 'pending', accountType: 'alipay', accountNo: 'userb@alipay.com', realName: '李四', remark: '急用', rejectReason: '', paidAt: null, createdAt: '2026-06-13 11:00:00' },
  { id: 7004, userId: 1009, username: 'userC', nickname: '下级用户C', amount: '50.00', status: 'rejected', accountType: 'alipay', accountNo: 'userc@alipay.com', realName: '王五', remark: '', rejectReason: '账号信息有误', paidAt: null, createdAt: '2026-06-11 16:00:00' },
]

export const mockRebateConfig: RebateConfig = {
  maxLevel: 3,
  decayRate: '0.50',
  levelRules: [
    { level: 1, rate: '0.10', enabled: true },
    { level: 2, rate: '0.05', enabled: true },
    { level: 3, rate: '0.025', enabled: true },
  ],
  milestoneRules: [
    { id: 1, name: '首充奖励', threshold: '100.00', reward: '15.00', enabled: true },
    { id: 2, name: '累计充值500', threshold: '500.00', reward: '50.00', enabled: true },
    { id: 3, name: '累计充值2000', threshold: '2000.00', reward: '150.00', enabled: false },
  ],
}

export const mockFullRebateConfig: FullRebateConfig = {
  milestone: {
    threshold: '100',
    reward: '5.00',
    maxTimes: 3,
  },
  multiLevel: {
    enabled: true,
    totalPoolRate: '15',
    decayCoefficient: '0.5',
    maxDepth: 5,
    inactiveNodeMode: 'platform',
  },
  withdrawLimit: {
    minAmount: '100.00',
    cooldownHours: 24,
  },
  riskControl: {
    blacklistEnabled: true,
    autoFreezeThreshold: 50,
    lieFlatEnabled: true,
    lieFlatDays: 7,
  },
  lastModifiedBy: 'admin_01',
  lastModifiedAt: '2023-11-24 14:20',
}

export const mockRelationshipTree: RelationshipNode = {
  id: 1001,
  username: 'u1',
  nickname: '测试用户1',
  avatar: '',
  level: 'Top Master',
  totalRecharge: '45200.00',
  directReferrals: 4,
  status: 'active',
  children: [
    {
      id: 1002,
      username: 'u2',
      nickname: '测试用户2',
      avatar: '',
      level: 'Referral L1',
      totalRecharge: '12800.00',
      directReferrals: 3,
      status: 'active',
      children: [
        {
          id: 1005,
          username: 'u5',
          nickname: '测试用户5',
          avatar: '',
          level: 'Referral L2',
          totalRecharge: '2400.00',
          directReferrals: 2,
          status: 'active',
          children: [
            {
              id: 1009,
              username: 'u9',
              nickname: '测试用户9',
              avatar: '',
              level: 'Referral L3',
              totalRecharge: '620.00',
              directReferrals: 1,
              status: 'active',
              children: [
                { id: 1011, username: 'u11', nickname: '测试用户11', avatar: '', level: 'Referral L4', totalRecharge: '120.00', directReferrals: 0, status: 'active' },
              ],
            },
            { id: 1010, username: 'u10', nickname: '测试用户10', avatar: '', level: 'Referral L3', totalRecharge: '480.00', directReferrals: 0, status: 'active' },
          ],
        },
        {
          id: 1006,
          username: 'u6',
          nickname: '测试用户6',
          avatar: '',
          level: 'Referral L2',
          totalRecharge: '1950.00',
          directReferrals: 1,
          status: 'active',
          children: [
            { id: 1012, username: 'u12', nickname: '测试用户12', avatar: '', level: 'Referral L3', totalRecharge: '300.00', directReferrals: 0, status: 'active' },
          ],
        },
      ],
    },
    {
      id: 1003,
      username: 'u3',
      nickname: '测试用户3',
      avatar: '',
      level: 'Referral L1',
      totalRecharge: '2100.00',
      directReferrals: 2,
      status: 'warning',
      rebateStatus: 'disabled',
      rebateDisabledReason: 'lie_flat',
      children: [
        { id: 1007, username: 'u7', nickname: '测试用户7', avatar: '', level: 'Referral L2', totalRecharge: '860.00', directReferrals: 1, status: 'active', children: [
          { id: 1013, username: 'u13', nickname: '测试用户13', avatar: '', level: 'Referral L3', totalRecharge: '160.00', directReferrals: 0, status: 'active' },
        ] },
        { id: 1008, username: 'u8', nickname: '测试用户8', avatar: '', level: 'Referral L2', totalRecharge: '420.00', directReferrals: 0, status: 'active' },
      ],
    },
    {
      id: 1004,
      username: 'u4',
      nickname: '测试用户4',
      avatar: '',
      level: 'Referral L1',
      totalRecharge: '3150.00',
      directReferrals: 1,
      status: 'banned',
      children: [
        { id: 1014, username: 'u14', nickname: '测试用户14', avatar: '', level: 'Referral L2', totalRecharge: '500.00', directReferrals: 0, status: 'active' },
      ],
    },
  ],
}

export const mockAdminDashboardStats: AdminDashboardStats = {
  totalUsers: 256,
  todayNewUsers: 5,
  totalRebateAmount: '89600.00',
  totalWithdrawAmount: '52300.00',
  pendingWithdrawCount: 2,
  pendingWithdrawAmount: '280.00',
  todayRebateAmount: '420.00',
  monthRebateAmount: '12800.00',
}

export const mockAdminTrends: AdminTrendItem[] = [
  { date: '2026-06-07', newUsers: 3, rebateAmount: '380.00', withdrawAmount: '200.00' },
  { date: '2026-06-08', newUsers: 5, rebateAmount: '520.00', withdrawAmount: '0.00' },
  { date: '2026-06-09', newUsers: 2, rebateAmount: '290.00', withdrawAmount: '150.00' },
  { date: '2026-06-10', newUsers: 8, rebateAmount: '680.00', withdrawAmount: '300.00' },
  { date: '2026-06-11', newUsers: 4, rebateAmount: '450.00', withdrawAmount: '100.00' },
  { date: '2026-06-12', newUsers: 6, rebateAmount: '560.00', withdrawAmount: '250.00' },
  { date: '2026-06-13', newUsers: 5, rebateAmount: '420.00', withdrawAmount: '0.00' },
]
