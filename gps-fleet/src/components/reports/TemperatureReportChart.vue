<template>
  <div class="temperature-chart">
    <div class="criteria">
      <span><strong>{{ t('fuelChartPlate') }}:</strong> {{ plateNo || '-' }}</span>
      <span><strong>{{ t('fuelChartDuration') }}:</strong> {{ rangeStart }} - {{ rangeEnd }}</span>
    </div>
    <div v-if="points.length < 2" class="empty-chart">{{ t('reportNoData') }}</div>
    <svg
      v-else
      ref="chartSvg"
      :viewBox="`0 0 ${width} 370`"
      :style="{ minWidth: `${width}px` }"
      role="img"
      :aria-label="t('temperatureChart')"
    >
      <g v-for="tick in yTicks" :key="tick.value">
        <line x1="60" :y1="tick.y" :x2="width - 20" :y2="tick.y" class="grid" />
        <text x="50" :y="tick.y + 4" text-anchor="end">{{ tick.value }}</text>
      </g>
      <line x1="60" y1="20" x2="60" y2="310" class="axis" />
      <line x1="60" y1="310" :x2="width - 20" y2="310" class="axis" />

      <line
        v-for="segment in segments"
        :key="segment.key"
        :x1="segment.x1"
        :y1="segment.y1"
        :x2="segment.x2"
        :y2="segment.y2"
        :class="['temperature-line', segment.status]"
      />
      <g v-for="tick in xAxisTicks" :key="tick.timestamp">
        <line :x1="tick.x" y1="310" :x2="tick.x" y2="316" class="axis-tick" />
        <text :x="tick.x" y="334" :text-anchor="tick.anchor">
          <tspan :x="tick.x">{{ tick.time }}</tspan>
          <tspan v-if="tick.date" :x="tick.x" dy="15">{{ tick.date }}</tspan>
        </text>
      </g>
      <circle
        v-for="(point, index) in points"
        :key="point.id"
        :cx="xFor(point.timestamp, index)"
        :cy="yFor(point.temperature)"
        r="3"
        :class="['temperature-point', point.status]"
      />

      <rect x="60" :width="width - 80" y="20" height="290" class="hover-area" @mousemove="onMouseMove" @mouseleave="hover = null" />
      <g v-if="hover" pointer-events="none">
        <line :x1="hover.x" :x2="hover.x" y1="20" y2="310" class="hover-line" />
        <circle :cx="hover.x" :cy="hover.y" r="5" class="hover-dot" />
        <g :transform="`translate(${tooltipX}, 28)`">
          <rect width="255" height="50" rx="7" class="tooltip" />
          <text x="10" y="20" class="tooltip-text">{{ t('fuelChartTime') }}: {{ hover.time }}</text>
          <text x="10" y="39" class="tooltip-text">{{ t('temperature') }}: {{ hover.temperature }} °C</text>
        </g>
      </g>
      <text x="14" y="16" class="unit">°C</text>
    </svg>
    <div class="legend">
      <span><i class="park"></i>{{ t('fuelChartPark') }}</span>
      <span><i class="idle"></i>{{ t('fuelChartIdle') }}</span>
      <span><i class="run"></i>{{ t('fuelChartRun') }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from '@/i18n'

const props = defineProps<{ rows: Record<string, unknown>[]; plateNo?: string; rangeStart?: string; rangeEnd?: string }>()
const { t } = useI18n()
const chartSvg = ref<SVGSVGElement | null>(null)
const hover = ref<{ x: number; y: number; time: string; temperature: number } | null>(null)

function value(row: Record<string, unknown>, names: string[]) {
  const key = Object.keys(row).find((key) => names.includes(key.toLowerCase()))
  return key ? row[key] : undefined
}

function statusOf(row: Record<string, unknown>) {
  const speed = Number(value(row, ['speed']) ?? 0)
  const raw = String(value(row, ['state', 'vehicle_status', 'status']) ?? '').toLowerCase()
  if (speed > 0 || raw.includes('run') || raw.includes('moving')) return 'run'
  if (raw === '1' || raw.includes('idle') || raw.includes('start') || raw.includes('on')) return 'idle'
  return 'park'
}

const points = computed(() => props.rows.map((row, index) => {
  const dateTime = String(value(row, ['mm_time', 'data_date']) ?? '')
  return {
    id: `${dateTime}-${index}`,
    temperature: Number(value(row, ['temperature']) ?? NaN),
    dateTime,
    timestamp: timestampOf(dateTime),
    status: statusOf(row),
  }
}).filter((point) => Number.isFinite(point.temperature)))

const minTemperature = computed(() => Math.floor(Math.min(...points.value.map((point) => point.temperature), 0) / 5) * 5)
const maxTemperature = computed(() => Math.ceil(Math.max(...points.value.map((point) => point.temperature), 10) / 5) * 5)
const HOUR_MS = 60 * 60 * 1000
const MINUTE_MS = 60 * 1000
const firstTimestamp = computed(() => points.value[0]?.timestamp ?? NaN)
const lastTimestamp = computed(() => points.value[points.value.length - 1]?.timestamp ?? NaN)
const tickInterval = computed(() => {
  const duration = lastTimestamp.value - firstTimestamp.value
  if (duration < HOUR_MS) return 10 * MINUTE_MS
  if (duration <= 6 * HOUR_MS) return 30 * MINUTE_MS
  return HOUR_MS
})
const scaleTimestamps = computed(() => {
  if (!Number.isFinite(firstTimestamp.value) || !Number.isFinite(lastTimestamp.value)) return []
  const interval = tickInterval.value
  const start = Math.ceil(firstTimestamp.value / interval) * interval
  const end = Math.floor(lastTimestamp.value / interval) * interval
  if (start > end) return [firstTimestamp.value]
  return Array.from({ length: Math.floor((end - start) / interval) + 1 }, (_, index) => start + index * interval)
})
const width = computed(() => Math.max(1000, scaleTimestamps.value.length * 64 + 80))
function xFor(timestamp: number, index = 0) {
  const range = lastTimestamp.value - firstTimestamp.value
  if (Number.isFinite(timestamp) && range > 0) {
    return 60 + ((timestamp - firstTimestamp.value) / range) * (width.value - 80)
  }
  return 60 + (index / Math.max(points.value.length - 1, 1)) * (width.value - 80)
}
const yFor = (temperature: number) => 310 - ((temperature - minTemperature.value) / Math.max(maxTemperature.value - minTemperature.value, 1)) * 290
const yTicks = computed(() => Array.from({ length: 6 }, (_, index) => {
  const value = Math.round((minTemperature.value + ((maxTemperature.value - minTemperature.value) / 5) * index) * 10) / 10
  return { value, y: yFor(value) }
}))
const segments = computed(() => points.value.slice(1).map((point, index) => ({
  key: `${point.id}-${index}`,
  x1: xFor(points.value[index].timestamp, index), y1: yFor(points.value[index].temperature),
  x2: xFor(point.timestamp, index + 1), y2: yFor(point.temperature), status: point.status,
})))

function timestampOf(label: string) {
  const match = label.trim().match(/^(\d{4})-(\d{2})-(\d{2})[T\s]+(\d{2}):(\d{2})(?::(\d{2}))?/)
  if (!match) return NaN
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5]), Number(match[6] ?? 0)).getTime()
}

function pad(value: number) {
  return String(value).padStart(2, '0')
}

const xAxisTicks = computed(() => scaleTimestamps.value.map((timestamp, index) => {
  const date = new Date(timestamp)
  const x = xFor(timestamp)
  return {
    timestamp,
    x,
    anchor: x <= 70 ? 'start' : x >= width.value - 30 ? 'end' : 'middle',
    time: `${pad(date.getHours())}:${pad(date.getMinutes())}`,
    date: index === 0 || (date.getHours() === 0 && date.getMinutes() === 0)
      ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
      : '',
  }
}))

function onMouseMove(event: MouseEvent) {
  if (!chartSvg.value || points.value.length === 0) return
  const rect = chartSvg.value.getBoundingClientRect()
  const rawX = ((event.clientX - rect.left) / rect.width) * width.value
  const x = Math.min(width.value - 20, Math.max(60, rawX))
  const ratio = (x - 60) / Math.max(width.value - 80, 1)
  const targetTimestamp = firstTimestamp.value + ratio * (lastTimestamp.value - firstTimestamp.value)
  const index = points.value.reduce((nearest, point, pointIndex) =>
    Math.abs(point.timestamp - targetTimestamp) < Math.abs(points.value[nearest].timestamp - targetTimestamp) ? pointIndex : nearest, 0)
  const point = points.value[index]
  hover.value = { x: xFor(point.timestamp, index), y: yFor(point.temperature), time: point.dateTime, temperature: point.temperature }
}

const tooltipX = computed(() => !hover.value ? 60 : hover.value.x + 265 > width.value - 20 ? hover.value.x - 265 : hover.value.x + 10)
</script>

<style scoped>
.temperature-chart { min-height: 360px; overflow-x: auto; }
.criteria { position: sticky; left: 0; display: flex; gap: 24px; padding-bottom: 12px; color: #64748b; font-size: 13px; }
svg { width: 100%; min-width: 700px; }
text { fill: #94a3b8; font-size: 12px; }
.axis { stroke: #64748b; }
.axis-tick { stroke: #64748b; }
.grid { stroke: #cbd5e1; stroke-dasharray: 3 5; opacity: .3; }
.temperature-line { stroke-width: 4; stroke-linecap: round; }
.temperature-line.run, .temperature-point.run, .legend i.run { stroke: #22c55e; fill: #22c55e; background: #22c55e; }
.temperature-line.park, .temperature-point.park, .legend i.park { stroke: #ef4444; fill: #ef4444; background: #ef4444; }
.temperature-line.idle, .temperature-point.idle, .legend i.idle { stroke: #eab308; fill: #eab308; background: #eab308; }
.hover-area { fill: transparent; cursor: crosshair; pointer-events: all; }
.hover-line { stroke: #475569; stroke-dasharray: 4 4; }
.hover-dot { fill: #0f172a; stroke: #f8fafc; stroke-width: 2; }
.tooltip { fill: rgba(15, 23, 42, .94); stroke: rgba(148, 163, 184, .45); }
.tooltip-text { fill: #f8fafc; font-size: 12px; font-weight: 700; }
.unit { fill: #38bdf8; font-weight: 700; }
.legend { display: flex; justify-content: center; gap: 20px; color: #cbd5e1; }
.legend span { display: flex; align-items: center; gap: 6px; }
.legend i { width: 18px; height: 3px; }
.empty-chart { padding: 70px; color: #94a3b8; text-align: center; }
</style>
