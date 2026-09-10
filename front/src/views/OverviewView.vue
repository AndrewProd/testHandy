<script setup>
import { computed } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import { kpis, repliesByDay, sentimentMix, campaigns, funnel, statusTone } from '../data/mock'

const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const signed = funnel.at(-1).count
const enrolled = funnel[0].count

const textColor = 'rgba(130,140,160,0.9)'
const grid = 'rgba(130,140,160,0.15)'

const repliesData = {
  labels: dayLabels,
  datasets: [{ label: 'Replies', data: repliesByDay, backgroundColor: '#6366f1', borderRadius: 6 }],
}
const repliesOpts = {
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { color: textColor }, grid: { color: grid } },
    y: { ticks: { color: textColor }, grid: { color: grid }, beginAtZero: true },
  },
  maintainAspectRatio: false,
}

const sentimentData = {
  labels: sentimentMix.map((s) => s.label),
  datasets: [
    {
      data: sentimentMix.map((s) => s.value),
      backgroundColor: ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444', '#e11d48', '#8b5cf6', '#94a3b8'],
    },
  ],
}
const sentimentOpts = {
  plugins: { legend: { position: 'right', labels: { color: textColor, boxWidth: 12 } } },
  maintainAspectRatio: false,
}

const campaignRows = computed(() => campaigns)
</script>

<template>
  <div>
    <PageHeader title="Overview" subtitle="Remarketing pipeline health for the current period" />

    <div class="grid cols-4">
      <StatCard label="Replies today" :value="kpis.replies_today" icon="pi pi-comments" />
      <StatCard label="Open reply tasks" :value="kpis.open_tasks" severity="warn" icon="pi pi-inbox" hint="Awaiting a manager" />
      <StatCard label="Active enrollments" :value="kpis.active_enrollments" icon="pi pi-users" />
      <StatCard
        label="Conversion to signed"
        :value="(kpis.conversion_rate * 100).toFixed(1) + '%'"
        icon="pi pi-check-circle"
        :hint="signed + ' signed / ' + enrolled + ' enrolled'"
      />
    </div>

    <div class="grid cols-2" style="margin-top: 1rem">
      <Card>
        <template #title>Replies · last 7 days</template>
        <template #content>
          <div style="height: 220px"><Chart type="bar" :data="repliesData" :options="repliesOpts" /></div>
        </template>
      </Card>
      <Card>
        <template #title>Sentiment mix</template>
        <template #content>
          <div style="height: 220px"><Chart type="doughnut" :data="sentimentData" :options="sentimentOpts" /></div>
        </template>
      </Card>
    </div>

    <Card style="margin-top: 1rem">
      <template #title>Campaigns</template>
      <template #content>
        <DataTable :value="campaignRows" size="small" stripedRows>
          <Column field="name" header="Campaign" />
          <Column header="Status">
            <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
          </Column>
          <Column field="active" header="Active" />
          <Column field="stopped" header="Stopped" />
          <Column field="completed" header="Completed" />
          <Column header="Reply rate">
            <template #body="{ data }">{{ (data.reply_rate * 100).toFixed(0) }}%</template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>
