<script setup lang="ts">
import { ref, reactive, computed, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { money } from '@/utils/money'
import { useMock, delay } from '@/mocks'

// 充值套餐
const packages = [
  { id: 1, amount: 50, bonus: 0, label: '¥50' },
  { id: 2, amount: 100, bonus: 5, label: '¥100', tag: '送¥5' },
  { id: 3, amount: 200, bonus: 15, label: '¥200', tag: '送¥15' },
  { id: 4, amount: 500, bonus: 50, label: '¥500', tag: '送¥50', popular: true },
  { id: 5, amount: 1000, bonus: 120, label: '¥1000', tag: '送¥120' },
]

const step = ref<'select' | 'pay' | 'result'>('select')
const selectedPackage = ref(packages[3]) // 默认500
const customAmount = ref('')
const useCustom = ref(false)
const payMethod = ref<'alipay' | 'wechat'>('alipay')
const orderId = ref('')
const pollTimer = ref<number | null>(null)
const payLoading = ref(false)
const paySuccess = ref(false)

const finalAmount = computed(() => {
  if (useCustom.value) return parseFloat(customAmount.value) || 0
  return selectedPackage.value.amount
})

const finalBonus = computed(() => {
  if (useCustom.value) return 0
  return selectedPackage.value.bonus
})

const selectPackage = (pkg: typeof packages[0]) => {
  selectedPackage.value = pkg
  useCustom.value = false
}

const enableCustom = () => {
  useCustom.value = true
}

// 创建支付订单
const createOrder = async () => {
  if (finalAmount.value < 10) {
    ElMessage.warning('最低充值金额为 ¥10')
    return
  }
  payLoading.value = true
  try {
    if (useMock) {
      await delay(800)
      orderId.value = `PAY-${Date.now()}`
    }
    step.value = 'pay'
    // 开始轮询支付状态
    startPolling()
  } finally {
    payLoading.value = false
  }
}

// 轮询支付状态
const startPolling = () => {
  let count = 0
  pollTimer.value = window.setInterval(async () => {
    count++
    if (useMock && count >= 5) {
      // mock：5秒后自动成功
      paySuccess.value = true
      step.value = 'result'
      stopPolling()
      return
    }
    // 真实环境调用 checkPayStatus(orderId.value)
  }, 2000)
}

const stopPolling = () => {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

const onPayDone = () => {
  // 用户手动确认已支付
  paySuccess.value = true
  step.value = 'result'
  stopPolling()
}

const reset = () => {
  step.value = 'select'
  paySuccess.value = false
  orderId.value = ''
  stopPolling()
}

onUnmounted(() => stopPolling())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="额度充值" description="扫码支付为你的 Sub2API 账户充值额度，支付成功后自动到账。" />

    <!-- 步骤指示 -->
    <div class="flex items-center justify-center gap-4 text-sm">
      <span :class="step === 'select' ? 'font-bold text-[var(--sr-secondary)]' : 'text-[var(--sr-muted)]'" class="flex items-center gap-1">
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="step === 'select' ? 'bg-[var(--sr-secondary)] text-white' : 'bg-gray-200'">1</span>
        选择金额
      </span>
      <span class="h-px w-8 bg-gray-300" />
      <span :class="step === 'pay' ? 'font-bold text-[var(--sr-secondary)]' : 'text-[var(--sr-muted)]'" class="flex items-center gap-1">
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="step === 'pay' ? 'bg-[var(--sr-secondary)] text-white' : 'bg-gray-200'">2</span>
        扫码支付
      </span>
      <span class="h-px w-8 bg-gray-300" />
      <span :class="step === 'result' ? 'font-bold text-green-600' : 'text-[var(--sr-muted)]'" class="flex items-center gap-1">
        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="step === 'result' ? 'bg-green-500 text-white' : 'bg-gray-200'">3</span>
        充值完成
      </span>
    </div>

    <!-- Step 1: 选择金额 -->
    <template v-if="step === 'select'">
      <AppCard>
        <h3 class="mb-4 text-sm font-bold">选择充值套餐</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          <button
            v-for="pkg in packages"
            :key="pkg.id"
            class="relative rounded-xl border-2 px-4 py-4 text-center transition"
            :class="!useCustom && selectedPackage.id === pkg.id
              ? 'border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/5'
              : 'border-[var(--sr-border)] hover:border-[var(--sr-secondary)]/50'"
            @click="selectPackage(pkg)"
          >
            <span v-if="pkg.popular" class="absolute -top-2 right-2 rounded bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">推荐</span>
            <div class="text-lg font-bold">{{ pkg.label }}</div>
            <div v-if="pkg.bonus" class="mt-1 text-xs font-semibold text-orange-500">{{ pkg.tag }}</div>
          </button>
        </div>

        <!-- 自定义金额 -->
        <div class="mt-4">
          <button
            class="mb-2 text-sm font-semibold text-[var(--sr-secondary)]"
            @click="enableCustom"
          >
            自定义金额 ›
          </button>
          <el-input
            v-if="useCustom"
            v-model="customAmount"
            placeholder="输入充值金额 (最低 ¥10)"
            style="width: 200px"
          >
            <template #prefix><span class="font-bold text-[var(--sr-muted)]">¥</span></template>
          </el-input>
        </div>

        <!-- 充值信息确认 -->
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
            <span class="font-semibold">实际到账</span>
            <span class="text-lg font-bold text-green-600">¥{{ (finalAmount + finalBonus).toFixed(2) }}</span>
          </div>
        </div>

        <!-- 支付方式 -->
        <div class="mt-4">
          <h3 class="mb-2 text-sm font-bold">支付方式</h3>
          <div class="flex gap-3">
            <button
              class="flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold transition"
              :class="payMethod === 'alipay' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-[var(--sr-border)]'"
              @click="payMethod = 'alipay'"
            >
              <span class="text-lg">📱</span> 支付宝
            </button>
            <button
              class="flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold transition"
              :class="payMethod === 'wechat' ? 'border-green-500 bg-green-50 text-green-600' : 'border-[var(--sr-border)]'"
              @click="payMethod = 'wechat'"
            >
              <span class="text-lg">💬</span> 微信支付
            </button>
          </div>
        </div>

        <div class="mt-6">
          <el-button type="primary" size="large" :loading="payLoading" class="w-full" @click="createOrder">
            确认充值 ¥{{ finalAmount.toFixed(2) }}
          </el-button>
          <p class="mt-2 text-center text-xs text-[var(--sr-muted)]">充值即表示同意《服务条款》，额度到账后不可退款</p>
        </div>
      </AppCard>
    </template>

    <!-- Step 2: 扫码支付 -->
    <template v-if="step === 'pay'">
      <AppCard class="mx-auto max-w-md text-center">
        <h3 class="mb-2 text-lg font-bold">{{ payMethod === 'alipay' ? '支付宝' : '微信' }}扫码支付</h3>
        <p class="mb-4 text-sm text-[var(--sr-muted)]">请使用{{ payMethod === 'alipay' ? '支付宝' : '微信' }}扫描下方二维码完成支付</p>

        <!-- 二维码占位 -->
        <div class="mx-auto flex h-48 w-48 items-center justify-center rounded-xl border-2 border-dashed border-[var(--sr-border)] bg-gray-50">
          <div class="text-center">
            <div class="text-4xl">📷</div>
            <p class="mt-2 text-xs text-[var(--sr-muted)]">二维码加载中...</p>
          </div>
        </div>

        <div class="mt-4 rounded-lg bg-[var(--sr-surface-low)] p-3 text-sm">
          <div class="flex justify-between"><span class="text-[var(--sr-muted)]">订单号</span><span class="font-mono text-xs">{{ orderId }}</span></div>
          <div class="mt-1 flex justify-between"><span class="text-[var(--sr-muted)]">支付金额</span><span class="font-bold text-[var(--sr-secondary)]">¥{{ finalAmount.toFixed(2) }}</span></div>
        </div>

        <div class="mt-4 flex items-center justify-center gap-2">
          <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-blue-500" />
          <span class="text-sm text-[var(--sr-muted)]">等待支付中，支付成功后自动跳转...</span>
        </div>

        <div class="mt-4 flex justify-center gap-3">
          <el-button @click="reset">取消支付</el-button>
          <el-button type="primary" @click="onPayDone">我已完成支付</el-button>
        </div>

        <p class="mt-3 text-xs text-[var(--sr-muted)]">⚠ 请勿关闭此页面。如遇支付问题，订单将在 15 分钟后自动取消。</p>
      </AppCard>
    </template>

    <!-- Step 3: 充值结果 -->
    <template v-if="step === 'result'">
      <AppCard class="mx-auto max-w-md text-center">
        <div v-if="paySuccess">
          <div class="text-5xl">🎉</div>
          <h3 class="mt-4 text-xl font-bold text-green-600">充值成功</h3>
          <p class="mt-2 text-sm text-[var(--sr-muted)]">额度已到账，可立即使用</p>
          <div class="mt-4 rounded-lg bg-green-50 p-4">
            <div class="text-sm text-[var(--sr-muted)]">本次到账额度</div>
            <div class="text-2xl font-bold text-green-600">¥{{ (finalAmount + finalBonus).toFixed(2) }}</div>
            <div v-if="finalBonus > 0" class="mt-1 text-xs text-orange-500">含赠送 ¥{{ finalBonus.toFixed(2) }}</div>
          </div>
          <div class="mt-6 flex justify-center gap-3">
            <el-button @click="reset">继续充值</el-button>
            <router-link to="/dashboard">
              <el-button type="primary">返回首页</el-button>
            </router-link>
          </div>
        </div>
      </AppCard>
    </template>
  </div>
</template>
