<script setup lang="ts">
import { ref, computed, onBeforeUnmount, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { createRechargeOrder, getRechargeConfig, getRechargeOrder, getRechargeOrders, submitRechargeOrder, syncEpayReturn } from '@/api/recharge'
import { money } from '@/utils/money'
import { getRechargeStatus } from '@/utils/status'
import type { RechargeConfig, RechargeOrder, EpayReturnReq } from '@/types/recharge'

const packages = [
  { id: 1, amount: 50, bonus: 0, label: '¥50' },
  { id: 2, amount: 100, bonus: 5, label: '¥100', tag: '送¥5' },
  { id: 3, amount: 200, bonus: 15, label: '¥200', tag: '送¥15' },
  { id: 4, amount: 500, bonus: 50, label: '¥500', tag: '送¥50', popular: true },
  { id: 5, amount: 1000, bonus: 120, label: '¥1000', tag: '送¥120' },
]

const step = ref<'select' | 'pay' | 'result'>('select')
const selectedPackage = ref(packages[3])
const customAmount = ref('')
const useCustom = ref(false)
const loading = ref(false)
const submitLoading = ref(false)
const syncingReturn = ref(false)
const cfg = ref<RechargeConfig | null>(null)
const curOrder = ref<RechargeOrder | null>(null)
const recent = ref<RechargeOrder[]>([])
const detailOrder = ref<RechargeOrder | null>(null)
const detailVisible = ref(false)
const payerName = ref('')
const payerAccount = ref('')
const resultText = ref('已提交支付信息，等待管理员审核到账。')
const isEpay = computed(() => curOrder.value?.channel === 'epay')
const epayReturnKeys = ['pid', 'trade_no', 'out_trade_no', 'type', 'name', 'money', 'trade_status', 'sign']
const orderTimer = ref<number | null>(null)
const orderPagination = ref({ page: 1, pageSize: 10, total: 0 })
const statusFilter = ref('')
const dateRange = ref<[string, string] | null>(null)

const statusOptions = [
  { label: '全部', value: '' },
  { label: '待支付', value: 'pending' },
  { label: '待审核', value: 'submitted' },
  { label: '入账中', value: 'paid' },
  { label: '已到账', value: 'approved' },
  { label: '入账失败', value: 'failed' },
  { label: '已拒绝', value: 'rejected' },
  { label: '已过期', value: 'expired' },
]

const terminalStatuses = ['approved', 'failed', 'rejected', 'expired']

const finalAmount = computed(() => {
  if (useCustom.value) return parseFloat(customAmount.value) || 0
  return selectedPackage.value.amount
})

const finalBonus = computed(() => {
  if (useCustom.value) {
    const val = parseFloat(customAmount.value) || 0
    if (val >= 1000) return 120
    if (val >= 500) return 50
    if (val >= 200) return 15
    if (val >= 100) return 5
    return 0
  }
  return selectedPackage.value.bonus
})

const selectPackage = (pkg: typeof packages[0]) => {
  selectedPackage.value = pkg
  useCustom.value = false
}

const fetchBase = async () => {
  loading.value = true
  try {
    const [cfgRes, orderRes] = await Promise.all([getRechargeConfig(), fetchOrders(orderPagination.value.page)])
    if (cfgRes.code === 0) cfg.value = cfgRes.data
  } finally {
    loading.value = false
  }
}

const fetchOrders = async (page = 1) => {
  orderPagination.value.page = page
  const [startDate, endDate] = dateRange.value || []
  const res = await getRechargeOrders(page, orderPagination.value.pageSize, statusFilter.value || undefined, startDate, endDate)
  if (res.code === 0) {
    recent.value = res.data.list
    orderPagination.value.page = res.data.page
    orderPagination.value.total = res.data.total
  }
  return res
}

const createOrder = async () => {
  if (finalAmount.value <= 0) {
    ElMessage.warning('请输入充值金额')
    return
  }
  loading.value = true
  try {
    const res = await createRechargeOrder({ amount: finalAmount.value })
    if (res.code === 0) {
      curOrder.value = res.data
      payerName.value = ''
      payerAccount.value = ''
      step.value = 'pay'
      if (res.data.channel === 'epay' && res.data.payUrl) {
        startOrderPolling(res.data.id)
        window.location.href = res.data.payUrl
      }
    }
  } finally {
    loading.value = false
  }
}

const submitOrder = async () => {
  if (!curOrder.value) return
  if (!payerName.value.trim() || !payerAccount.value.trim()) {
    ElMessage.warning('请填写付款姓名和付款账号')
    return
  }
  submitLoading.value = true
  try {
    const res = await submitRechargeOrder(curOrder.value.id, {
      payerName: payerName.value.trim(),
      payerAccount: payerAccount.value.trim(),
    })
    if (res.code === 0) {
      curOrder.value = res.data
      resultText.value = res.data.status === 'submitted' ? '已提交支付信息，等待管理员审核到账。' : '充值订单状态已更新。'
      step.value = 'result'
      await fetchBase()
    }
  } finally {
    submitLoading.value = false
  }
}

const refreshOrder = async () => {
  if (!curOrder.value) return
  const res = await getRechargeOrder(curOrder.value.id)
  if (res.code === 0) {
    curOrder.value = res.data
    if (terminalStatuses.includes(res.data.status)) {
      stopOrderPolling()
      resultText.value = '支付已完成，额度已自动入账。'
      step.value = 'result'
      await fetchBase()
    }
  }
}

const returnPayload = (): EpayReturnReq | null => {
  const params = new URLSearchParams(window.location.search)
  if (params.get('trade_status') !== 'TRADE_SUCCESS') return null
  if (!epayReturnKeys.every((key) => !!params.get(key))) return null

  return {
    pid: params.get('pid') || '',
    trade_no: params.get('trade_no') || '',
    out_trade_no: params.get('out_trade_no') || '',
    type: params.get('type') || '',
    name: params.get('name') || '',
    money: params.get('money') || '',
    trade_status: params.get('trade_status') || '',
    sign: params.get('sign') || '',
    sign_type: params.get('sign_type') || '',
  }
}

const clearReturnQuery = () => {
  window.history.replaceState(null, document.title, window.location.pathname)
}

const syncReturn = async () => {
  const payload = returnPayload()
  if (!payload) return

  syncingReturn.value = true
  try {
    const res = await syncEpayReturn(payload)
    if (res.code === 0) {
      curOrder.value = res.data
      resultText.value = res.data.status === 'approved'
        ? '支付已完成，额度已自动入账。'
        : '支付已确认，正在等待额度入账。'
      step.value = 'result'
      if (!terminalStatuses.includes(res.data.status)) startOrderPolling(res.data.id)
      ElMessage.success('Epay 支付状态已同步')
    }
  } catch {
    ElMessage.warning('Epay 支付已返回，本地入账同步失败，请查看最近订单备注')
  } finally {
    clearReturnQuery()
    syncingReturn.value = false
  }
}

const openPayUrl = () => {
  if (curOrder.value?.payUrl) window.location.href = curOrder.value.payUrl
}

const reset = () => {
  stopOrderPolling()
  step.value = 'select'
  curOrder.value = null
  payerName.value = ''
  payerAccount.value = ''
}

const startOrderPolling = (id: number) => {
  stopOrderPolling()
  orderTimer.value = window.setInterval(async () => {
    const res = await getRechargeOrder(id)
    if (res.code !== 0) return
    curOrder.value = res.data
    if (terminalStatuses.includes(res.data.status)) {
      stopOrderPolling()
      resultText.value = res.data.status === 'approved' ? '支付已完成，额度已自动入账。' : '充值订单状态已更新。'
      step.value = 'result'
      await fetchOrders()
    }
  }, 5000)
}

const stopOrderPolling = () => {
  if (orderTimer.value !== null) {
    window.clearInterval(orderTimer.value)
    orderTimer.value = null
  }
}

const onFilterChange = () => fetchOrders(1)

const copyOrderNo = async (orderNo: string) => {
  await navigator.clipboard.writeText(orderNo)
  ElMessage.success('订单号已复制')
}

const showDetail = async (row: RechargeOrder) => {
  const res = await getRechargeOrder(row.id)
  detailOrder.value = res.code === 0 ? res.data : row
  detailVisible.value = true
}

onMounted(async () => {
  await syncReturn()
  await fetchBase()
})

onBeforeUnmount(() => stopOrderPolling())
</script>

<template>
  <div class="space-y-6" v-loading="loading || syncingReturn">
    <PageHeader title="额度充值" description="选择充值金额并完成支付，到账后自动增加 API 额度。" />

    <div v-if="cfg && !cfg.enabled" class="rounded-lg border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-700">
      当前未开启二维码充值，请联系管理员。
    </div>

    <template v-if="step === 'select'">
      <AppCard>
        <h3 class="mb-4 text-sm font-bold">选择充值套餐</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <button
            v-for="pkg in packages"
            :key="pkg.id"
            class="relative rounded-lg border-2 px-4 py-4 text-center transition"
            :class="!useCustom && selectedPackage.id === pkg.id ? 'border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/5' : 'border-[var(--sr-border)] hover:border-[var(--sr-secondary)]/50'"
            @click="selectPackage(pkg)"
          >
            <span v-if="pkg.popular" class="absolute -top-2 right-2 rounded bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">推荐</span>
            <div class="text-lg font-bold">{{ pkg.label }}</div>
            <div v-if="pkg.bonus" class="mt-1 text-xs font-semibold text-orange-500">{{ pkg.tag }}</div>
          </button>
        </div>

        <div class="mt-4">
          <button class="mb-2 text-sm font-semibold text-[var(--sr-secondary)]" @click="useCustom = true">自定义金额 ›</button>
          <el-input v-if="useCustom" v-model="customAmount" placeholder="输入充值金额" style="width: 220px">
            <template #prefix><span class="font-bold text-[var(--sr-muted)]">¥</span></template>
          </el-input>
        </div>

        <div class="mt-6 rounded-lg bg-[var(--sr-surface-low)] p-4">
          <div class="flex items-center justify-between text-sm">
            <span class="text-[var(--sr-muted)]">充值金额</span>
            <span class="font-bold">¥{{ finalAmount.toFixed(2) }}</span>
          </div>
          <div v-if="finalBonus > 0" class="mt-2 flex items-center justify-between text-sm">
            <span class="text-[var(--sr-muted)]">赠送额度</span>
            <span class="font-bold text-orange-500">+¥{{ finalBonus.toFixed(2) }}</span>
          </div>
          <div class="mt-2 flex items-center justify-between border-t border-[var(--sr-border)] pt-2 text-sm">
            <span class="font-semibold">预计到账</span>
            <span class="text-lg font-bold text-green-600">¥{{ (finalAmount + finalBonus).toFixed(2) }}</span>
          </div>
        </div>

        <div class="mt-6">
          <el-button type="primary" size="large" :disabled="!cfg?.enabled" class="w-full" @click="createOrder">
            创建充值订单 ¥{{ finalAmount.toFixed(2) }}
          </el-button>
        </div>
      </AppCard>
    </template>

    <template v-else-if="step === 'pay' && curOrder">
      <AppCard class="mx-auto max-w-md text-center">
        <h3 class="mb-2 text-lg font-bold">扫码支付</h3>
        <p class="mb-4 text-sm text-[var(--sr-muted)]">
          {{ isEpay ? '请在 Epay 支付页面完成支付，成功后会自动入账。' : '请使用支付宝扫描下方二维码，并备注订单号完成支付' }}
        </p>

        <img v-if="!isEpay && curOrder.qrUrl" :src="curOrder.qrUrl" alt="支付宝收款码" class="mx-auto h-64 w-64 rounded-lg border border-[var(--sr-border)] object-cover" />
        <div v-else-if="!isEpay" class="mx-auto flex h-64 w-64 items-center justify-center rounded-lg border border-dashed border-[var(--sr-border)] text-sm text-[var(--sr-muted)]">
          暂未配置收款二维码
        </div>
        <div v-else class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-5 text-sm text-blue-700">
          <div class="font-semibold">订单已创建</div>
          <div class="mt-2">如果没有自动跳转，请点击下方按钮前往支付页面。</div>
        </div>

        <div class="mt-4 rounded-lg bg-[var(--sr-surface-low)] p-3 text-left text-sm">
          <div class="flex justify-between gap-3"><span class="text-[var(--sr-muted)]">订单号</span><span class="font-mono text-xs">{{ curOrder.orderNo }}</span></div>
          <div v-if="curOrder.outTradeNo" class="mt-2 flex justify-between gap-3"><span class="text-[var(--sr-muted)]">支付单号</span><span class="font-mono text-xs">{{ curOrder.outTradeNo }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">支付金额</span><span class="font-bold text-[var(--sr-secondary)]">¥{{ curOrder.amount }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">预计到账</span><span class="font-bold text-green-600">¥{{ curOrder.creditAmount }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">收款方</span><span>{{ curOrder.displayName || (isEpay ? 'Epay 当面付' : '支付宝收款码') }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">有效期</span><span>{{ curOrder.expireAt }}</span></div>
        </div>

        <div v-if="!isEpay" class="mt-4 space-y-3 text-left">
          <el-input v-model="payerName" placeholder="付款姓名，例如 张三" />
          <el-input v-model="payerAccount" placeholder="付款账号，例如 138****1234 / alipay账号" />
        </div>

        <div class="mt-4 flex justify-center gap-3">
          <el-button @click="reset">取消</el-button>
          <template v-if="isEpay">
            <el-button type="primary" @click="openPayUrl">前往支付</el-button>
            <el-button @click="refreshOrder">刷新状态</el-button>
          </template>
          <el-button v-else type="primary" :loading="submitLoading" @click="submitOrder">我已完成支付</el-button>
        </div>
      </AppCard>
    </template>

    <template v-else-if="step === 'result' && curOrder">
      <AppCard class="mx-auto max-w-md text-center">
        <div class="text-5xl">⌛</div>
        <h3 class="mt-4 text-xl font-bold text-[var(--sr-secondary)]">提交成功</h3>
        <p class="mt-2 text-sm text-[var(--sr-muted)]">{{ resultText }}</p>
        <div class="mt-4 rounded-lg bg-[var(--sr-surface-low)] p-4 text-left text-sm">
          <div class="flex justify-between"><span class="text-[var(--sr-muted)]">订单号</span><span class="font-mono text-xs">{{ curOrder.orderNo }}</span></div>
          <div v-if="curOrder.outTradeNo" class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">支付单号</span><span class="font-mono text-xs">{{ curOrder.outTradeNo }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">状态</span><StatusTag :text="getRechargeStatus(curOrder.status).text" :type="getRechargeStatus(curOrder.status).type" /></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">到账额度</span><span class="font-bold text-green-600">¥{{ curOrder.creditAmount }}</span></div>
        </div>
        <div class="mt-6 flex justify-center gap-3">
          <el-button @click="reset">继续充值</el-button>
          <router-link to="/dashboard"><el-button type="primary">返回首页</el-button></router-link>
        </div>
      </AppCard>
    </template>

    <AppCard>
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-sm font-bold">最近充值订单</h3>
        <div class="flex flex-wrap items-center gap-2">
          <el-select v-model="statusFilter" placeholder="状态" clearable size="small" style="width: 120px" @change="onFilterChange">
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
      </div>
      <el-table :data="recent" style="width: 100%">
        <el-table-column prop="orderNo" label="订单号" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <button class="font-mono text-xs text-[var(--sr-primary)] hover:underline" @click="copyOrderNo(row.orderNo)">{{ row.orderNo }}</button>
          </template>
        </el-table-column>
        <el-table-column prop="channel" label="通道" width="120">
          <template #default="{ row }">{{ row.channel === 'epay' ? 'Epay' : '支付宝' }}</template>
        </el-table-column>
        <el-table-column label="实付金额/初始额度" min-width="150">
          <template #default="{ row }">
            <div class="font-semibold">{{ money(row.paidAmount || row.amount) }}</div>
            <div class="mt-1 text-xs text-[var(--sr-muted)]">初始：{{ row.sub2BalanceBefore ? money(row.sub2BalanceBefore) : '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="到账额度/充值后额度" min-width="160">
          <template #default="{ row }">
            <div class="font-semibold text-green-600">{{ money(row.creditAmount) }}</div>
            <div class="mt-1 text-xs text-[var(--sr-muted)]">充值后：{{ row.sub2BalanceAfter ? money(row.sub2BalanceAfter) : '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <StatusTag :text="getRechargeStatus(row.status).text" :type="getRechargeStatus(row.status).type" />
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="160" />
        <el-table-column label="备注" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">{{ row.remark || row.reviewRemark || row.creditFailMsg || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="90" fixed="right">
          <template #default="{ row }">
            <el-button text type="primary" size="small" @click="showDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="orderPagination.total > orderPagination.pageSize" class="mt-4 flex justify-end">
        <el-pagination
          v-model:current-page="orderPagination.page"
          :page-size="orderPagination.pageSize"
          :total="orderPagination.total"
          layout="prev, pager, next"
          @current-change="fetchOrders"
        />
      </div>
    </AppCard>

    <el-drawer v-model="detailVisible" title="充值订单详情" size="420px">
      <el-descriptions v-if="detailOrder" :column="1" border>
        <el-descriptions-item label="订单号">
          <button class="font-mono text-xs text-[var(--sr-primary)] hover:underline" @click="copyOrderNo(detailOrder.orderNo)">{{ detailOrder.orderNo }}</button>
        </el-descriptions-item>
        <el-descriptions-item label="支付单号">{{ detailOrder.outTradeNo || '-' }}</el-descriptions-item>
        <el-descriptions-item label="第三方流水">{{ detailOrder.providerTradeNo || '-' }}</el-descriptions-item>
        <el-descriptions-item label="通道">{{ detailOrder.channel === 'epay' ? 'Epay' : '支付宝' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <StatusTag :text="getRechargeStatus(detailOrder.status).text" :type="getRechargeStatus(detailOrder.status).type" />
        </el-descriptions-item>
        <el-descriptions-item label="支付状态">{{ detailOrder.tradeStatus || '-' }}</el-descriptions-item>
        <el-descriptions-item label="入账状态">{{ detailOrder.creditStatus || '-' }}</el-descriptions-item>
        <el-descriptions-item label="充值金额">{{ money(detailOrder.amount) }}</el-descriptions-item>
        <el-descriptions-item label="实付金额">{{ detailOrder.paidAmount ? money(detailOrder.paidAmount) : '-' }}</el-descriptions-item>
        <el-descriptions-item label="到账额度">{{ money(detailOrder.creditAmount) }}</el-descriptions-item>
        <el-descriptions-item label="充值前额度">{{ detailOrder.sub2BalanceBefore ? money(detailOrder.sub2BalanceBefore) : '-' }}</el-descriptions-item>
        <el-descriptions-item label="充值后额度">{{ detailOrder.sub2BalanceAfter ? money(detailOrder.sub2BalanceAfter) : '-' }}</el-descriptions-item>
        <el-descriptions-item label="付款人">{{ detailOrder.payerName || '-' }}</el-descriptions-item>
        <el-descriptions-item label="付款账号">{{ detailOrder.payerAccount || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ detailOrder.createdAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="提交时间">{{ detailOrder.submittedAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="支付时间">{{ detailOrder.paidAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审核时间">{{ detailOrder.reviewedAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="入账时间">{{ detailOrder.creditedAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="有效期">{{ detailOrder.expireAt || '-' }}</el-descriptions-item>
        <el-descriptions-item label="备注">{{ detailOrder.remark || detailOrder.reviewRemark || detailOrder.creditFailMsg || '-' }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
  </div>
</template>
