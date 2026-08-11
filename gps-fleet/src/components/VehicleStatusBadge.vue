<template>
  <span class="vehicle-status-badge" :style="badgeStyle">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { locale } from '@/i18n'
import { vehicleStatusBadgeStyle, vehicleStatusLabel } from '@/utils/vehicleStatus'

const props = defineProps<{
  status?: unknown
}>()

const label = computed(() => vehicleStatusLabel(props.status, locale.value))
const badgeStyle = computed(() => vehicleStatusBadgeStyle(props.status))
</script>

<style scoped>
.vehicle-status-badge {
  display: inline-flex;
  min-width: 64px;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
}
</style>
