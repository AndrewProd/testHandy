<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import {
  createCampaign,
  deleteCampaign,
  fetchCampaign,
  fetchCampaignMeta,
  updateCampaign,
} from '../api/campaigns'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isNew = computed(() => route.name === 'campaign-new')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})
const confirmDelete = ref(false)

const meta = reactive({
  lead_statuses: [],
  channels: [],
  campaign_statuses: [],
  intents: [],
})

const delayUnits = [
  { value: 'minutes', label: 'Minutes' },
  { value: 'hours', label: 'Hours' },
  { value: 'days', label: 'Days' },
]

const form = reactive({
  name: '',
  description: '',
  status: 'draft',
  entry_status: 'dormant',
  stop_on_reply: true,
  handoff_intents: ['interested', 'call_back', 'question', 'request_quote', 'request_measurement'],
  field_intents: ['request_measurement'],
  suppress_intents: ['unsubscribe', 'wrong_person'],
  steps: [],
})

function blankStep(overrides = {}) {
  return {
    status: form.entry_status || 'dormant',
    channel: 'email',
    delay_value: 0,
    delay_unit: 'minutes',
    subject: '',
    message: '',
    is_active: true,
    ...overrides,
  }
}

function fromMinutes(minutes) {
  const m = Number(minutes) || 0
  if (m === 0) return { delay_value: 0, delay_unit: 'minutes' }
  if (m % 1440 === 0) return { delay_value: m / 1440, delay_unit: 'days' }
  if (m % 60 === 0) return { delay_value: m / 60, delay_unit: 'hours' }
  return { delay_value: m, delay_unit: 'minutes' }
}

function toMinutes(step) {
  const n = Number(step.delay_value) || 0
  if (step.delay_unit === 'days') return n * 1440
  if (step.delay_unit === 'hours') return n * 60
  return n
}

function addStep() {
  form.steps.push(
    blankStep({
      delay_value: form.steps.length ? 2 : 0,
      delay_unit: form.steps.length ? 'days' : 'minutes',
    }),
  )
}

function removeStep(index) {
  if (form.steps.length === 1) return
  form.steps.splice(index, 1)
}

function moveStep(index, dir) {
  const next = index + dir
  if (next < 0 || next >= form.steps.length) return
  const copy = form.steps.splice(index, 1)[0]
  form.steps.splice(next, 0, copy)
}

function toggleIntent(list, value) {
  const i = list.indexOf(value)
  if (i === -1) list.push(value)
  else list.splice(i, 1)
}

function fieldError(path) {
  const bag = fieldErrors.value[path]
  return Array.isArray(bag) ? bag[0] : bag
}

function payload() {
  return {
    name: form.name,
    description: form.description || null,
    status: form.status,
    entry_status: form.entry_status,
    stop_on_reply: form.stop_on_reply,
    handoff_intents: form.handoff_intents,
    field_intents: form.field_intents,
    suppress_intents: form.suppress_intents,
    steps: form.steps.map((step) => ({
      status: form.entry_status,
      channel: step.channel,
      delay_minutes: toMinutes(step),
      subject: step.channel === 'email' ? step.subject || null : null,
      message: step.message,
      is_active: step.is_active,
    })),
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const options = await fetchCampaignMeta()
    meta.lead_statuses = options.lead_statuses
    meta.channels = options.channels
    meta.campaign_statuses = options.campaign_statuses
    meta.intents = options.intents || []

    if (isNew.value) {
      form.name = ''
      form.description = ''
      form.status = 'draft'
      form.entry_status = 'dormant'
      form.stop_on_reply = true
      form.handoff_intents = ['interested', 'call_back', 'question', 'request_quote', 'request_measurement']
      form.field_intents = ['request_measurement']
      form.suppress_intents = ['unsubscribe', 'wrong_person']
      form.steps = [blankStep()]
      return
    }

    const res = await fetchCampaign(route.params.id)
    const campaign = res.data
    form.name = campaign.name
    form.description = campaign.description || ''
    form.status = campaign.status
    form.entry_status = campaign.entry_status || 'dormant'
    form.stop_on_reply = campaign.stop_on_reply !== false
    form.handoff_intents = [...(campaign.handoff_intents || [])]
    form.field_intents = [...(campaign.field_intents || [])]
    form.suppress_intents = [...(campaign.suppress_intents || [])]
    form.steps = campaign.steps.map((step) => ({
      status: campaign.entry_status || step.status,
      channel: step.channel,
      subject: step.subject || '',
      message: step.message,
      is_active: step.is_active,
      ...fromMinutes(step.delay_minutes),
    }))
  } catch (e) {
    error.value = e.message || 'Could not load campaign'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  fieldErrors.value = {}
  try {
    const res = isNew.value
      ? await createCampaign(payload())
      : await updateCampaign(route.params.id, payload())
    toast.add({ severity: 'success', summary: 'Campaign saved', life: 1600 })
    if (isNew.value) {
      router.replace({ name: 'campaign-edit', params: { id: res.data.id } })
    } else {
      await load()
    }
  } catch (e) {
    fieldErrors.value = e.errors || {}
    toast.add({ severity: 'error', summary: e.message || 'Could not save', life: 2400 })
  } finally {
    saving.value = false
  }
}

async function remove() {
  saving.value = true
  try {
    await deleteCampaign(route.params.id)
    toast.add({ severity: 'success', summary: 'Campaign deleted', life: 1600 })
    router.push({ name: 'campaigns' })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Could not delete', life: 2200 })
  } finally {
    saving.value = false
    confirmDelete.value = false
  }
}

watch(() => [route.name, route.params.id], load, { immediate: true })
</script>

<template>
  <div>
    <PageHeader
      :title="isNew ? 'New drip campaign' : 'Edit drip campaign'"
      subtitle="Touches fire while there is no reply. A reply stops the drip and goes to Reply Center."
    >
      <template #actions>
        <Button label="Back" icon="pi pi-arrow-left" outlined @click="router.push({ name: 'campaigns' })" />
        <Button v-if="!isNew" label="Delete" icon="pi pi-trash" severity="danger" outlined :disabled="saving" @click="confirmDelete = true" />
        <Button label="Save" icon="pi pi-check" :loading="saving" :disabled="loading" @click="save" />
      </template>
    </PageHeader>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <p v-else-if="loading" class="muted">Loading…</p>

    <div v-else class="stack">
      <Card>
        <template #title>Campaign</template>
        <template #content>
          <div class="grid cols-2">
            <label class="fld">
              <span>Name</span>
              <InputText v-model="form.name" maxlength="255" />
              <small v-if="fieldError('name')" class="field-err">{{ fieldError('name') }}</small>
            </label>
            <label class="fld">
              <span>Status</span>
              <Select v-model="form.status" :options="meta.campaign_statuses" optionLabel="label" optionValue="value" />
            </label>
            <label class="fld">
              <span>Who enters this drip</span>
              <Select v-model="form.entry_status" :options="meta.lead_statuses" optionLabel="label" optionValue="value" />
            </label>
          </div>
          <label class="fld" style="margin-top: 0.9rem">
            <span>Description</span>
            <Textarea v-model="form.description" rows="2" autoResize />
          </label>
        </template>
      </Card>

      <Card>
        <template #title>Flow</template>
        <template #content>
          <div class="flow">
            <span class="flow-node">{{ (meta.lead_statuses.find((s) => s.value === form.entry_status) || {}).label || form.entry_status }}</span>
            <span class="flow-arrow">→</span>
            <span class="flow-node">Drip touches</span>
            <span class="flow-arrow">→</span>
            <span class="flow-node">Customer reply</span>
            <span class="flow-arrow">→</span>
            <span class="flow-node">AI</span>
            <span class="flow-arrow">→</span>
            <span class="flow-node">Reply Center</span>
            <span class="flow-arrow">→</span>
            <span class="flow-node">Field team / quote</span>
          </div>
          <p class="muted" style="margin: 0.75rem 0 0">
            Steps below only send if the lead has not replied. AI does not send. A manager sends from the conversation.
          </p>
        </template>
      </Card>

      <Card>
        <template #title>When the customer replies</template>
        <template #content>
          <label class="row">
            <Checkbox v-model="form.stop_on_reply" :binary="true" inputId="stop-reply" />
            <label for="stop-reply">Stop the drip (except auto-replies)</label>
          </label>

          <div style="margin-top: 1rem">
            <div class="muted" style="margin-bottom: 0.4rem">Hand to Reply Center (manager + copilot)</div>
            <div class="row">
              <label v-for="intent in meta.intents" :key="'h-' + intent.value" class="row">
                <Checkbox :modelValue="form.handoff_intents.includes(intent.value)" :binary="true" @update:modelValue="toggleIntent(form.handoff_intents, intent.value)" />
                <span>{{ intent.label }}</span>
              </label>
            </div>
          </div>

          <div style="margin-top: 1rem">
            <div class="muted" style="margin-bottom: 0.4rem">Open a field service request</div>
            <div class="row">
              <label v-for="intent in meta.intents" :key="'f-' + intent.value" class="row">
                <Checkbox :modelValue="form.field_intents.includes(intent.value)" :binary="true" @update:modelValue="toggleIntent(form.field_intents, intent.value)" />
                <span>{{ intent.label }}</span>
              </label>
            </div>
          </div>

          <div style="margin-top: 1rem">
            <div class="muted" style="margin-bottom: 0.4rem">Suppress and stop marketing</div>
            <div class="row">
              <label v-for="intent in meta.intents" :key="'s-' + intent.value" class="row">
                <Checkbox :modelValue="form.suppress_intents.includes(intent.value)" :binary="true" @update:modelValue="toggleIntent(form.suppress_intents, intent.value)" />
                <span>{{ intent.label }}</span>
              </label>
            </div>
          </div>
        </template>
      </Card>

      <div class="row">
        <h2 style="margin: 0">Touches if there is no reply</h2>
        <span class="muted">{{ form.steps.length }} in sequence</span>
        <span class="spacer" />
        <Button label="Add touch" icon="pi pi-plus" size="small" outlined @click="addStep" />
      </div>
      <small v-if="fieldError('steps')" class="field-err">{{ fieldError('steps') }}</small>

      <Card v-for="(step, index) in form.steps" :key="index">
        <template #content>
          <div class="row" style="margin-bottom: 0.75rem">
            <Tag :value="'Touch ' + (index + 1)" severity="info" />
            <span class="muted">{{ index === 0 ? 'on enroll' : 'if still no reply' }}</span>
            <span class="muted">via</span>
            <strong>{{ (meta.channels.find((c) => c.value === step.channel) || {}).label || step.channel }}</strong>
            <span class="spacer" />
            <Button icon="pi pi-arrow-up" size="small" text :disabled="index === 0" @click="moveStep(index, -1)" />
            <Button icon="pi pi-arrow-down" size="small" text :disabled="index === form.steps.length - 1" @click="moveStep(index, 1)" />
            <Button icon="pi pi-times" size="small" text severity="danger" :disabled="form.steps.length === 1" @click="removeStep(index)" />
          </div>

          <div class="grid cols-3">
            <label class="fld">
              <span>Channel</span>
              <Select v-model="step.channel" :options="meta.channels" optionLabel="label" optionValue="value" />
            </label>
            <label class="fld">
              <span>Wait</span>
              <InputNumber v-model="step.delay_value" :min="0" showButtons />
            </label>
            <label class="fld">
              <span>Unit</span>
              <Select v-model="step.delay_unit" :options="delayUnits" optionLabel="label" optionValue="value" />
            </label>
          </div>

          <label v-if="step.channel === 'email'" class="fld" style="margin-top: 0.9rem">
            <span>Subject</span>
            <InputText v-model="step.subject" maxlength="255" />
            <small v-if="fieldError('steps.' + index + '.subject')" class="field-err">{{ fieldError('steps.' + index + '.subject') }}</small>
          </label>

          <label class="fld" style="margin-top: 0.9rem">
            <span>Message</span>
            <Textarea v-model="step.message" rows="4" autoResize />
            <small v-if="fieldError('steps.' + index + '.message')" class="field-err">{{ fieldError('steps.' + index + '.message') }}</small>
          </label>

          <label class="row" style="margin-top: 0.9rem">
            <Checkbox v-model="step.is_active" :binary="true" :inputId="'step-active-' + index" />
            <label :for="'step-active-' + index">Touch is active</label>
          </label>
        </template>
      </Card>
    </div>

    <Dialog v-model:visible="confirmDelete" header="Delete campaign" modal :style="{ width: '28rem' }">
      <p>Delete this campaign and all of its steps? Enrollments are left in place.</p>
      <template #footer>
        <Button label="Cancel" outlined @click="confirmDelete = false" />
        <Button label="Delete" severity="danger" :loading="saving" @click="remove" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.field-err {
  color: var(--red-500, #ef4444);
  font-weight: 500;
}
.flow {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}
.flow-node {
  background: var(--surface-100, #f3f4f6);
  border: 1px solid var(--surface-border, #e2e5ea);
  border-radius: 999px;
  padding: 0.2rem 0.7rem;
  font-size: 13px;
  font-weight: 600;
}
.flow-arrow {
  color: var(--text-color-secondary, #6b7280);
}
</style>
