import type { User, Balance, MeRes, LoginRes, AccountProfile } from '@/types/user'

export const mockUser: User = {
  id: 1001,
  username: 'u1',
  nickname: '测试用户1',
  email: 'u1@example.com',
  avatar: '',
  role: 'user',
  createdAt: '2026-06-13 12:00:00',
}

export const mockAdminUser: User = {
  id: 1,
  username: 'admin',
  nickname: '测试管理员',
  email: 'admin@example.com',
  avatar: '',
  role: 'admin',
  createdAt: '2026-01-01 00:00:00',
}

export const mockBalance: Balance = {
  availableAmount: '1280.00',
  frozenAmount: '200.00',
  totalAmount: '1480.00',
  withdrawnAmount: '500.00',
}

export const mockSub2ApiBalance = {
  currentAmount: '300.00',
  afterAmount: '300.00',
  totalChargedAmount: '500.00',
}

export const mockLoginRes: LoginRes = {
  token: 'mock-token-sub2rebate-dev',
  tokenType: 'Bearer',
  user: mockUser,
}

export const mockAdminLoginRes: LoginRes = {
  token: 'mock-token-sub2rebate-admin',
  tokenType: 'Bearer',
  user: mockAdminUser,
}

export const mockMeRes: MeRes = {
  user: mockUser,
  balance: mockBalance,
  sub2ApiBalance: mockSub2ApiBalance,
}

export const mockAdminMeRes: MeRes = {
  user: mockAdminUser,
  balance: mockBalance,
  sub2ApiBalance: mockSub2ApiBalance,
}

export const mockAccountProfile: AccountProfile = {
  user: mockUser,
  invite: {
    inviteCode: 'ABCD12',
    inviteUrl: 'https://rebate.example.com/register?inviteCode=ABCD12',
    sub2ApiAffCode: 'SUB2AFF12',
    sub2ApiInviteUrl: 'https://api.sjiaa.cc.cd/register?aff=SUB2AFF12',
    sub2ApiAffiliatePageUrl: 'https://api.sjiaa.cc.cd/affiliate',
    parentNickname: '上级用户',
    depth: 2,
  },
  balance: mockBalance,
  sub2ApiBalance: mockSub2ApiBalance,
}
