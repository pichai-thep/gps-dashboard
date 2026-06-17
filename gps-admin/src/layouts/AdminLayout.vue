<template>
  <div class="layout">
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <div v-if="!sidebarCollapsed" class="logo">GPS Admin</div>
        <Button
            icon="pi pi-bars"
            text
            rounded
            severity="secondary"
            @click="sidebarCollapsed = !sidebarCollapsed"
        />
      </div>

      <PanelMenu
          v-if="!sidebarCollapsed"
          v-model:expandedKeys="expandedKeys"
          :model="menuItems"
          class="sidebar-panel-menu"
      />

      <div v-else class="collapsed-menu">
        <button :class="{ active: route.path === '/' }" @click="router.push('/')" title="Dashboard">
          <i class="pi pi-home"></i>
        </button>
        <button :class="{ active: isActive('/users') }" @click="router.push('/users')" title="User Management">
          <i class="pi pi-users"></i>
        </button>
        <button :class="{ active: isActive('/customers') }" @click="router.push('/customers')" title="Customer Management">
          <i class="pi pi-building"></i>
        </button>
        <button :class="{ active: isActive('/trackers') }" @click="router.push('/trackers')" title="Tracker Management">
          <i class="pi pi-wifi"></i>
        </button>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-title">
          <div class="topbar-subtitle">{{ currentPageTitle }}</div>
        </div>

        <div class="user-section">
          <div class="user-info">
            <div class="avatar">{{ auth.userInitial }}</div>
            <div class="user-text">
              <div class="user-name">{{ auth.userName }}</div>
              <div class="user-role">{{ auth.roleName }}</div>
            </div>
          </div>

          <Button
              label="Logout"
              icon="pi pi-sign-out"
              severity="secondary"
              @click="logout"
          />
        </div>
      </header>

      <main class="content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import PanelMenu from 'primevue/panelmenu'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const sidebarCollapsed = ref(false)
const expandedKeys = ref<Record<string, boolean>>({})

function isActive(path: string) {
  return route.path === path || route.path.startsWith(path + '/')
}

const menuItems = computed(() => [
  {
    label: 'Dashboard',
    icon: 'pi pi-home',
    styleClass: route.path === '/' ? 'active-menu' : '',
    command: () => router.push('/'),
  },
  {
    label: 'User Management',
    icon: 'pi pi-users',
    styleClass: isActive('/users') ? 'active-menu' : '',
    command: () => router.push('/users'),
  },
  {
    label: 'Customer Management',
    icon: 'pi pi-building',
    styleClass: isActive('/customers') ? 'active-menu' : '',
    command: () => router.push('/customers'),
  },
  {
    label: 'Tracker Management',
    icon: 'pi pi-wifi',
    styleClass: isActive('/trackers') ? 'active-menu' : '',
    command: () => router.push('/trackers'),
  },
])

const currentPageTitle = computed(() => {
  const map: Record<string, string> = {
    '/': 'Dashboard',
    '/users': 'User Management',
    '/customers': 'Customer Management',
    '/trackers': 'Tracker Management',
  }
  return map[route.path] || 'GPS Admin'
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
  background: #020617;
}

.sidebar {
  width: 220px;
  flex-shrink: 0;
  padding: 10px;
  background: #111827;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  transition: width 0.2s ease;
}

.sidebar.collapsed {
  width: 72px;
  padding: 16px 10px;
}

.sidebar-header {
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 16px;
}

.logo {
  font-size: 18px;
  font-weight: 800;
  color: #f59e0b;
  letter-spacing: 0.3px;
  white-space: nowrap;
}

.sidebar-panel-menu {
  border: none;
  background: transparent;
}

:deep(.sidebar-panel-menu .p-panelmenu-panel) {
  background: transparent;
  border: none;
  margin-bottom: 4px;
}

:deep(.sidebar-panel-menu .p-panelmenu-header-content) {
  background: transparent;
  border: none;
  border-radius: 10px;
}

:deep(.sidebar-panel-menu .p-panelmenu-header-link) {
  color: #cbd5e1;
  padding: 11px 12px;
  border-radius: 10px;
}

:deep(.sidebar-panel-menu .p-panelmenu-header-link:hover) {
  background: #1f2937;
  color: #ffffff;
}

:deep(.sidebar-panel-menu .p-menuitem-link) {
  color: #cbd5e1;
  padding: 10px 12px 10px 28px;
  border-radius: 10px;
}

:deep(.sidebar-panel-menu .p-menuitem-link:hover) {
  background: #1f2937;
  color: #ffffff;
}

:deep(.sidebar-panel-menu .p-menuitem-icon),
:deep(.sidebar-panel-menu .p-panelmenu-header-icon) {
  color: #94a3b8;
}

:deep(.sidebar-panel-menu .active-menu > .p-menuitem-content) {
  background: #d97706;
}

:deep(.sidebar-panel-menu .active-menu > .p-menuitem-content .p-menuitem-link) {
  color: #ffffff;
}

:deep(.sidebar-panel-menu .active-menu .p-menuitem-icon) {
  color: #ffffff;
}

.collapsed-menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
}

.collapsed-menu button {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 12px;
  background: transparent;
  color: #cbd5e1;
  cursor: pointer;
  font-size: 16px;
}

.collapsed-menu button:hover {
  background: #1f2937;
  color: #ffffff;
}

.collapsed-menu button.active {
  background: #d97706;
  color: #ffffff;
}

.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.topbar {
  height: 64px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  background: #0f172a;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.28);
  z-index: 10;
}

.topbar-subtitle {
  color: #e2e8f0;
  font-size: 16px;
  font-weight: 700;
}

.content {
  flex: 1;
  overflow: auto;
  padding: 20px;
  background: #020617;
}

.user-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 10px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 800;
  color: #451a03;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.user-text {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.user-name {
  color: #fff;
  font-size: 13px;
  font-weight: 700;
}

.user-role {
  margin-top: 4px;
  color: #9ca3af;
  font-size: 11px;
  font-family: ui-monospace, monospace;
}
</style>
