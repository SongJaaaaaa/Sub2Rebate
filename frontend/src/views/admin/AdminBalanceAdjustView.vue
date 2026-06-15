<script setup lang="ts">
import { onMounted, ref, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CirclePlus, Remove, WarningFilled } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import { getBalanceAdjustRecords, adjustBalance, getAdminUsers } from '@/api/admin'
import { money } from '@/utils/money'
import { userAccount, userName } from '@/utils/userDisplay'
import type { BalanceAdjustRecord, BalanceAdjustReq, AdminUser } from '@/types/admin'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const submitting = ref(false)

// 目标用户
const userId = ref<number>(Number(route.query.userId) || 0)
const targetUser = ref<AdminUser | null>(null)

// 表单
const form = reactive<BalanceAdjustReq>({
  userId: 0,
  type: 'add',
  amount: 0,
  reason: '手动补偿',
  remark: '返利金额调整',
  adminPassword: '',
})

const reasonOptions = ['手动补偿', '违规扣除', '活动奖励', '系统修正', '其他']

// 历史记录
const records = ref<BalanceAdjustRecord[]>([])

const fetchUser = async () => {
  if (!userId.value) return
  const res = await getAdminUsers(1, 1, String(userId.value))
  if (res.code === 0 && res.data.list.length) {
    targetUser.value = res.data.list[0]
    form.userId = targetUser.value.id
  }
}

const fetchRecords = async () => {
  if (!userId.value) return
  loading.value = true
  try {
    const res = await getBalanceAdjustRecords(userId.value)
    if (res.code === 0) records.value = res.data
  } finally {
    loading.value = false
  }
}

const onSubmit = async () => {
  if (!form.amount || Number(form.amount) <= 0) {
    ElMessage.warning('请输入调整金额')
    return
  }
  if (!form.remark.trim()) {
    form.remark = '返利金额调整'
  }
  if (!form.adminPassword) {
    ElMessage.warning('请输入当前管理员登录密码')
    return
  }
  await ElMessageBox.confirm(
    `确认${form.type === 'add' ? '增加' : '减少'} ¥${form.amount} 余额？此操作不可撤销。`,
    '确认执行调整',
    { confirmButtonText: '确认执行调整', cancelButtonText: '取消返回', type: 'warning' }
  )
  submitting.value = true
  try {
    const res = await adjustBalance(form)
    if (res.code === 0) {
      ElMessage.success('余额调整成功')
      form.amount = 0
      form.remark = '返利金额调整'
      form.adminPassword = ''
      await fetchRecords()
    }
  } finally {
    submitting.value = false
  }
}

const getTagClass = (tagColor: string) => {
  const map: Record<string, string> = {
    warning: 'bg-yellow-100 text-yellow-700',
    danger: 'bg-red-100 text-red-700',
    success: 'bg-green-100 text-green-700',
    info: 'bg-gray-100 text-gray-700',
  }
  return map[tagColor] || map.info
}

onMounted(async () => {
  // 如果 URL 没带 userId，默认用 mock 用户
  if (!userId.value) userId.value = 1001
  await fetchUser()
  await fetchRecords()
})
</script>

<template>
  <div class="space-y-6">
    <!-- 面包屑 -->
    <div class="text-sm text-[var(--sr-muted)]">
      <router-link to="/admin/users" class="hover:text-[var(--sr-secondary)]">用户管理</router-link>
      <span class="mx-2">/</span>
      <span>用户详情</span>
      <span class="mx-2">/</span>
      <span class="font-semibold text-[var(--sr-primary)]">余额调整</span>
    </div>

    <!-- 用户信息卡 -->
    <AppCard v-if="targetUser">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-gray-100 text-sm font-bold text-gray-400">img</div>
          <div>
            <div class="text-lg font-bold">{{ userName(targetUser) }} (ID: {{ targetUser.id }})</div>
            <div class="text-xs text-[var(--sr-muted)]">@{{ userAccount(targetUser) }}</div>
            <div class="text-sm text-[var(--sr-muted)]">注册日期: {{ targetUser.createdAt.split(' ')[0].replace(/-/g, '年').replace(/-/, '月') }}</div>
          </div>
        </div>
        <div class="flex gap-8 text-right">
          <div>
            <div class="text-xs text-[var(--sr-muted)]">当前可用余额</div>
            <div class="text-xl font-bold text-green-600">¥ {{ targetUser.totalRebateAmount }}</div>
          </div>
          <div>
            <div class="text-xs text-[var(--sr-muted)]">累计返利</div>
            <div class="text-xl font-bold text-[var(--sr-secondary)]">¥ {{ targetUser.totalPaidAmount }}</div>
          </div>
        </div>
      </div>
    </AppCard>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- 左侧：余额手工调整表单 -->
      <div class="lg:col-span-2">
        <AppCard>
          <h2 class="mb-6 text-lg font-bold">余额手工调整</h2>

          <!-- 调整类型 -->
          <div class="mb-4">
            <label class="mb-2 block text-sm font-semibold">调整类型</label>
            <div class="flex gap-3">
              <button
                class="flex items-center gap-2 rounded-lg border-2 px-6 py-3 text-sm font-bold transition"
                :class="form.type === 'add' ? 'border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/5 text-[var(--sr-secondary)]' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                @click="form.type = 'add'"
              >
                <el-icon><CirclePlus /></el-icon>
                增加余额 (+)
              </button>
              <button
                class="flex items-center gap-2 rounded-lg border-2 px-6 py-3 text-sm font-bold transition"
                :class="form.type === 'subtract' ? 'border-red-500 bg-red-50 text-red-600' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                @click="form.type = 'subtract'"
              >
                <el-icon><Remove /></el-icon>
                减少余额 (-)
              </button>
            </div>
          </div>

          <!-- 调整金额 -->
          <div class="mb-4">
            <label class="mb-2 block text-sm font-semibold">调整金额</label>
            <el-input-number v-model="form.amount" :min="0" :precision="2" :step="10" controls-position="right" style="width: 280px" />
          </div>

          <!-- 调整原因类型 -->
          <div class="mb-4">
            <label class="mb-2 block text-sm font-semibold">调整原因类型</label>
            <el-select v-model="form.reason" placeholder="请选择原因类型" style="width: 100%">
              <el-option v-for="r in reasonOptions" :key="r" :label="r" :value="r" />
            </el-select>
          </div>

          <!-- 详细备注 -->
          <div class="mb-6">
            <label class="mb-2 block text-sm font-semibold">详细备注</label>
            <el-input
              v-model="form.remark"
              type="textarea"
              :rows="4"
              placeholder="请详细描述调整原因，该内容将同步记录于系统审计日志..."
            />
          </div>

          <div class="mb-6">
            <label class="mb-2 block text-sm font-semibold">管理员密码</label>
            <el-input v-model="form.adminPassword" type="password" show-password autocomplete="current-password" placeholder="请输入当前管理员登录密码" />
          </div>

          <!-- 财务警告 -->
          <div class="mb-6 rounded-lg border border-orange-200 bg-orange-50 p-4">
            <div class="flex items-start gap-2">
              <el-icon class="mt-0.5 text-orange-500" :size="18"><WarningFilled /></el-icon>
              <div class="text-sm text-orange-800">
                <span class="font-bold">财务警告</span><br>
                手动余额调整将直接影响平台财务对账逻辑。此操作将被永久记录在
                <span class="font-bold text-[var(--sr-secondary)]">审计日志 (Audit Log)</span> 中，并抄送至合规部邮箱。
              </div>
            </div>
          </div>

          <!-- 操作按钮 -->
          <div class="flex justify-center gap-4">
            <el-button size="large" @click="router.back()">取消返回</el-button>
            <el-button type="primary" size="large" :loading="submitting" @click="onSubmit">确认执行调整</el-button>
          </div>
        </AppCard>
      </div>

      <!-- 右侧：最近调整记录 -->
      <div>
        <AppCard>
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-bold">最近手工调整记录</h3>
            <router-link to="/admin/audit-log" class="text-xs font-semibold text-[var(--sr-secondary)]">查看全部</router-link>
          </div>

          <div v-loading="loading" class="space-y-4">
            <div v-for="record in records" :key="record.id" class="rounded-lg border border-[var(--sr-border)] p-3">
              <div class="mb-1 flex items-center justify-between">
                <span class="rounded px-2 py-0.5 text-[10px] font-bold" :class="getTagClass(record.tagColor)">{{ record.tag }}</span>
                <span class="text-sm font-bold" :class="record.type === 'add' ? 'text-green-600' : 'text-red-600'">
                  {{ record.type === 'add' ? '+' : '-' }} ¥ {{ record.amount }}
                </span>
              </div>
              <p class="my-1 text-xs text-[var(--sr-primary)]">{{ record.remark }}</p>
              <div class="text-[10px] text-[var(--sr-muted)]">
                操作人: {{ record.operator }} &nbsp;&nbsp; {{ record.createdAt }}
              </div>
            </div>

            <div v-if="!records.length && !loading" class="py-6 text-center text-sm text-[var(--sr-muted)]">暂无调整记录</div>
          </div>
        </AppCard>
      </div>
    </div>
  </div>
</template>
