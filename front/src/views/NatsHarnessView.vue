<script setup>
import { onMounted, reactive, ref } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import JsonBlock from '../components/JsonBlock.vue'
import { api } from '../api/http'
import { SENTIMENTS } from '../data/mock'

const form = reactive({
  tenant_id: 42,
  sender: 'd.walker@northshore-homes.ca',
  body_plain: 'Sounds good. Can you call me Thursday afternoon?',
  auto_reply: false,
})
const emailOptions = ref([])
const jobs = ref([])
const publishing = ref(false)
let seq = 100

async function loadClients() {
  try {
    const res = await api('/api/inbound/clients')
    emailOptions.value = (res.data || []).map((c) => ({
      label: `${c.email} · ${c.name || '—'}`,
      value: c.email,
    }))
    if (emailOptions.value.length && !emailOptions.value.some((o) => o.value === form.sender)) {
      form.sender = emailOptions.value[0].value
    }
  } catch {
    emailOptions.value = []
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

async function publish() {
  publishing.value = true
  const job = reactive({
    seq: seq++,
    at: new Date().toISOString(),
    payload: null,
    status: 'publishing',
    result: null,
  })
  jobs.value.unshift(job)

  try {
    const published = await api('/api/inbound/publish', {
      method: 'POST',
      body: {
        tenant_id: Number(form.tenant_id),
        sender: String(form.sender).trim(),
        body_plain: form.body_plain,
        auto_reply: form.auto_reply,
      },
    })
    job.payload = published.event
    job.status = 'waiting'

    let last = null
    for (let i = 0; i < 20; i++) {
      await sleep(400)
      last = await api('/api/inbound/events/' + published.event_id)
      if (last.processed) break
    }

    job.status = 'done'
    job.result = last || { outcome: 'published, consumer has not claimed it yet', data: null }
    if (!last?.processed) {
      job.result = { outcome: 'published to NATS — consumer did not pick it up in time', data: null }
    }
  } catch (e) {
    job.status = 'done'
    job.result = { outcome: e.message || 'publish failed', data: null }
  } finally {
    publishing.value = false
  }
}

onMounted(loadClients)
</script>

<template>
  <div>
    <PageHeader
      title="Reply Center — NATS test harness"
      subtitle="Publishes reply.received to JetStream. The consumer runs ProcessInboundReplyJob and opens a manager task."
    />

    <div class="grid cols-2">
      <Card>
        <template #title>Publish event</template>
        <template #content>
          <div class="stack">
            <div class="row" style="align-items: flex-end">
              <label class="fld" style="width: 110px">
                <span>tenant_id</span>
                <InputNumber v-model="form.tenant_id" :useGrouping="false" />
              </label>
              <label class="fld" style="flex: 1; min-width: 220px">
                <span>sender (client email)</span>
                <Select v-model="form.sender" :options="emailOptions" optionLabel="label" optionValue="value" editable />
              </label>
            </div>
            <label class="fld">
              <span>body_plain</span>
              <Textarea v-model="form.body_plain" rows="3" autoResize />
            </label>
            <div class="row">
              <Checkbox v-model="form.auto_reply" inputId="ar" binary />
              <label for="ar">mark as auto-reply (Auto-Submitted)</label>
            </div>
            <div><Button label="Publish to NATS" icon="pi pi-send" :loading="publishing" @click="publish" /></div>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>Rubric</template>
        <template #content>
          <div class="row">
            <Tag v-for="s in SENTIMENTS" :key="s" :value="s" severity="secondary" />
          </div>
          <p class="muted" style="margin-bottom: 0">
            Sender must be a real client in this tenant. Unknown emails are consumed but do not create a manager task.
          </p>
        </template>
      </Card>
    </div>

    <h2 style="margin: 1.5rem 0 0.5rem">Jobs — payload &amp; result</h2>
    <p v-if="!jobs.length" class="muted">Nothing published yet. Use the form above.</p>

    <div class="stack">
      <Card v-for="j in jobs" :key="j.seq">
        <template #content>
          <div class="row" style="margin-bottom: 0.5rem">
            <span class="mono">#{{ j.seq }}</span>
            <span v-if="j.payload" class="mono muted">{{ j.payload.event_id }}</span>
            <Tag v-if="j.status === 'publishing'" value="publishing…" severity="warning" />
            <Tag v-else-if="j.status === 'waiting'" value="waiting for consumer…" severity="warning" />
            <template v-else>
              <Tag :value="j.result?.outcome" :severity="j.result?.data ? 'success' : 'secondary'" />
              <Tag v-if="j.result?.data" :value="j.result.data.intent || j.result.data.sentiment || 'unclassified'" severity="info" />
              <RouterLink v-if="j.result?.data?.id" :to="'/reply-center/' + j.result.data.id">
                <Button label="Open task" size="small" text icon="pi pi-external-link" />
              </RouterLink>
            </template>
          </div>
          <JsonBlock v-if="j.payload" :value="j.payload" />
          <JsonBlock v-if="j.result?.data" :value="{ id: j.result.data.id, intent: j.result.data.intent, sentiment: j.result.data.sentiment, status: j.result.data.status }" />
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.fld {
  display: grid;
  gap: 0.35rem;
  font-weight: 600;
  font-size: 13px;
}
</style>
