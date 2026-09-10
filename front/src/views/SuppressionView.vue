<script setup>
import PageHeader from '../components/PageHeader.vue'
import { suppression, fmt } from '../data/mock'

const sourceLabel = {
  reply_pipeline: 'Reply pipeline',
  support_desk: 'Support desk',
  crm_connector: 'CRM connector',
}
const pending = suppression.filter((s) => !s.crm_synced).length
</script>

<template>
  <div>
    <PageHeader
      title="Suppression & Blacklist"
      subtitle="clients.suppressed_at is the single source of truth. Synced both ways with the CRM."
    >
      <template #actions>
        <RouterLink to="/crm-sync">
          <Button label="Manage CRM sync" icon="pi pi-sync" size="small" outlined />
        </RouterLink>
        <Tag :value="pending + ' pending sync'" :severity="pending ? 'warning' : 'success'" />
      </template>
    </PageHeader>

    <Card>
      <template #content>
        <DataTable :value="suppression" size="small" stripedRows>
          <Column field="client" header="Client" />
          <Column header="Email"><template #body="{ data }"><span class="mono">{{ data.email }}</span></template></Column>
          <Column header="Source">
            <template #body="{ data }"><Tag :value="sourceLabel[data.source] || data.source" severity="secondary" /></template>
          </Column>
          <Column field="reason" header="Reason" />
          <Column header="Added"><template #body="{ data }"><span class="muted">{{ fmt(data.added_at) }}</span></template></Column>
          <Column header="CRM">
            <template #body="{ data }">
              <Tag :value="data.crm_synced ? 'synced' : 'queued'" :severity="data.crm_synced ? 'success' : 'warning'" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Message severity="secondary" :closable="false" style="margin-top: 1rem">
      Set from three places: the support desk, the CRM connector (when a suppression lands in the CRM),
      and the reply pipeline (explicit opt-out). <code>SendCampaignStepJob</code> checks it before every send.
    </Message>
  </div>
</template>
