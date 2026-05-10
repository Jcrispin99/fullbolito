import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import App from './App.vue'
import { setUnauthorizedHandler } from './lib/api'
import { useAuthStore } from './stores/auth'
import '../../css/app.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Handle 401 responses: only clear user state, router guard handles redirect
const authStore = useAuthStore()
setUnauthorizedHandler(() => {
  authStore.user = null
})

app.mount('#app')
