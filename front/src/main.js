import { createApp } from 'vue'
import App from './App.vue'
import { router } from './router'

import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'

// PrimeVue 3 — styled mode, light theme
import 'primevue/resources/themes/aura-light-blue/theme.css'
import 'primevue/resources/primevue.min.css'
import 'primeicons/primeicons.css'
import './assets/styles.css'

// components used across the app, registered globally
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Chip from 'primevue/chip'
import Badge from 'primevue/badge'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Menu from 'primevue/menu'
import Timeline from 'primevue/timeline'
import MeterGroup from 'primevue/metergroup'
import ProgressBar from 'primevue/progressbar'
import Chart from 'primevue/chart'
import Panel from 'primevue/panel'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import Toast from 'primevue/toast'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tooltip from 'primevue/tooltip'
import Dialog from 'primevue/dialog'
import SelectButton from 'primevue/selectbutton'

const app = createApp(App)

app.use(router)
app.use(PrimeVue, { ripple: true })
app.use(ToastService)

const registry = {
  Button, Card, Tag, Chip, Badge, DataTable, Column, InputText, InputNumber,
  Textarea, Checkbox, Menu, Timeline, MeterGroup, ProgressBar, Chart, Panel,
  Divider, Message, Toast, IconField, InputIcon, Dialog, SelectButton,
  // v5 name `Select` is `Dropdown` in v3 — register both so views can use either
  Dropdown, Select: Dropdown,
}
for (const [name, comp] of Object.entries(registry)) app.component(name, comp)
app.directive('tooltip', Tooltip)

app.mount('#app')
