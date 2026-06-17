<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">User Management</h1>
      <Button label="Add User" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <InputText
          v-model="search"
          placeholder="Search by name or username..."
          @input="onSearch"
      />
      <Select
          v-model="filterCustomer"
          :options="customerOptions"
          option-label="label"
          option-value="value"
          placeholder="All Customers"
          show-clear
          @change="loadUsers"
      />
    </div>

    <!-- Table -->
    <DataTable
        :value="users"
        :loading="loading"
        striped-rows
        class="data-table"
    >
      <Column field="id" header="ID" style="width: 60px" />
      <Column field="name" header="Name" />
      <Column field="username" header="Username" />
      <Column field="email" header="Email" />
      <Column field="customer_name" header="Customer" />
      <Column field="role" header="Role">
        <template #body="{ data }">
          <Tag :value="data.role || 'user'" />
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
        <div class="empty-state">No users found.</div>
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

    <!-- Create / Edit Dialog -->
    <Dialog
        v-model:visible="dialogVisible"
        :header="editingUser ? 'Edit User' : 'Add User'"
        modal
        :style="{ width: '440px' }"
    >
      <div class="dialog-form">
        <div class="field">
          <label>Name</label>
          <InputText v-model="form.name" fluid />
        </div>
        <div class="field">
          <label>Username</label>
          <InputText v-model="form.username" fluid />
        </div>
        <div class="field">
          <label>Email</label>
          <InputText v-model="form.email" fluid />
        </div>
        <div class="field" v-if="!editingUser">
          <label>Password</label>
          <Password v-model="form.password" :feedback="false" fluid toggleMask />
        </div>
        <div class="field">
          <label>Role</label>
          <Select
              v-model="form.role"
              :options="roleOptions"
              option-label="label"
              option-value="value"
              fluid
          />
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
      </div>

      <template #footer>
        <Button label="Cancel" severity="secondary" @click="dialogVisible = false" />
        <Button label="Save" :loading="saving" @click="saveUser" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { usersService, type User, type UserForm } from '@/services/users'
import { customersService } from '@/services/customers'

const toast = useToast()
const confirm = useConfirm()

const users = ref<User[]>([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const filterCustomer = ref<string | number | null>(null)
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

const dialogVisible = ref(false)
const editingUser = ref<User | null>(null)
const form = ref<UserForm>({
  name: '',
  username: '',
  email: '',
  password: '',
  role: 'user',
  customer_id: undefined,
})

const roleOptions = [
  { label: 'Admin', value: 'admin' },
  { label: 'User', value: 'user' },
  { label: 'Viewer', value: 'viewer' },
]

const customerOptions = ref<{ label: string; value: string | number }[]>([])

let searchTimer: ReturnType<typeof setTimeout>

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadUsers()
  }, 400)
}

function onPageChange(event: { page: number }) {
  page.value = event.page + 1
  loadUsers()
}

async function loadUsers() {
  loading.value = true
  try {
    const res = await usersService.list({
      search: search.value || undefined,
      customer_id: filterCustomer.value || undefined,
      page: page.value,
      per_page: perPage.value,
    })
    users.value = res.data.data
    total.value = res.data.total ?? res.data.data.length
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load users', life: 3000 })
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
  editingUser.value = null
  form.value = { name: '', username: '', email: '', password: '', role: 'user', customer_id: undefined }
  dialogVisible.value = true
}

function openEditDialog(user: User) {
  editingUser.value = user
  form.value = {
    name: user.name,
    username: user.username,
    email: user.email ?? '',
    role: user.role ?? 'user',
    customer_id: user.customer_id,
  }
  dialogVisible.value = true
}

async function saveUser() {
  saving.value = true
  try {
    if (editingUser.value) {
      await usersService.update(editingUser.value.id, form.value)
      toast.add({ severity: 'success', summary: 'Updated', detail: 'User updated', life: 3000 })
    } else {
      await usersService.create(form.value)
      toast.add({ severity: 'success', summary: 'Created', detail: 'User created', life: 3000 })
    }
    dialogVisible.value = false
    loadUsers()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save user', life: 3000 })
  } finally {
    saving.value = false
  }
}

function confirmDelete(user: User) {
  confirm.require({
    message: `Delete user "${user.name || user.username}"?`,
    header: 'Confirm Delete',
    icon: 'pi pi-trash',
    rejectProps: { label: 'Cancel', severity: 'secondary' },
    acceptProps: { label: 'Delete', severity: 'danger' },
    accept: async () => {
      try {
        await usersService.delete(user.id)
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'User deleted', life: 3000 })
        loadUsers()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete user', life: 3000 })
      }
    },
  })
}

onMounted(() => {
  loadUsers()
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

.filter-bar {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-bar :deep(.p-inputtext),
.filter-bar :deep(.p-select) {
  min-width: 200px;
}

.data-table { background: #0f172a; border-radius: 12px; }

.row-actions { display: flex; gap: 4px; }

.empty-state {
  text-align: center;
  color: #64748b;
  padding: 32px;
}

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
</style>
