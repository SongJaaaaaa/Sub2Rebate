import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockDashboardSummary, mockRebateTrends, mockRecentActivities } from '@/mocks/dashboard'
import type { ApiRes } from '@/types/api'
import type { DashboardSummary, RebateTrendItem, RecentActivity } from '@/types/rebate'

export const getDashboardSummary = async (): Promise<ApiRes<DashboardSummary>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockDashboardSummary }
  }
  return request.get('/dashboard/summary')
}

export const getRebateTrends = async (range = '7d'): Promise<ApiRes<{ items: RebateTrendItem[] }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { items: mockRebateTrends } }
  }
  return request.get('/dashboard/rebate-trends', { params: { range } })
}

export const getRecentActivities = async (): Promise<ApiRes<{ list: RecentActivity[] }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { list: mockRecentActivities } }
  }
  return request.get('/dashboard/recent-activities')
}
