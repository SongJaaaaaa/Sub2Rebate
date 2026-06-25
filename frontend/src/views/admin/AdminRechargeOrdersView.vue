<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { getAdminRechargeOrders, approveRechargeOrder, rejectRechargeOrder } from '@/api/recharge'
import { money } from '@/utils/money'
import { getRechargeStatus } from '@/utils/status'
import type { AdminRechargeOrder } from '@/types/recharge'

const rows = ref<AdminRechargeOrder[]>([])
const loading = ref(false)
const statusFilter = ref('')
const channelFilter = ref('')
const keyword = ref('')
const pagination = ref({ page: 1, pageSize: 20, total: 0 })

const opts = [
  { label: '全部', value: '' },
  { label: '待支付', value: 'pending' },
  { label: '待审核', value: 'submitted' },
  { label: '已到账(人工)', value: 'approved' },
  { label: '已到账(在线)', value: 'paid' },
  { label: '已拒绝', value: 'rejected' },
  { label: '已过期', value: 'expired' },
]

const channelOpts = [
  { label: '全部通道', value: '' },
  { label: '在线支付(Epay)', value: 'epay' },
  { label: '人工二维码', value: 'alipay' },
]

const fetchList = async (page = 1) => {
  loading.value = true
  try {
    const res = await getAdminRechargeOrders(
      page,
      pagination.value.pageSize,
      statusFilter.value || undefined,
      keyword.value.trim() || undefined,
      channelFilter.value || undefined,
    )
    if (res.code === 0) {
      rows.value = res.data.list
      pagination.value.page = res.data.page
      pagination.value.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

const onApprove = async (row: AdminRechargeOrder) => {
  const { value } = await ElMessageBox.prompt('请输入审核备注', '确认到账', {
    confirmButtonText: '确认到账',
    inputPlaceholder: '如：已核对支付宝收款',
    inputValue: '已核对支付宝收款',
    inputValidator: (val) => !!val?.trim() || '请输入审核备注',
  })
  const res = await approveRechargeOrder(row.id, value)
  if (res.code === 0) {
    await fetchList(pagination.value.page)
    ElMessage.success('已确认到账并加额度')
  }
}

const onReject = async (row: AdminRechargeOrder) => {
  const { value } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝充值', {
    confirmButtonText: '确认拒绝',
    inputPlaceholder: '如：付款信息不匹配',
    inputValidator: (val) => !!val?.trim() || '请输入拒绝原因',
  })
  const res = await rejectRechargeOrder(row.id, value)
  if (res.code === 0) {
    await fetchList(pagination.value.page)
    ElMessage.success('已拒绝该充值订单')
  }
}

onMounted(() => fetchList())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="充值日志" description="全部充值订单：在线支付(Epay)自动到账与人工二维码审核统一查询，支持按订单号、Epay流水号、用户搜索。" />

    <AppCard>
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <el-input
          v-model="keyword"
          placeholder="搜索订单号 / Epay流水号 / 用户名"
          clearable
          style="width: 260px"
          @keyup.enter="() => fetchList(1)"
          @clear="() => fetchList(1)"
        />
        <el-select v-model="channelFilter" placeholder="通道" clearable style="width: 150px" @change="() => fetchList(1)">
          <el-option v-for="opt in channelOpts" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-select v-model="statusFilter" placeholder="状态筛选" clearable style="width: 150px" @change="() => fetchList(1)">
          <el-option v-for="opt in opts" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-button type="primary" @click="() => fetchList(1)">搜索</el-button>
      </div>

      <el-table :data="rows" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="64" />
        <el-table-column prop="nickname" label="用户" min-width="90" show-overflow-tooltip />
        <el-table-column prop="orderNo" label="订单号" min-width="170" show-overflow-tooltip />
        <el-table-column label="通道" width="90">
          <template #default="{ row }">
            <span class="text-xs">{{ row.channel === 'epay' ? '在线支付' : '人工码' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="epayTradeNo" label="支付流水号" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            <span class="font-mono text-xs">{{ row.epayTradeNo || '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="实付" width="90">
          <template #default="{ row }">{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column prop="creditAmount" label="到账额度" width="100">
          <template #default="{ row }">{{ money(row.creditAmount) }}</template>
        </el-table-column>
        <el-table-column label="充值前余额" width="110">
          <template #default="{ row }">
            <span v-if="row.sub2BalanceBefore != null">{{ money(row.sub2BalanceBefore) }}</span>
            <span v-else class="text-[var(--sr-muted)]">—</span>
          </template>
        </el-table-column>
        <el-table-column label="充值后余额" width="110">
          <template #default="{ row }">
            <span v-if="row.sub2BalanceAfter != null" class="font-semibold text-green-600">{{ money(row.sub2BalanceAfter) }}</span>
            <span v-else class="text-[var(--sr-muted)]">—</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <StatusTag :text="getRechargeStatus(row.status).text" :type="getRechargeStatus(row.status).type" />
          </template>
        </el-table-column>
        <el-table-column prop="paidAt" label="到账时间" width="150" />
        <el-table-column prop="reviewRemark" label="备注" min-width="140" show-overflow-tooltip />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'submitted'">
              <el-button type="success" text size="small" @click="onApprove(row)">确认到账</el-button>
              <el-button type="danger" text size="small" @click="onReject(row)">拒绝</el-button>
            </template>
            <span v-else class="text-xs text-[var(--sr-muted)]">—</span>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="pagination.total > pagination.pageSize" class="mt-4 flex items-center justify-between">
        <span class="text-xs text-[var(--sr-muted)]">共 {{ pagination.total }} 条记录</span>
        <el-pagination
          v-model:current-page="pagination.page"
          :page-size="pagination.pageSize"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </AppCard>
  </div>
</template>