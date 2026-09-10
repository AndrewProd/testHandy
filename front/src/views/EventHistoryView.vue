<script setup>
import { ref, computed } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import JsonBlock from '../components/JsonBlock.vue'
import { busEvents, clientName, fmt } from '../data/mock'

const types = [...new Set(busEvents.map((e) => e.type))]
const typeFilter = ref(null)
const q = ref('')
const expanded = ref({})

const rows = computed(() =>
  [...busEvents]
    .reverse()
    .filter((e) => (!typeFilter.value ? true : e.type === typeFilter.value))
    .filter((e) => {
      const s = q.value.trim().toLowerCase()
      if (!s) return true
      return JSON.stringify(e).toLowerCase().includes(s)
    })
    .map((e) => ({ ...e, id: e.seq, client: e.client_id ? clientName(e.client_id) : '—' })),
)
</script>

<template>
  <div>
    <PageHeader
      title="Event History"
      subtitle="Full audit stream from the bus — reply.classified, task, campaign, suppression, CRM sync, handoff. Raw inbound payloads live in Inbound Requests."
    >
      <template #actions>
        <Tag :value="busEvents.length + ' events'" severity="secondary" />
      </template>
    </PageHeader>

    <Card>
      <template #content>
        <div class="row" style="margin-bottom: 0.9rem">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="q" placeholder="Search payload…" />
          </IconField>
          <Select v-model="typeFilter" :options="types" placeholder="All types" showClear style="min-width: 220px" />
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
          <Column header="Seq"><template #body="{ data }"><span class="mono">#{{ data.seq }}</span></template></Column>
          <Column header="Type"><template #body="{ data }"><Tag :value="data.type" severity="info" /></template></Column>
          <Column field="client" header="Client" />
          <Column header="When"><template #body="{ data }"><span class="muted">{{ fmt(data.ts) }}</span></template></Column>
          <template #expansion="{ data }"><JsonBlock :value="data.payload" /></template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>
