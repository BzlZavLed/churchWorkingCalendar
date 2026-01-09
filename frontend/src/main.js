import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css'
import './styles/superadmin.css'
import App from './App.vue'
import router from './router'
import { initDatatables, observeDatatables } from './plugins/datatables'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.mount('#app')

router.afterEach(() => {
  setTimeout(() => initDatatables(), 0)
})

initDatatables()
observeDatatables()
