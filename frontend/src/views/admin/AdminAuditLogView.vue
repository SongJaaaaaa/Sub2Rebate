<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Search, Download } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import { getAuditLogs } from '@/api/admin'
import { pageSizes } from '@/constants/pagination'
import type { AuditLogItem } from '@/types/admin'

const loading = ref(false)
const logs = ref<AuditLogItem[]>([])
const keyword = ref('')
const dateRange = ref('')
const actionTypeFilter = ref('')
const selectedLog = ref<AuditLogItem | null>(null)
const detailVisible = ref(false)
const pagination = ref({ page: 1, pageSize: 10, total: 0 })

const actionTypes = ['所有类别', '配置更改', '手动余额调整', '用户冻结', '提现审批', '角色变更', '返利层级调整']

const fetchLogs = async (page = 1) => {
  loading.value = true
  try {
    const filter = actionTypeFilter.value && actionTypeFilter.value !== '所有类别' ? actionTypeFilter.value : undefined
    const res = await getAuditLogs(page, pagination.value.pageSize, filter)
    if (res.code === 0) {
      logs.value = res.data.list
      pagination.value.page = res.data.page
      pagination.value.total = res.data.total
    }
  } finally {
    loading.value = false
  }
}

const showDetail = (log: AuditLogItem) => {
  selectedLog.value = log
  detailVisible.value = true
}

const onSizeChange = (size: number) => {
  pagination.value.pageSize = size
  fetchLogs(1)
}

onMounted(() => fetchLogs())
</script>

<template>
  <div class="space-y-6">
    <!-- 筛选栏 -->
    <AppCard>
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="mb-1 block text-xs font-semibold text-[var(--sr-muted)]">搜索操作员/目标</label>
          <el-input v-model="keyword" placeholder="输入 ID 或名称..." :prefix-icon="Search" clearable style="width: 200px" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-[var(--sr-muted)]">日期范围</label>
          <el-input v-model="dateRange" placeholder="2023-10-01 至 2023-" style="width: 200px" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold text-[var(--sr-muted)]">日志类别</label>
          <el-select v-model="actionTypeFilter" placeholder="所有类别" style="width: 140px" @change="() => fetchLogs(1)">
            <el-option v-for="t in actionTypes" :key="t" :label="t" :value="t" />
          </el-select>
        </div>
        <el-button type="primary" @click="fetchLogs(1)">执行筛选</el-button>
        <el-button :icon="Download">导出 CSV</el-button>
      </div>
    </AppCard>

    <!-- 列表 + 详情 -->
    <div class="flex gap-6">
      <!-- 左侧列表 -->
      <AppCard class="min-w-0 flex-1">
        <el-table :data="logs" v-loading="loading" style="width: 100%" @row-click="showDetail" class="cursor-pointer">
          <el-table-column label="日期与时间" width="140">
            <template #default="{ row }">
              <div class="text-sm font-semibold">{{ row.datetime.split(' ')[0] }}</div>
              <div class="text-xs text-[var(--sr-muted)]">{{ row.datetime.split(' ')[1] }}</div>
            </template>
          </el-table-column>
          <el-table-column label="操作员" min-width="160">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-xs font-bold text-gray-600">{{ row.operatorAvatar }}</span>
                <span class="text-sm font-semibold">{{ row.operator }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="动作类型" width="120">
            <template #default="{ row }">
              <span class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold">{{ row.actionType }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="80">
            <template #default="{ row }">
              <span :class="row.status === '成功' ? 'text-green-600' : 'text-red-600'" class="flex items-center gap-1 text-sm font-bold">
                <span class="inline-block h-2 w-2 rounded-full" :class="row.status === '成功' ? 'bg-green-500' : 'bg-red-500'" />
                {{ row.status }}
              </span>
            </template>
          </el-table-column>
          <el-table-column width="40">
            <template #default><span class="text-[var(--sr-muted)]">›</span></template>
          </el-table-column>
        </el-table>

        <div class="mt-4 flex items-center justify-between text-sm text-[var(--sr-muted)]">
          <span>显示 1-{{ logs.length }} 条，共 {{ pagination.total }} 条记录</span>
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.pageSize"
            :page-sizes="pageSizes"
            :total="pagination.total"
            layout="total, sizes, prev, pager, next, jumper"
            small
            @current-change="fetchLogs"
            @size-change="onSizeChange"
          />
        </div>
      </AppCard>

      <!-- 右侧详情预览 -->
      <aside v-if="selectedLog" class="hidden w-80 shrink-0 xl:block">
        <AppCard>
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-bold">操作详情</h3>
            <span class="rounded bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--sr-muted)]">SYSTEMLOG</span>
          </div>

          <!-- 操作卡片 -->
          <div class="rounded-lg border border-[var(--sr-border)] p-3">
            <div class="text-sm font-bold">{{ selectedLog.actionType }}</div>
            <div class="text-xs text-[var(--sr-muted)]">Target: {{ selectedLog.target }}</div>
          </div>

          <div class="mt-3 space-y-2 text-xs">
            <div class="flex justify-between"><span class="text-[var(--sr-muted)]">IP 地址</span><span class="font-semibold">{{ selectedLog.ip }}</span></div>
            <div class="flex justify-between"><span class="text-[var(--sr-muted)]">事务 ID</span><span class="font-mono font-semibold">{{ selectedLog.transactionId }}</span></div>
          </div>

          <!-- 数据变更对照 -->
          <div v-if="selectedLog.changes?.length" class="mt-4">
            <h4 class="mb-2 text-xs font-bold">数据变更对照</h4>
            <div v-for="c in selectedLog.changes" :key="c.field" class="flex items-center gap-2">
              <span class="rounded bg-red-50 px-2 py-0.5 text-xs text-red-600">{{ c.oldValue }}</span>
              <span class="text-xs text-[var(--sr-muted)]">→</span>
              <span class="rounded bg-green-50 px-2 py-0.5 text-xs text-green-600">{{ c.newValue }}</span>
            </div>
          </div>

          <!-- 变更备注 -->
          <div class="mt-4">
            <h4 class="mb-1 text-xs font-bold">变更备注</h4>
            <p class="text-xs leading-relaxed text-[var(--sr-muted)]">{{ selectedLog.remark }}</p>
          </div>

          <!-- 事件流 -->
          <div v-if="selectedLog.events?.length" class="mt-4">
            <h4 class="mb-2 text-xs font-bold">相关事件流</h4>
            <div class="space-y-2 border-l-2 border-[var(--sr-border)] pl-3">
              <div v-for="(ev, i) in selectedLog.events" :key="i" class="relative">
                <span class="absolute -left-[17px] top-1 h-2.5 w-2.5 rounded-full" :class="ev.status === 'done' ? 'bg-gray-700' : ev.status === 'pending' ? 'bg-green-500' : 'bg-purple-400'" />
                <div class="text-xs font-semibold">{{ ev.text }}</div>
                <div class="text-[10px] text-[var(--sr-muted)]">{{ ev.time }}</div>
              </div>
            </div>
          </div>
        </AppCard>

        <!-- 风控预警卡片 -->
        <div class="mt-4 rounded-xl bg-gray-900 p-4 text-white">
          <h4 class="mb-3 text-sm font-bold text-gray-300">风控预警 (关联动作)</h4>
          <div class="space-y-3">
            <div class="flex items-start gap-2">
              <span class="mt-0.5 text-yellow-400">⚠</span>
              <div>
                <div class="text-sm font-bold">异地 IP 登录</div>
                <div class="text-xs text-gray-400">系统自动触发 Admin_01 账户临时安全检查</div>
              </div>
            </div>
            <div class="flex items-start gap-2">
              <span class="mt-0.5 text-green-400">✓</span>
              <div>
                <div class="text-sm font-bold">MFA 二步验证通过</div>
                <div class="text-xs text-gray-400">本次配置更改已通过物理令牌授权</div>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- 详情弹窗 (移动端 / 点击查看) -->
    <el-dialog v-model="detailVisible" title="操作详情" width="680px" :close-on-click-modal="true">
      <template v-if="selectedLog">
        <!-- 基本信息 -->
        <div class="mb-6">
          <h3 class="mb-3 flex items-center gap-2 text-sm font-bold"><span class="inline-block h-4 w-1 rounded bg-[var(--sr-secondary)]" />基本信息</h3>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-[10px] text-[var(--sr-muted)]">操作人员</div><div class="mt-1 text-sm font-bold">{{ selectedLog.operator }}</div></div>
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-[10px] text-[var(--sr-muted)]">操作时间</div><div class="mt-1 text-sm font-bold">{{ selectedLog.datetime }}</div></div>
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-[10px] text-[var(--sr-muted)]">IP 地址</div><div class="mt-1 text-sm font-bold">{{ selectedLog.ip }}</div></div>
            <div class="rounded-lg bg-gray-50 p-3"><div class="text-[10px] text-[var(--sr-muted)]">设备信息</div><div class="mt-1 text-sm font-bold">{{ selectedLog.device }}</div></div>
          </div>
        </div>

        <!-- 变更对比 -->
        <div v-if="selectedLog.changes?.length" class="mb-6">
          <h3 class="mb-3 flex items-center gap-2 text-sm font-bold"><span class="inline-block h-4 w-1 rounded bg-[var(--sr-secondary)]" />变更对比</h3>
          <div class="flex justify-end gap-4 text-xs text-[var(--sr-muted)] mb-2">
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-red-100" />修改前</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-green-100" />修改后</span>
          </div>
          <el-table :data="selectedLog.changes" border style="width: 100%">
            <el-table-column label="配置项" min-width="160">
              <template #default="{ row }">
                <div class="text-sm font-semibold">{{ row.fieldLabel }}</div>
                <div class="text-xs text-[var(--sr-muted)]">{{ row.field }}</div>
              </template>
            </el-table-column>
            <el-table-column label="修改前" width="140">
              <template #default="{ row }"><span class="rounded bg-red-50 px-2 py-1 text-sm font-bold text-red-600">{{ row.oldValue }}</span></template>
            </el-table-column>
            <el-table-column width="40"><template #default><span class="text-[var(--sr-muted)]">→</span></template></el-table-column>
            <el-table-column label="修改后" width="140">
              <template #default="{ row }"><span class="rounded bg-green-50 px-2 py-1 text-sm font-bold text-green-600">{{ row.newValue }}</span></template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 审核备注 -->
        <div class="mb-6">
          <h3 class="mb-3 flex items-center gap-2 text-sm font-bold"><span class="inline-block h-4 w-1 rounded bg-[var(--sr-secondary)]" />审核备注</h3>
          <div class="rounded-lg border border-[var(--sr-border)] p-4">
            <h4 class="mb-2 text-sm font-bold text-[var(--sr-secondary)]">变更原因 / 说明</h4>
            <p class="text-sm leading-relaxed text-gray-700">{{ selectedLog.remark }}</p>
            <div v-if="selectedLog.reviewer" class="mt-3 flex items-center gap-2 text-xs text-green-600">
              <span>✓</span><span>{{ selectedLog.reviewStatus }}</span><span class="text-[var(--sr-muted)]">复核人: {{ selectedLog.reviewer }}</span>
            </div>
          </div>
        </div>
      </template>

      <template #footer>
        <div class="flex items-center justify-between">
          <el-button text type="primary" size="small">🔗 查看关联日志</el-button>
          <div class="flex gap-2">
            <el-button @click="detailVisible = false">关闭</el-button>
            <el-button type="primary">导出详情</el-button>
          </div>
        </div>
      </template>
    </el-dialog>
  </div>
</template>
