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

<!--      <nav class="menu">-->
<!--        <RouterLink to="/" class="menu-item">-->
<!--          <i class="pi pi-chart-line" title="Fleet Dashboard"></i>-->
<!--          <span v-if="!sidebarCollapsed">Dashboard</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/tracking" class="menu-item">-->
<!--          <i class="pi pi-map" title="Current tracking"></i>-->
<!--          <span v-if="!sidebarCollapsed">Tracking</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/history" class="menu-item">-->
<!--          <i class="pi pi-history" title="History Query"></i>-->
<!--          <span v-if="!sidebarCollapsed">History</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/vehicles" class="menu-item">-->
<!--          <i class="pi pi-car" title="Manage Vehicles"></i>-->
<!--          <span v-if="!sidebarCollapsed">Vehicle Management</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/stations" class="menu-item">-->
<!--          <i class="pi pi-warehouse" title="Manage Stations"></i>-->
<!--          <span v-if="!sidebarCollapsed">Station Management</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/pois" class="menu-item">-->
<!--          <i class="pi pi-map-marker" title="Manage POIs"></i>-->
<!--          <span v-if="!sidebarCollapsed">POI Management</span>-->
<!--        </RouterLink>-->

<!--        <RouterLink to="/forbidden-zones" class="menu-item">-->
<!--          <i class="pi pi-ban" title="Manage Forbidden Zones"></i>-->
<!--          <span v-if="!sidebarCollapsed">Forbidden Zone Management</span>-->
<!--        </RouterLink>-->

<!--        <NotificationBell title="Notification Messages" />-->

<!--      </nav>-->

      <PanelMenu
          v-if="!sidebarCollapsed && !isMobileLayout"
          v-model:expandedKeys="expandedKeys"
          :model="menuItems"
          class="sidebar-panel-menu"
      />

<!--      <PanelMenu-->
<!--          v-if="!sidebarCollapsed"-->
<!--          v-model:expandedKeys="expandedKeys"-->
<!--          :model="menuItems"-->
<!--          class="sidebar-panel-menu"-->
<!--      >-->

<!--        <template #item="{ item }">-->

<!--          &lt;!&ndash; notification &ndash;&gt;-->
<!--          <div-->
<!--              v-if="item.notification"-->
<!--              class="notification-menu-wrap"-->
<!--          >-->
<!--            <NotificationBell title="Notification Messages" />-->
<!--          </div>-->

<!--          &lt;!&ndash; normal menu &ndash;&gt;-->
<!--          <a-->
<!--              v-else-->
<!--              class="p-menuitem-link"-->
<!--              @click="item.command && item.command()"-->
<!--          >-->
<!--            <span :class="item.icon" class="p-menuitem-icon"></span>-->

<!--            <span class="p-menuitem-text">-->
<!--              {{ item.label }}-->
<!--            </span>-->
<!--          </a>-->

<!--        </template>-->

<!--      </PanelMenu>-->


      <div v-if="sidebarCollapsed || isMobileLayout" class="collapsed-menu">

        <button :class="{ active: route.path === '/' }" @click="router.push('/')">
          <i class="pi pi-chart-line"></i>
        </button>

        <button :class="{ active: isActive('/tracking') }" @click="router.push('/tracking')">
          <i class="pi pi-map"></i>
        </button>

        <button :class="{ active: isActive('/history') }" @click="router.push('/history')">
          <i class="pi pi-history"></i>
        </button>

        <button :class="{ active: isActive('/vehicles') }" @click="router.push('/vehicles')">
          <i class="pi pi-car"></i>
        </button>

        <button :class="{ active: isActive('/stations') }" @click="router.push('/stations')">
          <i class="pi pi-warehouse"></i>
        </button>

        <button :class="{ active: isActive('/pois') }" @click="router.push('/pois')">
          <i class="pi pi-map-marker"></i>
        </button>

        <button :class="{ active: isActive('/forbidden-zones') }" @click="router.push('/forbidden-zones')">
          <i class="pi pi-ban"></i>
        </button>

        <button
            :class="{ active: isActive('/reports') }"
            :title="t('reports')"
            @click="openReportsMenu"
        >
          <i class="pi pi-chart-bar"></i>
        </button>

      </div>

      <div
          v-if="isMobileLayout && mobileReportsOpen"
          class="mobile-report-menu"
      >
        <button
            :class="{ active: isActive('/reports/daily-summary') }"
            type="button"
            @click="goReport('/reports/daily-summary')"
        >
          {{ t('daily') }}
        </button>

        <button
            :class="{ active: isActive('/reports/status-summary') }"
            type="button"
            @click="goReport('/reports/status-summary')"
        >
          {{ t('status') }}
        </button>

        <button
            :class="{ active: isActive('/reports/station-summary') }"
            type="button"
            @click="goReport('/reports/station-summary')"
        >
          {{ t('station') }}
        </button>
      </div>



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
            <div v-if="sidebarCollapsed || isMobileLayout" class="topbar-heading">{{ brandName }}</div>

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

          <div class="topbar-notification">
            <NotificationBell :title="t('notificationsTitle')" />
          </div>

          <div class="language-switch" :title="t('language')">
            <button
                type="button"
                :class="{ active: locale === 'th' }"
                @click="setLocale('th')"
            >
              <span class="flag-icon flag-th"></span>
              <span>Thai</span>
            </button>

            <button
                type="button"
                :class="{ active: locale === 'en' }"
                @click="setLocale('en')"
            >
              <span class="flag-icon flag-en"></span>
              <span>US</span>
            </button>
          </div>

          <Button
              :label="t('logout')"
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
import { computed, ref, onBeforeUnmount, onMounted } from 'vue'
import Button from 'primevue/button'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import PanelMenu from 'primevue/panelmenu'
import NotificationBell from "@/components/notifications/NotificationBell.vue";
import { useI18n } from '@/i18n'

function isActive(path: string) {
  return route.path === path || route.path.startsWith(path + '/')
}

const expandedKeys = ref<Record<string, boolean>>({
  reports: true,
})

const { locale, setLocale, t } = useI18n()

const menuItems = computed(() => [
  {
    label: t('dashboard'),
    icon: 'pi pi-chart-line',
    styleClass: route.path === '/' ? 'active-menu' : '',
    command: () => router.push('/'),
  },
  {
    label: t('tracking'),
    icon: 'pi pi-map',
    styleClass: isActive('/tracking') ? 'active-menu' : '',
    command: () => router.push('/tracking'),
  },
  {
    label: t('history'),
    icon: 'pi pi-history',
    styleClass: isActive('/history') ? 'active-menu' : '',
    command: () => router.push('/history'),
  },
  {
    label: t('myVehicles'),
    icon: 'pi pi-car',
    styleClass: isActive('/vehicles') ? 'active-menu' : '',
    command: () => router.push('/vehicles'),
  },
  {
    label: t('myStations'),
    icon: 'pi pi-warehouse',
    styleClass: isActive('/stations') ? 'active-menu' : '',
    command: () => router.push('/stations'),
  },
  {
    label: t('myPois'),
    icon: 'pi pi-map-marker',
    styleClass: isActive('/pois') ? 'active-menu' : '',
    command: () => router.push('/pois'),
  },
  {
    label: t('forbiddenZones'),
    icon: 'pi pi-ban',
    styleClass: isActive('/forbidden-zones') ? 'active-menu' : '',
    command: () => router.push('/forbidden-zones'),
  },
  {
    key: 'reports',
    label: t('reports'),
    icon: 'pi pi-chart-bar',
    items: [
      {
        label: t('dailySummary'),
        icon: 'pi pi-calendar',
        styleClass: isActive('/reports/daily-summary') ? 'active-menu' : '',
        command: () => router.push('/reports/daily-summary'),
      },
      {
        label: t('statusTimeline'),
        icon: 'pi pi-clock',
        styleClass: isActive('/reports/status-summary') ? 'active-menu' : '',
        command: () => router.push('/reports/status-summary'),
      },
      {
        label: t('stationVisit'),
        icon: 'pi pi-warehouse',
        styleClass: isActive('/reports/station-summary') ? 'active-menu' : '',
        command: () => router.push('/reports/station-summary'),
      },
    ],
  },


])

const sidebarCollapsed = ref(false)
const isMobileLayout = ref(false)
const mobileReportsOpen = ref(false)
let mobileQuery: MediaQueryList | null = null

function handleMobileLayoutChange(event: MediaQueryListEvent) {
  isMobileLayout.value = event.matches

  if (!event.matches) {
    mobileReportsOpen.value = false
  }
}

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
    '/': t('dashboard'),
    '/tracking': t('liveTracking'),
    '/history': t('historyTracking'),
    '/vehicles': t('vehicleManagement'),
    '/notifications': t('notifications'),
    '/stations': t('stationManagement'),
    '/pois': t('pois'),
    '/forbidden-zones': t('forbiddenZoneManagement'),
    '/reports/daily-summary': t('dailySummaryReport'),
    '/reports/status-summary': t('statusTimelineReport'),
    '/reports/station-summary': t('stationVisitReport'),
  }

  return map[route.path] || t('fleetCommandCenter')
})

onMounted(async () => {
  mobileQuery = window.matchMedia('(max-width: 768px)')
  isMobileLayout.value = mobileQuery.matches

  mobileQuery.addEventListener('change', handleMobileLayoutChange)

  if (auth.token && !auth.user) {
    await auth.fetchMe()
  }
})

onBeforeUnmount(() => {
  if (!mobileQuery) return

  mobileQuery.removeEventListener('change', handleMobileLayoutChange)
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

function openReportsMenu() {
  if (isMobileLayout.value) {
    mobileReportsOpen.value = !mobileReportsOpen.value
    return
  }

  sidebarCollapsed.value = false
  expandedKeys.value = {
    ...expandedKeys.value,
    reports: true,
  }
}

function goReport(path: string) {
  mobileReportsOpen.value = false
  router.push(path)
}

</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
  background: #020617;
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
  background: #2563eb;
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
  min-height: calc(100vh - 72px);
}

.collapsed-menu button {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 12px;
  background: transparent;
  color: #cbd5e1;
  cursor: pointer;
}

.collapsed-menu button:hover {
  background: #1f2937;
  color: #ffffff;
}

.collapsed-menu button.active {
  background: #2563eb;
  color: #ffffff;
}

.sidebar {
  width: 210px;
  flex-shrink: 0;
  padding: 10px;
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
  height: 40px;
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
  height: 90px;
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
  width: 100px;
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

.topbar-notification {
  display: flex;
  align-items: center;
}

.topbar-notification :deep(.sidebar-bell-btn) {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  font-size: 20px;
}

.topbar-notification :deep(.sidebar-badge) {
  top: 4px;
  right: 4px;
}

.language-switch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.language-switch button {
  height: 34px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 8px;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.95);
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.language-switch button.active {
  border-color: rgba(34, 197, 94, 0.58);
  background: rgba(34, 197, 94, 0.16);
  color: #bbf7d0;
}

.flag-icon {
  width: 22px;
  height: 22px;
  flex: 0 0 22px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.6);
  box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.45);
}

.flag-th {
  background: linear-gradient(
      to bottom,
      #da291c 0 16.66%,
      #ffffff 16.66% 33.33%,
      #2d2a4a 33.33% 66.66%,
      #ffffff 66.66% 83.33%,
      #da291c 83.33% 100%
  );
}

.flag-en {
  background:
      radial-gradient(circle at 18% 18%, #ffffff 0 1px, transparent 1.3px),
      radial-gradient(circle at 36% 18%, #ffffff 0 1px, transparent 1.3px),
      radial-gradient(circle at 54% 18%, #ffffff 0 1px, transparent 1.3px),
      radial-gradient(circle at 27% 34%, #ffffff 0 1px, transparent 1.3px),
      radial-gradient(circle at 45% 34%, #ffffff 0 1px, transparent 1.3px),
      linear-gradient(#3c3b6e 0 54%, transparent 54%),
      repeating-linear-gradient(
          to bottom,
          #b22234 0 7.69%,
          #ffffff 7.69% 15.38%
      );
  background-size:
      100% 100%,
      100% 100%,
      100% 100%,
      100% 100%,
      100% 100%,
      56% 54%,
      100% 100%;
  background-repeat: no-repeat;
  background-position: left top;
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

:deep(.sidebar-panel-menu .p-panelmenu-content) {
  background: transparent;
  border: none;
  padding: 4px 0 6px 0;
}

:deep(.sidebar-panel-menu .p-panelmenu-submenu) {
  display: block;
  padding-left: 8px;
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
}

.collapsed-menu button:hover {
  background: #1f2937;
  color: #ffffff;
}

:deep(.sidebar-panel-menu .active-menu > .p-menuitem-content) {
  background: #2563eb;
}

:deep(.sidebar-panel-menu .active-menu > .p-menuitem-content .p-menuitem-link) {
  color: #ffffff;
}

:deep(.sidebar-panel-menu .active-menu .p-menuitem-icon) {
  color: #ffffff;
}

.collapsed-menu button.active {
  background: #2563eb;
  color: #ffffff;
}

.mobile-report-menu {
  display: none;
}

@media (max-width: 768px) {
  .layout {
    flex-direction: column;
    min-height: 100dvh;
  }

  .sidebar,
  .sidebar.collapsed {
    width: 100%;
    padding: 8px 10px;
    border-right: 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  }

  .sidebar-header {
    height: 34px;
    margin-bottom: 8px;
  }

  .logo {
    font-size: 16px;
  }

  .collapsed-menu {
    flex-direction: row;
    align-items: center;
    gap: 6px;
    min-height: 0;
    overflow-x: auto;
    padding-bottom: 2px;
    scrollbar-width: none;
  }

  .collapsed-menu::-webkit-scrollbar {
    display: none;
  }

  .collapsed-menu button {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
  }

  .mobile-report-menu {
    display: flex;
    gap: 6px;
    padding: 8px 2px 2px;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .mobile-report-menu::-webkit-scrollbar {
    display: none;
  }

  .mobile-report-menu button {
    flex: 0 0 auto;
    height: 32px;
    padding: 0 12px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 999px;
    background: #0f172a;
    color: #cbd5e1;
    font-size: 12px;
    font-weight: 800;
  }

  .mobile-report-menu button.active {
    background: #2563eb;
    border-color: #3b82f6;
    color: #ffffff;
  }

  .topbar {
    height: auto;
    min-height: 64px;
    gap: 10px;
    padding: 8px 12px;
    flex-wrap: wrap;
  }

  .topbar-logo {
    display: none;
  }

  .topbar-title {
    flex: 1 1 100%;
    min-width: 0;
  }

  .topbar-heading,
  .topbar-subtitle {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .topbar-heading {
    max-width: 100%;
    font-size: 14px;
  }

  .topbar-subtitle {
    max-width: 100%;
  }

  .user-section {
    width: 100%;
    gap: 8px;
    margin-left: 0;
  }

  .user-info {
    display: flex;
    flex: 1;
    min-width: 0;
    gap: 8px;
    padding: 4px 8px;
    border-radius: 12px;
  }

  .avatar {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    font-size: 12px;
  }

  .user-text {
    min-width: 0;
  }

  .user-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .user-role {
    display: none;
  }

  .topbar-notification :deep(.sidebar-bell-btn) {
    width: 40px;
    height: 40px;
    font-size: 18px;
  }

  .user-section :deep(.p-button-label) {
    display: none;
  }

  .content {
    padding: 10px;
  }
}


</style>
