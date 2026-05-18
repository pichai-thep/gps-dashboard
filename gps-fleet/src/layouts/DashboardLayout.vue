<template>
  <div class="layout">
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <div v-if="!sidebarCollapsed" class="logo-wrap">
<!--          <img-->
<!--              :src="brandLogo"-->
<!--              class="sidebar-logo"-->
<!--              alt="Brand Logo"-->
<!--              @error="onLogoError"-->
<!--          />-->

          <div class="logo">
            {{ brandName }}
          </div>

        </div>

        <Button
            icon="pi pi-bars"
            text
            rounded
            severity="secondary"
            @click="sidebarCollapsed = !sidebarCollapsed"
        />
      </div>

      <nav class="menu">
        <RouterLink to="/" class="menu-item">
          <i class="pi pi-chart-line"></i>
          <span v-if="!sidebarCollapsed">Dashboard</span>
        </RouterLink>

        <RouterLink to="/tracking" class="menu-item">
          <i class="pi pi-map"></i>
          <span v-if="!sidebarCollapsed">Tracking</span>
        </RouterLink>

        <RouterLink to="/history" class="menu-item">
          <i class="pi pi-history"></i>
          <span v-if="!sidebarCollapsed">History</span>
        </RouterLink>

        <RouterLink to="/vehicles" class="menu-item">
          <i class="pi pi-car"></i>
          <span v-if="!sidebarCollapsed">Vehicle Management</span>
        </RouterLink>


        <NotificationBell />

      </nav>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-title">
          <img
              :src="brandLogo"
              class="topbar-logo"
              alt="Brand Logo"
              @error="onLogoError"
          />

          <div>
            <div v-if="sidebarCollapsed" class="topbar-heading">{{ brandName }}</div>

            <div class="topbar-subtitle">{{ currentPageTitle }}</div>
          </div>
        </div>

        <div class="user-section">
          <div class="user-info">
            <div class="avatar">
              {{ userInitial }}
            </div>

            <div class="user-text">
              <div class="user-name">
                {{ serverName }} / {{ userName }}
              </div>

<!--              <div class="user-role">-->
<!--                host: {{ gpsConnection }} ({{ dbHost }}) role: {{ roleName }}-->
<!--              </div>-->
              <div class="user-role">
<!--                {{ gpsConnection }} ({{ dbEndpoint }}) · {{ roleName }}-->
                {{ gpsConnection }} · {{ roleName }}
              </div>

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
import { computed, ref, onMounted } from 'vue'
import Button from 'primevue/button'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import NotificationBell from "@/components/notifications/NotificationBell.vue";
const sidebarCollapsed = ref(true)

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const hostname = window.location.hostname


const brandLogo = ref(`/logos/${hostname}.png`)

const brandName = computed(() => {
  return hostname.split('.')[0].toUpperCase()
})

const user = computed(() => auth.user)
const userName = computed(() => {
  return user.value?.name || user.value?.username || 'User'
})

const serverName = computed(() => {
  return user.value?.server_name || 'unknown-server'
})

const roleName = computed(() => {
  return user.value?.roles?.join(', ') || user.value?.role || 'user'
})

const gpsConnection = computed(() => {
  return user.value?.gps_connection || '-'
})

const dbHost = computed(() => {
  return user.value?.db_host || '-'
})

const dbEndpoint = computed(() => {
  const host = user.value?.db_host || '-'
  const port = user.value?.db_port

  return port ? `${host}:${port}` : host
})

const userInitial = computed(() => {
  return userName.value.charAt(0).toUpperCase()
})

const currentPageTitle = computed(() => {
  const map: Record<string, string> = {
    '/': 'Dashboard',
    '/tracking': 'Live Tracking',
    '/history': 'History',
    '/vehicles': 'Vehicle Management',
    '/notifications': 'Notifications',
  }

  return map[route.path] || 'Fleet Command Center'
})

onMounted(async () => {
  if (auth.token && !auth.user) {
    await auth.fetchMe()
    console.log('onMount Dashboard layout')
  }
})

async function logout() {
  await auth.logout()
  router.push('/login')
}

function onLogoError(event: Event) {
  const target = event.target as HTMLImageElement

  if (!target.dataset.fallback) {
    target.dataset.fallback = 'true'
    target.src = '/brands/default.png'
  }
}


</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
  background: #020617;
}

.sidebar {
  width: 240px;
  flex-shrink: 0;
  padding: 16px;
  overflow: hidden;
  background: #111827;
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  transition: width 0.2s ease;
}

.sidebar.collapsed {
  width: 72px;
  padding: 16px 10px;
}

.sidebar-header {
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 16px;
}

.logo {
  font-size: 20px;
  font-weight: 800;
  color: #fff;
  letter-spacing: 0.3px;
  white-space: nowrap;
}

.logo-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow: hidden;
}

.sidebar-logo {
  width: 40px;
  object-fit: contain;
  border-radius: 2px;
  background: #fff;
  padding: 1px;
  flex-shrink: 0;
}

.menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.menu-item {
  height: 44px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 12px;
  border-radius: 12px;
  color: #e5e7eb;
  text-decoration: none;
  white-space: nowrap;
}

.sidebar.collapsed .menu-item {
  justify-content: center;
  padding: 0;
}

.menu-item:hover,
.menu-item.router-link-active {
  background: rgba(255, 255, 255, 0.08);
}

.menu-item i {
  font-size: 18px;
  flex-shrink: 0;
}

.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0;
}

.topbar {
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  background: #0f172a;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.28);
  z-index: 10;
}

.topbar-title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.topbar-logo {
  width: 80px;
  height: 60px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  font-weight: 900;
  color: #052e16;
  background: linear-gradient(135deg, #34d399, #22c55e);
}

.topbar-heading {
  color: #fff;
  font-size: 17px;
  font-weight: 800;
  line-height: 1.1;
}

.topbar-subtitle {
  margin-top: 4px;
  color: #9ca3af;
  font-size: 12px;
}

.content {
  flex: 1;
  display: block;
  min-width: 0;
  min-height: 0;
  overflow: auto;
  padding: 16px;
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
  color: #052e16;
  background: linear-gradient(135deg, #34d399, #22c55e);
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
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>