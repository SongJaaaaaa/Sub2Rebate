export type RebateType = 'milestone' | 'decay' | 'manual'
export type RebateStatus = 'pending' | 'confirmed' | 'frozen' | 'available' | 'canceled'

export interface DashboardSummary {
  todayRebateAmount: string
  monthRebateAmount: string
  totalRebateAmount: string
  totalRechargeCreditAmount?: string
  availableAmount: string
  directInviteCount: number
  teamInviteCount: number
  pendingWithdrawAmount: string
}

export interface RebateTrendItem {
  date: string
  rebateAmount: string
  inviteCount: number
}

export interface RecentActivity {
  id: number
  type: string
  title: string
  content: string
  amount: string
  createdAt: string
}

export interface RebateRecord {
  id: number
  type: RebateType
  status: RebateStatus
  sourceUserId: number
  sourceUserName: string
  receiverUserId: number
  level: number
  sourceAmount: string
  rebateAmount: string
  remark: string
  createdAt: string
}

export interface InviteInfo {
  inviteCode: string
  inviteUrl: string
  sub2ApiAffCode: string
  sub2ApiInviteUrl: string
  sub2ApiAffiliatePageUrl: string
  parent: { id: number; username: string; nickname: string } | null
  depth: number
  directInviteCount: number
  teamInviteCount: number
}

export interface InviteTreeNode {
  id: number
  username: string
  nickname: string
  level: number
  children: InviteTreeNode[]
}

export interface InviteRecord {
  id: number
  username: string
  nickname: string
  level: number
  totalPaidAmount: string
  totalRebateAmount: string
  boundAt: string
}

export interface PromotionSummary {
  inviteCode: string
  inviteUrl: string
  sub2ApiAffCode: string
  sub2ApiInviteUrl: string
  sub2ApiAffiliatePageUrl: string
  directInviteCount: number
  teamInviteCount: number
  conversionRate: string
  totalPaidUserCount: number
  totalRebateAmount: string
}

export interface ConversionRecord {
  id: number
  userId: number
  username: string
  nickname: string
  level: number
  paidAmount: string
  rebateAmount: string
  type: RebateType
  createdAt: string
}
