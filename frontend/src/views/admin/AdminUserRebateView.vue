<script setup lang="ts">
import { onMounted, ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { getUserRebateOverrides, saveUserRebateOverride, getAdminUsers } from '@/api/admin'
import { userAccount, userInitial, userName } from '@/utils/userDisplay'
import type { UserRebateOverride, AdminUser } from '@/types/admin'

const loading = ref(false)
const saving = ref(false)
const overrides = ref<UserRebateOverride[]>([])
const keyword = ref('')
const searchResults = ref<AdminUser[]>([])

// 当前编辑用户
const editingUser = ref<UserRebateOverride | null>(null)
const editForm = reactive<UserRebateOverride>({
  userId: 0,
  username: '',
  nickname: '',
  customRates: [],
  enabled: true,
  updatedAt: '',
})

const fetchOverrides = async () => {
  loading.value = true
  try {
    const res = await getUserRebateOverrides()
    if (res.code === 0) overrides.value = res.data
  } finally {
    loading.value = false
  }
}

const onSearch = async () => {
  if (!keyword.value.trim()) return
  const res = await getAdminUsers(1, 10, keyword.value)
  if (res.code === 0) searchResults.value = res.data.list
}

const selectUser = (user: AdminUser) => {
  const existing = overrides.value.find((o) => o.userId === user.id)
  if (existing) {
    startEdit(existing)
  } else {
    Object.assign(editForm, {
      userId: user.id,
      username: userAccount(user),
      nickname: userName(user),
      customRates: [
        { level: 1, rate: '0.10' },
        { level: 2, rate: '0.05' },
        { level: 3, rate: '0.025' },
      ],
      enabled: true,
      updatedAt: '',
    })
    editingUser.value = editForm
  }
  searchResults.value = []
  keyword.value = ''
}

const startEdit = (override: UserRebateOverride) => {
  Object.assign(editForm, JSON.parse(JSON.stringify(override)))
  editingUser.value = editForm
}

const addLevel = () => {
  const next = editForm.customRates.length + 1
  editForm.customRates.push({ level: next, rate: '0.01' })
}

const removeLevel = (index: number) => {
  editForm.customRates.splice(index, 1)
  editForm.customRates.forEach((r, i) => { r.level = i + 1 })
}

const onSave = async () => {
  saving.value = true
  try {
    const res = await saveUserRebateOverride(editForm)
    if (res.code === 0) {
      ElMessage.success('返利层级已保存')
      editForm.updatedAt = new Date().toISOString().replace('T', ' ').slice(0, 19)
      await fetchOverrides()
    }
  } finally {
    saving.value = false
  }
}

const cancelEdit = () => {
  editingUser.value = null
}

onMounted(() => fetchOverrides())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="用户返利层级设置" description="对特定用户单独设置返利比例，覆盖全局配置。" />

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- 左侧：已配置用户列表 + 搜索 -->
      <div class="space-y-4">
        <!-- 搜索 -->
        <AppCard>
          <h3 class="mb-3 text-sm font-bold">选择用户</h3>
          <div class="flex gap-2">
            <el-input v-model="keyword" placeholder="搜索用户名/昵称" :prefix-icon="Search" size="small" @keyup.enter="onSearch" />
            <el-button size="small" type="primary" @click="onSearch">搜索</el-button>
          </div>
          <!-- 搜索结果 -->
          <div v-if="searchResults.length" class="mt-2 max-h-40 space-y-1 overflow-y-auto rounded border border-[var(--sr-border)] p-2">
            <button
              v-for="u in searchResults"
              :key="u.id"
              class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-[var(--sr-surface-low)]"
              @click="selectUser(u)"
            >
              <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold">{{ userInitial(u) }}</span>
              <span class="font-semibold">{{ userName(u) }}</span>
              <span class="text-xs text-[var(--sr-muted)]">@{{ userAccount(u) }}</span>
            </button>
          </div>
        </AppCard>

        <!-- 已设置的用户列表 -->
        <AppCard>
          <h3 class="mb-3 text-sm font-bold">已配置用户 ({{ overrides.length }})</h3>
          <div v-loading="loading" class="space-y-2">
            <button
              v-for="o in overrides"
              :key="o.userId"
              class="flex w-full items-center justify-between rounded-lg border border-[var(--sr-border)] px-3 py-2.5 text-left transition hover:border-[var(--sr-secondary)] hover:bg-[var(--sr-surface-low)]"
              :class="editingUser?.userId === o.userId ? 'border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/5' : ''"
              @click="startEdit(o)"
            >
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-bold">{{ userInitial(o) }}</span>
                <div>
                  <div class="text-sm font-semibold">{{ userName(o) }}</div>
                  <div class="text-xs text-[var(--sr-muted)]">@{{ userAccount(o) }}</div>
                </div>
              </div>
              <el-tag :type="o.enabled ? 'success' : 'info'" size="small" effect="plain">
                {{ o.enabled ? '生效中' : '已关闭' }}
              </el-tag>
            </button>
            <div v-if="!overrides.length && !loading" class="py-4 text-center text-sm text-[var(--sr-muted)]">暂无自定义配置</div>
          </div>
        </AppCard>
      </div>

      <!-- 右侧：编辑区 -->
      <div class="lg:col-span-2">
        <AppCard v-if="editingUser">
          <div class="mb-6 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold">{{ editForm.nickname }} 的返利层级</h2>
              <p class="text-sm text-[var(--sr-muted)]">ID: {{ editForm.userId }} · @{{ editForm.username }}</p>
            </div>
            <el-switch v-model="editForm.enabled" active-text="启用" inactive-text="关闭" />
          </div>

          <!-- 层级表格 -->
          <div class="mb-4 rounded-lg border border-[var(--sr-border)]">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-[var(--sr-border)] bg-[var(--sr-surface-low)]">
                  <th class="px-4 py-3 text-left font-semibold">层级</th>
                  <th class="px-4 py-3 text-left font-semibold">返利比例</th>
                  <th class="px-4 py-3 text-left font-semibold">实际比例</th>
                  <th class="px-4 py-3 text-right font-semibold">操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(rate, index) in editForm.customRates" :key="index" class="border-b border-[var(--sr-border)] last:border-0">
                  <td class="px-4 py-3">
                    <span class="rounded bg-[var(--sr-secondary)]/10 px-2 py-0.5 text-xs font-bold text-[var(--sr-secondary)]">L{{ rate.level }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <el-input v-model="rate.rate" placeholder="0.10" style="width: 120px" size="small">
                      <template #suffix><span class="text-xs text-[var(--sr-muted)]">比例</span></template>
                    </el-input>
                  </td>
                  <td class="px-4 py-3 font-semibold text-[var(--sr-secondary)]">
                    {{ (parseFloat(rate.rate || '0') * 100).toFixed(1) }}%
                  </td>
                  <td class="px-4 py-3 text-right">
                    <el-button type="danger" text size="small" @click="removeLevel(index)">删除</el-button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mb-6 flex items-center justify-between">
            <el-button text type="primary" size="small" @click="addLevel">+ 添加层级</el-button>
            <span v-if="editForm.updatedAt" class="text-xs text-[var(--sr-muted)]">上次修改: {{ editForm.updatedAt }}</span>
          </div>

          <!-- 全局对比提示 -->
          <div class="mb-6 rounded-lg bg-blue-50 p-4">
            <p class="text-sm text-blue-700">
              <span class="font-bold">提示：</span>此配置将覆盖全局返利设置。当该用户的下级产生消费时，系统将优先使用此处配置的比例进行返利计算。
            </p>
          </div>

          <!-- 操作按钮 -->
          <div class="flex justify-end gap-3">
            <el-button @click="cancelEdit">取消</el-button>
            <el-button type="primary" :loading="saving" @click="onSave">保存配置</el-button>
          </div>
        </AppCard>

        <!-- 未选择用户时 -->
        <AppCard v-else>
          <div class="py-16 text-center">
            <div class="text-4xl">⚙️</div>
            <h3 class="mt-4 text-lg font-bold text-[var(--sr-muted)]">选择用户开始配置</h3>
            <p class="mt-2 text-sm text-[var(--sr-muted)]">从左侧列表选择已配置的用户，或搜索新用户进行设置。</p>
          </div>
        </AppCard>
      </div>
    </div>
  </div>
</template>
