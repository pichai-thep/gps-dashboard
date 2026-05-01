<template>
  <div class="tracking-page">
    <!-- LEFT: VEHICLE LIST -->
    <aside class="vehicle-panel">
      <div class="panel-header">

        <Message v-if="error" severity="error" class="m-3">
          {{ error }}
        </Message>

        <div v-if="loading" class="loading-text">
          Loading tracking...
        </div>

        <h2>Current Tracking</h2>
        <p>{{ vehicles.length }} vehicles</p>
      </div>

      <div class="vehicle-list">
          <button
              v-for="vehicle in vehicles"
              :key="getVehicleKey(vehicle)"
              class="vehicle-item"
              :class="{ active: selectedVehicleId === getVehicleKey(vehicle) }"
              @click="selectVehicle(vehicle)"
          >
<!--            <div class="vehicle-info">-->
<!--              <span>{{ vehicle.plate_no }}</span>-->
<!--              <span class="vehicle-meta">-->
<!--                Speed: {{ vehicle.speed ?? 0 }} km/h-->
<!--                Fuel: {{ formatFuel(vehicle.fuel) }}-->
<!--              </span>-->
<!--              <span class="vehicle-meta">-->
<!--                {{ formatGpsTime(vehicle.gps_time) }}-->
<!--              </span>-->

<!--            </div>-->

            <div class="vehicle-info">
              <strong>{{ vehicle.plate_no }}</strong>

              <span>{{ vehicle.location || '-' }}</span>

              <div class="vehicle-stats">
                <span>🚗 {{ vehicle.speed ?? 0 }} km/h</span>
                <span>⛽ {{ formatFuel(vehicle.fuel) }}
                  <div class="fuel-bar">
                    <div
                        class="fuel-fill"
                        :style="{ width: (vehicle.fuel ?? 0) + '%' }"
                    ></div>
                  </div>
                </span>
              </div>

              <small class="gps-time">
                🕒 {{ formatGpsTime(vehicle.gps_time) }}
              </small>
            </div>

          <Tag
              :value="vehicle.status"
              :severity="statusSeverity(vehicle.status)"
          />
        </button>
      </div>
    </aside>

    <!-- RIGHT: MAP -->
    <section class="map-area">
      <FleetMap
          :vehicles="vehicles"
          :focus-vehicle-id="selectedVehicleId"
          @vehicle-click="selectVehicle"
      />
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import Tag from 'primevue/tag'
import FleetMap from '../components/FleetMap.vue'
import type { Vehicle, VehicleStatus } from '../types/fleet'
import { getCurrentTracking } from '../services/tracking'
import Message from 'primevue/message'

const selectedVehicleId = ref<string | null>(null)
const vehicles = ref<Vehicle[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

let pollingTimer: number | null = null

async function loadVehicles() {
  console.log('LOAD VEHICLES CALLED')

  try {
    loading.value = true
    error.value = null
    vehicles.value = await getCurrentTracking()
    console.log('VEHICLES LOADED', vehicles.value)
  } catch (e) {
    console.error('LOAD VEHICLES ERROR', e)
    error.value = 'Cannot load tracking data'
    vehicles.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  console.log('TRACKING ON MOUNTED')

  await loadVehicles()

  pollingTimer = window.setInterval(() => {
    loadVehicles()
  }, 30_000)
})

onBeforeUnmount(() => {
  if (pollingTimer) {
    window.clearInterval(pollingTimer)
  }
})

function getVehicleKey(vehicle: Vehicle): string {
  return String(
      vehicle.vehicle_id ||
      vehicle.id ||
      vehicle.plate_no
  )
}
function selectVehicle(vehicle: Vehicle) {
  selectedVehicleId.value = getVehicleKey(vehicle)
}

function statusSeverity(status: VehicleStatus) {
  return {
    running: 'success',
    idle: 'warn',
    parking: 'secondary',
    offline: 'danger',
    no_gps: 'contrast',
  }[status] || 'secondary'
}
function formatGpsTime(value?: string | null): string {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('th-TH', {
    dateStyle: 'short',
    timeStyle: 'medium',
    hour12: false,
  }).format(date)
}

function formatFuel(value?: number | string | null): string {
  if (value === null || value === undefined || value === '') return '-'

  return `${value}%`
}

</script>

<style scoped>
.tracking-page {
  flex: 1;              /* 👈 เปลี่ยนจาก height calc */
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 16px;
  min-height: 0;        /* 👈 สำคัญ */
}

.vehicle-panel {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  border: 1px solid var(--p-surface-700);
  background: var(--p-surface-900);
  overflow: hidden;
}

.vehicle-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.panel-header {
  padding: 18px;
  border-bottom: 1px solid var(--p-surface-700);
}

.panel-header h2 {
  margin: 0;
  font-size: 20px;
}

.panel-header p {
  margin: 4px 0 0;
  color: var(--p-text-muted-color);
}

.vehicle-item {
  width: 100%;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--p-text-color);
  text-align: left;
  cursor: pointer;
}

.vehicle-item:hover,
.vehicle-item.active {
  background: var(--p-surface-800);
  border-color: var(--p-surface-600);
}

.vehicle-item strong,
.vehicle-item span {
  display: block;
}

.vehicle-item span {
  margin-top: 4px;
  font-size: 13px;
  color: var(--p-text-muted-color);
}

.map-area {
  min-width: 0;
  height: 100%;
  display: flex;        /* 👈 เพิ่ม */
}

@media (max-width: 960px) {
  .tracking-page {
    grid-template-columns: 1fr;
  }
}

.loading-text {
  padding: 12px 18px;
  color: var(--p-text-muted-color);
  font-size: 14px;
}

.vehicle-stats {
  display: flex;
  gap: 10px;
  margin-top: 6px;
  font-size: 12px;
  color: var(--p-text-muted-color);
}

.vehicle-stats span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.gps-time {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  opacity: 0.8;
}

.fuel-bar {
  height: 4px;
  background: var(--p-surface-700);
  border-radius: 4px;
  margin-top: 4px;
  overflow: hidden;
}

.fuel-fill {
  height: 100%;
  background: #22c55e;
}

</style>