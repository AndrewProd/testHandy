<script setup>
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'
import BarList from '../components/BarList.vue'
import { sentimentMix, kpis, promptVersions } from '../data/mock'

const failures = [
  { label: 'timeout', value: 7 },
  { label: 'invalid JSON', value: 11 },
  { label: 'off-rubric label', value: 4 },
]
const active = promptVersions.find((p) => p.status === 'active')
</script>

<template>
  <div>
    <PageHeader
      title="Classification Metrics"
      subtitle="How the sentiment classifier is performing on inbound replies."
    >
      <template #actions><Tag :value="'prompt ' + active.version" severity="info" /></template>
    </PageHeader>

    <div class="grid cols-4">
      <StatCard label="Accuracy (sampled)" :value="(active.accuracy * 100).toFixed(0) + '%'" icon="pi pi-check" />
      <StatCard
        label="Failure rate · 7d"
        :value="(kpis.classifier_fail_rate_7d * 100).toFixed(1) + '%'"
        severity="warn"
        icon="pi pi-exclamation-triangle"
        hint="Handled: task still created, sentiment = null"
      />
      <StatCard label="Avg latency" value="810 ms" icon="pi pi-clock" />
      <StatCard label="Est. cost · 30d" value="$4.20" icon="pi pi-dollar" hint="Every inbound reply is classified" />
    </div>

    <div class="grid cols-2" style="margin-top: 1rem">
      <Card>
        <template #title>Predicted sentiment mix</template>
        <template #content><BarList :items="sentimentMix" /></template>
      </Card>
      <Card>
        <template #title>Failure breakdown · 7d</template>
        <template #content>
          <BarList :items="failures" />
          <p class="muted" style="margin-bottom: 0">
            None of these block the pipeline — the job records <code>sentiment = null</code>, stops the
            campaign, and leaves the task for a manager to label.
          </p>
        </template>
      </Card>
    </div>
  </div>
</template>
