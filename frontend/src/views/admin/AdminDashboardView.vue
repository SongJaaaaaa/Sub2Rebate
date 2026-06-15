<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import MetricCard from '@/components/common/MetricCard.vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import TrendChart from '@/components/common/TrendChart.vue'
import { getAdminDashboard, getAdminTrends } from '@/api/admin'
import { money } from '@/utils/money'
import type { AdminDashboardStats, AdminTrendItem } from '@/types/admin'

const stats = ref<AdminDashboardStats | null>(null)
const trends = ref<AdminTrendItem[]>([])
const loading = ref(false)

const metrics = computed(() => {
  if (!stats.value) return []
  return [
    { label: '总用户数', value: `${stats.value.totalUsers}`, hint: `今日 +${stats.value.todayNewUsers}` },
    { label: '累计返利', value: money(stats.value.totalRebateAmount), hint: `本月 ${money(stats.value.monthRebateAmount)}` },
    { label: '累计提现', value: money(stats.value.totalWithdrawAmount), hint: `待处理 ${stats.value.pendingWithdrawCount} 笔` },
    { label: '待审提现', value: money(stats.value.pendingWithdrawAmount), hint: `${stats.value.pendingWithdrawCount} 笔待审` },
  ]
})

const rebateTrend = computed(() => trends.value.map((t) => ({ date: t.date, value: parseFloat(t.rebateAmount) })))
const userTrend = computed(() => trends.value.map((t) => ({ date: t.date, value: t.newUsers })))

onMounted(async () => {
  loading.value = true
  try {
    const [dashRes, trendRes] = await Promise.all([getAdminDashboard(), getAdminTrends()])
    if (dashRes.code === 0) stats.value = dashRes.data
    if (trendRes.code === 0) trends.value = trendRes.data.items
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="数据看板" description="全平台核心指标总览，数据每 5 分钟自动刷新。" />

    <div v-loading="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <MetricCard v-for="item in metrics" :key="item.label" v-bind="item" />
    </div>

    <!-- 快捷操作提示 -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] px-4 py-3">
      <span class="text-xs font-semibold text-[var(--sr-muted)]">快捷入口：</span>
      <router-link to="/admin/withdrawals" class="text-xs text-[var(--sr-secondary)] hover:underline">待审提现 →</router-link>
      <router-link to="/admin/audit-log" class="text-xs text-[var(--sr-secondary)] hover:underline">审计日志 →</router-link>
      <router-link to="/admin/rebate-config" class="text-xs text-[var(--sr-secondary)] hover:underline">返利配置 →</router-link>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <AppCard>
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-bold">返利趋势（近 7 日）</h2>
          <span class="text-xs text-[var(--sr-muted)]">单位：CNY</span>
        </div>
        <TrendChart v-if="rebateTrend.length" :data="rebateTrend" :height="200" />
        <div v-else class="flex h-[200px] items-center justify-center text-sm text-[var(--sr-muted)]">暂无数据</div>
      </AppCard>
      <AppCard>
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-bold">新增用户（近 7 日）</h2>
          <span class="text-xs text-[var(--sr-muted)]">单位：人</span>
        </div>
        <TrendChart v-if="userTrend.length" :data="userTrend" :height="200" color="var(--sr-primary)" />
        <div v-else class="flex h-[200px] items-center justify-center text-sm text-[var(--sr-muted)]">暂无数据</div>
      </AppCard>
    </div>
  </div>
</template>
