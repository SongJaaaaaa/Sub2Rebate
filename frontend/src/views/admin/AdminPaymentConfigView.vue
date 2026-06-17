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
  channel: 'alipay',
  qrUrl: '',
  displayName: '',
  note: '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
  expireMinutes: 15,
  creditRate: '1',
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
  if (form.enabled && !form.qrUrl.trim()) {
    ElMessage.warning('请先填写支付宝二维码地址，或上传二维码图片')
    return
  }
  saving.value = true
  try {
    const res = await saveAdminPaymentConfig({
      ...form,
      qrUrl: form.qrUrl.trim(),
      displayName: form.displayName.trim(),
      note: form.note.trim(),
      creditRate: String(form.creditRate).trim(),
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
      当前版本使用个人支付宝二维码收款。用户付款后提交付款信息，由管理员在“充值审核”里确认到账。
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
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

          <div>
            <label class="mb-2 block text-sm font-semibold">收款展示名</label>
            <el-input v-model="form.displayName" placeholder="例如：张三-支付宝收款码" />
          </div>

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

          <div class="sm:col-span-2">
            <label class="mb-2 block text-sm font-semibold">付款提示文案</label>
            <el-input v-model="form.note" type="textarea" :rows="4" placeholder="付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。" />
          </div>
        </div>
      </AppCard>

      <AppCard>
        <div class="text-sm font-bold">充值页预览</div>
        <div class="mt-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] p-4">
          <div class="text-sm font-semibold">收款方式：支付宝二维码</div>
          <div class="mt-1 text-sm">{{ form.displayName || '支付宝收款码' }}</div>
          <div class="mt-2 overflow-hidden rounded-lg border border-[var(--sr-border)] bg-white">
            <img v-if="form.qrUrl" :src="form.qrUrl" alt="支付宝二维码预览" class="h-64 w-full object-contain" />
            <div v-else class="flex h-64 items-center justify-center text-sm text-[var(--sr-muted)]">暂无二维码</div>
          </div>
          <div class="mt-3 text-xs leading-6 text-[var(--sr-muted)]">
            <div>开关：{{ form.enabled ? '开启' : '关闭' }}</div>
            <div>订单有效期：{{ form.expireMinutes }} 分钟</div>
            <div>换算比例：1 元 = {{ form.creditRate || '1' }} 额度</div>
            <div class="mt-2 rounded-lg bg-white px-3 py-2">{{ form.note || '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。' }}</div>
          </div>
        </div>
      </AppCard>
    </div>
  </div>
</template>