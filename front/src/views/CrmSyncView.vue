<script setup>
import { reactive } from 'vue'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import { crmSync, crmSyncQueue, crmSyncRuns, fmt, statusTone } from '../data/mock'

const toast = useToast()
const cfg = reactive({ ...crmSync })
const queue = reactive(crmSyncQueue.map((q) => ({ ...q })))

const directionOptions = ['push', 'pull', 'bidirectional']
const conflictOptions = [
  { label: 'Suppression wins', value: 'suppress_wins' },
  { label: 'CRM wins', value: 'crm_wins' },
  { label: 'Newest change wins', value: 'newest_wins' },
]
const scheduleOptions = ['every 5 min', 'every 15 min', 'hourly', 'manual only']

function runSync(kind) {
  toast.add({ severity: 'info', summary: `${kind} sync started`, detail: cfg.endpoint, life: 2200 })
}
function retry(row) {
  row.status = 'pending'
  row.attempts += 1
  row.last_error = null
  toast.add({ severity: 'success', summary: `Re-queued ${row.email}`, life: 1800 })
}
function skip(row) {
  const i = queue.indexOf(row)
  if (i > -1) queue.splice(i, 1)
  toast.add({ severity: 'warn', summary: `Skipped ${row.email}`, life: 1800 })
}
function save() {
  Object.assign(crmSync, cfg)
  toast.add({ severity: 'success', summary: 'Sync settings saved', life: 1800 })
}
</script>

<template>
  <div>
    <PageHeader
      title="CRM Sync"
      subtitle="Synchronise blacklist / suppression data with the CRM — direction, schedule, queue and conflicts."
    >
      <template #actions>
        <Tag :value="cfg.enabled ? 'enabled' : 'disabled'" :severity="cfg.enabled ? 'success' : 'secondary'" />
        <Button label="Run delta sync" icon="pi pi-sync" size="small" outlined @click="runSync('Delta')" />
        <Button label="Run full sync" icon="pi pi-refresh" size="small" @click="runSync('Full')" />
      </template>
    </PageHeader>

    <div class="grid cols-4">
      <StatCard label="In sync" :value="cfg.in_sync" icon="pi pi-check-circle" />
      <StatCard label="Pending push → CRM" :value="cfg.pending_push" severity="warn" icon="pi pi-arrow-up" />
      <StatCard label="Pending pull ← CRM" :value="cfg.pending_pull" severity="warn" icon="pi pi-arrow-down" />
      <StatCard label="Failed" :value="cfg.failed" :severity="cfg.failed ? 'danger' : ''" icon="pi pi-exclamation-triangle" />
    </div>

    <div class="grid cols-2" style="margin-top: 1rem">
      <Card>
        <template #title>Connection &amp; schedule</template>
        <template #content>
          <div class="stack">
            <div class="row">
              <Checkbox v-model="cfg.enabled" inputId="en" binary />
              <label for="en" style="font-weight: 600">Sync enabled</label>
            </div>
            <label class="fld">
              <span>Direction</span>
              <Select v-model="cfg.direction" :options="directionOptions" />
            </label>
            <label class="fld">
              <span>Schedule</span>
              <Select v-model="cfg.schedule" :options="scheduleOptions" />
            </label>
            <label class="fld">
              <span>Conflict policy</span>
              <Select v-model="cfg.conflict_policy" :options="conflictOptions" optionLabel="label" optionValue="value" />
            </label>
            <label class="fld">
              <span>CRM endpoint</span>
              <InputText v-model="cfg.endpoint" />
            </label>
            <div class="row">
              <Button label="Save settings" icon="pi pi-save" @click="save" />
              <span class="muted">
                last run {{ fmt(cfg.last_run_at) }} · next {{ fmt(cfg.next_run_at) }}
              </span>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>Field mapping</template>
        <template #content>
          <DataTable :value="cfg.field_map" size="small">
            <Column header="Reply Center">
              <template #body="{ data }"><span class="mono">{{ data.local }}</span></template>
            </Column>
            <Column header="">
              <template #body><i class="pi pi-arrows-h muted" /></template>
            </Column>
            <Column header="CRM">
              <template #body="{ data }"><span class="mono">{{ data.crm }}</span></template>
            </Column>
            <Column header="">
              <template #body="{ data }"><Tag v-if="data.key" value="match key" severity="secondary" /></template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>

    <Card style="margin-top: 1rem">
      <template #title>
        <div class="row">
          <span>Sync queue</span>
          <Tag :value="queue.filter((q) => q.status === 'failed').length + ' failed'" severity="danger" />
          <Tag :value="queue.filter((q) => q.status === 'pending').length + ' pending'" severity="warning" />
        </div>
      </template>
      <template #content>
        <DataTable :value="queue" size="small" stripedRows>
          <Column header="Email"><template #body="{ data }"><span class="mono">{{ data.email }}</span></template></Column>
          <Column header="Direction">
            <template #body="{ data }">
              <Tag :value="data.direction" :severity="data.direction === 'push' ? 'info' : 'secondary'" />
            </template>
          </Column>
          <Column field="reason" header="Reason" />
          <Column field="origin" header="Origin" />
          <Column header="Status">
            <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
          </Column>
          <Column field="attempts" header="Tries" />
          <Column header="Last error">
            <template #body="{ data }"><span class="muted">{{ data.last_error || '—' }}</span></template>
          </Column>
          <Column header="Queued"><template #body="{ data }"><span class="muted">{{ fmt(data.queued_at) }}</span></template></Column>
          <Column header="">
            <template #body="{ data }">
              <div class="row" style="gap: 0.35rem">
                <Button icon="pi pi-replay" size="small" text @click="retry(data)" v-tooltip.top="'Retry'" />
                <Button icon="pi pi-times" size="small" text severity="danger" @click="skip(data)" v-tooltip.top="'Skip'" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Card style="margin-top: 1rem">
      <template #title>Recent sync runs</template>
      <template #content>
        <DataTable :value="crmSyncRuns" size="small" stripedRows>
          <Column header="#"><template #body="{ data }"><span class="mono">{{ data.id }}</span></template></Column>
          <Column header="Started"><template #body="{ data }"><span class="muted">{{ fmt(data.started_at) }}</span></template></Column>
          <Column field="direction" header="Direction" />
          <Column field="pushed" header="Pushed" />
          <Column field="pulled" header="Pulled" />
          <Column field="conflicts" header="Conflicts" />
          <Column header="Duration">
            <template #body="{ data }">{{ (data.duration_ms / 1000).toFixed(1) }}s</template>
          </Column>
          <Column header="Result">
            <template #body="{ data }">
              <Tag :value="data.result" :severity="statusTone(data.result === 'failed' ? 'failed' : data.result)" />
              <span v-if="data.note" class="muted"> · {{ data.note }}</span>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
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
