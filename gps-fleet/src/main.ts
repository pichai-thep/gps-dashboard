import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'

import App from './App.vue'
import router from './router'
import './style.css'
import 'primeicons/primeicons.css'
import ToastService from 'primevue/toastservice'

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
    .mount('#app')

// console.log('APP START')