<template>
  <div class="page-header">
    <div>
      <h1>{{ title }}</h1>
      <p>{{ subtitle }}</p>
    </div>

    <div v-if="$slots.actions" class="header-actions">
      <slot name="actions" />
    </div>

    <slot name="trailing" />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  subtitle: string
}>()
</script>

<style scoped>
@import '@/views/reports/report-dark.css';

@media print {
  .header-actions {
    display: none !important;
  }

  .page-header {
    align-items: flex-start;
  }

  .page-header p {
    color: #475569;
  }
}
</style>
