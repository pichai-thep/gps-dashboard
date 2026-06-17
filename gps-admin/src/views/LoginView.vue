<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-brand">
        <div class="brand-icon">
          <i class="pi pi-shield"></i>
        </div>
        <h1>GPS Admin</h1>
        <p>System Administration</p>
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="field">
          <label for="username">Username</label>
          <InputText
              id="username"
              v-model="form.username"
              placeholder="Enter username"
              :disabled="loading"
              fluid
          />
        </div>

        <div class="field">
          <label for="password">Password</label>
          <Password
              id="password"
              v-model="form.password"
              placeholder="Enter password"
              :feedback="false"
              :disabled="loading"
              fluid
              toggleMask
          />
        </div>

        <Message v-if="errorMsg" severity="error" :closable="false">
          {{ errorMsg }}
        </Message>

        <Button
            type="submit"
            label="Sign In"
            icon="pi pi-sign-in"
            :loading="loading"
            fluid
        />
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const form = ref({ username: '', password: '' })
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  errorMsg.value = ''
  loading.value = true

  try {
    await auth.login(form.value.username, form.value.password)
    router.push('/')
  } catch (err: unknown) {
    const error = err as { response?: { data?: { message?: string } } }
    errorMsg.value = error?.response?.data?.message || 'Login failed. Please try again.'
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
  background: #020617;
}

.login-card {
  width: 360px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 40px 32px;
}

.login-brand {
  text-align: center;
  margin-bottom: 32px;
}

.brand-icon {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  display: grid;
  place-items: center;
  margin: 0 auto 16px;
  font-size: 24px;
  color: #451a03;
}

.login-brand h1 {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #f59e0b;
}

.login-brand p {
  margin: 4px 0 0;
  font-size: 13px;
  color: #64748b;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
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
