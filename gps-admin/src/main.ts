import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import ToastService from 'primevue/toastservice'
import { ConfirmationService } from 'primevue'

import App from './App.vue'
import router from './router'
import './style.css'
import 'primeicons/primeicons.css'

createApp(App)
    .use(createPinia())
    .use(router)
    .use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: '.app-dark',
            },
        },
        ripple: true,
    })
    .use(ToastService)
    .use(ConfirmationService)
    .mount('#app')
