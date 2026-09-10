<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { fetchFieldTeams, fetchFieldVisits, fetchFieldRequests, updateFieldTeam } from '../api/field'
import {
  fmt,
  statusTone,
  FIELD_SKILLS,
  FIELD_SERVICES,
  FIELD_KIT,
  fieldServiceTypes,
  fieldDispatchStrategies,
  fieldHolidays,
  fieldTeamExtras,
  fieldCalendarVisits,
  defaultFieldHours,
} from '../data/mock'

const toast = useToast()
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const teams = ref([])
const visits = ref([])
const requests = ref([])
const extras = reactive(copyExtras(fieldTeamExtras))

const settingsOpen = ref(false)
const settingsTab = ref('team')
const settingsTeam = ref(null)
const settings = reactive(blankSettings())

const cursor = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1))
const teamFilter = ref(null)
const selectedKey = ref(null)

const SETTINGS_TABS = [
  { label: 'Team', value: 'team' },
  { label: 'Area', value: 'area' },
  { label: 'Hours', value: 'hours' },
  { label: 'Capacity', value: 'capacity' },
  { label: 'Dispatch', value: 'dispatch' },
  { label: 'Booking', value: 'booking' },
]

const typeLabel = {
  measurement: 'On-site measure',
  consultation: 'Consultation',
  quote_review: 'Quote review',
  site_visit: 'Site visit',
  install: 'Install',
}

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

function copyExtras(src) {
  const out = {}
  for (const [key, value] of Object.entries(src || {})) {
    out[key] = copyExtra(value)
  }
  return out
}

function copyExtra(value) {
  const e = value || {}
  return {
    services: [...(e.services || [])],
    skills: [...(e.skills || [])],
    cities: Array.isArray(e.cities) ? [...e.cities] : e.cities || [],
    postal_prefixes: e.postal_prefixes || '',
    hours: (e.hours || defaultFieldHours()).map((h) => ({ ...h })),
    lunch_start: e.lunch_start || '12:00',
    lunch_end: e.lunch_end || '13:00',
    kit: [...(e.kit || [])],
    dispatch: e.dispatch || 'nearest',
    confirm_customer: e.confirm_customer !== false,
    remind_24h: e.remind_24h !== false,
    remind_2h: !!e.remind_2h,
    notify_email: e.notify_email !== false,
    notify_sms: e.notify_sms !== false,
    arrival_window_min: e.arrival_window_min || 60,
  }
}

function blankSettings() {
  return {
    daily_capacity: 5,
    lead_cap: 20,
    work_starts_at: '08:00',
    work_ends_at: '18:00',
    is_active: true,
    services: [],
    skills: [],
    cities: '',
    postal_prefixes: '',
    hours: defaultFieldHours(),
    lunch_start: '12:00',
    lunch_end: '13:00',
    kit: [],
    dispatch: 'nearest',
    confirm_customer: true,
    remind_24h: true,
    remind_2h: true,
    notify_email: true,
    notify_sms: true,
    arrival_window_min: 60,
  }
}

function extrasFor(id) {
  return extras[id] || extras[String(id)] || copyExtra(null)
}

const upcoming = computed(() =>
  visits.value.filter((v) => ['scheduled', 'en_route', 'confirmed'].includes(v.status)),
)

const teamOptions = computed(() => teams.value.map((t) => ({ label: t.name, value: t.id })))

const monthLabel = computed(() =>
  cursor.value.toLocaleString('en-CA', { month: 'long', year: 'numeric' }),
)

const calendarDays = computed(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1)
  const pad = (first.getDay() + 6) % 7
  const lastDate = new Date(year, month + 1, 0).getDate()
  const cells = []
  for (let i = 0; i < pad; i++) cells.push(null)
  for (let d = 1; d <= lastDate; d++) cells.push(new Date(year, month, d))
  while (cells.length % 7 !== 0) cells.push(null)
  return cells
})

const visitsByDay = computed(() => {
  const map = {}
  for (const v of visits.value) {
    if (teamFilter.value && v.team_id !== teamFilter.value) continue
    if (!['scheduled', 'en_route', 'completed', 'confirmed'].includes(v.status)) continue
    const key = (v.scheduled_at || '').slice(0, 10)
    if (!key) continue
    if (!map[key]) map[key] = []
    map[key].push(v)
  }
  return map
})

const selectedVisits = computed(() => (selectedKey.value ? visitsByDay.value[selectedKey.value] || [] : []))

function dayKey(d) {
  if (!d) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function isToday(d) {
  if (!d) return false
  const n = new Date()
  return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate()
}

function shiftMonth(delta) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1)
  selectedKey.value = null
}

function toggle(list, value) {
  const i = list.indexOf(value)
  if (i === -1) list.push(value)
  else list.splice(i, 1)
}

function openSettings(team) {
  settingsTeam.value = team
  settingsTab.value = 'team'
  const e = copyExtra(extrasFor(team.id))
  settings.daily_capacity = team.daily_capacity
  settings.lead_cap = team.lead_cap
  settings.work_starts_at = team.work_starts_at
  settings.work_ends_at = team.work_ends_at
  settings.is_active = team.is_active !== false
  settings.services = e.services
  settings.skills = e.skills
  settings.cities = Array.isArray(e.cities) ? e.cities.join(', ') : e.cities || ''
  settings.postal_prefixes = e.postal_prefixes
  settings.hours = e.hours
  settings.lunch_start = e.lunch_start
  settings.lunch_end = e.lunch_end
  settings.kit = e.kit
  settings.dispatch = e.dispatch
  settings.confirm_customer = e.confirm_customer
  settings.remind_24h = e.remind_24h
  settings.remind_2h = e.remind_2h
  settings.notify_email = e.notify_email
  settings.notify_sms = e.notify_sms
  settings.arrival_window_min = e.arrival_window_min
  settingsOpen.value = true
}

async function saveSettings() {
  if (!settingsTeam.value) return
  saving.value = true
  extras[settingsTeam.value.id] = {
    services: [...settings.services],
    skills: [...settings.skills],
    cities: settings.cities.split(',').map((s) => s.trim()).filter(Boolean),
    postal_prefixes: settings.postal_prefixes,
    hours: settings.hours.map((h) => ({ ...h })),
    lunch_start: settings.lunch_start,
    lunch_end: settings.lunch_end,
    kit: [...settings.kit],
    dispatch: settings.dispatch,
    confirm_customer: settings.confirm_customer,
    remind_24h: settings.remind_24h,
    remind_2h: settings.remind_2h,
    notify_email: settings.notify_email,
    notify_sms: settings.notify_sms,
    arrival_window_min: settings.arrival_window_min,
  }
  try {
    const res = await updateFieldTeam(settingsTeam.value.id, {
      daily_capacity: settings.daily_capacity,
      lead_cap: settings.lead_cap,
      work_starts_at: settings.work_starts_at,
      work_ends_at: settings.work_ends_at,
      is_active: settings.is_active,
    })
    const idx = teams.value.findIndex((t) => t.id === settingsTeam.value.id)
    if (idx !== -1) teams.value[idx] = { ...teams.value[idx], ...res.data }
  } catch {
    /* extra fields stay local */
  }
  settingsOpen.value = false
  saving.value = false
  toast.add({ severity: 'success', summary: 'Team settings saved', life: 1600 })
}

function capMeter(team) {
  const leads = Math.min(team.open_leads || 0, team.lead_cap || 1)
  const free = Math.max((team.lead_cap || 0) - leads, 0)
  return [
    { label: 'open leads', value: leads, color: '#f59e0b' },
    { label: 'free', value: free, color: '#22c55e' },
  ]
}

function extraLabel(team, key) {
  const e = extrasFor(team.id)
  if (key === 'dispatch') {
    return fieldDispatchStrategies.find((s) => s.value === e.dispatch)?.label || e.dispatch
  }
  if (key === 'area') return (e.cities || []).join(', ')
  return ''
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [t, v, r] = await Promise.all([fetchFieldTeams(), fetchFieldVisits(), fetchFieldRequests()])
    teams.value = t.data
    const live = v.data || []
    const seen = new Set(live.map((x) => x.scheduled_at + '|' + x.client))
    visits.value = [
      ...live,
      ...fieldCalendarVisits.filter((x) => !seen.has(x.scheduled_at + '|' + x.client)),
    ]
    requests.value = r.data
    const today = dayKey(new Date())
    if (visitsByDay.value[today]) selectedKey.value = today
  } catch (e) {
    error.value = e.message || 'Could not load field teams'
    visits.value = [...fieldCalendarVisits]
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Field Teams"
      subtitle="Crew settings for measurement visits. Extra fields are local until the FSM module is wired."
    />

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <p v-else-if="loading" class="muted">Loading field teams…</p>

    <template v-else>
      <div class="grid cols-3">
        <Card v-for="t in teams" :key="t.id">
          <template #content>
            <div class="row">
              <h2 style="margin: 0">{{ t.name }}</h2>
              <Tag :value="t.is_active ? 'active' : 'paused'" :severity="t.is_active ? 'success' : 'warning'" />
              <span class="spacer" />
              <Button label="Settings" icon="pi pi-cog" size="small" outlined @click="openSettings(t)" />
            </div>
            <div class="muted">{{ extraLabel(t, 'area') || t.region }}</div>
            <div class="muted" style="margin-top: 0.25rem">{{ extraLabel(t, 'dispatch') }} · {{ t.work_starts_at }}–{{ t.work_ends_at }}</div>
            <div class="grid cols-2" style="margin-top: 0.6rem">
              <div><div class="muted">Lead cap</div><strong>{{ t.open_leads }}/{{ t.lead_cap }}</strong></div>
              <div><div class="muted">Visits / day</div><strong>{{ t.open_visits }} open · {{ t.daily_capacity }}/day</strong></div>
            </div>
            <MeterGroup :value="capMeter(t)" style="margin-top: 0.65rem" />
            <div class="row" style="margin-top: 0.6rem">
              <Tag v-for="s in extrasFor(t.id).services" :key="s" :value="s" severity="secondary" />
            </div>
            <ul v-if="t.members?.length" class="members">
              <li v-for="m in t.members" :key="m.id">
                <strong>{{ m.name }}</strong>
                <span class="muted"> · {{ m.role }} · {{ (m.skills || []).join(', ') }}</span>
              </li>
            </ul>
          </template>
        </Card>
      </div>

      <Card style="margin-top: 1rem">
        <template #title>Visit calendar</template>
        <template #content>
          <div class="row" style="margin-bottom: 0.85rem">
            <Button icon="pi pi-chevron-left" text rounded @click="shiftMonth(-1)" />
            <strong>{{ monthLabel }}</strong>
            <Button icon="pi pi-chevron-right" text rounded @click="shiftMonth(1)" />
            <span class="spacer" />
            <Select
              v-model="teamFilter"
              :options="teamOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="All teams"
              showClear
              style="min-width: 200px"
            />
          </div>

          <div class="cal-week">
            <span v-for="d in weekdays" :key="d" class="cal-dow">{{ d }}</span>
          </div>
          <div class="cal">
            <button
              v-for="(d, i) in calendarDays"
              :key="i"
              type="button"
              class="cal-day"
              :class="{
                empty: !d,
                today: isToday(d),
                selected: d && selectedKey === dayKey(d),
                busy: d && (visitsByDay[dayKey(d)] || []).length,
              }"
              :disabled="!d"
              @click="d && (selectedKey = dayKey(d))"
            >
              <template v-if="d">
                <span class="cal-num">{{ d.getDate() }}</span>
                <span v-if="(visitsByDay[dayKey(d)] || []).length" class="cal-count">
                  {{ visitsByDay[dayKey(d)].length }}
                </span>
              </template>
            </button>
          </div>

          <div style="margin-top: 1rem">
            <h3 v-if="selectedKey">{{ selectedKey }} · {{ selectedVisits.length }} visit{{ selectedVisits.length === 1 ? '' : 's' }}</h3>
            <p v-else class="muted">Pick a day to see booked visits.</p>
            <DataTable v-if="selectedVisits.length" :value="selectedVisits" size="small" stripedRows>
              <Column field="client" header="Client" />
              <Column field="team" header="Team" />
              <Column header="When"><template #body="{ data }"><span class="muted">{{ fmt(data.scheduled_at) }}</span></template></Column>
              <Column header="Window"><template #body="{ data }">{{ data.arrival_window || '—' }}</template></Column>
              <Column header="Duration"><template #body="{ data }">{{ data.duration_min ? data.duration_min + ' min' : '—' }}</template></Column>
              <Column header="Type"><template #body="{ data }">{{ typeLabel[data.type] || data.type }}</template></Column>
              <Column header="Status">
                <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
              </Column>
            </DataTable>
          </div>
        </template>
      </Card>

      <Card style="margin-top: 1rem">
        <template #title>Service types</template>
        <template #content>
          <p class="muted" style="margin-top: 0">Catalog for the scheduler. Repair / install stay out until those crews exist.</p>
          <DataTable :value="fieldServiceTypes" size="small" stripedRows>
            <Column field="label" header="Type" />
            <Column header="Duration"><template #body="{ data }">{{ data.duration_min }} min</template></Column>
            <Column header="Travel buffer"><template #body="{ data }">{{ data.travel_buffer_min }} min</template></Column>
            <Column field="required_skill" header="Required skill" />
            <Column header="Confirm">
              <template #body="{ data }"><Tag :value="data.confirm ? 'yes' : 'no'" :severity="data.confirm ? 'success' : 'secondary'" /></template>
            </Column>
            <Column header="Fee">
              <template #body="{ data }">{{ data.fee ? '$' + data.fee : 'Free' }}</template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <Card style="margin-top: 1rem">
        <template #title>Field service requests</template>
        <template #content>
          <DataTable :value="requests" size="small" stripedRows>
            <Column field="id" header="#" style="width: 4rem" />
            <Column header="Client"><template #body="{ data }">{{ data.client?.name || '—' }}</template></Column>
            <Column header="Type">
              <template #body="{ data }"><Tag :value="typeLabel[data.type] || data.type" severity="info" /></template>
            </Column>
            <Column header="Status">
              <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
            </Column>
            <Column header="Source">
              <template #body="{ data }"><Tag :value="data.source" :severity="statusTone(data.source)" /></template>
            </Column>
            <Column header="Team"><template #body="{ data }">{{ data.assigned_team || '—' }}</template></Column>
            <Column header="Region"><template #body="{ data }">{{ data.region || '—' }}</template></Column>
          </DataTable>
        </template>
      </Card>
    </template>

    <Dialog v-model:visible="settingsOpen" header="Team settings" modal appendTo="body" :style="{ width: '40rem' }">
      <div v-if="settingsTeam" class="stack">
        <div class="row">
          <strong>{{ settingsTeam.name }}</strong>
          <span class="muted">{{ settingsTeam.region }}</span>
        </div>
        <SelectButton
          :modelValue="settingsTab"
          :options="SETTINGS_TABS"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          @update:modelValue="settingsTab = $event"
        />

        <div v-if="settingsTab === 'team'" class="stack">
          <label class="row">
            <Checkbox v-model="settings.is_active" :binary="true" inputId="team-active" />
            <label for="team-active">Team is active</label>
          </label>
          <div>
            <div class="muted" style="margin-bottom: 0.4rem">Services</div>
            <div class="row">
              <label v-for="s in FIELD_SERVICES" :key="s.value" class="row">
                <Checkbox :modelValue="settings.services.includes(s.value)" :binary="true" @update:modelValue="toggle(settings.services, s.value)" />
                <span>{{ s.label }}</span>
              </label>
            </div>
          </div>
          <div>
            <div class="muted" style="margin-bottom: 0.4rem">Skills</div>
            <div class="row">
              <label v-for="s in FIELD_SKILLS" :key="s" class="row">
                <Checkbox :modelValue="settings.skills.includes(s)" :binary="true" @update:modelValue="toggle(settings.skills, s)" />
                <span>{{ s }}</span>
              </label>
            </div>
          </div>
          <div>
            <div class="muted" style="margin-bottom: 0.4rem">Kit on the van</div>
            <div class="row">
              <label v-for="s in FIELD_KIT" :key="s" class="row">
                <Checkbox :modelValue="settings.kit.includes(s)" :binary="true" @update:modelValue="toggle(settings.kit, s)" />
                <span>{{ s }}</span>
              </label>
            </div>
          </div>
        </div>

        <div v-else-if="settingsTab === 'area'" class="stack">
          <label class="fld">
            <span>Cities</span>
            <InputText v-model="settings.cities" placeholder="North York, Toronto" />
          </label>
          <label class="fld">
            <span>Postal prefixes</span>
            <InputText v-model="settings.postal_prefixes" placeholder="M2N, M5M" />
          </label>
          <p class="muted" style="margin: 0">ZIP polygons and radius maps are overkill for three GTA/Ottawa crews.</p>
        </div>

        <div v-else-if="settingsTab === 'hours'" class="stack">
          <div v-for="h in settings.hours" :key="h.day" class="row hours-row">
            <span class="hours-day">{{ h.day }}</span>
            <Checkbox v-model="h.off" :binary="true" />
            <span class="muted">off</span>
            <InputText v-model="h.start" type="time" :disabled="h.off" style="width: 8rem" />
            <span class="muted">–</span>
            <InputText v-model="h.end" type="time" :disabled="h.off" style="width: 8rem" />
          </div>
          <div class="grid cols-2">
            <label class="fld"><span>Lunch start</span><InputText v-model="settings.lunch_start" type="time" /></label>
            <label class="fld"><span>Lunch end</span><InputText v-model="settings.lunch_end" type="time" /></label>
          </div>
          <div>
            <div class="muted" style="margin-bottom: 0.35rem">Holidays (blocked)</div>
            <div class="row">
              <Tag v-for="h in fieldHolidays" :key="h.date" :value="h.date + ' ' + h.name" severity="secondary" />
            </div>
          </div>
        </div>

        <div v-else-if="settingsTab === 'capacity'" class="stack">
          <label class="fld">
            <span>Lead cap (open requests)</span>
            <InputNumber v-model="settings.lead_cap" :min="1" :max="500" showButtons />
          </label>
          <label class="fld">
            <span>Daily visit cap</span>
            <InputNumber v-model="settings.daily_capacity" :min="1" :max="50" showButtons />
          </label>
          <label class="fld">
            <span>Arrival window (minutes)</span>
            <InputNumber v-model="settings.arrival_window_min" :min="15" :max="180" showButtons />
          </label>
        </div>

        <div v-else-if="settingsTab === 'dispatch'" class="stack">
          <label class="fld">
            <span>Strategy</span>
            <Select v-model="settings.dispatch" :options="fieldDispatchStrategies" optionLabel="label" optionValue="value" />
          </label>
          <p class="muted" style="margin: 0">Order used when auto-offering a crew: skill → area → hours → remaining cap → distance.</p>
          <p class="muted" style="margin: 0">Round-robin is skipped — window crews are skill-matched, not shuffled.</p>
        </div>

        <div v-else class="stack">
          <label class="row">
            <Checkbox v-model="settings.confirm_customer" :binary="true" inputId="confirm-cust" />
            <label for="confirm-cust">Customer must confirm the slot</label>
          </label>
          <label class="row">
            <Checkbox v-model="settings.remind_24h" :binary="true" inputId="r24" />
            <label for="r24">Reminder 24h before</label>
          </label>
          <label class="row">
            <Checkbox v-model="settings.remind_2h" :binary="true" inputId="r2" />
            <label for="r2">Reminder 2h before</label>
          </label>
          <label class="row">
            <Checkbox v-model="settings.notify_email" :binary="true" inputId="nemail" />
            <label for="nemail">Email customer</label>
          </label>
          <label class="row">
            <Checkbox v-model="settings.notify_sms" :binary="true" inputId="nsms" />
            <label for="nsms">SMS customer</label>
          </label>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" outlined @click="settingsOpen = false" />
        <Button label="Save" icon="pi pi-check" :loading="saving" @click="saveSettings" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.members {
  margin: 0.75rem 0 0;
  padding: 0;
  list-style: none;
  font-size: 13px;
}
.members li {
  margin-top: 0.2rem;
}
.hours-day {
  width: 6.5rem;
  font-weight: 600;
  font-size: 13px;
}
.hours-row {
  align-items: center;
}
.cal-week,
.cal {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.35rem;
}
.cal-dow {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-color-secondary, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0 0.35rem 0.25rem;
}
.cal-day {
  min-height: 64px;
  border: 1px solid var(--surface-border, #e2e5ea);
  border-radius: 8px;
  background: var(--surface-card, #fff);
  text-align: left;
  padding: 0.35rem 0.45rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.cal-day:disabled {
  background: transparent;
  border-color: transparent;
  cursor: default;
}
.cal-day.today {
  border-color: var(--primary-color, #3b82f6);
}
.cal-day.busy {
  background: color-mix(in srgb, var(--primary-color, #3b82f6) 8%, white);
}
.cal-day.selected {
  border-color: var(--primary-color, #3b82f6);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary-color, #3b82f6) 25%, transparent);
}
.cal-num {
  font-weight: 600;
  font-size: 13px;
}
.cal-count {
  font-size: 11px;
  color: var(--primary-color, #3b82f6);
  font-weight: 600;
}
</style>
