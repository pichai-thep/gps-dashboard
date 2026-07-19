<template>
  <div class="fuel-chart">
    <div v-if="points.length < 2" class="empty-chart">{{ t('reportNoData') }}</div>
    <svg v-else viewBox="0 0 1000 360" role="img" :aria-label="t('fuelChart')">
      <line x1="60" y1="20" x2="60" y2="315" class="axis" />
      <line x1="60" y1="315" x2="980" y2="315" class="axis" />

      <text x="18" y="28" class="axis-label">{{ maxFuel.toFixed(1) }}</text>
      <text x="25" y="319" class="axis-label">{{ minFuel.toFixed(1) }}</text>
      <text x="60" y="344" class="axis-label">{{ firstLabel }}</text>
      <text x="980" y="344" text-anchor="end" class="axis-label">{{ lastLabel }}</text>

      <line
        v-for="segment in segments"
        :key="segment.key"
        :x1="segment.x1"
        :y1="segment.y1"
        :x2="segment.x2"
        :y2="segment.y2"
        :class="['fuel-line', segment.status]"
      />
    </svg>

    <div class="legend">
      <span><i class="run"></i>{{ t('running') }}</span>
      <span><i class="park"></i>{{ t('parking') }}</span>
      <span><i class="idle"></i>{{ t('idle') }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/i18n'

const props = defineProps<{
  rows: Record<string, unknown>[]
}>()

const { t } = useI18n()

function value(row: Record<string, unknown>, names: string[]) {
  const key = Object.keys(row).find((item) =>
    names.some((name) => item.toLowerCase() === name.toLowerCase())
  )
  return key ? row[key] : undefined
}

function statusOf(row: Record<string, unknown>) {
  const raw = String(value(row, ['vehicle_status', 'status', 'state']) ?? '').toLowerCase()
  const speed = Number(value(row, ['speed']) ?? 0)
  if (raw.includes('park') || raw === '0') return 'park'
  if (raw.includes('idle') || raw.includes('start')) return 'idle'
  return speed > 0 ? 'run' : 'idle'
}

const points = computed(() => props.rows
  .map((row, index) => ({
    index,
    fuel: Number(value(row, ['fuel', 'fuel_left']) ?? NaN),
    label: String(value(row, ['data_date', 'gps_time', 'date']) ?? ''),
    status: statusOf(row),
  }))
  .filter((point) => Number.isFinite(point.fuel))
)

const minFuel = computed(() => Math.min(...points.value.map((point) => point.fuel), 0))
const maxFuel = computed(() => Math.max(...points.value.map((point) => point.fuel), 1))
const firstLabel = computed(() => points.value[0]?.label ?? '')
const lastLabel = computed(() => points.value[points.value.length - 1]?.label ?? '')

const segments = computed(() => {
  const range = Math.max(maxFuel.value - minFuel.value, 1)
  const width = Math.max(points.value.length - 1, 1)

  return points.value.slice(1).map((point, index) => {
    const previous = points.value[index]
    return {
      key: `${index}-${point.index}`,
      x1: 60 + (index / width) * 920,
      y1: 315 - ((previous.fuel - minFuel.value) / range) * 285,
      x2: 60 + ((index + 1) / width) * 920,
      y2: 315 - ((point.fuel - minFuel.value) / range) * 285,
      status: point.status,
    }
  })
})
</script>

<style scoped>
.fuel-chart {
  min-height: 360px;
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
