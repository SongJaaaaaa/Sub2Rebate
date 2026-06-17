<script setup lang="ts">
import { defineComponent, h, onMounted, reactive, ref, type PropType, type VNode } from 'vue'
import { Aim, Search, ZoomIn, ZoomOut } from '@element-plus/icons-vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { getRelationshipTree, getAdminUsers } from '@/api/admin'
import { money } from '@/utils/money'
import { userInitial, userName } from '@/utils/userDisplay'
import type { RelationshipNode, AdminUser } from '@/types/admin'

const loading = ref(false)
const keyword = ref('')
const tree = ref<RelationshipNode | null>(null)
const searchResults = ref<AdminUser[]>([])

// 画布状态
const canvas = reactive({
  scale: 1,
  translateX: 0,
  translateY: 0,
  isDragging: false,
  startX: 0,
  startY: 0,
})

// 节点详情弹窗
const nodeDetailVisible = ref(false)
const selectedNode = ref<RelationshipNode | null>(null)

// 展开/折叠状态（用 userId 追踪）
const expandedNodes = ref<Set<number>>(new Set())

// 初始展开前3层，直接呈现主要树状结构
const initExpand = (node: RelationshipNode, depth = 0) => {
  if (depth < 3) {
    expandedNodes.value.add(node.id)
    node.children?.forEach((child) => initExpand(child, depth + 1))
  }
}

const isExpanded = (nodeId: number) => expandedNodes.value.has(nodeId)

const toggleExpand = (nodeId: number) => {
  if (expandedNodes.value.has(nodeId)) {
    expandedNodes.value.delete(nodeId)
  } else {
    expandedNodes.value.add(nodeId)
  }
}

// 搜索
const onSearch = async () => {
  if (!keyword.value.trim()) {
    searchResults.value = []
    return
  }
  const res = await getAdminUsers(1, 5, keyword.value)
  if (res.code === 0) searchResults.value = res.data.list
}

const selectSearchUser = async (user: AdminUser) => {
  searchResults.value = []
  keyword.value = userName(user)
  loading.value = true
  try {
    const res = await getRelationshipTree(user.id)
    if (res.code === 0) {
      tree.value = res.data || null
      expandedNodes.value.clear()
      if (res.data) initExpand(res.data)
      resetView()
    }
  } finally {
    loading.value = false
  }
}

// 画布操作
const zoomIn = () => { canvas.scale = Math.min(canvas.scale + 0.15, 2.0) }
const zoomOut = () => { canvas.scale = Math.max(canvas.scale - 0.15, 0.3) }
const resetView = () => { canvas.scale = 1; canvas.translateX = 0; canvas.translateY = 0 }

const onWheel = (e: WheelEvent) => {
  e.preventDefault()
  if (e.deltaY < 0) zoomIn()
  else zoomOut()
}

const onMouseDown = (e: MouseEvent) => {
  if (e.button !== 0) return
  canvas.isDragging = true
  canvas.startX = e.clientX - canvas.translateX
  canvas.startY = e.clientY - canvas.translateY
}

const onMouseMove = (e: MouseEvent) => {
  if (!canvas.isDragging) return
  canvas.translateX = e.clientX - canvas.startX
  canvas.translateY = e.clientY - canvas.startY
}

const onMouseUp = () => { canvas.isDragging = false }

// 节点点击
const onNodeClick = (node: RelationshipNode) => {
  selectedNode.value = node
  nodeDetailVisible.value = true
}

// 样式
const getLevelBorder = (level: string) => {
  if (level === 'Top Master') return 'border-purple-500 shadow-purple-100'
  if (level === 'VIP Partner') return 'border-blue-400 shadow-blue-50'
  return 'border-gray-200'
}

const getStatusDot = (status: string) => {
  if (status === 'active') return 'bg-green-500'
  if (status === 'warning') return 'bg-orange-500'
  return 'bg-red-500'
}

const getRebateReason = (node: RelationshipNode) => {
  if (node.rebateDisabledReason === 'lie_flat') return '防躺平：连续无活跃'
  return node.rebateDisabledReason || '返利资格已失效'
}

const getChildCount = (node: RelationshipNode): number => {
  if (!node.children) return 0
  return node.children.length + node.children.reduce((sum, c) => sum + getChildCount(c), 0)
}

const TreeNode = defineComponent({
  name: 'TreeNode',
  props: {
    node: { type: Object as PropType<RelationshipNode>, required: true },
    depth: { type: Number, default: 0 },
  },
  setup(props) {
    const renderNode = (node: RelationshipNode, depth: number): VNode => {
      const children = node.children || []
      const hasChildren = children.length > 0
      const expanded = isExpanded(node.id)

      return h('div', { class: 'flex flex-col items-center' }, [
        h(
          'div',
          {
            class: [
              'relative min-w-[180px] cursor-pointer rounded-lg border-2 bg-white px-4 py-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
              getLevelBorder(node.level),
              node.rebateStatus === 'disabled' ? 'opacity-60 grayscale' : '',
            ],
            onClick: () => onNodeClick(node),
          },
          [
            hasChildren
              ? h(
                  'button',
                  {
                    class: 'absolute -bottom-3 left-1/2 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-[var(--sr-border)] bg-white text-sm font-bold shadow-sm',
                    onClick: (e: MouseEvent) => {
                      e.stopPropagation()
                      toggleExpand(node.id)
                    },
                  },
                  expanded ? '-' : '+',
                )
              : null,
            h('div', {
              class: ['mx-auto mb-2 h-3 w-3 rounded-full', getStatusDot(node.status)],
            }),
            h('div', { class: 'truncate text-sm font-bold text-[var(--sr-text)]' }, userName(node)),
            h('div', { class: 'mt-1 text-xs text-[var(--sr-muted)]' }, `ID: ${node.id}`),
            h('div', { class: 'mt-2 text-xs text-[var(--sr-muted)]' }, node.level),
            node.rebateStatus === 'disabled'
              ? h('div', { class: 'mt-1 text-xs font-semibold text-gray-500' }, '返利失效')
              : null,
            h('div', { class: 'mt-1 text-xs font-semibold text-[var(--sr-secondary)]' }, money(node.totalRecharge)),
            h('div', { class: 'mt-1 text-xs text-[var(--sr-muted)]' }, `直邀 ${node.directReferrals} 人`),
          ],
        ),
        hasChildren && expanded
          ? h('div', { class: 'mt-8 flex items-start justify-center gap-6' }, children.map((child) => renderNode(child, depth + 1)))
          : null,
      ])
    }

    return () => renderNode(props.node, props.depth)
  },
})

const fetchTree = async () => {
  loading.value = true
  try {
    const res = await getRelationshipTree()
    if (res.code === 0) {
      tree.value = res.data || null
      expandedNodes.value.clear()
      if (res.data) initExpand(res.data)
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchTree())
</script>

<template>
  <div class="space-y-4">
    <PageHeader title="推荐关系" description="可视化查看用户推荐链路，支持搜索定位、画布拖拽、层级展开收起。" />

    <!-- 工具栏 -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="relative flex items-center gap-3">
        <el-input
          v-model="keyword"
          placeholder="搜索用户名/ID 定位推荐树..."
          :prefix-icon="Search"
          clearable
          style="width: 300px"
          @input="onSearch"
          @clear="fetchTree"
        />
        <!-- 搜索下拉 -->
        <div v-if="searchResults.length" class="absolute left-0 top-full z-50 mt-1 w-[300px] rounded-lg border border-[var(--sr-border)] bg-white p-2 shadow-lg">
          <button
            v-for="u in searchResults"
            :key="u.id"
            class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm hover:bg-[var(--sr-surface-low)]"
            @click="selectSearchUser(u)"
          >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-xs font-bold">{{ userInitial(u) }}</span>
            <div>
              <div class="font-semibold">{{ userName(u) }}</div>
              <div class="text-xs text-[var(--sr-muted)]">ID: {{ u.id }} · 直邀 {{ u.directInviteCount }} 人</div>
            </div>
          </button>
        </div>
      </div>

      <!-- 缩放控制 -->
      <div class="flex items-center gap-2">
        <el-tooltip content="缩小 (或滚轮下)" placement="top">
          <el-button :icon="ZoomOut" circle size="small" @click="zoomOut" />
        </el-tooltip>
        <span class="min-w-[3em] text-center text-xs font-semibold text-[var(--sr-muted)]">{{ Math.round(canvas.scale * 100) }}%</span>
        <el-tooltip content="放大 (或滚轮上)" placement="top">
          <el-button :icon="ZoomIn" circle size="small" @click="zoomIn" />
        </el-tooltip>
        <el-tooltip content="重置视图" placement="top">
          <el-button :icon="Aim" circle size="small" @click="resetView" />
        </el-tooltip>
      </div>
    </div>

    <!-- 图例 -->
    <div class="flex items-center gap-5 rounded-lg border border-[var(--sr-border)] bg-[var(--sr-surface-low)] px-4 py-2 text-xs">
      <span class="font-semibold text-[var(--sr-muted)]">图例:</span>
      <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-green-500" />正常</span>
      <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-orange-500" />预警</span>
      <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-red-500" />封禁</span>
      <span class="flex items-center gap-1 opacity-60 grayscale"><span class="h-2.5 w-2.5 rounded-full bg-gray-400" />返利失效</span>
      <span class="ml-4 text-[var(--sr-muted)]">操作：拖拽移动画布 · 滚轮缩放 · 点击节点查看详情 · 点击 +/- 展开收起</span>
    </div>

    <!-- 画布 -->
    <div
      v-loading="loading"
      class="relative cursor-grab overflow-hidden rounded-xl border border-[var(--sr-border)] bg-[var(--sr-surface)] active:cursor-grabbing"
      style="min-height: 600px"
      @wheel.prevent="onWheel"
      @mousedown="onMouseDown"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @mouseleave="onMouseUp"
    >
      <!-- 空状态 -->
      <div v-if="!loading && !tree" class="flex h-[500px] flex-col items-center justify-center gap-3">
        <div class="text-5xl">🌳</div>
        <p class="text-lg font-semibold text-[var(--sr-muted)]">暂无推荐关系数据</p>
        <p class="text-sm text-[var(--sr-muted)]">用户通过邀请链接注册后，关系树将自动生成</p>
      </div>

      <!-- 树内容 -->
      <div
        v-if="tree"
        class="inline-block min-w-full p-8 transition-transform duration-100"
        :style="{ transform: `translate(${canvas.translateX}px, ${canvas.translateY}px) scale(${canvas.scale})`, transformOrigin: 'top center' }"
      >
        <!-- 递归树组件 -->
        <div class="flex flex-col items-center">
          <TreeNode :node="tree" :depth="0" />
        </div>
      </div>
    </div>

    <!-- 节点详情弹窗 -->
    <el-dialog v-model="nodeDetailVisible" title="用户详情" width="480px" :close-on-click-modal="true">
      <template v-if="selectedNode">
        <div class="flex items-center gap-4 rounded-lg bg-[var(--sr-surface-low)] p-4">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-[var(--sr-secondary)]/10 text-lg font-bold text-[var(--sr-secondary)]">
            {{ userInitial(selectedNode) }}
          </div>
          <div>
            <div class="text-lg font-bold">{{ userName(selectedNode) }}</div>
            <div class="text-sm text-[var(--sr-muted)]">ID: USR-{{ selectedNode.id }} · 等级: {{ selectedNode.level }}</div>
          </div>
          <span class="ml-auto flex h-3 w-3 rounded-full" :class="getStatusDot(selectedNode.status)" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
          <div class="rounded-lg border border-[var(--sr-border)] p-3 text-center">
            <div class="text-xs text-[var(--sr-muted)]">累计充值</div>
            <div class="mt-1 text-lg font-bold">{{ money(selectedNode.totalRecharge) }}</div>
          </div>
          <div class="rounded-lg border border-[var(--sr-border)] p-3 text-center">
            <div class="text-xs text-[var(--sr-muted)]">直接邀请</div>
            <div class="mt-1 text-lg font-bold text-[var(--sr-secondary)]">{{ selectedNode.directReferrals }} 人</div>
          </div>
          <div class="rounded-lg border border-[var(--sr-border)] p-3 text-center">
            <div class="text-xs text-[var(--sr-muted)]">团队总人数</div>
            <div class="mt-1 text-lg font-bold">{{ getChildCount(selectedNode) }} 人</div>
          </div>
          <div class="rounded-lg border border-[var(--sr-border)] p-3 text-center">
            <div class="text-xs text-[var(--sr-muted)]">状态</div>
            <div class="mt-1 text-lg font-bold" :class="selectedNode.status === 'active' ? 'text-green-600' : 'text-red-600'">
              {{ selectedNode.status === 'active' ? '正常' : selectedNode.status === 'warning' ? '预警' : '封禁' }}
            </div>
          </div>
          <div class="rounded-lg border border-[var(--sr-border)] p-3 text-center">
            <div class="text-xs text-[var(--sr-muted)]">返利资格</div>
            <div class="mt-1 text-lg font-bold" :class="selectedNode.rebateStatus === 'disabled' ? 'text-gray-500' : 'text-green-600'">
              {{ selectedNode.rebateStatus === 'disabled' ? '已失效' : '正常' }}
            </div>
          </div>
        </div>

        <div v-if="selectedNode.rebateStatus === 'disabled'" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
          {{ getRebateReason(selectedNode) }}
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <router-link :to="`/admin/user-rebate?userId=${selectedNode.id}`">
            <el-button size="small">设置返利层级</el-button>
          </router-link>
          <router-link :to="`/admin/api-quota?userId=${selectedNode.id}`">
            <el-button size="small">API 额度</el-button>
          </router-link>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
/* 递归树结构的连线用 CSS 实现 */
</style>
