<template>
  <div class="min-h-screen flex bg-surface-100">
    <aside class="w-18rem surface-card border-right-1 surface-border p-3 hidden lg:block">
      <h2 class="text-xl font-bold mb-4">GPS Fleet</h2>

      <div class="flex flex-column gap-2">
        <RouterLink to="/" class="p-3 border-round text-color no-underline">Dashboard</RouterLink>
        <RouterLink to="/tracking" class="p-3 border-round text-color no-underline">Current Tracking</RouterLink>
        <RouterLink to="/history" class="p-3 border-round text-color no-underline">History</RouterLink>
        <RouterLink to="/notifications" class="p-3 border-round text-color no-underline">Notifications</RouterLink>
      </div>
    </aside>

    <main class="flex-1">
      <header class="surface-card border-bottom-1 surface-border p-3 flex justify-content-between align-items-center">
        <div>
          <div class="font-bold">Fleet Command Center</div>
          <div class="text-sm text-color-secondary">fleet.gpsthaistar.com</div>
        </div>

        <Button label="Logout" severity="secondary" outlined @click="$router.push('/login')" />
      </header>

      <section class="p-4">
        <RouterView />
      </section>
    </main>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
</script>