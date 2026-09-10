<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { fetchReplyTask, updateReplyTask, scheduleMeasurement, sendReply, runCopilot } from '../api/replyTasks'
import { scheduleFieldVisit } from '../api/field'
import {
  TASK_STATUSES,
  SENTIMENTS,
  managers,
  campaignName,
  fmt,
  sentimentTone,
  statusTone,
} from '../data/mock'

const route = useRoute()
const toast = useToast()
const loading = ref(true)
const error = ref('')
const sending = ref(false)
const copilotBusy = ref('')
const scheduling = ref(false)
const task = ref(null)
const offers = ref([])
const copilot = ref(null)
const mailProvider = ref('mailgun')
const composer = ref('')
const drafted = ref(false)

const managerOptions = [{ id: null, name: '— unassigned —' }, ...managers]
const draft = reactive({
  status: '',
  sentiment: null,
  assignee_id: null,
  requested_window: '',
  address: '',
  city: '',
  region: '',
  postal_code: '',
})
const pick = reactive({ team_id: null, scheduled_at: '' })

const fsr = computed(() => task.value?.field_service_request || null)
const client = computed(() => task.value?.client || {})
const messages = computed(() => task.value?.messages || [])
const assignee = computed(() => managers.find((m) => m.id === task.value?.assignee_id)?.name || 'Unassigned')

function conversationStatus(row) {
  return (
    {
      open: 'Needs manager',
      in_progress: 'Manager active',
      waiting_customer: 'Waiting customer',
      resolved: 'Resolved',
      dismissed: 'Closed',
    }[row?.status] || row?.status
  )
}

function fillDraft(row) {
  draft.status = row.status
  draft.sentiment = row.sentiment
  draft.assignee_id = row.assignee_id
  draft.requested_window = row.field_service_request?.requested_window || guessWindow(row.body)
  draft.address = row.client?.address || ''
  draft.city = row.client?.city || ''
  draft.region = row.client?.region || ''
  draft.postal_code = row.client?.postal_code || ''
}

function guessWindow(body) {
  const text = (body || '').toLowerCase()
  const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
  const day = days.find((d) => text.includes(d))
  const part = text.includes('morning') ? 'morning' : text.includes('evening') ? 'evening' : text.includes('afternoon') ? 'afternoon' : ''
  return [day, part].filter(Boolean).join(' ')
}

function applyPayload(payload, extraOffers, extraCopilot, extraMail) {
  task.value = payload
  fillDraft(payload)
  if (extraOffers) offers.value = extraOffers
  if (extraCopilot) copilot.value = extraCopilot
  if (extraMail) mailProvider.value = extraMail
  if (payload.field_service_request && Array.isArray(payload.offers)) offers.value = payload.offers
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchReplyTask(route.params.id)
    applyPayload(res.data, res.offers, res.copilot, res.mail_provider)
  } catch (e) {
    error.value = e.message || 'Could not load conversation'
    task.value = null
  } finally {
    loading.value = false
  }
}

async function saveMeta() {
  try {
    const res = await updateReplyTask(task.value.id, {
      status: draft.status,
      sentiment: draft.sentiment || null,
      assignee_id: draft.assignee_id,
    })
    applyPayload(res.data, res.offers, res.copilot, res.mail_provider)
    toast.add({ severity: 'success', summary: 'Task updated', life: 1400 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Could not save', life: 2200 })
  }
}

async function copilotAction(action) {
  copilotBusy.value = action
  try {
    const res = await runCopilot(task.value.id, { action, body: composer.value || null })
    copilot.value = res.data.snapshot
    if (action === 'summarize' || action === 'next_action') {
      toast.add({ severity: 'info', summary: res.data.text, life: 4200 })
    } else {
      composer.value = res.data.text
      drafted.value = true
    }
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Copilot failed', life: 2200 })
  } finally {
    copilotBusy.value = ''
  }
}

async function send() {
  if (!composer.value.trim()) return
  sending.value = true
  try {
    const res = await sendReply(task.value.id, {
      body: composer.value.trim(),
      ai_generated: drafted.value,
      manager_id: draft.assignee_id,
    })
    applyPayload(res.data, res.offers, res.copilot, res.mail_provider)
    composer.value = ''
    drafted.value = false
    toast.add({ severity: 'success', summary: 'Sent via ' + (res.mail_provider || mailProvider.value), life: 1800 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Send failed', life: 2400 })
  } finally {
    sending.value = false
  }
}

async function createFieldRequest() {
  scheduling.value = true
  try {
    const res = await scheduleMeasurement(task.value.id, {
      requested_window: draft.requested_window || null,
      address: draft.address || null,
      city: draft.city || null,
      region: draft.region || null,
      postal_code: draft.postal_code || null,
    })
    applyPayload(res.data.task, res.data.offers, res.data.copilot, res.mail_provider)
    offers.value = res.data.offers || []
    if (offers.value[0]) {
      pick.team_id = offers.value[0].team_id
      pick.scheduled_at = toLocalInput(offers.value[0].proposed_at)
    }
    toast.add({ severity: 'success', summary: 'Field request created', life: 1600 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Could not create field request', life: 2400 })
  } finally {
    scheduling.value = false
  }
}

function toLocalInput(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function selectOffer(offer) {
  pick.team_id = offer.team_id
  pick.scheduled_at = toLocalInput(offer.proposed_at)
}

async function confirmVisit() {
  if (!fsr.value || !pick.team_id || !pick.scheduled_at) return
  scheduling.value = true
  try {
    await scheduleFieldVisit(fsr.value.id, {
      team_id: pick.team_id,
      scheduled_at: new Date(pick.scheduled_at).toISOString(),
    })
    await load()
    toast.add({ severity: 'success', summary: 'Visit scheduled', life: 1600 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Could not schedule visit', life: 2400 })
  } finally {
    scheduling.value = false
  }
}

function actionLabel(code) {
  return (
    {
      SCHEDULE_MEASUREMENT: 'Schedule measurement',
      SCHEDULE_CALLBACK: 'Schedule callback',
      SEND_QUOTE: 'Send quote',
      ANSWER_QUESTION: 'Answer question',
      STOP_CONTACT: 'Stop contact',
      PAUSE_DRIP: 'Pause drip',
      DRAFT_REPLY: 'Draft reply',
    }[code] || code
  )
}

watch(() => route.params.id, load, { immediate: true })
</script>

<template>
  <div class="workspace-page">
    <p v-if="loading" class="muted">Loading conversation…</p>
    <Message v-else-if="error || !task" severity="error" :closable="false">
      {{ error || 'Conversation not found.' }} <RouterLink to="/reply-center">Back to queue</RouterLink>
    </Message>
    <template v-else>
      <PageHeader :title="'Conversation #' + task.id" :subtitle="(client.name || 'Customer') + ' · copilot drafts, manager sends'">
        <template #actions>
          <Tag :value="'via ' + mailProvider" severity="secondary" />
          <RouterLink to="/reply-center"><Button label="Back to queue" icon="pi pi-arrow-left" text /></RouterLink>
        </template>
      </PageHeader>

      <div class="workspace">
        <Card class="col-card">
          <template #title>Customer</template>
          <template #content>
            <h2 style="margin: 0">{{ client.name || '—' }}</h2>
            <div class="muted mono">{{ client.email }}</div>
            <div class="muted">{{ client.phone }}</div>
            <div class="muted" style="margin-top: 0.4rem">{{ [client.address, client.city, client.region].filter(Boolean).join(', ') || 'No address on file' }}</div>
            <Divider />
            <div class="muted">Campaign</div>
            <div>{{ campaignName(task.campaign_id) }}</div>
            <div class="muted" style="margin-top: 0.45rem">Flow</div>
            <div>Windows remarketing</div>
            <Divider />
            <div class="muted">Status</div>
            <Tag :value="conversationStatus(task)" :severity="statusTone(task.status)" />
            <label class="fld" style="margin-top: 0.75rem">
              <span>Assigned</span>
              <Select v-model="draft.assignee_id" :options="managerOptions" optionLabel="name" optionValue="id" />
            </label>
            <label class="fld">
              <span>Task status</span>
              <Select v-model="draft.status" :options="TASK_STATUSES" />
            </label>
            <label class="fld">
              <span>Sentiment override</span>
              <Select v-model="draft.sentiment" :options="SENTIMENTS" showClear placeholder="unclassified" />
            </label>
            <Button label="Save" size="small" icon="pi pi-check" style="margin-top: 0.5rem" @click="saveMeta" />
            <div class="muted" style="margin-top: 0.6rem">Currently {{ assignee }}</div>
          </template>
        </Card>

        <div class="stack">
          <Card>
            <template #title>Conversation</template>
            <template #content>
              <div v-if="messages.length" class="thread">
                <div v-for="m in messages" :key="m.id" class="bubble" :class="m.sender_type">
                  <div class="bubble-meta">
                    <Tag :value="m.sender_type" :severity="m.sender_type === 'customer' ? 'info' : m.sender_type === 'manager' ? 'success' : 'secondary'" />
                    <span class="muted">{{ fmt(m.created_at) }}</span>
                    <span v-if="m.provider" class="muted mono">{{ m.provider }}</span>
                  </div>
                  <div class="bubble-body">{{ m.body }}</div>
                </div>
              </div>
              <p v-else class="muted">No messages yet. The inbound reply will land here.</p>
            </template>
          </Card>

          <Card>
            <template #content>
              <label class="fld">
                <span>Reply to customer</span>
                <Textarea v-model="composer" rows="6" autoResize placeholder="Type your reply… Copilot can draft, you send." @input="drafted = false" />
              </label>
              <div class="row" style="margin-top: 0.65rem">
                <Button label="Draft reply" icon="pi pi-sparkles" size="small" outlined :loading="copilotBusy === 'draft'" @click="copilotAction('draft')" />
                <Button label="Improve" size="small" outlined :disabled="!composer" :loading="copilotBusy === 'improve'" @click="copilotAction('improve')" />
                <Button label="Summarize" size="small" outlined :loading="copilotBusy === 'summarize'" @click="copilotAction('summarize')" />
                <span class="spacer" />
                <Button label="Send" icon="pi pi-send" :disabled="!composer.trim()" :loading="sending" @click="send" />
              </div>
              <p class="muted" style="margin: 0.45rem 0 0">AI never sends. Mock provider: <code>{{ mailProvider }}</code> (Mailgun / SendGrid).</p>
            </template>
          </Card>
        </div>

        <div class="stack">
          <Card v-if="fsr || task.can_schedule_measurement">
            <template #title>Field visit</template>
            <template #content>
              <p class="muted" style="margin-top: 0">Manager assigns the crew. AI only recommends.</p>
              <label class="fld">
                <span>Requested window</span>
                <InputText v-model="draft.requested_window" placeholder="Thursday afternoon" />
              </label>
              <label class="fld" style="margin-top: 0.55rem">
                <span>Address</span>
                <InputText v-model="draft.address" />
              </label>
              <div class="grid cols-2" style="margin-top: 0.55rem">
                <label class="fld"><span>City</span><InputText v-model="draft.city" /></label>
                <label class="fld"><span>Region</span><InputText v-model="draft.region" /></label>
              </div>

              <div v-if="fsr" class="row" style="margin-top: 0.7rem">
                <Tag :value="fsr.status" :severity="statusTone(fsr.status)" />
                <Tag :value="fsr.type || 'measurement'" severity="info" />
                <span class="muted">#{{ fsr.id }} · {{ fsr.assigned_team || 'unassigned' }}</span>
              </div>

              <Button
                v-if="!fsr && task.can_schedule_measurement"
                label="Assign field visit"
                icon="pi pi-map-marker"
                style="margin-top: 0.75rem; width: 100%"
                :loading="scheduling"
                @click="createFieldRequest"
              />

              <div v-if="fsr && fsr.status !== 'scheduled'" style="margin-top: 0.75rem">
                <div class="muted" style="margin-bottom: 0.35rem">Pick a team</div>
                <p v-if="!offers.length" class="muted">No team available for this window.</p>
                <div v-for="offer in offers" :key="offer.team_id" class="offer" :class="{ on: pick.team_id === offer.team_id }" @click="selectOffer(offer)">
                  <strong>{{ offer.name }}</strong>
                  <div class="muted">{{ offer.region }} · {{ offer.availability }} · {{ offer.distance_km != null ? offer.distance_km + ' km' : '—' }}</div>
                </div>
                <label class="fld" style="margin-top: 0.55rem">
                  <span>When</span>
                  <input v-model="pick.scheduled_at" type="datetime-local" class="p-inputtext p-component" />
                </label>
                <Button
                  label="Confirm visit"
                  icon="pi pi-check"
                  style="margin-top: 0.55rem; width: 100%"
                  :disabled="!pick.team_id || !pick.scheduled_at"
                  :loading="scheduling"
                  @click="confirmVisit"
                />
              </div>
            </template>
          </Card>

          <Card>
            <template #title>AI copilot</template>
            <template #content>
              <div class="row">
                <Tag v-if="copilot?.intent || task.intent" :value="copilot?.intent || task.intent" :severity="sentimentTone(copilot?.intent || task.intent)" />
                <Tag v-if="task.sentiment" :value="task.sentiment" :severity="sentimentTone(task.sentiment)" />
                <Tag v-else value="unclassified" severity="secondary" />
              </div>
              <div class="grid cols-2" style="margin-top: 0.7rem">
                <div><div class="muted">Urgency</div><strong>{{ copilot?.urgency || '—' }}</strong></div>
                <div><div class="muted">Confidence</div><strong>{{ copilot?.confidence != null ? Math.round(copilot.confidence * 100) + '%' : '—' }}</strong></div>
              </div>
              <Divider />
              <div class="muted">Next best action</div>
              <strong>{{ actionLabel(copilot?.next_best_action) }}</strong>
              <p class="muted" style="margin: 0.35rem 0 0">{{ copilot?.reason }}</p>
              <Button label="Draft from intent" size="small" outlined style="margin-top: 0.65rem" @click="copilotAction('draft')" />
            </template>
          </Card>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.workspace-page {
  max-width: 1280px;
}
.workspace {
  display: grid;
  grid-template-columns: 240px minmax(0, 1fr) 320px;
  gap: 1rem;
  align-items: start;
}
.offer {
  border: 1px solid var(--surface-border, #e2e5ea);
  border-radius: 8px;
  padding: 0.45rem 0.55rem;
  margin-bottom: 0.4rem;
  cursor: pointer;
}
.offer.on {
  border-color: var(--primary-color, #3b82f6);
  background: color-mix(in srgb, var(--primary-color, #3b82f6) 8%, white);
}
.col-card :deep(.p-card-body) {
  padding: 1rem;
}
.thread {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-height: 420px;
  overflow: auto;
}
.bubble {
  border: 1px solid var(--surface-border, #e2e5ea);
  border-radius: 10px;
  padding: 0.55rem 0.7rem;
  background: var(--surface-card, #fff);
}
.bubble.customer {
  background: color-mix(in srgb, var(--primary-color, #3b82f6) 7%, white);
}
.bubble.manager {
  background: color-mix(in srgb, #22c55e 8%, white);
}
.bubble.ai {
  background: var(--surface-100, #f4f5f7);
}
.bubble-meta {
  display: flex;
  gap: 0.45rem;
  align-items: center;
  margin-bottom: 0.3rem;
}
.bubble-body {
  white-space: pre-wrap;
  font-size: 13.5px;
}
@media (max-width: 1100px) {
  .workspace {
    grid-template-columns: 1fr;
  }
}
</style>
