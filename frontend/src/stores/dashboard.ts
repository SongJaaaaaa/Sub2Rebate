import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getDashboardSummary, getRebateTrends, getRecentActivities } from '@/api/dashboard'
import type { DashboardSummary, RebateTrendItem, RecentActivity } from '@/types/rebate'

export const useDashboardStore = defineStore('dashboard', () => {
  const summary = ref<DashboardSummary | null>(null)
  const trends = ref<RebateTrendItem[]>([])
  const activities = ref<RecentActivity[]>([])
  const loading = ref(false)

  const fetchSummary = async () => {
    loading.value = true
    try {
      const res = await getDashboardSummary()
      if (res.code === 0) summary.value = res.data
    } finally {
      loading.value = false
    }
  }

  const fetchTrends = async (range = '7d') => {
    const res = await getRebateTrends(range)
    if (res.code === 0) trends.value = res.data.items
  }

  const fetchActivities = async () => {
    const res = await getRecentActivities()
    if (res.code === 0) activities.value = res.data.list
  }

  const fetchDashboard = async () => {
    await Promise.all([fetchSummary(), fetchTrends(), fetchActivities()])
  }

  return { summary, trends, activities, loading, fetchSummary, fetchTrends, fetchActivities, fetchDashboard }
})
