<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import MetricCard from '@/components/common/MetricCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useWithdrawStore } from '@/stores/withdraw'
import { useAuthStore } from '@/stores/auth'
import { money } from '@/utils/money'
import { getWithdrawStatus } from '@/utils/status'
import { pageSizes } from '@/constants/pagination'

const withdraw = useWithdrawStore()
const auth = useAuthStore()

const formRef = ref<FormInstance>()
const form = reactive({ amount: '', remark: '' })
const submitting = ref(false)
const activeType = ref<'alipay' | 'api_quota'>('alipay')
const refreshing = ref(false)

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

  const isApiQuota = activeType.value === 'api_quota'
  await ElMessageBox.confirm(
    isApiQuota ? `确认转入 ${money(form.amount)} API 额度？` : `确认提现 ${money(form.amount)}？`,
    isApiQuota ? '确认转入 API 额度' : '确认提现',
    { confirmButtonText: '确认', cancelButtonText: '取消' },
  )

  submitting.value = true
  try {
    const balance = isApiQuota
      ? await withdraw.submitToApiQuota(form.amount, form.remark)
      : await withdraw.submitApply(form.amount, form.remark)
    if (balance) {
      auth.balance = balance
      if (withdraw.sub2ApiBalance) auth.sub2ApiBalance = withdraw.sub2ApiBalance
      ElMessage.success(isApiQuota ? '已转入 Sub2API 额度' : '提现申请已提交，等待审核')
      form.amount = ''
      form.remark = ''
      formRef.value.resetFields()
      // 刷新记录列表
      fetchRecords()
      await auth.fetchMe()
    }
  } catch {
    ElMessage.error('提交失败，请重试')
  } finally {
    submitting.value = false
  }
}

const quotaRateText = computed(() => {
  const rate = parseFloat(withdraw.config?.toApiQuotaRate || '1')
  return Number.isFinite(rate) && rate > 0 ? `${rate}` : '1'
})

const sub2ApiCurrent = computed(() => auth.sub2ApiBalance?.currentAmount || '0')

const sub2ApiAfter = computed(() => {
  if (activeType.value !== 'api_quota') return sub2ApiCurrent.value
  const amt = parseFloat(form.amount || '0')
  const rate = parseFloat(quotaRateText.value || '1')
  if (!Number.isFinite(amt) || amt <= 0) return sub2ApiCurrent.value
  const cur = parseFloat(sub2ApiCurrent.value || '0')
  return (cur + amt * rate).toFixed(2)
})

const recordTypeText = (type: string) => type === 'api_quota' ? 'API 额度' : '支付宝'

const fetchRecords = async (page = 1) => {
  pagination.value.page = page
  await withdraw.fetchRecords(page, pagination.value.pageSize, statusFilter.value || undefined)
  pagination.value.total = withdraw.total
}

const refreshPage = async () => {
  refreshing.value = true
  try {
    await Promise.all([
      auth.fetchMe(),
      withdraw.fetchConfig(),
      withdraw.fetchAccount(),
      fetchRecords(pagination.value.page),
    ])
  } finally {
    refreshing.value = false
  }
}

const onStatusChange = () => {
  fetchRecords(1)
}

const handlePageChange = (page: number) => {
  fetchRecords(page)
}

const handleSizeChange = (size: number) => {
  pagination.value.pageSize = size
  fetchRecords(1)
}

onMounted(() => refreshPage())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="提现管理" :description="withdraw.config ? `最低提现金额 ${money(withdraw.config.minAmount)}，${withdraw.config.reviewMode === 'manual' ? '人工审核' : '自动审核'}` : ''">
      <template #actions>
        <el-button :icon="Refresh" :loading="refreshing" @click="refreshPage">刷新</el-button>
      </template>
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
        <h2 class="mb-4 text-lg font-bold">返利余额处理</h2>

        <div v-if="withdraw.config?.tips?.length" class="mb-4 rounded-lg bg-[var(--sr-surface-low)] p-3">
          <ul class="space-y-1 text-xs text-[var(--sr-muted)]">
            <li v-for="tip in withdraw.config.tips" :key="tip">• {{ tip }}</li>
          </ul>
        </div>

        <el-tabs v-model="activeType" class="mb-4">
          <el-tab-pane label="提现到支付宝" name="alipay" />
          <el-tab-pane v-if="withdraw.config?.toApiQuotaEnabled" label="转入 API 额度" name="api_quota" />
        </el-tabs>

        <template v-if="activeType === 'alipay'">
          <div v-if="withdraw.account" class="mb-4 rounded-lg border border-[var(--sr-border)] p-3">
            <div class="text-xs font-semibold text-[var(--sr-muted)]">提现账号</div>
            <div class="mt-1 text-sm font-semibold">{{ withdraw.account.realName }} · {{ withdraw.account.accountNo }}</div>
          </div>
          <div v-else class="mb-4 rounded-lg border border-[var(--sr-border)] border-dashed p-3 text-center text-xs text-[var(--sr-muted)]">
            请先到账户设置中绑定支付宝账号
          </div>
        </template>
        <div v-else class="mb-4 rounded-lg border border-[var(--sr-border)] p-3">
          <div class="text-xs font-semibold text-[var(--sr-muted)]">转入目标</div>
          <div class="mt-1 text-sm font-semibold">Sub2API 账户额度 · 1：{{ quotaRateText }}</div>
          <div class="mt-2 text-xs text-[var(--sr-muted)]">
            当前额度 {{ money(sub2ApiCurrent) }}，预计转入后 {{ money(sub2ApiAfter) }}
          </div>
        </div>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
          <el-form-item :label="activeType === 'api_quota' ? '转入金额' : '提现金额'" prop="amount">
            <el-input v-model="form.amount" placeholder="请输入金额，如 100.00" :disabled="submitting" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="form.remark" placeholder="可选" :disabled="submitting" />
          </el-form-item>
          <el-button type="primary" class="w-full" :loading="submitting" :disabled="activeType === 'alipay' && !withdraw.account" @click="onSubmit">
            {{ activeType === 'api_quota' ? '立即转入 API 额度' : '提交申请' }}
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
          <div class="min-w-[760px]">
            <el-table :data="withdraw.records" style="width: 100%" v-loading="withdraw.loading">
          <el-table-column prop="type" label="方式" width="100">
            <template #default="{ row }">{{ recordTypeText(row.type) }}</template>
          </el-table-column>
          <el-table-column prop="amount" label="金额" width="100">
            <template #default="{ row }">{{ money(row.amount) }}</template>
          </el-table-column>
          <el-table-column prop="realName" label="账户" min-width="100" />
          <el-table-column prop="sub2ApiBalanceBefore" label="Sub2API 提现前额度" width="150">
            <template #default="{ row }">
              {{ row.type === 'api_quota' && row.sub2ApiBalanceBefore ? money(row.sub2ApiBalanceBefore) : '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="sub2ApiBalanceAfter" label="Sub2API 提现后额度" width="150">
            <template #default="{ row }">
              {{ row.type === 'api_quota' && row.sub2ApiBalanceAfter ? money(row.sub2ApiBalanceAfter) : '-' }}
            </template>
          </el-table-column>
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

        <div class="mt-4 flex justify-end">
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.pageSize"
            :page-sizes="pageSizes"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            @current-change="handlePageChange"
            @size-change="handleSizeChange"
          />
        </div>
      </AppCard>
    </div>
  </div>
</template>
