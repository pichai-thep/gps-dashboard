<template>
  <div class="summary-grid" :style="gridStyle">
    <div
      v-for="item in items"
      :key="item.key"
      :class="['summary-card', item.className]"
    >
      <span>{{ item.label }}</span>
      <strong>{{ item.value }}</strong>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface ReportSummaryItem {
  key: string
  label: string
  value: string | number
  className?: string
}

const props = defineProps<{
  items: ReportSummaryItem[]
  columns?: number
}>()

const gridStyle = computed(() => props.columns
  ? { '--report-summary-columns': props.columns }
  : undefined
)
</script>

<style scoped>
@import '@/views/reports/report-dark.css';

.summary-grid {
  grid-template-columns: repeat(var(--report-summary-columns, 6), minmax(0, 1fr));
}

@media (max-width: 1100px) {
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
