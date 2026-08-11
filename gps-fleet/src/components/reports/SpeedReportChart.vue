<template>
  <div class="speed-chart">
    <div class="criteria">
      <span><strong>{{ t('fuelChartPlate') }}:</strong> {{ plateNo || '-' }}</span>
      <span><strong>{{ t('fuelChartDuration') }}:</strong> {{ rangeStart }} - {{ rangeEnd }}</span>
    </div>
    <div v-if="points.length < 2" class="empty-chart">{{ t('reportNoData') }}</div>
    <svg ref="chartSvg" v-else :viewBox="`0 0 ${width} 360`" :style="{ minWidth: `${width}px` }" role="img" :aria-label="t('speedChart')">
      <g v-for="tick in yTicks" :key="tick.value">
        <line x1="60" :y1="tick.y" :x2="width - 20" :y2="tick.y" class="grid" />
        <text x="50" :y="tick.y + 4" text-anchor="end">{{ tick.value }}</text>
      </g>
      <line x1="60" y1="20" x2="60" y2="310" class="axis" />
      <line x1="60" y1="310" :x2="width - 20" y2="310" class="axis" />
      <polyline :points="linePoints" class="speed-line" />
      <g v-for="(point, index) in points" :key="point.id">
        <circle :cx="xFor(index)" :cy="yFor(point.speed)" r="3" class="speed-point" />
        <text v-if="index % labelStep === 0 || index === points.length - 1" :x="xFor(index)" y="333" text-anchor="middle">
          {{ point.time }}
        </text>
      </g>
      <rect
        x="60"
        :width="width - 80"
        y="20"
        height="290"
        class="chart-hover-area"
        @mousemove="onChartMouseMove"
        @mouseleave="hoverState = null"
      />
      <g v-if="hoverState" class="chart-hover-layer" pointer-events="none">
        <line :x1="hoverState.x" :x2="hoverState.x" y1="20" y2="310" class="chart-hover-line" />
        <circle :cx="hoverState.x" :cy="hoverState.y" r="5" class="chart-hover-dot" />
        <g :transform="`translate(${hoverTooltipX}, 28)`">
          <rect width="205" height="34" rx="7" class="chart-hover-tooltip" />
          <text x="10" y="22" class="chart-hover-text">
            {{ t('fuelChartTime') }}: {{ hoverState.time }}
          </text>
        </g>
      </g>
      <text x="14" y="16" class="unit">km/h</text>
    </svg>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from '@/i18n'

const props = defineProps<{ rows: Record<string, unknown>[]; plateNo?: string; rangeStart?: string; rangeEnd?: string }>()
const { t } = useI18n()
const chartSvg = ref<SVGSVGElement | null>(null)
const hoverState = ref<{ x: number; y: number; time: string } | null>(null)

function value(row: Record<string, unknown>, name: string) {
  const key = Object.keys(row).find((item) => item.toLowerCase() === name.toLowerCase())
  return key ? row[key] : undefined
}

const points = computed(() => props.rows.map((row, index) => {
  const date = String(value(row, 'data_date') ?? '')
  return {
    id: String(value(row, 'gpsdata_id') ?? index),
    speed: Number(value(row, 'speed') ?? 0),
    time: date.slice(11, 16) || String(index + 1),
    dateTime: formatHoverTime(date) || date || String(index + 1),
  }
}).filter((point) => Number.isFinite(point.speed)))
const maxSpeed = computed(() => Math.max(20, Math.ceil(Math.max(...points.value.map((point) => point.speed), 0) / 20) * 20))
const width = computed(() => Math.max(900, points.value.length * 24 + 80))
const labelStep = computed(() => Math.max(1, Math.ceil(points.value.length / 12)))
const xFor = (index: number) => 60 + (index / Math.max(points.value.length - 1, 1)) * (width.value - 80)
const yFor = (speed: number) => 310 - (Math.max(0, speed) / maxSpeed.value) * 290
const yTicks = computed(() => Array.from({ length: 6 }, (_, index) => {
  const value = Math.round((maxSpeed.value / 5) * index)
  return { value, y: yFor(value) }
}))
const linePoints = computed(() => points.value.map((point, index) => `${xFor(index)},${yFor(point.speed)}`).join(' '))

function formatHoverTime(value: string) {
  const match = value.trim().match(/^(\d{4})-(\d{2})-(\d{2})[T\s]+(\d{2}):(\d{2})(?::(\d{2}))?/)
  if (!match) return ''
  return `${match[1]}-${match[2]}-${match[3]} ${match[4]}:${match[5]}:${match[6] ?? '00'}`
}

function onChartMouseMove(event: MouseEvent) {
  if (!chartSvg.value || points.value.length === 0) return
  const rect = chartSvg.value.getBoundingClientRect()
  if (rect.width <= 0) return

  const rawX = ((event.clientX - rect.left) / rect.width) * width.value
  const ratio = (Math.min(width.value - 20, Math.max(60, rawX)) - 60) / Math.max(width.value - 80, 1)
  const index = Math.min(points.value.length - 1, Math.max(0, Math.round(ratio * (points.value.length - 1))))
  const point = points.value[index]
  hoverState.value = {
    x: xFor(index),
    y: yFor(point.speed),
    time: point.dateTime,
  }
}

const hoverTooltipX = computed(() => {
  if (!hoverState.value) return 60
  return hoverState.value.x + 215 > width.value - 20
    ? hoverState.value.x - 215
    : hoverState.value.x + 10
})
</script>

<style scoped>
.speed-chart { min-height: 360px; overflow-x: auto; }
.criteria { position: sticky; left: 0; display: flex; gap: 24px; padding-bottom: 12px; color: #64748b; font-size: 13px; }
svg { width: 100%; min-width: 700px; }
text { fill: #94a3b8; font-size: 12px; }
.axis { stroke: #64748b; }
.grid { stroke: #cbd5e1; stroke-dasharray: 3 5; opacity: .3; }
.speed-line { fill: none; stroke: #38bdf8; stroke-width: 3; stroke-linejoin: round; }
.speed-point { fill: #0ea5e9; }
.chart-hover-area { fill: transparent; cursor: crosshair; pointer-events: all; }
.chart-hover-line { stroke: #475569; stroke-width: 1; stroke-dasharray: 4 4; }
.chart-hover-dot { fill: #0ea5e9; stroke: #f8fafc; stroke-width: 2; }
.chart-hover-tooltip { fill: rgba(15, 23, 42, .94); stroke: rgba(148, 163, 184, .45); }
.chart-hover-text { fill: #f8fafc; font-size: 12px; font-weight: 700; }
.unit { fill: #38bdf8; font-weight: 700; }
.empty-chart { padding: 70px; color: #94a3b8; text-align: center; }
</style>
