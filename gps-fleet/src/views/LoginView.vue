<template>
  <main class="login-page">
    <section class="login-card">
      <div class="brand">
        <div class="brand-icon">GF</div>
        <div>
          <h1>GPS Fleet</h1>
          <p>Fleet command dashboard</p>
        </div>
      </div>

      <Message v-if="error" severity="error" class="mb-4">
        {{ error }}
      </Message>

      <form class="login-form" @submit.prevent="login">
        <InputText
            v-model="username"
            placeholder="Username"
            class="w-full p-inputtext-sm"
            autocomplete="username"

        />

        <InputText
            v-model="password"
            type="password"
            placeholder="Password"
            class="w-full p-inputtext-sm"
            autocomplete="current-password"
        />

        <Button
            type="submit"
            label="Login"
            icon="pi pi-sign-in"
            class="w-full"
            severity="success"
            :loading="loading"
            raised
        />


      </form>
    </section>
  </main>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const username = ref('gpsthaistar')
const password = ref('1234')
const loading = ref(false)
const error = ref('')

async function login() {
  try {
    loading.value = true
    error.value = ''

    await auth.login(username.value, password.value)
    router.push('/tracking')
  } catch (e) {
    error.value = e.response?.data?.message || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background:
      radial-gradient(circle at top left, rgba(16, 185, 129, 0.18), transparent 36%),
      radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.12), transparent 34%),
      var(--p-surface-950);
}

.login-card {
  width: 100%;
  max-width: 440px;
  padding: 32px;
  border-radius: 24px;
  background: color-mix(in srgb, var(--p-surface-900) 88%, transparent);
  border: 1px solid var(--p-surface-700);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(18px);
}

.brand {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 28px;
}

.brand-icon {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  border-radius: 16px;
  font-weight: 800;
  color: #06130d;
  background: linear-gradient(135deg, #34d399, #22c55e);
}

.brand h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.brand p {
  margin: 4px 0 0;
  color: var(--p-text-muted-color);
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
</style>