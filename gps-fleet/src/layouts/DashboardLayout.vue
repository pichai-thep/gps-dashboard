<template>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">GPS Fleet</div>

      <nav class="menu">
        <RouterLink to="/" class="menu-item">Dashboard</RouterLink>
        <RouterLink to="/tracking" class="menu-item">Tracking</RouterLink>
        <RouterLink to="/history" class="menu-item">History</RouterLink>
        <RouterLink to="/notifications" class="menu-item">Notifications</RouterLink>
      </nav>
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <strong>Fleet Command Center</strong>
          <div class="sub">fleet.gpsthaistar.com</div>
        </div>

        <Button
            label="Logout"
            icon="pi pi-sign-out"
            severity="secondary"
            @click="logout"
        />
      </header>

      <main class="content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

console.log('DASHBOARD LAYOUT MOUNTED')

const router = useRouter()
const auth = useAuthStore()

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 240px;
  background: var(--p-surface-900);
  border-right: 1px solid var(--p-surface-700);
  padding: 20px;
}

.logo {
  font-size: 20px;
  font-weight: 800;
  margin-bottom: 24px;
}

.menu {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.menu-item {
  padding: 12px;
  border-radius: 10px;
  color: var(--p-text-color);
  text-decoration: none;
}

.menu-item:hover,
.menu-item.router-link-active {
  background: var(--p-surface-800);
}

.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 0; /* 👈 สำคัญมาก */
}

.topbar {
  height: 72px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid var(--p-surface-700);
  background: var(--p-surface-900);
}

.sub {
  font-size: 12px;
  color: var(--p-text-muted-color);
}

.content {
  flex: 1;
  padding: 16px;
  background: var(--p-surface-950);
  min-width: 0;
  min-height: 0; /* 👈 สำคัญ */
  overflow: hidden;
  display: flex; /* 👈 เพิ่ม */
}
</style>