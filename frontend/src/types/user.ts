export type UserRole = 'user' | 'admin'

export interface User {
  id: number
  username: string
  nickname: string
  email: string
  avatar: string
  role: UserRole
  createdAt: string
}

export interface Balance {
  availableAmount: string
  frozenAmount: string
  totalAmount: string
  withdrawnAmount: string
}

export interface LoginReq {
  account: string
  password: string
}

export interface LoginRes {
  token: string
  tokenType: string
  user: User
}

export interface MeRes {
  user: User
  balance: Balance
}

export interface AccountProfile {
  user: User
  invite: {
    inviteCode: string
    inviteUrl: string
    sub2ApiAffCode: string
    sub2ApiInviteUrl: string
    sub2ApiAffiliatePageUrl: string
    parentNickname: string | null
    depth: number
  }
  balance: Balance
}
