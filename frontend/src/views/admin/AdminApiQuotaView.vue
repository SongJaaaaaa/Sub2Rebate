<script setup lang="ts">
import { onMounted, ref, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, CirclePlus, Remove } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { adjustApiQuota, getAdminUsers, getApiQuota, getApiQuotaRecords } from '@/api/admin'
import { useMock, delay } from '@/mocks'
import { money } from '@/utils/money'
import { userAccount, userInitial, userName } from '@/utils/userDisplay'
import type { AdminUser, ApiQuotaInfo, ApiQuotaReason, ApiQuotaRecord } from '@/types/admin'

const loading = ref(false)
const quotaLoading = ref(false)
const submitting = ref(false)
const keyword = ref('')
const users = ref<AdminUser[]>([])
const selectedUser = ref<ApiQuotaInfo | null>(null)
const quotaRecords = ref<ApiQuotaRecord[]>([])

const form = reactive({
  type: 'add' as 'add' | 'subtract',
  amount: 0,
  reason: '充值' as ApiQuotaReason,
  remark: '余额充值',
  rebateEnabled: false,
})
const reasonOptions: ApiQuotaReason[] = ['充值', '手动补偿', '违规扣除', '系统修正', '其他']

const fetchUsers = async () => {
  loading.value = true
  try {
    const res = await getAdminUsers(1, 50, keyword.value || undefined)
    if (res.code === 0) users.value = res.data.list
  } finally {
    loading.value = false
  }
}

const fetchQuota = async (user: AdminUser) => {
  quotaLoading.value = true
  try {
    const [quotaRes, recordsRes] = await Promise.all([
      getApiQuota(user.id),
      getApiQuotaRecords(user.id),
    ])
    if (quotaRes.code === 0) selectedUser.value = quotaRes.data
    if (recordsRes.code === 0) quotaRecords.value = recordsRes.data
  } finally {
    quotaLoading.value = false
  }
}

const selectUser = async (user: AdminUser) => {
  selectedUser.value = {
    userId: user.id,
    nickname: userName(user),
    username: userAccount(user),
    apiBalance: '0.00',
    totalUsed: '0.00',
    totalCharged: '0.00',
    sub2ApiAffCode: user.sub2ApiAffCode || '',
    sub2ApiInviterId: user.sub2ApiInviterId || null,
    updatedAt: '',
  }
  quotaRecords.value = []
  await fetchQuota(user)
}

const onSubmit = async () => {
  if (!selectedUser.value) return
  if (!form.amount || Number(form.amount) <= 0) {
    ElMessage.warning('请输入调整金额')
    return
  }
  if (!form.reason.trim()) {
    form.reason = '充值'
  }
  if (!form.remark.trim()) {
    form.remark = '余额充值'
  }
  const action = form.type === 'add' ? '增加' : '减少'
  const rebateTip = form.type === 'add'
    ? (form.rebateEnabled ? '本次会计入充值返利。' : '本次仅增加 API 额度，不计入充值返利。')
    : '扣减不参与返利。'
  await ElMessageBox.confirm(
    `确认为「${userName(selectedUser.value)}」${action} Sub2API 额度 ¥${form.amount}？${rebateTip}`,
    '确认额度调整',
    { confirmButtonText: '确认', type: 'warning' }
  )

  submitting.value = true
  try {
    if (useMock) await delay(500)
    const res = await adjustApiQuota(selectedUser.value.userId, form)
    if (res.code !== 0) return
    ElMessage.success('额度调整成功')
    await fetchQuota({
      id: selectedUser.value.userId,
      username: selectedUser.value.username,
      nickname: selectedUser.value.nickname,
    } as AdminUser)

    form.amount = 0
    form.reason = '充值'
    form.remark = '余额充值'
    form.rebateEnabled = false
  } finally {
    submitting.value = false
  }
}

onMounted(() => fetchUsers())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Sub2API 额度管理" description="管理用户在 Sub2API 中的可用额度，手动充值或扣减。" />

    <!-- 说明提示 -->
    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
      <p class="text-sm text-blue-700">
        <span class="font-bold">说明：</span>API 额度是用户调用 Sub2API 接口的消费凭证。充值后用户可在额度范围内使用 API 服务，超出额度将被限流。所有变动均记录在审计日志中。
      </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- 左侧：用户选择 -->
      <div class="space-y-4">
        <AppCard>
          <h3 class="mb-3 text-sm font-bold">选择用户</h3>
          <el-input v-model="keyword" placeholder="搜索用户..." :prefix-icon="Search" clearable @keyup.enter="fetchUsers" @clear="fetchUsers" />
          <div class="mt-3 max-h-[400px] space-y-1 overflow-y-auto">
            <button
              v-for="u in users"
              :key="u.id"
              class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left transition hover:bg-[var(--sr-surface-low)]"
              :class="selectedUser?.userId === u.id ? 'bg-[var(--sr-secondary)]/5 border border-[var(--sr-secondary)]' : 'border border-transparent'"
              @click="selectUser(u)"
            >
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-bold">{{ userInitial(u) }}</span>
                <div>
                  <div class="text-sm font-semibold">{{ userName(u) }}</div>
                  <div class="text-xs text-[var(--sr-muted)]">ID: {{ u.id }}</div>
                </div>
              </div>
              <span class="text-xs font-semibold text-green-600">{{ selectedUser?.userId === u.id ? money(selectedUser.apiBalance) : '选择查看' }}</span>
            </button>
          </div>
        </AppCard>
      </div>

      <!-- 右侧：额度详情和操作 -->
      <div class="lg:col-span-2 space-y-4">
        <template v-if="selectedUser">
          <div v-if="quotaLoading" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-700">
            正在读取 Sub2API 额度...
          </div>
          <!-- 额度概览 -->
          <AppCard>
            <div class="flex flex-wrap items-center justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-[var(--sr-secondary)]/10 text-lg font-bold text-[var(--sr-secondary)]">
                  {{ userInitial(selectedUser) }}
                </div>
                <div>
                  <div class="text-lg font-bold">{{ userName(selectedUser) }}</div>
                  <div class="text-sm text-[var(--sr-muted)]">@{{ userAccount(selectedUser) }} · ID: {{ selectedUser.userId }}</div>
                </div>
              </div>
              <div class="flex gap-6 text-right">
                <div>
                  <div class="text-xs text-[var(--sr-muted)]">API 可用余额</div>
                  <div class="text-xl font-bold text-green-600">{{ money(selectedUser.apiBalance) }}</div>
                </div>
                <div>
                  <div class="text-xs text-[var(--sr-muted)]">累计已用</div>
                  <div class="text-xl font-bold text-orange-500">{{ money(selectedUser.totalUsed) }}</div>
                </div>
                <div>
                  <div class="text-xs text-[var(--sr-muted)]">累计充入</div>
                  <div class="text-xl font-bold">{{ money(selectedUser.totalCharged) }}</div>
                </div>
              </div>
            </div>
          </AppCard>

          <!-- 额度调整表单 -->
          <AppCard>
            <h2 class="mb-4 text-lg font-bold">额度调整</h2>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-2 block text-sm font-semibold">调整类型</label>
                <div class="flex gap-3">
                  <button
                    class="flex items-center gap-2 rounded-lg border-2 px-4 py-2.5 text-sm font-bold transition"
                    :class="form.type === 'add' ? 'border-green-500 bg-green-50 text-green-600' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                    @click="form.type = 'add'"
                  >
                    <el-icon><CirclePlus /></el-icon> 充值 (+)
                  </button>
                  <button
                    class="flex items-center gap-2 rounded-lg border-2 px-4 py-2.5 text-sm font-bold transition"
                    :class="form.type === 'subtract' ? 'border-red-500 bg-red-50 text-red-600' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                    @click="form.type = 'subtract'"
                  >
                    <el-icon><Remove /></el-icon> 扣减 (-)
                  </button>
                </div>
              </div>
              <div>
                <label class="mb-2 block text-sm font-semibold">金额</label>
                <el-input-number v-model="form.amount" :min="0" :precision="2" :step="10" controls-position="right" style="width: 100%" />
              </div>
            </div>
            <div class="mt-4">
              <label class="mb-2 block text-sm font-semibold">原因类型</label>
              <el-select v-model="form.reason" style="width: 100%">
                <el-option v-for="r in reasonOptions" :key="r" :label="r" :value="r" />
              </el-select>
            </div>
            <div v-if="form.type === 'add'" class="mt-4 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] px-4 py-3">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <div class="text-sm font-semibold">参与返利</div>
                  <div class="mt-1 text-xs text-[var(--sr-muted)]">关闭时仅增加 API 额度，不计入充值返利。</div>
                </div>
                <el-switch v-model="form.rebateEnabled" />
              </div>
            </div>
            <div class="mt-4">
              <label class="mb-2 block text-sm font-semibold">备注</label>
              <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="该内容将记录于审计日志..." />
            </div>
            <div class="mt-4 flex justify-end gap-3">
              <el-button @click="form.amount = 0; form.reason = '充值'; form.remark = '余额充值'; form.rebateEnabled = false">重置</el-button>
              <el-button type="primary" :loading="submitting" @click="onSubmit">确认调整</el-button>
            </div>
            <p class="mt-3 text-xs text-[var(--sr-muted)]">* 额度调整立即生效，操作记录将同步至审计日志。扣减额度不会退还已消费部分。</p>
          </AppCard>

          <!-- 操作记录 -->
          <AppCard>
            <h3 class="mb-4 text-sm font-bold">额度变动记录</h3>
            <div class="space-y-3">
              <div v-for="r in quotaRecords" :key="r.recordId || r.id" class="flex items-center justify-between gap-4 rounded-lg border border-[var(--sr-border)] px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                    :class="r.type === 'add' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                  >
                    {{ r.type === 'add' ? '+' : '-' }}
                  </span>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                      <span>{{ r.reason }}</span>
                      <el-tag size="small" :type="r.source === 'sub2api' ? 'info' : 'success'">{{ r.sourceLabel || '返利系统' }}</el-tag>
                      <el-tag size="small" :type="r.rebateEnabled ? 'warning' : 'info'">{{ r.rebateEnabled ? '参与返利' : '不参与返利' }}</el-tag>
                    </div>
                    <div class="text-xs text-[var(--sr-muted)]">{{ r.operator }} · {{ r.createdAt }}</div>
                    <div v-if="r.remark" class="mt-1 truncate text-xs text-[var(--sr-muted)]">备注：{{ r.remark }}</div>
                  </div>
                </div>
                <span class="shrink-0 text-sm font-bold" :class="r.type === 'add' ? 'text-green-600' : 'text-red-600'">
                  {{ r.type === 'add' ? '+' : '-' }} {{ money(r.amount) }}
                </span>
              </div>
            </div>
          </AppCard>
        </template>

        <AppCard v-else class="flex min-h-[300px] flex-col items-center justify-center gap-3">
          <div class="text-4xl">💳</div>
          <p class="font-semibold text-[var(--sr-muted)]">请从左侧选择一个用户</p>
          <p class="text-xs text-[var(--sr-muted)]">选择后可查看其 API 额度详情并执行充值或扣减操作</p>
        </AppCard>
      </div>
    </div>
  </div>
</template>
