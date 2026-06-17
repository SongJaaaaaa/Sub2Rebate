<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  data: { date: string; value: number }[]
  height?: number
  color?: string
}>()

const svgHeight = computed(() => props.height || 180)
const strokeColor = computed(() => props.color || 'var(--sr-secondary)')

const viewBoxWidth = 600
const padding = { top: 20, right: 24, bottom: 44, left: 24 }

const chartPoints = computed(() => {
  if (!props.data.length) return []
  const values = props.data.map((d) => d.value)
  const max = Math.max(...values, 1)
  const chartH = svgHeight.value - padding.top - padding.bottom
  const chartW = viewBoxWidth - padding.left - padding.right
  const step = props.data.length > 1 ? chartW / (props.data.length - 1) : 0

  return props.data.map((d, i) => ({
    x: padding.left + step * i,
    y: padding.top + chartH - (d.value / max) * chartH,
    date: d.date,
    value: d.value,
  }))
})

const polylineStr = computed(() =>
  chartPoints.value.map((p) => `${p.x},${p.y}`).join(' ')
)

const areaStr = computed(() => {
  if (!chartPoints.value.length) return ''
  const bottom = svgHeight.value - padding.bottom
  const first = chartPoints.value[0]
  const last = chartPoints.value[chartPoints.value.length - 1]
  return `${first.x},${bottom} ${polylineStr.value} ${last.x},${bottom}`
})

const gridLines = computed(() => {
  const chartH = svgHeight.value - padding.top - padding.bottom
  return [1, 2, 3].map((i) => padding.top + (chartH / 4) * i)
})

const dateLabel = (date: string) => {
  const [month, day] = date.slice(5).split('-')
  return month && day ? `${Number(day)}日` : date
}
</script>

<template>
  <div class="w-full overflow-x-auto">
    <svg
      :viewBox="`0 0 ${viewBoxWidth} ${svgHeight}`"
      preserveAspectRatio="none"
      class="h-auto w-full select-none"
      :style="{ minHeight: `${svgHeight}px`, minWidth: '520px' }"
    >
      <!-- Grid lines -->
      <line
        v-for="(y, i) in gridLines"
        :key="'g' + i"
        :x1="padding.left"
        :x2="viewBoxWidth - padding.right"
        :y1="y"
        :y2="y"
        stroke="var(--sr-border)"
        stroke-width="0.5"
        stroke-dasharray="4,4"
      />

      <!-- Area fill -->
      <polygon
        v-if="areaStr"
        :points="areaStr"
        :fill="strokeColor"
        fill-opacity="0.1"
      />

      <!-- Line -->
      <polyline
        v-if="polylineStr"
        :points="polylineStr"
        fill="none"
        :stroke="strokeColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />

      <!-- Points & labels -->
      <template v-for="(p, i) in chartPoints" :key="i">
        <circle
          :cx="p.x"
          :cy="p.y"
          r="4"
          :fill="strokeColor"
          stroke="white"
          stroke-width="2"
        />
        <text
          :x="p.x"
          :y="svgHeight - 18"
          text-anchor="middle"
          fill="var(--sr-muted)"
          font-size="12"
          font-family="system-ui, sans-serif"
        >
          {{ dateLabel(p.date) }}
        </text>
      </template>
    </svg>
  </div>
</template>
