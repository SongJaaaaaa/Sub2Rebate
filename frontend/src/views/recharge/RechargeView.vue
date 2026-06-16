<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { createRechargeOrder, getRechargeConfig, getRechargeOrders, submitRechargeOrder } from '@/api/recharge'
import { money } from '@/utils/money'
import { getRechargeStatus } from '@/utils/status'
import type { RechargeConfig, RechargeOrder } from '@/types/recharge'

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
const cfg = ref<RechargeConfig | null>(null)
const curOrder = ref<RechargeOrder | null>(null)
const recent = ref<RechargeOrder[]>([])
const payerName = ref('')
const payerAccount = ref('')
const resultText = ref('已提交支付信息，等待管理员审核到账。')

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
    const [cfgRes, orderRes] = await Promise.all([
      getRechargeConfig(),
      getRechargeOrders(1, 10),
    ])
    if (cfgRes.code === 0) cfg.value = cfgRes.data
    if (orderRes.code === 0) recent.value = orderRes.data.list
  } finally {
    loading.value = false
  }
}

const createOrder = async () => {
  if (finalAmount.value < 10) {
    ElMessage.warning('最低充值金额为 ¥10')
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

const reset = () => {
  step.value = 'select'
  curOrder.value = null
  payerName.value = ''
  payerAccount.value = ''
}

onMounted(() => fetchBase())
</script>

<template>
  <div class="space-y-6" v-loading="loading">
    <PageHeader title="额度充值" description="使用支付宝收款码充值，提交付款信息后由管理员审核到账。" />

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
          <el-input v-if="useCustom" v-model="customAmount" placeholder="输入充值金额 (最低 ¥10)" style="width: 220px">
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

        <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
          <div class="font-semibold">收款方式：支付宝二维码</div>
          <div class="mt-1">{{ cfg?.displayName || '支付宝收款码' }}</div>
          <div class="mt-1 text-xs">{{ cfg?.note }}</div>
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
        <h3 class="mb-2 text-lg font-bold">支付宝扫码支付</h3>
        <p class="mb-4 text-sm text-[var(--sr-muted)]">请使用支付宝扫描下方二维码，并备注订单号完成支付</p>

        <img v-if="curOrder.qrUrl" :src="curOrder.qrUrl" alt="支付宝收款码" class="mx-auto h-64 w-64 rounded-lg border border-[var(--sr-border)] object-cover" />
        <div v-else class="mx-auto flex h-64 w-64 items-center justify-center rounded-lg border border-dashed border-[var(--sr-border)] text-sm text-[var(--sr-muted)]">
          暂未配置收款二维码
        </div>

        <div class="mt-4 rounded-lg bg-[var(--sr-surface-low)] p-3 text-left text-sm">
          <div class="flex justify-between gap-3"><span class="text-[var(--sr-muted)]">订单号</span><span class="font-mono text-xs">{{ curOrder.orderNo }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">支付金额</span><span class="font-bold text-[var(--sr-secondary)]">¥{{ curOrder.amount }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">预计到账</span><span class="font-bold text-green-600">¥{{ curOrder.creditAmount }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">收款方</span><span>{{ curOrder.displayName || '支付宝收款码' }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-[var(--sr-muted)]">有效期</span><span>{{ curOrder.expireAt }}</span></div>
        </div>

        <div class="mt-4 space-y-3 text-left">
          <el-input v-model="payerName" placeholder="付款姓名，例如 张三" />
          <el-input v-model="payerAccount" placeholder="付款账号，例如 138****1234 / alipay账号" />
        </div>

        <div class="mt-4 flex justify-center gap-3">
          <el-button @click="reset">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitOrder">我已完成支付</el-button>
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
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-bold">最近充值订单</h3>
        <span class="text-xs text-[var(--sr-muted)]">管理员确认到账后会自动增加 API 额度</span>
      </div>
      <el-table :data="recent" style="width: 100%">
        <el-table-column prop="orderNo" label="订单号" min-width="180" show-overflow-tooltip />
        <el-table-column prop="amount" label="实付金额" width="100">
          <template #default="{ row }">{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column prop="creditAmount" label="到账额度" width="100">
          <template #default="{ row }">{{ money(row.creditAmount) }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <StatusTag :text="getRechargeStatus(row.status).text" :type="getRechargeStatus(row.status).type" />
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="创建时间" width="160" />
        <el-table-column prop="reviewRemark" label="备注" min-width="180" show-overflow-tooltip />
      </el-table>
    </AppCard>
  </div>
</template>