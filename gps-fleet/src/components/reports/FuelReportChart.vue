<template>
  <div class="fuel-chart">
    <div class="chart-toolbar">
      <div v-if="plateNo || rangeStart || rangeEnd" class="chart-criteria">
        <span><strong>{{ t('fuelChartPlate') }}:</strong> {{ plateNo || '-' }}</span>
        <span>
          <strong>{{ t('fuelChartDuration') }}:</strong>
          {{ formatCriteriaDateTime(rangeStart) }} - {{ formatCriteriaDateTime(rangeEnd) }}
        </span>
      </div>
      <Button
        :label="t('saveChartImage')"
        icon="pi pi-image"
        severity="secondary"
        outlined
        size="small"
        :loading="savingImage"
        :disabled="points.length < 2"
        @click="saveChartImage"
      />
    </div>

    <div v-if="points.length < 2" class="empty-chart">{{ t('reportNoData') }}</div>
    <svg
      v-else
      ref="chartSvg"
      :viewBox="`0 0 ${chartWidth} 370`"
      :style="{ minWidth: `${chartWidth}px` }"
      role="img"
      :aria-label="t('fuelChart')"
    >
      <g v-for="tick in yAxisTicks" :key="tick.value">
        <line x1="60" :y1="tick.y" :x2="plotRight" :y2="tick.y" class="axis-grid" />
        <line x1="54" :y1="tick.y" x2="60" :y2="tick.y" class="axis-tick" />
        <text x="49" :y="tick.y + 4" text-anchor="end" class="axis-label">
          {{ tick.value }}%
        </text>
      </g>

      <line x1="60" y1="20" x2="60" y2="315" class="axis" />
      <line x1="60" y1="315" :x2="plotRight" y2="315" class="axis" />

      <g v-for="tick in xAxisTicks" :key="tick.timestamp">
        <line :x1="tick.x" y1="315" :x2="tick.x" y2="321" class="axis-tick" />
        <text :x="tick.x" y="339" :text-anchor="tick.anchor" class="axis-label">
          <tspan :x="tick.x">{{ tick.time }}</tspan>
          <tspan v-if="tick.date" :x="tick.x" dy="15">{{ tick.date }}</tspan>
        </text>
      </g>

      <line
        v-for="segment in fuelSegments"
        :key="segment.key"
        :x1="segment.x1"
        :y1="segment.y1"
        :x2="segment.x2"
        :y2="segment.y2"
        :class="['fuel-line', segment.status]"
      />

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
import Button from 'primevue/button'
import { useI18n } from '@/i18n'

const props = defineProps<{
  rows: Record<string, unknown>[]
  plateNo?: string
  rangeStart?: string
  rangeEnd?: string
}>()

const { t } = useI18n()
const chartSvg = ref<SVGSVGElement | null>(null)
const savingImage = ref(false)

function formatCriteriaDateTime(value?: string) {
  const match = value?.trim().match(
    /^(\d{4})-(\d{2})-(\d{2})[T\s]+(\d{2}):(\d{2})/
  )
  if (!match) return value || '-'
  return `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}`
}

function criteriaText() {
  return `${t('fuelChartPlate')}: ${props.plateNo || '-'}   ${t('fuelChartDuration')}: ${formatCriteriaDateTime(props.rangeStart)} - ${formatCriteriaDateTime(props.rangeEnd)}`
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function drawLegendItem(
  context: CanvasRenderingContext2D,
  x: number,
  y: number,
  color: string,
  label: string,
) {
  context.strokeStyle = color
  context.lineWidth = 4
  context.lineCap = 'round'
  context.beginPath()
  context.moveTo(x, y)
  context.lineTo(x + 24, y)
  context.stroke()
  context.fillStyle = '#334155'
  context.font = '14px sans-serif'
  context.fillText(label, x + 32, y + 5)
}

async function saveChartImage() {
  if (!chartSvg.value || savingImage.value) return
  savingImage.value = true

  try {
    const exportWidth = chartWidth.value
    const exportHeight = 500
    const pixelRatio = Math.min(window.devicePixelRatio || 1, 2)
    const canvas = document.createElement('canvas')
    canvas.width = exportWidth * pixelRatio
    canvas.height = exportHeight * pixelRatio

    const context = canvas.getContext('2d')
    if (!context) return
    context.scale(pixelRatio, pixelRatio)
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, exportWidth, exportHeight)

    context.fillStyle = '#1e293b'
    context.font = 'bold 22px sans-serif'
    context.fillText(t('fuelChart'), 24, 32)
    context.fillStyle = '#475569'
    context.font = '14px sans-serif'
    context.fillText(criteriaText(), 24, 58)

    const svg = chartSvg.value.cloneNode(true) as SVGSVGElement
    svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg')
    svg.setAttribute('width', String(exportWidth))
    svg.setAttribute('height', '370')
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style')
    style.textContent = `
      .axis { stroke: #475569; stroke-width: 1; }
      .axis-label { fill: #64748b; font: 12px sans-serif; }
      .axis-tick { stroke: #64748b; stroke-width: 1; }
      .axis-grid { stroke: #cbd5e1; stroke-dasharray: 3 5; stroke-width: 1; opacity: .55; }
      .fuel-line { fill: none; stroke-width: 3; stroke-linecap: round; }
      .fuel-line.run { stroke: #22c55e; }
      .fuel-line.park { stroke: #ef4444; }
      .fuel-line.idle { stroke: #eab308; }
    `
    svg.prepend(style)

    const svgBlob = new Blob([new XMLSerializer().serializeToString(svg)], {
      type: 'image/svg+xml;charset=utf-8',
    })
    const svgUrl = URL.createObjectURL(svgBlob)
    const image = new Image()
    await new Promise<void>((resolve, reject) => {
      image.onload = () => resolve()
      image.onerror = () => reject(new Error('Unable to render chart image'))
      image.src = svgUrl
    })
    context.drawImage(image, 0, 70, exportWidth, 370)
    URL.revokeObjectURL(svgUrl)

    const legendWidth = 360
    const legendStart = Math.max((exportWidth - legendWidth) / 2, 24)
    drawLegendItem(context, legendStart, 470, '#ef4444', t('fuelChartPark'))
    drawLegendItem(context, legendStart + 120, 470, '#eab308', t('fuelChartIdle'))
    drawLegendItem(context, legendStart + 240, 470, '#22c55e', t('fuelChartRun'))

    const imageBlob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'))
    if (!imageBlob) return
    const safePlate = (props.plateNo || 'report').replace(/[^a-zA-Z0-9ก-๙_-]+/g, '-')
    downloadBlob(imageBlob, `fuel-chart-${safePlate}.png`)
  } finally {
    savingImage.value = false
  }
}

function value(row: Record<string, unknown>, names: string[]) {
  const key = Object.keys(row).find((item) =>
    names.some((name) => item.toLowerCase() === name.toLowerCase())
  )
  return key ? row[key] : undefined
}

function statusOf(row: Record<string, unknown>) {
  const raw = String(value(row, ['vehicle_status', 'status', 'state']) ?? '').toLowerCase()
  const speed = Number(value(row, ['speed']) ?? 0)
  if (speed > 0 || raw.includes('run') || raw.includes('moving') || raw.includes('วิ่ง')) {
    return 'run'
  }
  if (
    raw === '1'
    || raw.includes('idle')
    || raw.includes('start')
    || raw.includes('on')
    || raw.includes('true')
    || raw.includes('ติดเครื่อง')
  ) {
    return 'idle'
  }
  return 'park'
}

function timestampOf(label: string) {
  const match = label.trim().match(
    /^(\d{4})-(\d{2})-(\d{2})[T\s]+(\d{2}):(\d{2})(?::(\d{2}))?/
  )
  if (!match) return NaN

  return new Date(
    Number(match[1]),
    Number(match[2]) - 1,
    Number(match[3]),
    Number(match[4]),
    Number(match[5]),
    Number(match[6] ?? 0),
  ).getTime()
}

const points = computed(() => {
  const rawPoints = props.rows.map((row, index) => {
    const label = String(value(row, ['data_date', 'gps_time', 'date']) ?? '')
    return {
      index,
      fuel: Number(value(row, ['fuel', 'fuel_left']) ?? NaN),
      label,
      timestamp: timestampOf(label),
      status: statusOf(row),
    }
  })
  .filter((point) => Number.isFinite(point.fuel))

  let lastActiveFuel: number | null = null
  return rawPoints.map((point) => {
    if (point.status === 'park' && lastActiveFuel !== null) {
      return { ...point, fuel: lastActiveFuel }
    }

    if (point.status === 'run' || point.status === 'idle') {
      lastActiveFuel = point.fuel
    }

    return point
  })
})

const PLOT_TOP = 20
const PLOT_BOTTOM = 315
const FUEL_MAX = 100

function yFor(value: number) {
  return PLOT_BOTTOM - (value / FUEL_MAX) * (PLOT_BOTTOM - PLOT_TOP)
}

const yAxisTicks = Array.from({ length: 11 }, (_, index) => ({
  value: index * 10,
  y: yFor(index * 10),
}))

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

  return Array.from(
    { length: Math.floor((end - start) / interval) + 1 },
    (_, index) => start + index * interval,
  )
})

const chartWidth = computed(() => Math.max(1000, scaleTimestamps.value.length * 64 + 80))
const plotRight = computed(() => chartWidth.value - 20)

function xFor(timestamp: number, pointIndex = 0) {
  const timeRange = lastTimestamp.value - firstTimestamp.value
  if (Number.isFinite(timestamp) && timeRange > 0) {
    return 60 + ((timestamp - firstTimestamp.value) / timeRange) * (chartWidth.value - 80)
  }

  const indexRange = Math.max(points.value.length - 1, 1)
  return 60 + (pointIndex / indexRange) * (chartWidth.value - 80)
}

function pad(value: number) {
  return String(value).padStart(2, '0')
}

const xAxisTicks = computed(() => {
  return scaleTimestamps.value.map((timestamp, index) => {
    const date = new Date(timestamp)
    const x = xFor(timestamp)
    const showDate = index === 0 || (date.getHours() === 0 && date.getMinutes() === 0)

    return {
      timestamp,
      x,
      anchor: x <= 70 ? 'start' : x >= plotRight.value - 10 ? 'end' : 'middle',
      date: showDate
        ? `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
        : '',
      time: `${pad(date.getHours())}:${pad(date.getMinutes())}`,
    }
  })
})

const fuelSegments = computed(() => {
  return points.value.slice(1).map((point, index) => {
    const previous = points.value[index]
    return {
      key: `fuel-${index}-${point.index}`,
      x1: xFor(previous.timestamp, index),
      y1: yFor(Math.min(Math.max(previous.fuel, 0), FUEL_MAX)),
      x2: xFor(point.timestamp, index + 1),
      y2: yFor(Math.min(Math.max(point.fuel, 0), FUEL_MAX)),
      status: point.status,
    }
  })
})

</script>

<style scoped>
.fuel-chart {
  min-height: 360px;
  overflow-x: auto;
}

.chart-toolbar {
  position: sticky;
  left: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 0 0 12px;
}

.chart-criteria {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 24px;
  color: #64748b;
  font-size: 13px;
}

.chart-criteria strong {
  color: #334155;
}

svg {
  width: 100%;
  min-width: 700px;
}

.axis {
  stroke: #475569;
  stroke-width: 1;
}

.axis-label {
  fill: #94a3b8;
  font-size: 12px;
}

.axis-tick {
  stroke: #64748b;
  stroke-width: 1;
}

.axis-grid {
  stroke: #cbd5e1;
  stroke-dasharray: 3 5;
  stroke-width: 1;
  opacity: 0.35;
}

.fuel-line {
  stroke-width: 3;
  stroke-linecap: round;
}

.fuel-line.run,
.legend i.run {
  stroke: #22c55e;
  background: #22c55e;
}

.fuel-line.park,
.legend i.park {
  stroke: #ef4444;
  background: #ef4444;
}

.fuel-line.idle,
.legend i.idle {
  stroke: #eab308;
  background: #eab308;
}

.legend {
  display: flex;
  justify-content: center;
  gap: 20px;
  color: #cbd5e1;
}

.legend span {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend i {
  width: 18px;
  height: 3px;
}

.empty-chart {
  display: grid;
  min-height: 300px;
  place-items: center;
  color: #94a3b8;
}
</style>
