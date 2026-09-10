<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import BarList from '../components/BarList.vue'
import {
  analyticsKpis,
  analyticsFunnel,
  revenueTrend,
  replyIntent,
  campaignPerf,
  aiPerf,
  revenueByCampaign,
  aiIntentRevenue,
  analyticsCustomers,
  liveFeedSeed,
  fmtNum,
  fmtMoney,
  fmtPct,
} from '../data/mock'

/* ---- global filter bar (cosmetic in the prototype) ---- */
const range = ref('Last 30 days')
const campaign = ref('All campaigns')
const flow = ref('All flows')
const ranges = ['Last 7 days', 'Last 30 days', 'Last 90 days', 'Quarter to date']
const campaignOpts = ['All campaigns', ...campaignPerf.map((c) => c.name)]
const flowOpts = ['All flows', 'Win-back Q3', 'Long-term nurture', 'Hot handoff']

/* ---- KPI helpers ---- */
function kpiValue(k) {
  if (k.money) return fmtMoney(k.value)
  if (k.pct) return fmtPct(k.value)
  return fmtNum(k.value)
}

/* ---- funnel ---- */
const drill = ref(null) // the clicked funnel stage
function funnelConversion(i) {
  if (i === 0) return null
  const cur = analyticsFunnel[i]
  const prev = analyticsFunnel[i - 1]
  // Revenue is a $ amount, not a count — a stage-to-stage ratio is meaningless there
  if (cur.money || prev.money || !prev.count) return null
  return cur.count / prev.count
}
// label shown between two blocks: conversion %, or avg deal for the money block
function gapLabel(i) {
  const cur = analyticsFunnel[i]
  const prev = analyticsFunnel[i - 1]
  if (cur.money && prev.count) return 'avg deal $' + Math.round(cur.count / prev.count).toLocaleString('en-US')
  const c = funnelConversion(i)
  return c != null ? fmtPct(c, 1) : ''
}
function selectStage(stage) {
  if (!stage.drill) return
  drill.value = drill.value?.key === stage.key ? null : stage
}
const drillCustomers = computed(() =>
  drill.value ? analyticsCustomers.filter((c) => c.progress >= drill.value.drill) : [],
)

/* ---- customer journey dialog ---- */
const journey = ref(null)
const showJourney = ref(false)
function openJourney(c) {
  journey.value = c
  showJourney.value = true
}
const journeyIcon = {
  enter: 'pi pi-sign-in',
  email: 'pi pi-send',
  reply: 'pi pi-comment',
  ai: 'pi pi-sparkles',
  task: 'pi pi-flag',
  qualified: 'pi pi-check',
  manager: 'pi pi-user',
  quote: 'pi pi-file',
  contract: 'pi pi-verified',
  revenue: 'pi pi-dollar',
}

/* ---- charts ---- */
const axis = 'rgba(130,140,160,0.85)'
const gridc = 'rgba(130,140,160,0.15)'
const revenueData = {
  labels: revenueTrend.map((p) => p.label),
  datasets: [
    {
      label: 'Revenue',
      data: revenueTrend.map((p) => p.value),
      borderColor: '#6366f1',
      backgroundColor: 'rgba(99,102,241,0.15)',
      fill: true,
      tension: 0.4,
      pointRadius: 0,
    },
  ],
}
const revenueOpts = {
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks: { color: axis }, grid: { color: gridc } },
    y: { ticks: { color: axis, callback: (v) => '$' + v / 1000 + 'K' }, grid: { color: gridc } },
  },
  maintainAspectRatio: false,
}

/* ---- live feed ---- */
const feed = ref(
  liveFeedSeed.slice(0, 5).map((e, i) => ({ ...e, id: i, secs: (i + 1) * 6 })),
)
let feedTimer
let feedId = 100
onMounted(() => {
  feedTimer = setInterval(() => {
    feed.value.forEach((e) => (e.secs += 3))
    const pick = liveFeedSeed[Math.floor(Math.random() * liveFeedSeed.length)]
    feed.value.unshift({ ...pick, id: feedId++, secs: 2 })
    feed.value = feed.value.slice(0, 7)
  }, 3200)
})
onUnmounted(() => clearInterval(feedTimer))
function ago(s) {
  if (s < 60) return s + 's ago'
  return Math.floor(s / 60) + 'm ago'
}
</script>

<template>
  <div>
    <PageHeader title="Remarketing Analytics" subtitle="What is working, and which actions bring the money." />

    <!-- filter bar -->
    <div class="filter-bar card-ish">
      <Select v-model="range" :options="ranges" />
      <Select v-model="campaign" :options="campaignOpts" />
      <Select v-model="flow" :options="flowOpts" />
      <span class="spacer" />
      <span class="muted">prototype · mock data (Postgres later, not ClickHouse)</span>
    </div>

    <!-- KPI row -->
    <div class="kpi-row">
      <div v-for="k in analyticsKpis" :key="k.key" class="kpi">
        <div class="kpi-label">{{ k.label }}</div>
        <div class="kpi-value">{{ kpiValue(k) }}</div>
        <div class="kpi-delta" :class="k.delta >= 0 ? 'up' : 'down'">
          <i :class="k.delta >= 0 ? 'pi pi-arrow-up-right' : 'pi pi-arrow-down-right'" />
          {{ (k.delta >= 0 ? '+' : '') + fmtPct(k.delta) }}
        </div>
      </div>
    </div>

    <!-- revenue trend -->
    <Card style="margin-top: 1rem">
      <template #title>Revenue</template>
      <template #content><div style="height: 200px"><Chart type="line" :data="revenueData" :options="revenueOpts" /></div></template>
    </Card>

    <!-- funnel + reply intent -->
    <div class="grid cols-2" style="margin-top: 1rem; align-items: start">
      <Card>
        <template #title>Conversion Funnel <span class="muted" style="font-weight: 400">— click a stage</span></template>
        <template #content>
          <div class="funnel">
            <template v-for="(s, i) in analyticsFunnel" :key="s.key">
              <div v-if="i > 0" class="funnel-gap">
                <span class="funnel-pct">{{ gapLabel(i) }}</span>
              </div>
              <button
                class="funnel-block"
                :class="{ active: drill?.key === s.key, dead: !s.drill }"
                :disabled="!s.drill"
                @click="selectStage(s)"
              >
                <span class="fb-count">{{ s.money ? '$' + fmtNum(s.count) : fmtNum(s.count) }}</span>
                <span class="fb-label">{{ s.label }}</span>
              </button>
            </template>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>Reply Intent</template>
        <template #content>
          <BarList :items="replyIntent.map((r) => ({ label: r.label, value: Math.round(r.pct * 100) }))" unit="%" />
        </template>
      </Card>
    </div>

    <!-- drill-down customers -->
    <Card v-if="drill" style="margin-top: 1rem">
      <template #title>
        <div class="row">
          <span>Customers · reached “{{ drill.label }}”</span>
          <Tag :value="drillCustomers.length + ' sample'" severity="secondary" />
          <span class="spacer" />
          <Button icon="pi pi-times" text rounded size="small" @click="drill = null" />
        </div>
      </template>
      <template #content>
        <DataTable
          :value="drillCustomers"
          size="small"
          stripedRows
          selectionMode="single"
          dataKey="id"
          @rowSelect="(e) => openJourney(e.data)"
        >
          <Column header="Customer"><template #body="{ data }">#{{ data.id }} · {{ data.name }}</template></Column>
          <Column field="campaign" header="Campaign" />
          <Column header="Intent"><template #body="{ data }"><Tag :value="data.intent" severity="info" /></template></Column>
          <Column header="Confidence"><template #body="{ data }">{{ data.confidence.toFixed(2) }}</template></Column>
          <Column header="Stage"><template #body="{ data }"><Tag :value="data.stage" severity="secondary" /></template></Column>
          <Column header="Revenue"><template #body="{ data }">{{ data.revenue ? fmtMoney(data.revenue) : '—' }}</template></Column>
          <Column header=""><template #body><i class="pi pi-angle-right muted" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <!-- campaign + AI performance -->
    <div class="grid cols-2" style="margin-top: 1rem">
      <Card>
        <template #title>Campaign Performance <span class="muted" style="font-weight: 400">— reply rate</span></template>
        <template #content>
          <BarList :items="campaignPerf.map((c) => ({ label: c.name, value: +(c.reply_rate * 100).toFixed(1) }))" unit="%" />
        </template>
      </Card>
      <Card>
        <template #title>AI Performance</template>
        <template #content>
          <div class="grid cols-3">
            <div><div class="muted">Accuracy</div><strong class="big">{{ fmtPct(aiPerf.accuracy) }}</strong></div>
            <div><div class="muted">Confidence</div><strong class="big">{{ fmtPct(aiPerf.confidence) }}</strong></div>
            <div><div class="muted">Fallback</div><strong class="big">{{ fmtPct(aiPerf.fallback) }}</strong></div>
          </div>
        </template>
      </Card>
    </div>

    <!-- revenue by campaign -->
    <Card style="margin-top: 1rem">
      <template #title>Revenue by Campaign</template>
      <template #content>
        <div class="rev-bars">
          <div v-for="r in revenueByCampaign" :key="r.name" class="rev-row">
            <span class="rev-name">{{ r.name }}</span>
            <div class="rev-track">
              <div
                class="rev-fill"
                :style="{ width: (r.value / Math.max(...revenueByCampaign.map((x) => x.value))) * 100 + '%' }"
              />
            </div>
            <span class="rev-val">{{ fmtMoney(r.value) }}</span>
          </div>
        </div>
      </template>
    </Card>

    <!-- AI -> Revenue (the important one) -->
    <Card style="margin-top: 1rem">
      <template #title>AI Intent → Revenue</template>
      <template #content>
        <DataTable :value="aiIntentRevenue" size="small" stripedRows>
          <Column header="Intent"><template #body="{ data }"><Tag :value="data.intent" severity="info" /></template></Column>
          <Column field="replies" header="Replies"><template #body="{ data }">{{ fmtNum(data.replies) }}</template></Column>
          <Column field="contracts" header="Contracts" />
          <Column header="Conversion">
            <template #body="{ data }">
              <strong :style="{ color: data.conversion > 0.05 ? '#16a34a' : data.conversion < 0.01 ? '#dc2626' : 'inherit' }">
                {{ fmtPct(data.conversion, 2) }}
              </strong>
            </template>
          </Column>
        </DataTable>
        <p class="muted" style="margin-bottom: 0">
          Read: when AI says <code>INTERESTED</code>, ~8.8% of those replies become a contract.
        </p>
      </template>
    </Card>

    <!-- live feed -->
    <Card style="margin-top: 1rem">
      <template #title><span class="live-dot" /> Live activity</template>
      <template #content>
        <TransitionGroup name="feed" tag="div" class="feed">
          <div v-for="e in feed" :key="e.id" class="feed-row">
            <i :class="journeyIcon[e.kind] || 'pi pi-circle'" class="feed-ic" />
            <div>
              <div>{{ e.text }} <span v-if="e.meta" class="muted">→ {{ e.meta }}</span></div>
              <div class="muted feed-ago">{{ ago(e.secs) }}</div>
            </div>
          </div>
        </TransitionGroup>
      </template>
    </Card>

    <!-- customer journey dialog -->
    <Dialog
      v-model:visible="showJourney"
      modal
      :style="{ width: '480px' }"
      :header="journey ? `Customer #${journey.id} · ${journey.name}` : ''"
    >
      <div v-if="journey">
        <div class="row" style="margin-bottom: 0.75rem">
          <Tag :value="journey.intent" severity="info" />
          <Tag :value="'confidence ' + journey.confidence.toFixed(2)" severity="secondary" />
          <Tag :value="journey.campaign" severity="secondary" />
        </div>
        <Timeline :value="journey.timeline">
          <template #opposite="{ item }"><span class="muted mono">{{ item.date }}</span></template>
          <template #marker="{ item }">
            <span class="tl-marker"><i :class="journeyIcon[item.kind] || 'pi pi-circle-fill'" /></span>
          </template>
          <template #content="{ item }">
            <div>{{ item.text }}</div>
            <div v-if="item.meta" class="muted" style="font-size: 12px">{{ item.meta }}</div>
          </template>
        </Timeline>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.filter-bar {
  display: flex;
  gap: 0.6rem;
  align-items: center;
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--surface-border);
  border-radius: 10px;
  background: var(--surface-card);
  flex-wrap: wrap;
}
.filter-bar :deep(.p-dropdown) {
  min-width: 160px;
}

.kpi-row {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.75rem;
  margin-top: 1rem;
}
.kpi {
  border: 1px solid var(--surface-border);
  border-radius: 10px;
  background: var(--surface-card);
  padding: 0.8rem 0.9rem;
}
.kpi-label {
  font-size: 11px;
  color: var(--text-color-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.kpi-value {
  font-size: 1.45rem;
  font-weight: 750;
  margin: 0.15rem 0;
}
.kpi-delta {
  font-size: 12px;
  font-weight: 600;
}
.kpi-delta.up {
  color: #16a34a;
}
.kpi-delta.down {
  color: #dc2626;
}
@media (max-width: 1100px) {
  .kpi-row {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 640px) {
  .kpi-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* funnel */
.funnel {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.funnel-block {
  width: 100%;
  max-width: 360px;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
  border-radius: 10px;
  padding: 0.55rem 0.9rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  transition: all 0.12s;
}
.funnel-block:not(.dead):hover {
  border-color: var(--primary-color);
}
.funnel-block.active {
  border-color: var(--primary-color);
  background: color-mix(in srgb, var(--primary-color) 10%, transparent);
}
.funnel-block.dead {
  cursor: default;
  opacity: 0.7;
}
.fb-count {
  font-weight: 750;
  font-size: 1.05rem;
}
.fb-label {
  font-size: 12px;
  color: var(--text-color-secondary);
}
.funnel-gap {
  height: 22px;
  width: 2px;
  background: var(--surface-border);
  position: relative;
}
.funnel-pct {
  position: absolute;
  left: 10px;
  top: 2px;
  font-size: 11px;
  color: var(--text-color-secondary);
  white-space: nowrap;
}

.big {
  font-size: 1.4rem;
}

/* revenue bars */
.rev-bars {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}
.rev-row {
  display: grid;
  grid-template-columns: 180px 1fr 70px;
  align-items: center;
  gap: 0.7rem;
  font-size: 13px;
}
.rev-name {
  color: var(--text-color-secondary);
}
.rev-track {
  background: var(--surface-100, rgba(0, 0, 0, 0.05));
  border-radius: 6px;
  height: 20px;
  overflow: hidden;
}
.rev-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #7c3aed);
}
.rev-val {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/* live feed */
.live-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #16a34a;
  margin-right: 6px;
  box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.5);
  animation: pulse 1.8s infinite;
}
@keyframes pulse {
  70% {
    box-shadow: 0 0 0 8px rgba(22, 163, 74, 0);
  }
}
.feed {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.feed-row {
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
  font-size: 13px;
}
.feed-ic {
  color: var(--primary-color);
  margin-top: 2px;
}
.feed-ago {
  font-size: 11px;
}
.feed-enter-active {
  transition: all 0.3s;
}
.feed-enter-from {
  opacity: 0;
  transform: translateY(-6px);
}

.tl-marker {
  display: inline-flex;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  align-items: center;
  justify-content: center;
  background: var(--surface-100, rgba(0, 0, 0, 0.06));
  font-size: 11px;
}
</style>
