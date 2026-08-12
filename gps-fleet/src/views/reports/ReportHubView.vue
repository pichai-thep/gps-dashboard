<template>
  <main class="report-hub">
    <header class="hub-header">
      <div class="hub-icon"><i :class="sectionIcon"></i></div>
      <div>
        <h1>{{ sectionTitle }}</h1>
        <p>{{ t('reportHubHint') }}</p>
      </div>
    </header>

    <section class="report-card-grid">
      <button
        v-for="report in reports"
        :key="report.path"
        type="button"
        class="report-card"
        @click="router.push(report.path)"
      >
        <span class="card-icon"><i :class="report.icon"></i></span>
        <span class="card-content">
          <strong>{{ report.title }}</strong>
          <small>{{ report.subtitle }}</small>
        </span>
        <span class="card-open">
          {{ t('openReport') }}
          <i class="pi pi-arrow-right"></i>
        </span>
      </button>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { locale, useI18n } from '@/i18n'
import { reportNavigation } from './reportNavigation'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const auth = useAuthStore()

function reportMenuLabel(label: string) {
  return label.replace(/^รายงาน\s*/, '').replace(/\s+Reports?$/i, '')
}

const section = computed(() => route.meta.reportSection as 'summary' | 'general')
const sectionTitle = computed(() =>
  section.value === 'summary' ? t('summaryReports') : t('generalReports'),
)
const sectionIcon = computed(() =>
  section.value === 'summary' ? 'pi pi-chart-pie' : 'pi pi-list',
)

const iconByKey: Record<string, string> = {
  'daily-summary': 'pi pi-calendar',
  'status-summary': 'pi pi-clock',
  'station-summary': 'pi pi-warehouse',
  'speed-over-summary': 'pi pi-gauge',
  'drive4h-summary': 'pi pi-stopwatch',
  'passenger-summary': 'pi pi-users',
  'monthly-distance': 'pi pi-chart-bar',
  'monthly-income': 'pi pi-wallet',
  'status-detail': 'pi pi-list-check',
  'speed-over': 'pi pi-gauge',
  speed: 'pi pi-chart-line',
  event: 'pi pi-bell',
  fuel: 'pi pi-chart-line',
  temperature: 'pi pi-chart-line',
  swipe: 'pi pi-id-card',
  drive4h: 'pi pi-stopwatch',
  passenger: 'pi pi-users',
  'forbidden-inside': 'pi pi-ban',
}

const summaryCoreReports = computed(() => [
  ...(auth.hasFeature('summaryReport') ? [
    { key: 'daily-summary', path: '/reports/daily-summary', title: t('dailySummaryReport'), subtitle: t('dailySummarySubtitle') },
    { key: 'status-summary', path: '/reports/status-summary', title: t('statusTimelineReport'), subtitle: t('statusTimelineSubtitle') },
  ] : []),
  ...(auth.hasFeature('summaryReport') && auth.hasFeature('stationInOutSummaryReport') ? [
    { key: 'station-summary', path: '/reports/station-summary', title: t('stationVisitReport'), subtitle: t('stationVisitSubtitle') },
  ] : []),
])

const reports = computed(() => {
  const procedureReports = reportNavigation
    .filter((report) => report.section === section.value)
    .filter((report) => !report.features?.some((feature) => !auth.hasFeature(feature)))
    .map((report) => ({
      key: report.key,
      path: report.path,
      title: reportMenuLabel(report.title[locale.value]),
      subtitle: t('clickToOpenReport'),
    }))

  const items = section.value === 'summary'
    ? [...summaryCoreReports.value, ...procedureReports]
    : procedureReports

  return items.map((report) => ({
    ...report,
    title: reportMenuLabel(report.title),
    icon: iconByKey[report.key] ?? 'pi pi-file',
  }))
})
</script>

<style scoped>
.report-hub {
  min-height: 100%;
  padding: 24px;
  color: #e5e7eb;
}

.hub-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.hub-icon,
.card-icon {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  color: #60a5fa;
  background: rgb(59 130 246 / 14%);
  border: 1px solid rgb(96 165 250 / 25%);
}

.hub-icon {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  font-size: 22px;
}

.hub-header h1 {
  margin: 0;
  font-size: 26px;
}

.hub-header p {
  margin: 5px 0 0;
  color: #94a3b8;
}

.report-card-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.report-card {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 14px;
  min-height: 154px;
  padding: 18px;
  color: inherit;
  text-align: left;
  cursor: pointer;
  background: linear-gradient(145deg, #111827, #0f172a);
  border: 1px solid #1f2937;
  border-radius: 16px;
  transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.report-card:hover {
  transform: translateY(-2px);
  border-color: #3b82f6;
  box-shadow: 0 14px 30px rgb(2 6 23 / 40%);
}

.card-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  font-size: 18px;
}

.card-content {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 8px;
}

.card-content strong {
  color: #f8fafc;
  font-size: 16px;
  line-height: 1.4;
}

.card-content small {
  color: #94a3b8;
  line-height: 1.5;
}

.card-open {
  display: flex;
  grid-column: 1 / -1;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  color: #60a5fa;
  font-size: 13px;
  font-weight: 600;
}

@media (max-width: 1100px) {
  .report-card-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .report-hub { padding: 16px; }
  .report-card-grid { grid-template-columns: 1fr; }
}
</style>
