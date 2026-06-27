<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppCard from '@/components/common/AppCard.vue'
import MetricCard from '@/components/common/MetricCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { getRebateRecords } from '@/api/rebate'
import { money } from '@/utils/money'
import { getRebateStatusDisplay, getRebateTypeText } from '@/utils/status'
import type { RebateRecord, RebateStatus, RebateType } from '@/types/rebate'

const loading = ref(false)
const records = ref<RebateRecord[]>([])
const pagination = ref({ page: 1, pageSize: 10, total: 0 })
const typeFilter = ref<RebateType | ''>('')
const statusFilter = ref<RebateStatus | ''>('')
const dateRange = ref<[string, string] | null>(null)

const typeOptions: { label: string; value: RebateType | '' }[] = [
  { label: '全部类型', value: '' },
  { label: '里程碑奖励', value: 'milestone' },
  { label: '多级返利', value: 'decay' },
  { label: '手工调整', value: 'manual' },
]

const statusOptions: { label: string; value: RebateStatus | '' }[] = [
  { label: '全部状态', value: '' },
  { label: '待确认', value: 'pending' },
  { label: '已确认', value: 'confirmed' },
  { label: '冻结中', value: 'frozen' },
  { label: '可提现', value: 'available' },
  { label: '已取消', value: 'canceled' },
]

const monthAmount = computed(() => {
  const now = new Date()
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  return records.value
    .filter((item) => item.createdAt.startsWith(month))
    .reduce((sum, item) => sum + Number(item.rebateAmount || 0), 0)
})

const totalAmount = computed(() =>
  records.value.reduce((sum, item) => sum + Number(item.rebateAmount || 0), 0),
)

const pendingAmount = computed(() =>
  records.value
    .filter((item) => ['pending', 'frozen'].includes(item.status))
    .reduce((sum, item) => sum + Number(item.rebateAmount || 0), 0),
)

const fetchRecords = async (page = 1) => {
  loading.value = true
  try {
    pagination.value.page = page
    const [startDate, endDate] = dateRange.value || []
    const res = await getRebateRecords({
      page,
      pageSize: pagination.value.pageSize,
      type: typeFilter.value || undefined,
      status: statusFilter.value || undefined,
      startDate,
      endDate,
    })
    if (res.code === 0) {
      records.value = res.data.list
      pagination.value.page = res.data.page
      pagination.value.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

const onFilterChange = () => fetchRecords(1)

onMounted(() => fetchRecords())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="返利明细" description="查看返利来源、层级、状态和结算记录。" />

    <div class="grid gap-4 md:grid-cols-3">
      <MetricCard label="本页本月返利" :value="money(monthAmount)" hint="按当前筛选结果统计" />
      <MetricCard label="本页累计返利" :value="money(totalAmount)" hint="当前页记录合计" hint-type="muted" />
      <MetricCard label="待结算金额" :value="money(pendingAmount)" hint="待确认/冻结中" hint-type="muted" />
    </div>

    <AppCard>
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <el-select v-model="typeFilter" size="small" style="width: 140px" @change="onFilterChange">
          <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-select v-model="statusFilter" size="small" style="width: 140px" @change="onFilterChange">
          <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          size="small"
          value-format="YYYY-MM-DD"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          style="width: 240px"
          @change="onFilterChange"
        />
      </div>

      <el-table :data="records" style="width: 100%" v-loading="loading">
        <el-table-column prop="createdAt" label="时间" width="160" />
        <el-table-column prop="type" label="类型" width="120">
          <template #default="{ row }">
            <StatusTag :text="getRebateTypeText(row.type)" type="primary" />
          </template>
        </el-table-column>
        <el-table-column prop="sourceUserName" label="来源用户" min-width="120" />
        <el-table-column prop="level" label="层级" width="80" />
        <el-table-column prop="sourceAmount" label="来源金额" width="110">
          <template #default="{ row }">{{ money(row.sourceAmount) }}</template>
        </el-table-column>
        <el-table-column prop="rebateAmount" label="返利金额" width="110">
          <template #default="{ row }">
            <span class="font-semibold text-[var(--sr-success)]">+{{ money(row.rebateAmount) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <StatusTag :text="getRebateStatusDisplay(row.status).text" :type="getRebateStatusDisplay(row.status).type" />
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
      </el-table>

      <EmptyState v-if="!records.length && !loading" title="暂无返利明细" description="产生返利后会显示在这里" />

      <div v-if="pagination.total > pagination.pageSize" class="mt-4 flex justify-end">
        <el-pagination
          v-model:current-page="pagination.page"
          :page-size="pagination.pageSize"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchRecords"
        />
      </div>
    </AppCard>
  </div>
</template>
