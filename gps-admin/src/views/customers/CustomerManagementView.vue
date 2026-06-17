<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Customer Management</h1>
      <Button label="Add Customer" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <InputText
          v-model="search"
          placeholder="Search by name..."
          @input="onSearch"
      />
    </div>

    <!-- Table -->
    <DataTable
        :value="customers"
        :loading="loading"
        striped-rows
        class="data-table"
    >
      <Column field="id" header="ID" style="width: 60px" />
      <Column field="name" header="Name" />
      <Column field="email" header="Email" />
      <Column field="phone" header="Phone" />
      <Column field="user_count" header="Users" style="width: 80px" />
      <Column field="tracker_count" header="Trackers" style="width: 90px" />
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
        <div class="empty-state">No customers found.</div>
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
        :header="editingCustomer ? 'Edit Customer' : 'Add Customer'"
        modal
        :style="{ width: '480px' }"
    >
      <div class="dialog-form">
        <div class="field">
          <label>Name *</label>
          <InputText v-model="form.name" fluid />
        </div>
        <div class="field">
          <label>Email</label>
          <InputText v-model="form.email" fluid />
        </div>
        <div class="field">
          <label>Phone</label>
          <InputText v-model="form.phone" fluid />
        </div>
        <div class="field">
          <label>Address</label>
          <InputText v-model="form.address" fluid />
        </div>
        <div class="field">
          <label>Map API Provider</label>
          <Select
              v-model="form.map_api"
              :options="mapApiOptions"
              option-label="label"
              option-value="value"
              placeholder="Select provider"
              show-clear
              fluid
          />
        </div>
        <div class="field">
          <label>Map API Key</label>
          <InputText v-model="form.map_api_key" fluid />
        </div>
        <div class="field-row">
          <label>Active</label>
          <ToggleSwitch v-model="form.is_active" />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
        <Button label="Save" :loading="saving" @click="saveCustomer" />
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
import { customersService, type Customer, type CustomerForm } from '@/services/customers'

const toast = useToast()
const confirm = useConfirm()

const customers = ref<Customer[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

const dialogVisible = ref(false)
const editingCustomer = ref<Customer | null>(null)
const form = ref<CustomerForm>({
  name: '',
  email: '',
  phone: '',
  address: '',
  map_api: null,
  map_api_key: '',
  is_active: true,
})

const mapApiOptions = [
  { label: 'Google Maps', value: 'google' },
  { label: 'OpenStreetMap', value: 'osm' },
  { label: 'Longdo Map', value: 'longdo' },
]

let searchTimer: ReturnType<typeof setTimeout>

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadCustomers()
  }, 400)
}

function onPageChange(event: { page: number }) {
  page.value = event.page + 1
  loadCustomers()
}

async function loadCustomers() {
  loading.value = true
  try {
    const res = await customersService.list({
      search: search.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    customers.value = res.data.data
    total.value = res.data.total ?? res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load customers', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  editingCustomer.value = null
  form.value = { name: '', email: '', phone: '', address: '', map_api: null, map_api_key: '', is_active: true }
  dialogVisible.value = true
}

function openEditDialog(customer: Customer) {
  editingCustomer.value = customer
  form.value = {
    name: customer.name,
    email: customer.email ?? '',
    phone: customer.phone ?? '',
    address: customer.address ?? '',
    map_api: customer.map_api ?? null,
    map_api_key: customer.map_api_key ?? '',
    is_active: customer.is_active !== false,
  }
  dialogVisible.value = true
}

async function saveCustomer() {
  if (!form.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Validation', detail: 'Name is required', life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editingCustomer.value) {
      await customersService.update(editingCustomer.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Updated', detail: 'Customer updated', life: 3000 })
    } else {
      await customersService.create(form.value)
      toast.add({ severity: 'success', summary: 'Created', detail: 'Customer created', life: 3000 })
    }
    dialogVisible.value = false
    loadCustomers()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save customer', life: 3000 })
  } finally {
    saving.value = false
  }
}

function confirmDelete(customer: Customer) {
  confirm.require({
    message: `Delete customer "${customer.name}"?`,
    header: 'Confirm Delete',
    icon: 'pi pi-trash',
    rejectProps: { label: 'Cancel', severity: 'secondary' },
    acceptProps: { label: 'Delete', severity: 'danger' },
    accept: async () => {
      try {
        await customersService.delete(customer.id)
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'Customer deleted', life: 3000 })
        loadCustomers()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete customer', life: 3000 })
      }
    },
  })
}

onMounted(loadCustomers)
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

.filter-bar { display: flex; gap: 12px; }

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
</style>
