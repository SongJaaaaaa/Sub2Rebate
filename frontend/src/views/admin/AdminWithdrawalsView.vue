<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { getAdminWithdrawals, approveWithdraw, markPaid, rejectWithdraw } from '@/api/admin'
import { pageSizes } from '@/constants/pagination'
import { money } from '@/utils/money'
import { getWithdrawStatus } from '@/utils/status'
import type { AdminWithdrawRecord } from '@/types/admin'

const records = ref<AdminWithdrawRecord[]>([])
const loading = ref(false)
const statusFilter = ref('')
const pagination = ref({ page: 1, pageSize: 10, total: 0 })

const statusOptions = [
  { label: '全部', value: '' },
  { label: '待审核', value: 'pending' },
  { label: '已通过', value: 'approved' },
  { label: '已打款', value: 'paid' },
  { label: '已拒绝', value: 'rejected' },
]

const fetchList = async (page = 1) => {
  loading.value = true
  try {
    const res = await getAdminWithdrawals(page, pagination.value.pageSize, statusFilter.value || undefined)
    if (res.code === 0) {
      records.value = res.data.list
      pagination.value.page = res.data.page
      pagination.value.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

const onSizeChange = (size: number) => {
  pagination.value.pageSize = size
  fetchList(1)
}

const patchRow = (row: AdminWithdrawRecord, data: AdminWithdrawRecord) => {
  Object.assign(row, data)
}

const onApprove = async (row: AdminWithdrawRecord) => {
  await ElMessageBox.confirm(`通过「${row.nickname}」的提现申请 ¥${row.amount}？`, '审批通过', { confirmButtonText: '通过', type: 'success' })
  const res = await approveWithdraw(row.id)
  if (res.code === 0) {
    patchRow(row, res.data)
    ElMessage.success('已通过')
  }
}

const onMarkPaid = async (row: AdminWithdrawRecord) => {
  const title = row.payoutError ? '重试打款' : '确认打款'
  await ElMessageBox.confirm(`确认打款 ¥${row.amount} 给「${row.nickname}」？`, title, { confirmButtonText: title, type: 'success' })
  const res = await markPaid(row.id)
  if (res.code === 0) {
    patchRow(row, res.data)
    ElMessage.success(row.payoutTradeNo ? '自动打款成功' : '已标记打款')
  }
}

const onReject = async (row: AdminWithdrawRecord) => {
  const { value: reason } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝提现', {
    confirmButtonText: '确认拒绝',
    cancelButtonText: '取消',
    inputPlaceholder: '如：账号信息有误',
    inputValidator: (val) => !!val?.trim() || '请输入拒绝原因',
    type: 'warning',
  })
  const res = await rejectWithdraw(row.id, reason)
  if (res.code === 0) {
    patchRow(row, res.data)
    ElMessage.success('已拒绝')
  }
}

onMounted(() => fetchList())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="提现审核" description="审批用户提现申请、确认打款。" />

    <!-- 流程说明 -->
    <div class="flex items-center gap-6 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
      <div class="flex items-center gap-2 text-xs text-blue-700">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-200 text-[10px] font-bold">1</span>
        <span>用户提交申请</span>
      </div>
      <span class="text-blue-300">→</span>
      <div class="flex items-center gap-2 text-xs text-blue-700">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-200 text-[10px] font-bold">2</span>
        <span>管理员审核</span>
      </div>
      <span class="text-blue-300">→</span>
      <div class="flex items-center gap-2 text-xs text-blue-700">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-200 text-[10px] font-bold">3</span>
        <span>确认打款</span>
      </div>
      <span class="text-blue-300">→</span>
      <div class="flex items-center gap-2 text-xs text-blue-700">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-green-200 text-[10px] font-bold text-green-700">✓</span>
        <span>完成</span>
      </div>
    </div>

    <AppCard>
      <div class="mb-4 flex items-center justify-between gap-3">
        <el-select v-model="statusFilter" placeholder="状态筛选" clearable style="width: 140px" @change="() => fetchList(1)">
          <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <span class="text-xs text-[var(--sr-muted)]">提示：未开启支付宝自动打款时，确认打款仅标记线下完成。</span>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && !records.length" class="py-12 text-center">
        <div class="text-lg text-[var(--sr-muted)]">暂无提现记录</div>
        <p class="mt-2 text-sm text-[var(--sr-muted)]">当前筛选条件下没有匹配的提现申请</p>
      </div>

      <el-table v-else :data="records" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="nickname" label="用户" min-width="100" />
        <el-table-column prop="amount" label="金额" width="100">
          <template #default="{ row }">{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column prop="realName" label="姓名" width="80" />
        <el-table-column prop="accountNo" label="账号" min-width="140" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tooltip :content="row.status === 'pending' ? '等待管理员审核' : row.status === 'approved' ? '已通过审核，待打款' : row.status === 'paid' ? '已完成打款' : '申请被拒绝'" placement="top">
              <StatusTag :text="getWithdrawStatus(row.status).text" :type="getWithdrawStatus(row.status).type" />
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column prop="rejectReason" label="拒绝原因" min-width="100" show-overflow-tooltip />
        <el-table-column prop="payoutTradeNo" label="转账流水" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ row.payoutTradeNo || '-' }}</template>
        </el-table-column>
        <el-table-column prop="payoutError" label="打款异常" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <span :class="row.payoutError ? 'text-red-500' : 'text-[var(--sr-muted)]'">{{ row.payoutError || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="申请时间" width="160" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'pending'">
              <el-tooltip content="审核通过后需要线下打款" placement="top">
                <el-button type="success" text size="small" @click="onApprove(row)">通过</el-button>
              </el-tooltip>
              <el-button type="danger" text size="small" @click="onReject(row)">拒绝</el-button>
            </template>
            <template v-else-if="row.status === 'approved'">
              <el-tooltip :content="row.payoutError ? '上次自动打款失败，可修正配置或账号后重试' : '开启自动打款时会调用支付宝转账，否则只标记线下完成'" placement="top">
                <el-button type="primary" text size="small" @click="onMarkPaid(row)">{{ row.payoutError ? '重试打款' : '确认打款' }}</el-button>
              </el-tooltip>
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
