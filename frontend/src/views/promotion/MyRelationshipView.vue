<script setup lang="ts">
import { onMounted, ref, reactive } from 'vue'
import { ZoomIn, ZoomOut, Aim } from '@element-plus/icons-vue'
import AppCard from '@/components/common/AppCard.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { money } from '@/utils/money'
import { getInviteTree } from '@/api/promotion'
import type { InviteTreeNode } from '@/types/rebate'

interface MyTreeNode {
  id: number
  nickname: string
  level: number
  totalRecharge: string
  directCount: number
  status: 'active' | 'inactive'
  joinDate: string
  children?: MyTreeNode[]
}

const loading = ref(false)
const tree = ref<MyTreeNode | null>(null)
const expandedNodes = ref<Set<number>>(new Set())

// 画布
const canvas = reactive({ scale: 1, translateX: 0, translateY: 0, isDragging: false, startX: 0, startY: 0 })

const fetchTree = async () => {
  loading.value = true
  try {
    const res = await getInviteTree(5)
    if (res.code === 0) {
      tree.value = mapTree(res.data.root)
      expandedNodes.value.clear()
      if (tree.value) initExpand(tree.value)
    }
  } finally {
    loading.value = false
  }
}

const mapTree = (node: InviteTreeNode): MyTreeNode => ({
  id: node.id,
  nickname: node.nickname || node.username || `用户${node.id}`,
  level: node.level,
  totalRecharge: '0.00',
  directCount: node.children?.length || 0,
  status: 'active',
  joinDate: '',
  children: node.children?.map(mapTree) || [],
})

const initExpand = (node: MyTreeNode, depth = 0) => {
  if (depth < 3) {
    expandedNodes.value.add(node.id)
    node.children?.forEach((c) => initExpand(c, depth + 1))
  }
}

const isExpanded = (id: number) => expandedNodes.value.has(id)
const toggleExpand = (id: number) => {
  if (expandedNodes.value.has(id)) expandedNodes.value.delete(id)
  else expandedNodes.value.add(id)
}

// 画布操作
const zoomIn = () => { canvas.scale = Math.min(canvas.scale + 0.15, 2.0) }
const zoomOut = () => { canvas.scale = Math.max(canvas.scale - 0.15, 0.3) }
const resetView = () => { canvas.scale = 1; canvas.translateX = 0; canvas.translateY = 0 }
const onWheel = (e: WheelEvent) => { e.preventDefault(); e.deltaY < 0 ? zoomIn() : zoomOut() }
const onMouseDown = (e: MouseEvent) => { if (e.button !== 0) return; canvas.isDragging = true; canvas.startX = e.clientX - canvas.translateX; canvas.startY = e.clientY - canvas.translateY }
const onMouseMove = (e: MouseEvent) => { if (!canvas.isDragging) return; canvas.translateX = e.clientX - canvas.startX; canvas.translateY = e.clientY - canvas.startY }
const onMouseUp = () => { canvas.isDragging = false }

// 统计
const countAll = (node: MyTreeNode): number => {
  if (!node.children) return 0
  return node.children.length + node.children.reduce((s, c) => s + countAll(c), 0)
}

onMounted(() => fetchTree())
</script>

<template>
  <div class="space-y-4">
    <PageHeader title="我的推荐关系" description="查看你邀请的下级及其团队关系。上级信息不可查看。" />

    <!-- 统计概览 -->
    <div v-if="tree" class="grid grid-cols-3 gap-4">
      <AppCard class="text-center">
        <div class="text-2xl font-bold text-[var(--sr-secondary)]">{{ tree.directCount }}</div>
        <div class="text-xs text-[var(--sr-muted)]">直邀人数</div>
      </AppCard>
      <AppCard class="text-center">
        <div class="text-2xl font-bold text-blue-600">{{ countAll(tree) }}</div>
        <div class="text-xs text-[var(--sr-muted)]">团队总人数</div>
      </AppCard>
      <AppCard class="text-center">
        <div class="text-2xl font-bold text-green-600">{{ tree.children?.filter(c => c.status === 'active').length || 0 }}</div>
        <div class="text-xs text-[var(--sr-muted)]">活跃下级</div>
      </AppCard>
    </div>

    <!-- 画布工具 -->
    <div class="flex items-center justify-between">
      <p class="text-xs text-[var(--sr-muted)]">💡 拖拽画布平移视图，滚轮缩放，点击节点展开/收起下级</p>
      <div class="flex items-center gap-2">
        <el-button :icon="ZoomOut" circle size="small" @click="zoomOut" />
        <span class="text-xs font-semibold text-[var(--sr-muted)]">{{ Math.round(canvas.scale * 100) }}%</span>
        <el-button :icon="ZoomIn" circle size="small" @click="zoomIn" />
        <el-button :icon="Aim" circle size="small" @click="resetView" />
      </div>
    </div>

    <!-- 树形画布 -->
    <div
      v-loading="loading"
      class="tree-canvas relative overflow-hidden rounded-xl border border-[var(--sr-border)] bg-[var(--sr-surface)]"
      style="min-height: 400px; cursor: grab;"
      :style="{ cursor: canvas.isDragging ? 'grabbing' : 'grab' }"
      @wheel.prevent="onWheel"
      @mousedown="onMouseDown"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @mouseleave="onMouseUp"
    >
      <div
        class="inline-block min-w-full p-8 transition-transform"
        :style="{ transform: `translate(${canvas.translateX}px, ${canvas.translateY}px) scale(${canvas.scale})`, transformOrigin: 'top center' }"
      >
        <div v-if="tree" class="flex flex-col items-center">
          <!-- 根节点(我) -->
          <div class="mb-2 rounded-xl border-2 border-[var(--sr-secondary)] bg-[var(--sr-secondary)]/5 px-5 py-3 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--sr-secondary)] text-sm font-bold text-white">我</div>
              <div>
                <div class="text-sm font-bold">{{ tree.nickname }}（我）</div>
                <div class="text-xs text-[var(--sr-muted)]">直邀 {{ tree.directCount }} 人 · 总充值 {{ money(tree.totalRecharge) }}</div>
              </div>
            </div>
          </div>

          <!-- L1 连线 -->
          <div v-if="tree.children?.length && isExpanded(tree.id)" class="h-8 w-px bg-[var(--sr-border)]" />

          <!-- L1 子节点 -->
          <div v-if="tree.children?.length && isExpanded(tree.id)" class="flex flex-wrap justify-center gap-6">
            <div v-for="child in tree.children" :key="child.id" class="flex flex-col items-center">
              <!-- L1 卡片 -->
              <button
                class="rounded-lg border bg-white px-4 py-2.5 shadow-sm transition hover:shadow-md"
                :class="child.status === 'active' ? 'border-green-200' : 'border-gray-200 opacity-60'"
                @click="toggleExpand(child.id)"
              >
                <div class="flex items-center gap-2">
                  <span class="h-2 w-2 rounded-full" :class="child.status === 'active' ? 'bg-green-500' : 'bg-gray-400'" />
                  <span class="text-sm font-semibold">{{ child.nickname }}</span>
                </div>
                <div class="mt-1 text-xs text-[var(--sr-muted)]">充值 {{ money(child.totalRecharge) }} · 邀请 {{ child.directCount }} 人</div>
                <div v-if="child.children?.length" class="mt-1 text-[10px] text-[var(--sr-secondary)]">
                  {{ isExpanded(child.id) ? '▼ 收起' : `▶ 展开 (${child.children.length})` }}
                </div>
              </button>

              <!-- L2 -->
              <template v-if="child.children?.length && isExpanded(child.id)">
                <div class="my-2 h-6 w-px bg-[var(--sr-border)]" />
                <div class="flex flex-wrap justify-center gap-3">
                  <div v-for="gc in child.children" :key="gc.id" class="rounded border bg-gray-50 px-3 py-2 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <span class="h-1.5 w-1.5 rounded-full" :class="gc.status === 'active' ? 'bg-green-500' : 'bg-gray-400'" />
                      <span class="text-xs font-semibold">{{ gc.nickname }}</span>
                    </div>
                    <div class="text-[10px] text-[var(--sr-muted)]">{{ money(gc.totalRecharge) }}</div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-if="!loading && !tree" class="flex h-[300px] flex-col items-center justify-center gap-2">
          <div class="text-4xl">🌱</div>
          <p class="font-semibold text-[var(--sr-muted)]">暂无下级推荐</p>
          <p class="text-xs text-[var(--sr-muted)]">分享你的推广链接，邀请好友注册后将在此显示</p>
        </div>
      </div>
    </div>

    <!-- 隐私说明 -->
    <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
      <p class="text-xs text-blue-700">🔒 隐私说明：出于隐私保护，你只能查看自己及下级的推荐关系，无法查看上级信息。如有疑问请联系客服。</p>
    </div>
  </div>
</template>
