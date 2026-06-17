<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Tracker Management</h1>
      <Button label="Add Tracker" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <InputText
          v-model="search"
          placeholder="Search by IMEI or name..."
          @input="onSearch"
      />
      <Select
          v-model="filterCustomer"
          :options="customerOptions"
          option-label="label"
          option-value="value"
          placeholder="All Customers"
          show-clear
          @change="loadTrackers"
      />
    </div>

    <!-- Table -->
    <DataTable
        :value="trackers"
        :loading="loading"
        striped-rows
        class="data-table"
    >
      <Column field="id" header="ID" style="width: 60px" />
      <Column field="imei" header="IMEI" />
      <Column field="serial" header="Serial" />
      <Column field="name" header="Name" />
      <Column field="model" header="Model" />
      <Column field="sim_number" header="SIM" />
      <Column field="customer_name" header="Customer" />
      <Column field="vehicle_name" header="Vehicle" />
      <Column field="last_seen_at" header="Last Seen">
        <template #body="{ data }">
          <span :class="lastSeenClass(data.last_seen_at)">
            {{ formatLastSeen(data.last_seen_at) }}
          </span>
        </template>
      </Column>
      <Column field="is_active" header="Status">
        <template #body="{ data }">
          <Tag
              :value="data.is_active !== false ? 'Active' : 'Inactive'"
              :severity="data.is_active !== false ? 'success' : 'danger'"
          />
        </template>
      </Column>
      <Column header="Actions" style="width: 120px">
        <template #body="{ data }">
          <div class="row-actions">
            <Button
                icon="pi pi-pencil"
                size="small"
                text
                rounded
                @click="openEditDialog(data)"
            />
            <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>

      <template #empty>
        <div class="empty-state">No trackers found.</div>
      </template>
    </DataTable>

    <!-- Paginator -->
    <Paginator
        v-if="total > perPage"
        :rows="perPage"
        :total-records="total"
        :first="(page - 1) * perPage"
        @page="onPageChange"
    />

    <!-- Dialog -->
    <Dialog
        v-model:visible="dialogVisible"
        :header="editingTracker ? 'Edit Tracker' : 'Add Tracker'"
        modal
        :style="{ width: '440px' }"
    >
      <div class="dialog-form">
        <div class="field">
          <label>IMEI *</label>
          <InputText v-model="form.imei" :disabled="!!editingTracker" fluid />
        </div>
        <div class="field">
          <label>Serial</label>
          <InputText v-model="form.serial" fluid />
        </div>
        <div class="field">
          <label>Name</label>
          <InputText v-model="form.name" fluid />
        </div>
        <div class="field">
          <label>Model</label>
          <InputText v-model="form.model" fluid />
        </div>
        <div class="field">
          <label>SIM Number</label>
          <InputText v-model="form.sim_number" fluid />
        </div>
        <div class="field">
          <label>Customer</label>
          <Select
              v-model="form.customer_id"
              :options="customerOptions"
              option-label="label"
              option-value="value"
              placeholder="Select customer"
              show-clear
              fluid
          />
        </div>
        <div class="field-row">
          <label>Active</label>
          <ToggleSwitch v-model="form.is_active" />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
        <Button label="Save" :loading="saving" @click="saveTracker" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { trackersService, type Tracker, type TrackerForm } from '@/services/trackers'
import { customersService } from '@/services/customers'

const toast = useToast()
const confirm = useConfirm()

const trackers = ref<Tracker[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const filterCustomer = ref<string | number | null>(null)
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

const dialogVisible = ref(false)
const editingTracker = ref<Tracker | null>(null)
const form = ref<TrackerForm>({
  imei: '',
  serial: '',
  name: '',
  model: '',
  sim_number: '',
  customer_id: undefined,
  is_active: true,
})

const customerOptions = ref<{ label: string; value: string | number }[]>([])

let searchTimer: ReturnType<typeof setTimeout>

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadTrackers()
  }, 400)
}

function onPageChange(event: { page: number }) {
  page.value = event.page + 1
  loadTrackers()
}

async function loadTrackers() {
  loading.value = true
  try {
    const res = await trackersService.list({
      search: search.value || undefined,
      customer_id: filterCustomer.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    trackers.value = res.data.data
    total.value = res.data.total ?? res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load trackers', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function loadCustomers() {
  try {
    const res = await customersService.list({ per_page: 999 })
    customerOptions.value = res.data.data.map((c) => ({
      label: c.name,
      value: c.id,
    }))
  } catch {
    // ignore
  }
}

function openCreateDialog() {
  editingTracker.value = null
  form.value = { imei: '', serial: '', name: '', model: '', sim_number: '', customer_id: undefined, is_active: true }
  dialogVisible.value = true
}

function openEditDialog(tracker: Tracker) {
  editingTracker.value = tracker
  form.value = {
    imei: tracker.imei,
    serial: tracker.serial ?? '',
    name: tracker.name ?? '',
    model: tracker.model ?? '',
    sim_number: tracker.sim_number ?? '',
    customer_id: tracker.customer_id,
    is_active: tracker.is_active !== false,
  }
  dialogVisible.value = true
}

async function saveTracker() {
  if (!form.value.imei.trim()) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'IMEI is required', life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editingTracker.value) {
      await trackersService.update(editingTracker.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Updated', detail: 'Tracker updated', life: 3000 })
    } else {
      await trackersService.create(form.value)
      toast.add({ severity: 'success', summary: 'Created', detail: 'Tracker created', life: 3000 })
    }
    dialogVisible.value = false
    loadTrackers()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save tracker', life: 3000 })
  } finally {
    saving.value = false
  }
}

function confirmDelete(tracker: Tracker) {
  confirm.require({
    message: `Delete tracker "${tracker.imei}"?`,
    header: 'Confirm Delete',
    icon: 'pi pi-trash',
    rejectProps: { label: 'Cancel', severity: 'secondary' },
    acceptProps: { label: 'Delete', severity: 'danger' },
    accept: async () => {
      try {
        await trackersService.delete(tracker.id)
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'Tracker deleted', life: 3000 })
        loadTrackers()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete tracker', life: 3000 })
      }
    },
  })
}

function formatLastSeen(val: string | null | undefined) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleString('th-TH', { timeZone: 'Asia/Bangkok' })
}

function lastSeenClass(val: string | null | undefined) {
  if (!val) return 'text-muted'
  const diff = Date.now() - new Date(val).getTime()
  if (diff < 5 * 60 * 1000) return 'text-online'
  if (diff < 60 * 60 * 1000) return 'text-warn'
  return 'text-muted'
}

onMounted(() => {
  loadTrackers()
  loadCustomers()
})
</script>

<style scoped>
.page { display: flex; flex-direction: column; gap: 16px; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  color: #f1f5f9;
}

.filter-bar { display: flex; gap: 12px; flex-wrap: wrap; }

.data-table { background: #0f172a; border-radius: 12px; }

.row-actions { display: flex; gap: 4px; }

.empty-state { text-align: center; color: #64748b; padding: 32px; }

.dialog-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 4px 0;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field label {
  font-size: 13px;
  font-weight: 600;
  color: #94a3b8;
}

.field-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.field-row label {
  font-size: 13px;
  font-weight: 600;
  color: #94a3b8;
}

.text-online { color: #34d399; font-size: 12px; }
.text-warn { color: #fbbf24; font-size: 12px; }
.text-muted { color: #64748b; font-size: 12px; }
</style>
