<template>
  <main class="login-page">
    <section class="login-card">
      <div class="brand">
        <img
            :src="logoUrl"
            class="brand-logo"
            alt="Brand Logo"
            @error="onLogoError"
        />

        <div>
          <h1>{{ brandName }}</h1>
          <p>{{ t('fleetCommandDashboard') }}</p>
        </div>
      </div>

      <div class="login-language-switch" :title="t('language')">
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

      <Message v-if="error" severity="error" class="mb-4">
        {{ error }}
      </Message>

      <form class="login-form" @submit.prevent="login">
        <InputText
            v-model="username"
            :placeholder="t('username')"
            class="w-full p-inputtext-sm"
            autocomplete="username"
        />

        <InputText
            v-model="password"
            type="password"
            :placeholder="t('password')"
            class="w-full p-inputtext-sm"
            autocomplete="current-password"
        />

        <Button
            type="submit"
            :label="t('login')"
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
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAuthStore } from '../stores/auth'
import { useI18n } from '@/i18n'

const router = useRouter()
const auth = useAuthStore()
const { locale, setLocale, t } = useI18n()

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

const hostname = window.location.hostname

const logoUrl = computed(() => `/logos/${hostname}.png`)

const brandName = computed(() => {
  return hostname.split('.')[0].toUpperCase()
})

function onLogoError(event) {
  if (!event.target.dataset.fallback) {
    event.target.dataset.fallback = 'true'
    event.target.src = '/logos/default.png'
  }
}

async function login() {
  try {
    loading.value = true
    error.value = ''

    await auth.login(username.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || t('loginFailed')
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

.login-language-switch {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 18px;
}

.login-language-switch button {
  height: 36px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 0 10px;
  border: 1px solid rgba(148, 163, 184, 0.24);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.92);
  color: #cbd5e1;
  font-size: 11px;
  font-weight: 900;
  cursor: pointer;
}

.login-language-switch button.active {
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

.brand-logo {
  width: 100px;
  object-fit: contain;
  border-radius: 0px;
  background: white;
  padding: 0px;
}

.brand h1 {
  margin: 0;
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #cbd5e0;
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
