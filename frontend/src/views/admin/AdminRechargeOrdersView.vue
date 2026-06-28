<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { getAdminRechargeOrders, approveRechargeOrder, rejectRechargeOrder, retryRechargeCredit } from '@/api/recharge'
import { pageSizes } from '@/constants/pagination'
import { money } from '@/utils/money'
import { getRechargeStatus } from '@/utils/status'
import type { AdminRechargeOrder } from '@/types/recharge'

const rows = ref<AdminRechargeOrder[]>([])
const loading = ref(false)
const statusFilter = ref('')
const pagination = ref({ page: 1, pageSize: 10, total: 0 })

const opts = [
  { label: '全部', value: '' },
  { label: '待支付', value: 'pending' },
  { label: '待审核', value: 'submitted' },
  { label: '入账中', value: 'paid' },
  { label: '入账失败', value: 'failed' },
  { label: '已到账', value: 'approved' },
  { label: '已拒绝', value: 'rejected' },
  { label: '已过期', value: 'expired' },
]

const fetchList = async (page = 1) => {
  loading.value = true
  try {
    const res = await getAdminRechargeOrders(page, pagination.value.pageSize, statusFilter.value || undefined)
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

const onSizeChange = (size: number) => {
  pagination.value.pageSize = size
  fetchList(1)
}

const onRetry = async (row: AdminRechargeOrder) => {
  const res = await retryRechargeCredit(row.id)
  if (res.code === 0) {
    await fetchList(pagination.value.page)
    ElMessage.success('已重试入账')
  }
}

onMounted(() => fetchList())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="充值审核" description="审核手工二维码充值订单，查看 Epay 自动回调订单并处理入账失败。" />

    <AppCard>
      <div class="mb-4 flex items-center justify-between gap-3">
        <el-select v-model="statusFilter" placeholder="状态筛选" clearable style="width: 160px" @change="() => fetchList(1)">
          <el-option v-for="opt in opts" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <span class="text-xs text-[var(--sr-muted)]">手工二维码需要审核；Epay 支付成功会自动回调入账。</span>
      </div>

      <el-table :data="rows" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="nickname" label="用户" min-width="100" />
        <el-table-column prop="orderNo" label="订单号" min-width="180" show-overflow-tooltip />
        <el-table-column prop="outTradeNo" label="支付单号" min-width="170" show-overflow-tooltip />
        <el-table-column prop="channel" label="通道" width="110">
          <template #default="{ row }">{{ row.channel === 'epay' ? 'Epay' : '支付宝' }}</template>
        </el-table-column>
        <el-table-column prop="amount" label="实付" width="100">
          <template #default="{ row }">{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column prop="paidAmount" label="到账金额" width="100">
          <template #default="{ row }">{{ row.paidAmount ? money(row.paidAmount) : '-' }}</template>
        </el-table-column>
        <el-table-column prop="creditAmount" label="到账额度" width="100">
          <template #default="{ row }">{{ money(row.creditAmount) }}</template>
        </el-table-column>
        <el-table-column prop="payerName" label="付款姓名" width="100" />
        <el-table-column prop="payerAccount" label="付款账号" min-width="140" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <StatusTag :text="getRechargeStatus(row.status).text" :type="getRechargeStatus(row.status).type" />
          </template>
        </el-table-column>
        <el-table-column prop="submittedAt" label="提交时间" width="160" />
        <el-table-column prop="paidAt" label="支付时间" width="160" />
        <el-table-column prop="creditFailMsg" label="失败原因" min-width="180" show-overflow-tooltip />
        <el-table-column prop="reviewRemark" label="审核备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="190" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'submitted'">
              <el-button type="success" text size="small" @click="onApprove(row)">确认到账</el-button>
              <el-button type="danger" text size="small" @click="onReject(row)">拒绝</el-button>
            </template>
            <template v-else-if="row.channel === 'epay' && row.creditStatus === 'failed'">
              <el-button type="primary" text size="small" @click="onRetry(row)">重试入账</el-button>
            </template>
            <span v-else class="text-xs text-[var(--sr-muted)]">—</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex items-center justify-end">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :page-sizes="pageSizes"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="fetchList"
          @size-change="onSizeChange"
        />
      </div>
    </AppCard>
  </div>
</template>
