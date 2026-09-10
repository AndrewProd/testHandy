<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { fetchCampaigns, updateCampaignStatus } from '../api/campaigns'
import { statusTone } from '../data/mock'

const router = useRouter()
const toast = useToast()
const campaigns = ref([])
const loading = ref(true)
const error = ref('')

const channelLabel = {
  email: 'Email',
  sms: 'SMS',
  whatsapp: 'WhatsApp',
}

function meter(c) {
  return [
    { label: 'active', value: c.active, color: '#22c55e' },
    { label: 'stopped', value: c.stopped, color: '#f59e0b' },
    { label: 'completed', value: c.completed, color: '#3b82f6' },
  ]
}

function formatDelay(minutes) {
  if (!minutes) return 'immediately'
  if (minutes % 1440 === 0) {
    const days = minutes / 1440
    return days === 1 ? 'wait 1 day' : `wait ${days} days`
  }
  if (minutes % 60 === 0) {
    const hours = minutes / 60
    return hours === 1 ? 'wait 1 hour' : `wait ${hours} hours`
  }
  return `wait ${minutes} min`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchCampaigns()
    campaigns.value = res.data
  } catch (e) {
    error.value = e.message || 'Could not load campaigns'
  } finally {
    loading.value = false
  }
}

async function toggle(c) {
  const next = c.status === 'active' ? 'paused' : 'active'
  try {
    const res = await updateCampaignStatus(c.id, next)
    const idx = campaigns.value.findIndex((row) => row.id === c.id)
    if (idx !== -1) campaigns.value[idx] = res.data
    toast.add({ severity: 'success', summary: next === 'paused' ? 'Paused' : 'Resumed', life: 1600 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message || 'Could not update status', life: 2200 })
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Drip Campaigns" subtitle="Automated sequences against dormant leads. A reply stops the sequence.">
      <template #actions>
        <Button label="New campaign" icon="pi pi-plus" @click="router.push({ name: 'campaign-new' })" />
      </template>
    </PageHeader>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <p v-else-if="loading" class="muted">Loading campaigns…</p>
    <p v-else-if="!campaigns.length" class="muted">No campaigns yet. Create one to start a drip sequence.</p>

    <div v-else class="stack">
      <Card v-for="c in campaigns" :key="c.id">
        <template #content>
          <div class="row">
            <h2 style="margin: 0">
              <a href="#" class="campaign-link" @click.prevent="router.push({ name: 'campaign-edit', params: { id: c.id } })">{{ c.name }}</a>
            </h2>
            <Tag :value="c.status" :severity="statusTone(c.status)" />
            <span class="spacer" />
            <span class="muted">{{ c.step_count }} steps<template v-if="c.cadence_days"> · every {{ c.cadence_days }} days</template></span>
            <Button :label="c.status === 'active' ? 'Pause' : 'Resume'" size="small" outlined @click="toggle(c)" />
            <Button label="Edit" size="small" icon="pi pi-pencil" @click="router.push({ name: 'campaign-edit', params: { id: c.id } })" />
          </div>
          <p v-if="c.description" class="muted" style="margin: 0.45rem 0 0">{{ c.description }}</p>
          <div class="grid cols-4" style="margin: 0.9rem 0">
            <div><div class="muted">Enrolled</div><strong>{{ c.enrolled }}</strong></div>
            <div><div class="muted">Active</div><strong>{{ c.active }}</strong></div>
            <div><div class="muted">Stopped (replied)</div><strong>{{ c.stopped }}</strong></div>
            <div><div class="muted">Reply rate</div><strong>{{ (c.reply_rate * 100).toFixed(0) }}%</strong></div>
          </div>
          <MeterGroup :value="meter(c)" />
          <ol v-if="c.steps?.length" class="step-chain">
            <li v-for="step in c.steps" :key="step.id">
              <span class="muted">{{ formatDelay(step.delay_minutes) }}</span>
              <span>{{ channelLabel[step.channel] || step.channel }}</span>
              <span class="muted">{{ step.subject || step.message }}</span>
            </li>
          </ol>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.campaign-link {
  color: inherit;
}
.campaign-link:hover {
  color: var(--primary-color, #3b82f6);
}
.step-chain {
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}
.step-chain li {
  display: flex;
  gap: 0.55rem;
  align-items: center;
  flex-wrap: wrap;
  font-size: 13px;
}
</style>
