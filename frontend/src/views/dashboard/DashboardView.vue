<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import MetricCard from '@/components/common/MetricCard.vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import TrendChart from '@/components/common/TrendChart.vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useAuthStore } from '@/stores/auth'
import { money } from '@/utils/money'

const dashboard = useDashboardStore()
const auth = useAuthStore()
const refreshing = ref(false)

const stats = computed(() => {
  if (!dashboard.summary) return []
  return [
    { label: '可提现余额', value: money(dashboard.summary.availableAmount), hint: '可发起提现' },
    { label: '今日返利', value: money(dashboard.summary.todayRebateAmount), hint: '+今日' },
    { label: '累计返利', value: money(dashboard.summary.totalRebateAmount), hint: '历史累计', hintType: 'muted' as const },
    { label: '团队人数', value: `${dashboard.summary.teamInviteCount}`, hint: `直邀 ${dashboard.summary.directInviteCount}` },
  ]
})

const trendChartData = computed(() =>
  dashboard.trends.map((t) => ({ date: t.date, value: parseFloat(t.rebateAmount) }))
)

const onRefresh = async () => {
  refreshing.value = true
  try {
    await Promise.all([auth.fetchMe(), dashboard.fetchDashboard()])
  } finally {
    refreshing.value = false
  }
}

onMounted(() => onRefresh())
</script>

<template>
  <div class="space-y-6">
    <PageHeader :title="`欢迎回来，${auth.user?.nickname || '用户'}`" description="查看返利余额、邀请表现和最近动态。">
      <template #actions>
        <div class="flex gap-2">
          <el-button :icon="Refresh" :loading="refreshing" @click="onRefresh">刷新</el-button>
          <RouterLink to="/withdraw">
            <el-button type="primary">申请提现</el-button>
          </RouterLink>
        </div>
      </template>
    </PageHeader>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <MetricCard v-for="item in stats" :key="item.label" v-bind="item" />
    </div>

    <!-- 返利趋势图 -->
    <AppCard>
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">返利趋势（近 7 日）</h2>
      </div>
      <TrendChart v-if="trendChartData.length" :data="trendChartData" :height="200" />
      <EmptyState v-else-if="!dashboard.loading" title="暂无趋势数据" />
    </AppCard>

    <!-- 最近动态 -->
    <AppCard>
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">最近动态</h2>
        <RouterLink class="text-sm font-semibold text-[var(--sr-secondary)]" to="/promotion">去推广中心</RouterLink>
      </div>

      <EmptyState v-if="!dashboard.activities.length && !dashboard.loading" title="暂无动态" description="开始推广后，返利记录将显示在这里。" />

      <el-table v-else :data="dashboard.activities" style="width: 100%" v-loading="dashboard.loading">
        <el-table-column prop="title" label="事件" min-width="120" />
        <el-table-column prop="content" label="详情" min-width="160" show-overflow-tooltip />
        <el-table-column prop="amount" label="金额" width="100">
          <template #default="{ row }">
            <span :class="{ 'text-[var(--sr-success)]': parseFloat(row.amount) > 0 }">
              {{ parseFloat(row.amount) > 0 ? '+' : '' }}{{ money(row.amount) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="时间" width="160" />
      </el-table>
    </AppCard>
  </div>
</template>
