<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { DocumentCopy, Refresh } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import MetricCard from '@/components/common/MetricCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { usePromotionStore } from '@/stores/promotion'
import { useAuthStore } from '@/stores/auth'
import { money } from '@/utils/money'
import { getRebateTypeText, getRebateStatusDisplay } from '@/utils/status'
import { getRebateRecords } from '@/api/promotion'
import type { RebateRecord } from '@/types/rebate'

const promotion = usePromotionStore()
const auth = useAuthStore()
const copied = ref(false)
const activeTab = ref('invites')
const refreshing = ref(false)

// 返利记录
const rebateRecords = ref<RebateRecord[]>([])
const rebateLoading = ref(false)
const rebatePagination = ref({ page: 1, pageSize: 10, total: 0 })

// 邀请记录分页
const invitePagination = ref({ page: 1, pageSize: 10, total: 0 })
// 转化记录分页
const conversionPagination = ref({ page: 1, pageSize: 10, total: 0 })

const copyLink = async () => {
  if (!promotion.summary?.sub2ApiInviteUrl) return
  try {
    await navigator.clipboard.writeText(promotion.summary.sub2ApiInviteUrl)
    copied.value = true
    ElMessage.success('Sub2API 邀请链接已复制')
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    ElMessage.error('复制失败，请手动复制')
  }
}

const fetchRebateRecords = async (page = 1) => {
  rebateLoading.value = true
  try {
    const res = await getRebateRecords(page, rebatePagination.value.pageSize)
    if (res.code === 0) {
      rebateRecords.value = res.data.list
      rebatePagination.value.page = res.data.page
      rebatePagination.value.total = res.data.total
    }
  } finally {
    rebateLoading.value = false
  }
}

const handleInvitePageChange = async (page: number) => {
  invitePagination.value.page = page
  await promotion.fetchInviteRecords(page, invitePagination.value.pageSize)
  invitePagination.value.total = promotion.inviteTotal
}

const handleConversionPageChange = async (page: number) => {
  conversionPagination.value.page = page
  await promotion.fetchConversions(page, conversionPagination.value.pageSize)
  conversionPagination.value.total = promotion.conversionTotal
}

const handleRebatePageChange = (page: number) => {
  fetchRebateRecords(page)
}

const refreshPage = async () => {
  refreshing.value = true
  try {
    await Promise.all([
      auth.fetchMe(),
      promotion.fetchSummary(),
      promotion.fetchConversions(conversionPagination.value.page, conversionPagination.value.pageSize),
      promotion.fetchInviteRecords(invitePagination.value.page, invitePagination.value.pageSize),
      fetchRebateRecords(rebatePagination.value.page),
    ])
    invitePagination.value.total = promotion.inviteTotal
    conversionPagination.value.total = promotion.conversionTotal
  } finally {
    refreshing.value = false
  }
}

onMounted(() => refreshPage())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="推广中心" description="分享推广链接，邀请用户，获得多级返利。">
      <template #actions>
        <el-button :icon="Refresh" :loading="refreshing" @click="refreshPage">刷新</el-button>
      </template>
    </PageHeader>

    <!-- 推广链接 -->
    <AppCard>
      <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wide text-[var(--sr-muted)]">Sub2API 邀请链接</div>
          <div class="mt-3 break-all rounded-lg bg-[var(--sr-surface-low)] p-4 font-mono text-sm">
            {{ promotion.summary?.sub2ApiInviteUrl || (promotion.loading ? '加载中...' : '暂无 Sub2API 邀请链接') }}
          </div>
        </div>
        <el-button type="primary" :icon="DocumentCopy" :disabled="!promotion.summary?.sub2ApiInviteUrl" @click="copyLink">
          {{ copied ? '已复制' : '复制链接' }}
        </el-button>
      </div>
      <div class="mt-4 text-sm text-[var(--sr-muted)]">
        Sub2API 邀请码：<span class="font-bold text-[var(--sr-primary)]">{{ promotion.summary?.sub2ApiAffCode || '-' }}</span>
      </div>
    </AppCard>

    <!-- 指标 -->
    <div v-if="promotion.summary" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      <MetricCard label="可提现余额" :value="money(auth.balance?.availableAmount || '0')" hint="可发起提现" />
      <MetricCard label="直邀人数" :value="`${promotion.summary.directInviteCount}`" hint="直接推广" hint-type="muted" />
      <MetricCard label="团队人数" :value="`${promotion.summary.teamInviteCount}`" hint="含间接下级" hint-type="muted" />
      <MetricCard label="转化率" :value="`${(parseFloat(promotion.summary.conversionRate) * 100).toFixed(1)}%`" hint="付费用户占比" />
      <MetricCard label="累计返利" :value="money(promotion.summary.totalRebateAmount)" hint="全部已获返利" />
    </div>

    <!-- Tabs：邀请记录 / 转化记录 / 返利记录 -->
    <AppCard>
      <el-tabs v-model="activeTab">
        <el-tab-pane label="邀请记录" name="invites">
          <el-table :data="promotion.inviteRecords" style="width: 100%" v-loading="promotion.loading">
            <el-table-column prop="nickname" label="用户" min-width="100" />
            <el-table-column prop="level" label="层级" width="70" />
            <el-table-column prop="totalPaidAmount" label="累计充值" width="110">
              <template #default="{ row }">{{ money(row.totalPaidAmount) }}</template>
            </el-table-column>
            <el-table-column prop="totalRebateAmount" label="贡献返利" width="110">
              <template #default="{ row }">{{ money(row.totalRebateAmount) }}</template>
            </el-table-column>
            <el-table-column prop="boundAt" label="加入时间" width="160" />
          </el-table>
          <div v-if="invitePagination.total > invitePagination.pageSize" class="mt-4 flex justify-end">
            <el-pagination
              v-model:current-page="invitePagination.page"
              :page-size="invitePagination.pageSize"
              :total="invitePagination.total"
              layout="prev, pager, next"
              @current-change="handleInvitePageChange"
            />
          </div>
          <EmptyState v-if="!promotion.inviteRecords.length && !promotion.loading" title="暂无邀请记录" description="分享推广链接开始邀请吧" />
        </el-tab-pane>

        <el-tab-pane label="转化记录" name="conversions">
          <el-table :data="promotion.conversions" style="width: 100%">
            <el-table-column prop="nickname" label="用户" min-width="100" />
            <el-table-column prop="paidAmount" label="充值金额" width="100">
              <template #default="{ row }">{{ money(row.paidAmount) }}</template>
            </el-table-column>
            <el-table-column prop="rebateAmount" label="返利金额" width="100">
              <template #default="{ row }">
                <span class="text-[var(--sr-success)]">+{{ money(row.rebateAmount) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="type" label="类型" width="100">
              <template #default="{ row }">
                <StatusTag :text="getRebateTypeText(row.type)" type="primary" />
              </template>
            </el-table-column>
            <el-table-column prop="createdAt" label="时间" width="160" />
          </el-table>
          <div v-if="conversionPagination.total > conversionPagination.pageSize" class="mt-4 flex justify-end">
            <el-pagination
              v-model:current-page="conversionPagination.page"
              :page-size="conversionPagination.pageSize"
              :total="conversionPagination.total"
              layout="prev, pager, next"
              @current-change="handleConversionPageChange"
            />
          </div>
          <EmptyState v-if="!promotion.conversions.length" title="暂无转化记录" description="下级用户付费后，转化记录会显示在这里" />
        </el-tab-pane>

        <el-tab-pane label="返利记录" name="rebates">
          <el-table :data="rebateRecords" style="width: 100%" v-loading="rebateLoading">
            <el-table-column prop="sourceUserName" label="来源用户" min-width="100" />
            <el-table-column prop="sourceAmount" label="充值金额" width="100">
              <template #default="{ row }">{{ money(row.sourceAmount) }}</template>
            </el-table-column>
            <el-table-column prop="rebateAmount" label="返利金额" width="100">
              <template #default="{ row }">
                <span class="text-[var(--sr-success)]">+{{ money(row.rebateAmount) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="type" label="类型" width="100">
              <template #default="{ row }">
                <StatusTag :text="getRebateTypeText(row.type)" type="primary" />
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="90">
              <template #default="{ row }">
                <StatusTag :text="getRebateStatusDisplay(row.status).text" :type="getRebateStatusDisplay(row.status).type" />
              </template>
            </el-table-column>
            <el-table-column prop="createdAt" label="时间" width="160" />
          </el-table>
          <div v-if="rebatePagination.total > rebatePagination.pageSize" class="mt-4 flex justify-end">
            <el-pagination
              v-model:current-page="rebatePagination.page"
              :page-size="rebatePagination.pageSize"
              :total="rebatePagination.total"
              layout="prev, pager, next"
              @current-change="handleRebatePageChange"
            />
          </div>
          <EmptyState v-if="!rebateRecords.length && !rebateLoading" title="暂无返利记录" description="产生返利后会显示在这里" />
        </el-tab-pane>
      </el-tabs>
    </AppCard>
  </div>
</template>
