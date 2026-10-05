import { Dark, Notify, Quasar } from 'quasar'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createApp } from 'vue'
import iconSet from 'quasar/icon-set/svg-material-symbols-rounded'
import 'quasar/src/css/index.sass'
import '@quasar/extras/material-symbols-rounded/material-symbols-rounded.css'

import App from './App.vue'
import { pinia } from './app/plugins/pinia'
import router from './app/router'
import { queryClient } from './app/plugins/query-client'
import './css/app.scss'

// Configuración de tema por defecto: MODO OSCURO (Obsidian & Refined Emerald)
const savedTheme = localStorage.getItem('whaticket_theme')
const isDark = savedTheme !== 'light'

Dark.set(isDark)
if (typeof document !== 'undefined') {
  if (isDark) {
    document.documentElement.setAttribute('data-theme', 'dark')
    document.body.classList.add('body--dark')
    document.body.classList.remove('body--light')
  } else {
    document.documentElement.setAttribute('data-theme', 'light')
    document.body.classList.add('body--light')
    document.body.classList.remove('body--dark')
  }
}

const app = createApp(App)

app.use(pinia)
app.use(router)
app.use(Quasar, {
  plugins: {
    Notify,
    Dark,
  },
  iconSet,
  config: {
    dark: isDark,
    brand: {
      primary: '#1f4e5f',
      secondary: '#b57a44',
      accent: '#d7b98d',
      positive: '#2f7d62',
      negative: '#b94d3f',
      info: '#3b6ea8',
      warning: '#bb8744',
      dark: '#18222d',
    },
    notify: {
      position: 'top-right',
      timeout: 3200,
      progress: true,
      textColor: 'white',
      classes: 'crm-notify',
      actions: [
        {
          icon: 'sym_r_close',
          color: 'white',
          round: true,
        },
      ],
    },
  },
})
app.use(VueQueryPlugin, {
  queryClient,
})

app.mount('#app')
