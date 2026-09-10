<script setup>
import { ref, computed } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import JsonBlock from '../components/JsonBlock.vue'
import { inboundRequests, fmt } from '../data/mock'

const q = ref('')
const tenant = ref(null)
const kindFilter = ref(null)
const expanded = ref({})

const tenants = [...new Set(inboundRequests.map((r) => r.tenant_id))]
const KINDS = ['reply', 'auto-reply', 'bounce', 'loop / own address']
const KIND_TONE = { reply: 'success', 'auto-reply': 'contrast', bounce: 'danger', 'loop / own address': 'warning' }

function requestKind(r) {
  const h = r.headers || {}
  const auto = (h['Auto-Submitted'] || '').toLowerCase()
  if (r.sender.startsWith('MAILER-DAEMON') || (h['Content-Type'] || '').includes('delivery-status'))
    return { label: 'bounce', tone: 'danger' }
  if (r.sender === r.recipient || r.sender.startsWith('campaign+'))
    return { label: 'loop / own address', tone: 'warning' }
  if (
    auto === 'auto-replied' ||
    auto === 'auto-generated' ||
    h['X-Autoreply'] ||
    h['X-Auto-Response-Suppress'] ||
    (h['Precedence'] || '') === 'bulk'
  )
    return { label: 'auto-reply', tone: 'contrast' }
  return { label: 'reply', tone: 'success' }
}

const enriched = computed(() => {
  const seen = {}
  return inboundRequests.map((r, i) => {
    const dup = seen[r.event_id] === true
    seen[r.event_id] = true
    return { ...r, id: r.event_id + '#' + i, kind: requestKind(r), duplicate: dup, when: r.timestamp * 1000 }
  })
})

const rows = computed(() =>
  [...enriched.value]
    .reverse()
    .filter((r) => (tenant.value == null ? true : r.tenant_id === tenant.value))
    .filter((r) => (!kindFilter.value ? true : r.kind.label === kindFilter.value))
    .filter((r) => {
      const s = q.value.trim().toLowerCase()
      if (!s) return true
      return JSON.stringify(r).toLowerCase().includes(s)
    }),
)

const counts = computed(() => {
  const c = {}
  for (const r of enriched.value) c[r.kind.label] = (c[r.kind.label] || 0) + 1
  return c
})
</script>

<template>
  <div>
    <PageHeader
      title="Inbound Requests"
      subtitle="Raw reply.received payloads from the mail gateway, before the pipeline processes them."
    >
      <template #actions>
        <Tag :value="inboundRequests.length + ' received'" severity="secondary" />
      </template>
    </PageHeader>

    <div class="row" style="margin-bottom: 1rem; gap: 0.5rem">
      <Tag
        v-for="k in KINDS"
        :key="k"
        :value="k + ' · ' + (counts[k] || 0)"
        :severity="KIND_TONE[k]"
        style="cursor: pointer"
        :class="{ dim: kindFilter && kindFilter !== k }"
        @click="kindFilter = kindFilter === k ? null : k"
      />
    </div>

    <Card>
      <template #content>
        <div class="row" style="margin-bottom: 0.9rem">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="q" placeholder="Search sender / body / header…" />
          </IconField>
          <Select v-model="tenant" :options="tenants" placeholder="All tenants" showClear style="min-width: 150px" />
          <Select v-model="kindFilter" :options="KINDS" placeholder="All kinds" showClear style="min-width: 180px" />
        </div>

        <DataTable
          v-model:expandedRows="expanded"
          :value="rows"
          dataKey="id"
          size="small"
          stripedRows
          paginator
          :rows="15"
        >
          <Column expander style="width: 3rem" />
          <Column header="event_id">
            <template #body="{ data }">
              <span class="mono">{{ data.event_id }}</span>
              <Tag
                v-if="data.duplicate"
                value="duplicate"
                severity="warning"
                style="margin-left: 0.4rem; transform: scale(0.85)"
              />
            </template>
          </Column>
          <Column header="Kind">
            <template #body="{ data }"><Tag :value="data.kind.label" :severity="data.kind.tone" /></template>
          </Column>
          <Column field="tenant_id" header="Tenant" style="width: 5rem" />
          <Column header="Sender"><template #body="{ data }"><span class="mono">{{ data.sender }}</span></template></Column>
          <Column header="Body">
            <template #body="{ data }"><span class="muted clamp">{{ data.body_plain || data.body_html || '—' }}</span></template>
          </Column>
          <Column header="When"><template #body="{ data }"><span class="muted">{{ fmt(data.when) }}</span></template></Column>
          <template #expansion="{ data }">
            <JsonBlock
              :value="{
                event_id: data.event_id,
                tenant_id: data.tenant_id,
                sender: data.sender,
                recipient: data.recipient,
                body_plain: data.body_plain,
                ...(data.body_html ? { body_html: data.body_html } : {}),
                headers: data.headers,
                timestamp: data.timestamp,
              }"
            />
          </template>
        </DataTable>

        <p class="muted" style="margin-bottom: 0; font-size: 12px">
          Includes auto-replies, a bounce (<code>MAILER-DAEMON</code>), a loop to our own campaign address,
          and a re-delivered duplicate. What the pipeline makes of these is in
          <RouterLink to="/events">Event History</RouterLink>.
        </p>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.clamp {
  display: inline-block;
  max-width: 420px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: bottom;
}
.dim {
  opacity: 0.4;
}
</style>
