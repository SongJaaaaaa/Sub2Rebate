<script setup lang="ts">
import { onMounted, ref, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, CirclePlus, Remove, WarningFilled } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import StatusTag from '@/components/common/StatusTag.vue'
import { getAdminUsers, banUser, unbanUser, setUserRole, adjustBalance, getBalanceAdjustRecords } from '@/api/admin'
import { money } from '@/utils/money'
import { userAccount, userInitial, userName } from '@/utils/userDisplay'
import type { AdminUser, BalanceAdjustReq, BalanceAdjustRecord } from '@/types/admin'

const users = ref<AdminUser[]>([])
const loading = ref(false)
const keyword = ref('')
const pagination = ref({ page: 1, pageSize: 20, total: 0 })

// 余额调整弹窗
const balanceDialogVisible = ref(false)
const balanceTarget = ref<AdminUser | null>(null)
const balanceSubmitting = ref(false)
const balanceRecords = ref<BalanceAdjustRecord[]>([])
const balanceForm = reactive<BalanceAdjustReq>({
  userId: 0,
  type: 'add',
  amount: 0,
  reason: '手动补偿',
  remark: '返利金额调整',
  adminPassword: '',
})
const reasonOptions = ['手动补偿', '违规扣除', '活动奖励', '系统修正', '其他']

const fetchUsers = async (page = 1) => {
  loading.value = true
  try {
    const res = await getAdminUsers(page, pagination.value.pageSize, keyword.value || undefined)
    if (res.code === 0) {
      users.value = res.data.list
      pagination.value.page = res.data.page
      pagination.value.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

const onSearch = () => fetchUsers(1)

const onBan = async (user: AdminUser) => {
  await ElMessageBox.confirm(`确认封禁用户「${userName(user)}」？封禁后其返利、提现功能将被冻结。`, '封禁用户', { confirmButtonText: '确认封禁', type: 'warning' })
  const res = await banUser(user.id)
  if (res.code === 0) {
    user.status = 'banned'
    ElMessage.success('已封禁')
  }
}

const onUnban = async (user: AdminUser) => {
  await ElMessageBox.confirm(`确认解封用户「${userName(user)}」？`, '解封用户', { confirmButtonText: '确认解封' })
  const res = await unbanUser(user.id)
  if (res.code === 0) {
    user.status = 'active'
    ElMessage.success('已解封')
  }
}

const onSetAdmin = async (user: AdminUser) => {
  await ElMessageBox.confirm(`将「${userName(user)}」设为管理员？管理员拥有后台所有权限，请谨慎操作。`, '设置管理员', { confirmButtonText: '确认' })
  const res = await setUserRole(user.id, 'admin')
  if (res.code === 0) {
    user.role = 'admin'
    ElMessage.success('已设为管理员')
  }
}

// 余额调整
const openBalanceDialog = async (user: AdminUser) => {
  balanceTarget.value = user
  balanceForm.userId = user.id
  balanceForm.type = 'add'
  balanceForm.amount = 0
  balanceForm.reason = '手动补偿'
  balanceForm.remark = '返利金额调整'
  balanceForm.adminPassword = ''
  balanceDialogVisible.value = true
  // 加载历史记录
  const res = await getBalanceAdjustRecords(user.id)
  if (res.code === 0) balanceRecords.value = res.data
}

const onBalanceSubmit = async () => {
  if (!balanceForm.amount || Number(balanceForm.amount) <= 0) {
    ElMessage.warning('请输入调整金额')
    return
  }
  if (!balanceForm.remark.trim()) {
    balanceForm.remark = '返利金额调整'
  }
  if (!balanceForm.adminPassword) {
    ElMessage.warning('请输入当前管理员登录密码')
    return
  }
  await ElMessageBox.confirm(
    `确认${balanceForm.type === 'add' ? '增加' : '减少'} ¥${balanceForm.amount} 余额？此操作不可撤销。`,
    '确认执行调整',
    { confirmButtonText: '确认执行', cancelButtonText: '取消', type: 'warning' }
  )
  balanceSubmitting.value = true
  try {
    const res = await adjustBalance(balanceForm)
    if (res.code === 0) {
      ElMessage.success('余额调整成功')
      balanceDialogVisible.value = false
      await fetchUsers(pagination.value.page)
    }
  } finally {
    balanceSubmitting.value = false
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

onMounted(() => fetchUsers())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="用户管理" description="查看所有用户、修改角色、封禁/解封、调整余额。" />

    <AppCard>
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <el-input v-model="keyword" placeholder="搜索用户名/昵称" clearable :prefix-icon="Search" style="width: 240px" @keyup.enter="onSearch" />
          <el-button type="primary" @click="onSearch">搜索</el-button>
        </div>
        <span class="text-xs text-[var(--sr-muted)]">提示：封禁用户后其所有返利和提现功能将被冻结</span>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && !users.length" class="py-12 text-center">
        <div class="text-lg text-[var(--sr-muted)]">暂无匹配用户</div>
        <p class="mt-2 text-sm text-[var(--sr-muted)]">尝试更换搜索关键词，或清空筛选条件查看全部用户</p>
      </div>

      <el-table v-else :data="users" style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="昵称" min-width="100">
          <template #default="{ row }">{{ userName(row) }}</template>
        </el-table-column>
        <el-table-column label="用户名" min-width="100">
          <template #default="{ row }">{{ userAccount(row) }}</template>
        </el-table-column>
        <el-table-column prop="parentNickname" label="上级" width="100">
          <template #default="{ row }">{{ row.parentNickname || '-' }}</template>
        </el-table-column>
        <el-table-column label="直邀" width="70">
          <template #header>
            <el-tooltip content="该用户直接邀请的下级人数" placement="top">
              <span>直邀 <span class="text-[var(--sr-muted)]">ⓘ</span></span>
            </el-tooltip>
          </template>
          <template #default="{ row }">{{ row.directInviteCount }}</template>
        </el-table-column>
        <el-table-column label="返利" width="100">
          <template #header>
            <el-tooltip content="用户获得的累计返利总额（含已提现）" placement="top">
              <span>返利 <span class="text-[var(--sr-muted)]">ⓘ</span></span>
            </el-tooltip>
          </template>
          <template #default="{ row }">{{ money(row.totalRebateAmount) }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <StatusTag :text="row.status === 'active' ? '正常' : '封禁'" :type="row.status === 'active' ? 'success' : 'danger'" />
          </template>
        </el-table-column>
        <el-table-column prop="role" label="角色" width="80">
          <template #default="{ row }">
            <StatusTag :text="row.role === 'admin' ? '管理员' : '用户'" :type="row.role === 'admin' ? 'warning' : 'primary'" />
          </template>
        </el-table-column>
        <el-table-column prop="createdAt" label="注册时间" width="160" />
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-tooltip v-if="row.status === 'active'" content="封禁后该用户将无法登录和提现" placement="top">
              <el-button type="danger" text size="small" @click="onBan(row)">封禁</el-button>
            </el-tooltip>
            <el-button v-if="row.status === 'banned'" type="success" text size="small" @click="onUnban(row)">解封</el-button>
            <el-tooltip v-if="row.role !== 'admin'" content="管理员拥有后台所有权限" placement="top">
              <el-button type="warning" text size="small" @click="onSetAdmin(row)">设管理员</el-button>
            </el-tooltip>
            <el-button type="primary" text size="small" @click="openBalanceDialog(row)">调整余额</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="pagination.total > pagination.pageSize" class="mt-4 flex items-center justify-between">
        <span class="text-xs text-[var(--sr-muted)]">共 {{ pagination.total }} 条记录</span>
        <el-pagination
          v-model:current-page="pagination.page"
          :page-size="pagination.pageSize"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="fetchUsers"
        />
      </div>
    </AppCard>

    <!-- 余额调整弹窗 -->
    <el-dialog v-model="balanceDialogVisible" title="余额调整" width="720px" destroy-on-close>
      <template v-if="balanceTarget">
        <!-- 用户信息头 -->
        <div class="mb-6 flex items-center justify-between rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] p-4">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-sm font-bold">{{ userInitial(balanceTarget) }}</div>
            <div>
              <div class="font-bold">{{ userName(balanceTarget) }} (ID: {{ balanceTarget.id }})</div>
              <div class="text-xs text-[var(--sr-muted)]">@{{ userAccount(balanceTarget) }}</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs text-[var(--sr-muted)]">当前可用余额</div>
            <div class="text-lg font-bold text-green-600">{{ money(balanceTarget.totalRebateAmount) }}</div>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
          <!-- 左：表单 -->
          <div class="lg:col-span-3 space-y-4">
            <!-- 调整类型 -->
            <div>
              <label class="mb-2 block text-sm font-semibold">调整类型</label>
              <div class="flex gap-3">
                <button
                  class="flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-bold transition"
                  :class="balanceForm.type === 'add' ? 'border-green-500 bg-green-50 text-green-600' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                  @click="balanceForm.type = 'add'"
                >
                  <el-icon><CirclePlus /></el-icon> 增加 (+)
                </button>
                <button
                  class="flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-bold transition"
                  :class="balanceForm.type === 'subtract' ? 'border-red-500 bg-red-50 text-red-600' : 'border-[var(--sr-border)] text-[var(--sr-muted)]'"
                  @click="balanceForm.type = 'subtract'"
                >
                  <el-icon><Remove /></el-icon> 减少 (-)
                </button>
              </div>
            </div>

            <!-- 金额 -->
            <div>
              <label class="mb-1 block text-sm font-semibold">调整金额</label>
              <el-input-number v-model="balanceForm.amount" :min="0" :precision="2" :step="10" controls-position="right" style="width: 100%" />
            </div>

            <!-- 原因 -->
            <div>
              <label class="mb-1 block text-sm font-semibold">原因类型</label>
              <el-select v-model="balanceForm.reason" style="width: 100%">
                <el-option v-for="r in reasonOptions" :key="r" :label="r" :value="r" />
              </el-select>
            </div>

            <!-- 备注 -->
            <div>
              <label class="mb-1 block text-sm font-semibold">详细备注</label>
              <el-input v-model="balanceForm.remark" type="textarea" :rows="3" placeholder="请详细描述调整原因，将同步记录于审计日志..." />
            </div>

            <div>
              <label class="mb-1 block text-sm font-semibold">管理员密码</label>
              <el-input v-model="balanceForm.adminPassword" type="password" show-password autocomplete="current-password" placeholder="请输入当前管理员登录密码" />
            </div>

            <!-- 财务警告 -->
            <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
              <div class="flex items-start gap-2">
                <el-icon class="mt-0.5 text-orange-500"><WarningFilled /></el-icon>
                <p class="text-xs text-orange-800">
                  <span class="font-bold">财务警告：</span>手动余额调整将直接影响平台财务对账。此操作将被永久记录在审计日志中，并抄送至合规部邮箱。
                </p>
              </div>
            </div>
          </div>

          <!-- 右：最近记录 -->
          <div class="lg:col-span-2">
            <h4 class="mb-2 text-xs font-bold text-[var(--sr-muted)]">最近调整记录</h4>
            <div class="max-h-[320px] space-y-2 overflow-y-auto">
              <div v-for="record in balanceRecords" :key="record.id" class="rounded border border-[var(--sr-border)] p-2">
                <div class="flex items-center justify-between">
                  <span class="rounded px-1.5 py-0.5 text-[10px] font-bold" :class="getTagClass(record.tagColor)">{{ record.tag }}</span>
                  <span class="text-xs font-bold" :class="record.type === 'add' ? 'text-green-600' : 'text-red-600'">
                    {{ record.type === 'add' ? '+' : '-' }}¥{{ record.amount }}
                  </span>
                </div>
                <p class="mt-1 text-[10px] text-[var(--sr-muted)]">{{ record.remark }}</p>
                <div class="mt-1 text-[10px] text-[var(--sr-muted)]">{{ record.operator }} · {{ record.createdAt }}</div>
              </div>
              <div v-if="!balanceRecords.length" class="py-4 text-center text-xs text-[var(--sr-muted)]">暂无记录</div>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <el-button @click="balanceDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="balanceSubmitting" @click="onBalanceSubmit">确认执行调整</el-button>
      </template>
    </el-dialog>
  </div>
</template>
