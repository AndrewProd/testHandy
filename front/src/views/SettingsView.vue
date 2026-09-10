<script setup>
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { tenants, managers } from '../data/mock'

const toast = useToast()
const sla = ref(8)
const onFailure = ref('create task with sentiment=null, stop campaign')
const autoSuppress = ref('explicit unsubscribe only')
const failureOptions = ['create task with sentiment=null, stop campaign', 'hold for manual review']
const suppressOptions = ['explicit unsubscribe only', 'unsubscribe + wrong_person']
</script>

<template>
  <div>
    <PageHeader title="Settings" subtitle="Tenants, managers, and reply-handling defaults." />

    <div class="grid cols-2">
      <Card>
        <template #title>Tenants</template>
        <template #content>
          <DataTable :value="tenants" size="small">
            <Column field="id" header="ID" style="width: 4rem" />
            <Column field="name" header="Name" />
            <Column header="Plan"><template #body="{ data }"><Tag :value="data.plan" severity="secondary" /></template></Column>
          </DataTable>
        </template>
      </Card>

      <Card>
        <template #title>Managers</template>
        <template #content>
          <DataTable :value="managers" size="small">
            <Column field="name" header="Name" />
            <Column field="role" header="Role" />
            <Column header="Email"><template #body="{ data }"><span class="mono">{{ data.email }}</span></template></Column>
          </DataTable>
        </template>
      </Card>
    </div>

    <Card style="margin-top: 1rem">
      <template #title>Reply handling defaults</template>
      <template #content>
        <div class="stack" style="max-width: 460px">
          <label class="fld"><span>Reply task SLA (hours)</span><InputNumber v-model="sla" /></label>
          <label class="fld"><span>On classifier failure</span><Select v-model="onFailure" :options="failureOptions" /></label>
          <label class="fld"><span>Auto-suppress on</span><Select v-model="autoSuppress" :options="suppressOptions" /></label>
          <div><Button label="Save" icon="pi pi-check" @click="toast.add({ severity: 'success', summary: 'Settings saved', life: 1800 })" /></div>
        </div>
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
