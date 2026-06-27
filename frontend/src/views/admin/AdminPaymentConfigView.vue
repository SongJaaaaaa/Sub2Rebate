<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { getAdminPaymentConfig, saveAdminPaymentConfig } from '@/api/admin'
import type { AdminPaymentConfig } from '@/types/admin'

const loading = ref(false)
const saving = ref(false)

const form = reactive<AdminPaymentConfig>({
  enabled: true,
  mode: 'manual_qr',
  channel: 'alipay',
  qrUrl: '',
  displayName: '',
  note: '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
  expireMinutes: 15,
  creditRate: '1',
  withdrawDailyLimit: 1,
  epay: {
    enabled: false,
    pid: '',
    key: '',
    hasKey: false,
    gatewayUrl: 'https://pay.sjiaa.cc.cd',
    notifyUrl: '',
    returnUrl: '',
    displayName: 'Epay 当面付',
    sitename: 'Sub2Rebate',
    type: 'alipay',
  },
  alipayTransfer: {
    enabled: false,
    autoPayEnabled: false,
    retryEnabled: false,
    retryIntervalMinutes: 5,
    retryBatchSize: 50,
    gatewayUrl: 'https://openapi.alipay.com/gateway.do',
    appId: '',
    privateKey: '',
    hasPrivateKey: false,
    alipayPublicKey: '',
    hasAlipayPublicKey: false,
    singleMaxAmount: '500',
    dailyLimitAmount: '5000',
    identityType: 'ALIPAY_LOGON_ID',
    orderTitle: '返利提现',
  },
})

const fetchConfig = async () => {
  loading.value = true
  try {
    const res = await getAdminPaymentConfig()
    if (res.code === 0) Object.assign(form, res.data)
  } finally {
    loading.value = false
  }
}

const onPickFile = async (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    form.qrUrl = String(reader.result || '')
  }
  reader.readAsDataURL(file)
  input.value = ''
}

const onSave = async () => {
  if (form.enabled && form.mode === 'manual_qr' && !form.qrUrl.trim()) {
    ElMessage.warning('请先填写支付宝二维码地址，或上传二维码图片')
    return
  }
  if (form.enabled && form.mode === 'epay') {
    const epay = form.epay
    if (!epay.pid.trim() || (!epay.key?.trim() && !epay.hasKey) || !epay.gatewayUrl.trim() || !epay.notifyUrl.trim() || !epay.returnUrl.trim()) {
      ElMessage.warning('请补全 Epay 商户和地址配置')
      return
    }
  }
  const transfer = form.alipayTransfer
  if (transfer.enabled) {
    if (!transfer.gatewayUrl.trim() || !transfer.appId.trim() || (!transfer.privateKey?.trim() && !transfer.hasPrivateKey)) {
      ElMessage.warning('请补全支付宝自动打款配置')
      return
    }
  }
  saving.value = true
  try {
    const res = await saveAdminPaymentConfig({
      ...form,
      qrUrl: form.qrUrl.trim(),
      displayName: form.displayName.trim(),
      note: form.note.trim(),
      creditRate: String(form.creditRate).trim(),
      withdrawDailyLimit: Number(form.withdrawDailyLimit || 1),
      epay: {
        ...form.epay,
        pid: form.epay.pid.trim(),
        key: form.epay.key?.trim() || '',
        gatewayUrl: form.epay.gatewayUrl.trim(),
        notifyUrl: form.epay.notifyUrl.trim(),
        returnUrl: form.epay.returnUrl.trim(),
        displayName: form.epay.displayName.trim(),
        sitename: form.epay.sitename.trim(),
        type: form.epay.type.trim() || 'alipay',
      },
      alipayTransfer: {
        ...form.alipayTransfer,
        gatewayUrl: form.alipayTransfer.gatewayUrl.trim(),
        appId: form.alipayTransfer.appId.trim(),
        privateKey: form.alipayTransfer.privateKey?.trim() || '',
        alipayPublicKey: form.alipayTransfer.alipayPublicKey?.trim() || '',
        singleMaxAmount: String(form.alipayTransfer.singleMaxAmount).trim(),
        dailyLimitAmount: String(form.alipayTransfer.dailyLimitAmount).trim(),
        retryIntervalMinutes: Number(form.alipayTransfer.retryIntervalMinutes || 5),
        retryBatchSize: Number(form.alipayTransfer.retryBatchSize || 50),
        identityType: form.alipayTransfer.identityType,
        orderTitle: form.alipayTransfer.orderTitle.trim() || '返利提现',
      },
    })
    if (res.code === 0) {
      Object.assign(form, res.data)
      ElMessage.success('支付配置已保存')
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => fetchConfig())
</script>

<template>
  <div class="space-y-6" v-loading="loading">
    <PageHeader title="支付配置" description="配置支付宝二维码收款信息，用户充值页会直接读取这里的内容。">
      <template #actions>
        <el-button type="primary" :loading="saving" @click="onSave">保存配置</el-button>
      </template>
    </PageHeader>

    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
      当前支持手工支付宝二维码和 Epay 当面付两种通道。Epay 回调成功后会自动入账。
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="space-y-6">
        <AppCard>
          <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2 flex items-center justify-between rounded-lg border border-[var(--sr-border)] px-4 py-3">
              <div>
                <div class="text-sm font-bold">启用二维码充值</div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">关闭后，用户充值页会提示当前未开启二维码充值。</div>
              </div>
              <el-switch v-model="form.enabled" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">支付通道</label>
              <el-radio-group v-model="form.mode">
                <el-radio-button label="manual_qr">支付宝二维码</el-radio-button>
                <el-radio-button label="epay">Epay 当面付</el-radio-button>
              </el-radio-group>
            </div>

            <div v-if="form.mode === 'manual_qr'" class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">支付宝二维码地址</label>
              <el-input v-model="form.qrUrl" type="textarea" :rows="4" placeholder="可填写图片 URL，或上传本地二维码后自动转成 data URL" />
              <div class="mt-3 flex flex-wrap gap-3">
                <label class="inline-flex cursor-pointer items-center">
                  <input class="hidden" type="file" accept="image/*" @change="onPickFile" />
                  <span class="rounded-lg border border-[var(--sr-border)] px-4 py-2 text-sm font-semibold text-[var(--sr-secondary)]">上传二维码图片</span>
                </label>
                <span class="text-xs text-[var(--sr-muted)]">没有图床也没关系，直接上传图片即可。</span>
              </div>
            </div>

            <template v-if="form.mode === 'manual_qr'">
            <div>
              <label class="mb-2 block text-sm font-semibold">收款展示名</label>
              <el-input v-model="form.displayName" placeholder="例如：张三-支付宝收款码" />
            </div>
            </template>

            <template v-else>
            <div class="sm:col-span-2 flex items-center justify-between rounded-lg border border-[var(--sr-border)] px-4 py-3">
              <div>
                <div class="text-sm font-bold">启用 Epay</div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">创建订单后跳转 Epay 支付页，公网回调成功后自动入账。</div>
              </div>
              <el-switch v-model="form.epay.enabled" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">商户 PID</label>
              <el-input v-model="form.epay.pid" placeholder="Epay 商户 PID" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">商户 Key</label>
              <el-input v-model="form.epay.key" type="password" show-password :placeholder="form.epay.hasKey ? '已保存，留空表示不修改' : 'Epay 商户 Key'" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">Epay 地址</label>
              <el-input v-model="form.epay.gatewayUrl" placeholder="https://pay.sjiaa.cc.cd" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">展示名</label>
              <el-input v-model="form.epay.displayName" placeholder="Epay 当面付" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">回调地址 notify_url</label>
              <el-input v-model="form.epay.notifyUrl" placeholder="https://你的域名/api/v1/recharge/epay/notify" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">返回地址 return_url</label>
              <el-input v-model="form.epay.returnUrl" placeholder="https://你的前端域名/recharge" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">商户站点名</label>
              <el-input v-model="form.epay.sitename" placeholder="Sub2Rebate" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">支付类型</label>
              <el-input v-model="form.epay.type" placeholder="alipay" />
            </div>
            </template>

            <div>
              <label class="mb-2 block text-sm font-semibold">充值订单有效期（分钟）</label>
              <el-input-number v-model="form.expireMinutes" :min="1" :max="1440" controls-position="right" style="width: 100%" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">人民币额度换算比例</label>
              <el-input v-model="form.creditRate" placeholder="1">
                <template #append>1 元 = ? 额度</template>
              </el-input>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">每日支付宝提现次数</label>
              <el-input-number v-model="form.withdrawDailyLimit" :min="1" :max="100" controls-position="right" style="width: 100%" />
            </div>

            <div v-if="form.mode === 'manual_qr'" class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">付款提示文案</label>
              <el-input v-model="form.note" type="textarea" :rows="4" placeholder="付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。" />
            </div>
          </div>
        </AppCard>

        <AppCard>
          <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2 flex items-center justify-between rounded-lg border border-[var(--sr-border)] px-4 py-3">
              <div>
                <div class="text-sm font-bold">启用支付宝自动打款</div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">开启后，提现审核页点击确认打款会调用支付宝单笔转账。</div>
              </div>
              <el-switch v-model="form.alipayTransfer.enabled" />
            </div>

            <div class="flex items-center justify-between rounded-lg border border-[var(--sr-border)] px-4 py-3">
              <div>
                <div class="text-sm font-bold">审批后立即打款</div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">审批通过后自动执行一次打款，失败后保留待打款。</div>
              </div>
              <el-switch v-model="form.alipayTransfer.autoPayEnabled" :disabled="!form.alipayTransfer.enabled" />
            </div>

            <div class="flex items-center justify-between rounded-lg border border-[var(--sr-border)] px-4 py-3">
              <div>
                <div class="text-sm font-bold">失败自动重试</div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">定时重试已审核但打款失败的提现单。</div>
              </div>
              <el-switch v-model="form.alipayTransfer.retryEnabled" :disabled="!form.alipayTransfer.enabled" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">支付宝网关</label>
              <el-input v-model="form.alipayTransfer.gatewayUrl" placeholder="https://openapi.alipay.com/gateway.do" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">应用 ID</label>
              <el-input v-model="form.alipayTransfer.appId" placeholder="支付宝开放平台 app_id" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">收款标识类型</label>
              <el-select v-model="form.alipayTransfer.identityType" style="width: 100%">
                <el-option label="支付宝登录账号" value="ALIPAY_LOGON_ID" />
                <el-option label="支付宝用户 ID" value="ALIPAY_USER_ID" />
              </el-select>
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">应用私钥</label>
              <el-input v-model="form.alipayTransfer.privateKey" type="textarea" :rows="4" :placeholder="form.alipayTransfer.hasPrivateKey ? '已保存，留空表示不修改' : 'RSA2 应用私钥'" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">支付宝公钥</label>
              <el-input v-model="form.alipayTransfer.alipayPublicKey" type="textarea" :rows="3" :placeholder="form.alipayTransfer.hasAlipayPublicKey ? '已保存，留空表示不修改' : '支付宝开放平台公钥，可后续用于查询验签'" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">单笔限额</label>
              <el-input v-model="form.alipayTransfer.singleMaxAmount" placeholder="500">
                <template #append>元</template>
              </el-input>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">每日限额</label>
              <el-input v-model="form.alipayTransfer.dailyLimitAmount" placeholder="5000">
                <template #append>元</template>
              </el-input>
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">重试间隔</label>
              <el-input-number v-model="form.alipayTransfer.retryIntervalMinutes" :min="1" :max="1440" controls-position="right" style="width: 100%" />
            </div>

            <div>
              <label class="mb-2 block text-sm font-semibold">每批重试数</label>
              <el-input-number v-model="form.alipayTransfer.retryBatchSize" :min="1" :max="500" controls-position="right" style="width: 100%" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-2 block text-sm font-semibold">转账标题</label>
              <el-input v-model="form.alipayTransfer.orderTitle" placeholder="返利提现" />
            </div>
          </div>
        </AppCard>
      </div>

      <AppCard>
        <div class="text-sm font-bold">充值页预览</div>
        <div class="mt-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] p-4">
          <div class="text-sm font-semibold">收款方式：{{ form.mode === 'epay' ? 'Epay 当面付' : '支付宝二维码' }}</div>
          <div class="mt-1 text-sm">{{ form.mode === 'epay' ? (form.epay.displayName || 'Epay 当面付') : (form.displayName || '支付宝收款码') }}</div>
          <div v-if="form.mode === 'manual_qr'" class="mt-2 overflow-hidden rounded-lg border border-[var(--sr-border)] bg-white">
            <img v-if="form.qrUrl" :src="form.qrUrl" alt="支付宝二维码预览" class="h-64 w-full object-contain" />
            <div v-else class="flex h-64 items-center justify-center text-sm text-[var(--sr-muted)]">暂无二维码</div>
          </div>
          <div class="mt-3 text-xs leading-6 text-[var(--sr-muted)]">
            <div>开关：{{ form.enabled ? '开启' : '关闭' }}</div>
            <div>通道：{{ form.mode === 'epay' ? 'Epay 当面付' : '支付宝二维码' }}</div>
            <div>订单有效期：{{ form.expireMinutes }} 分钟</div>
            <div>换算比例：1 元 = {{ form.creditRate || '1' }} 额度</div>
            <div>自动打款：{{ form.alipayTransfer.enabled ? '开启' : '关闭' }}</div>
            <div>审批即打款：{{ form.alipayTransfer.autoPayEnabled ? '开启' : '关闭' }}</div>
            <div>失败重试：{{ form.alipayTransfer.retryEnabled ? '开启' : '关闭' }}</div>
            <div>单笔限额：{{ form.alipayTransfer.singleMaxAmount || '0' }} 元</div>
            <div>每日提现次数：{{ form.withdrawDailyLimit || 1 }} 次</div>
            <template v-if="form.mode === 'epay'">
              <div>PID：{{ form.epay.pid || '-' }}</div>
              <div>Epay：{{ form.epay.gatewayUrl || '-' }}</div>
              <div>支付类型：{{ form.epay.type || 'alipay' }}</div>
              <div>商户 Key：{{ form.epay.hasKey || form.epay.key ? '已配置' : '未配置' }}</div>
            </template>
            <div v-else class="mt-2 rounded-lg bg-white px-3 py-2">{{ form.note || '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。' }}</div>
          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>
