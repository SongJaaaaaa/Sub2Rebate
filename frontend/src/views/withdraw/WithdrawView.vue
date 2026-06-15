<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import MetricCard from '@/components/common/MetricCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useWithdrawStore } from '@/stores/withdraw'
import { useAuthStore } from '@/stores/auth'
import { money } from '@/utils/money'
import { getWithdrawStatus } from '@/utils/status'

const withdraw = useWithdrawStore()
const auth = useAuthStore()

const formRef = ref<FormInstance>()
const form = reactive({ amount: '', remark: '' })
const submitting = ref(false)

// 状态筛选 + 分页
const statusFilter = ref('')
const pagination = ref({ page: 1, pageSize: 10, total: 0 })

const statusOptions = [
  { label: '全部', value: '' },
  { label: '待审核', value: 'pending' },
  { label: '已通过', value: 'approved' },
  { label: '已打款', value: 'paid' },
  { label: '已拒绝', value: 'rejected' },
  { label: '已取消', value: 'canceled' },
]

const rules: FormRules = {
  amount: [
    { required: true, message: '请输入提现金额', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        const num = parseFloat(value)
        if (isNaN(num) || num <= 0) {
          callback(new Error('请输入有效金额'))
        } else if (withdraw.config && num < parseFloat(withdraw.config.minAmount)) {
          callback(new Error(`最低提现金额 ${withdraw.config.minAmount} 元`))
        } else {
          callback()
        }
      },
      trigger: 'blur',
    },
  ],
}

const onSubmit = async () => {
  if (!formRef.value) return
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  await ElMessageBox.confirm(`确认提现 ¥${form.amount}？`, '确认提现', { confirmButtonText: '确认', cancelButtonText: '取消' })

  submitting.value = true
  try {
    const balance = await withdraw.submitApply(form.amount, form.remark)
    if (balance) {
      auth.balance = balance
      ElMessage.success('提现申请已提交，等待审核')
      form.amount = ''
      form.remark = ''
      formRef.value.resetFields()
      // 刷新记录列表
      fetchRecords()
    }
  } catch {
    ElMessage.error('提交失败，请重试')
  } finally {
    submitting.value = false
  }
}

const fetchRecords = async (page = 1) => {
  pagination.value.page = page
  await withdraw.fetchRecords(page, pagination.value.pageSize, statusFilter.value || undefined)
  pagination.value.total = withdraw.records.length
}

const onStatusChange = () => {
  fetchRecords(1)
}

const handlePageChange = (page: number) => {
  fetchRecords(page)
}

onMounted(async () => {
  await Promise.all([withdraw.fetchConfig(), withdraw.fetchAccount(), fetchRecords()])
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="提现管理" :description="withdraw.config ? `最低提现金额 ${money(withdraw.config.minAmount)}，${withdraw.config.reviewMode === 'manual' ? '人工审核' : '自动审核'}` : ''">
    </PageHeader>

    <!-- 余额指标 -->
    <div v-if="auth.balance" class="grid gap-4 md:grid-cols-3">
      <MetricCard label="可提现余额" :value="money(auth.balance.availableAmount)" hint="可发起提现" />
      <MetricCard label="冻结金额" :value="money(auth.balance.frozenAmount)" hint="审核中" hint-type="muted" />
      <MetricCard label="已提现" :value="money(auth.balance.withdrawnAmount)" hint="历史累计" hint-type="muted" />
    </div>

    <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
      <!-- 申请提现 -->
      <AppCard class="min-w-0">
        <h2 class="mb-4 text-lg font-bold">申请提现</h2>

        <div v-if="withdraw.config?.tips?.length" class="mb-4 rounded-lg bg-[var(--sr-surface-low)] p-3">
          <ul class="space-y-1 text-xs text-[var(--sr-muted)]">
            <li v-for="tip in withdraw.config.tips" :key="tip">• {{ tip }}</li>
          </ul>
        </div>

        <div v-if="withdraw.account" class="mb-4 rounded-lg border border-[var(--sr-border)] p-3">
          <div class="text-xs font-semibold text-[var(--sr-muted)]">提现账号</div>
          <div class="mt-1 text-sm font-semibold">{{ withdraw.account.realName }} · {{ withdraw.account.accountNo }}</div>
        </div>
        <div v-else class="mb-4 rounded-lg border border-[var(--sr-border)] border-dashed p-3 text-center text-xs text-[var(--sr-muted)]">
          请先到账户设置中绑定支付宝账号
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
          <el-form-item label="提现金额" prop="amount">
            <el-input v-model="form.amount" placeholder="请输入金额，如 100.00" :disabled="submitting" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="form.remark" placeholder="可选" :disabled="submitting" />
          </el-form-item>
          <el-button type="primary" class="w-full" :loading="submitting" :disabled="!withdraw.account" @click="onSubmit">
            提交申请
          </el-button>
        </el-form>
      </AppCard>

      <!-- 提现记录 -->
      <AppCard class="min-w-0">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-bold">提现记录</h2>
          <el-select v-model="statusFilter" placeholder="筛选状态" clearable size="small" style="width: 120px" @change="onStatusChange">
            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </div>

        <div class="overflow-x-auto">
          <div class="min-w-[520px]">
            <el-table :data="withdraw.records" style="width: 100%" v-loading="withdraw.loading">
          <el-table-column prop="amount" label="金额" width="100">
            <template #default="{ row }">{{ money(row.amount) }}</template>
          </el-table-column>
          <el-table-column prop="realName" label="账户" min-width="100" />
          <el-table-column prop="status" label="状态" width="90">
            <template #default="{ row }">
              <StatusTag :text="getWithdrawStatus(row.status).text" :type="getWithdrawStatus(row.status).type" />
            </template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="100" show-overflow-tooltip />
          <el-table-column prop="createdAt" label="申请时间" width="160" />
            </el-table>
          </div>
        </div>

        <EmptyState v-if="!withdraw.records.length && !withdraw.loading" title="暂无提现记录" description="提交提现申请后，记录会显示在这里" />

        <div v-if="pagination.total > pagination.pageSize" class="mt-4 flex justify-end">
          <el-pagination
            v-model:current-page="pagination.page"
            :page-size="pagination.pageSize"
            :total="pagination.total"
            layout="prev, pager, next"
            @current-change="handlePageChange"
          />
        </div>
      </AppCard>
    </div>
  </div>
</template>
