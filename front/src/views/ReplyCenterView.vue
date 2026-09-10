<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { fetchReplyTasks, updateReplyTask } from '../api/replyTasks'
import {
  TASK_STATUSES,
  SENTIMENTS,
  managerName,
  fmt,
  sentimentTone,
  statusTone,
} from '../data/mock'

const router = useRouter()
const toast = useToast()
const tasks = ref([])
const loading = ref(true)
const error = ref('')

const VIEWS = [
  { label: 'Table', value: 'table', icon: 'pi pi-list' },
  { label: 'Board', value: 'board', icon: 'pi pi-th-large' },
]
const view = ref(localStorage.getItem('rc-view') || 'table')
function setView(v) {
  if (!v) return
  view.value = v
  localStorage.setItem('rc-view', v)
}

const q = ref('')
const status = ref(null)
const sentiment = ref(null)
const sentimentOptions = [...SENTIMENTS, 'unclassified']

function clientLabel(t) {
  return t.client?.name || t.client_name || `client #${t.client_id}`
}

const rows = computed(() =>
  tasks.value
    .filter((t) => (!status.value ? true : t.status === status.value))
    .filter((t) =>
      !sentiment.value
        ? true
        : sentiment.value === 'unclassified'
          ? t.sentiment === null
          : t.sentiment === sentiment.value,
    )
    .filter((t) => {
      const s = q.value.trim().toLowerCase()
      if (!s) return true
      return (
        clientLabel(t).toLowerCase().includes(s) ||
        (t.body || '').toLowerCase().includes(s) ||
        t.event_id.toLowerCase().includes(s)
      )
    })
    .map((t) => ({ ...t, client_label: clientLabel(t), assignee: managerName(t.assignee_id) })),
)

const openCount = computed(() => tasks.value.filter((t) => t.status === 'open').length)

const columns = computed(() =>
  TASK_STATUSES.map((s) => ({
    status: s,
    tasks: tasks.value.filter((t) => t.status === s),
  })),
)

const draggingId = ref(null)
const dragOver = ref(null)

function onDragStart(e, t) {
  draggingId.value = t.id
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(t.id))
}

async function onDrop(colStatus) {
  dragOver.value = null
  const t = tasks.value.find((x) => x.id === draggingId.value)
  draggingId.value = null
  if (!t || t.status === colStatus) return
  const from = t.status
  t.status = colStatus
  try {
    await updateReplyTask(t.id, { status: colStatus })
    toast.add({ severity: 'success', summary: `Task #${t.id}`, detail: `${from} → ${colStatus}`, life: 1800 })
  } catch (e) {
    t.status = from
    toast.add({ severity: 'error', summary: e.message || 'Could not update status', life: 2200 })
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchReplyTasks()
    tasks.value = res.data
  } catch (e) {
    error.value = e.message || 'Could not load reply tasks'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader
      title="Reply Center"
      subtitle="Every customer reply becomes one manager task. The manager owns status changes — and field visits."
    >
      <template #actions>
        <SelectButton
          :modelValue="view"
          :options="VIEWS"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          @update:modelValue="setView"
        >
          <template #option="{ option }">
            <i :class="option.icon" style="margin-right: 0.35rem" />{{ option.label }}
          </template>
        </SelectButton>
        <Tag :value="openCount + ' open'" severity="warning" />
        <Tag :value="tasks.length + ' total'" severity="secondary" />
      </template>
    </PageHeader>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <p v-else-if="loading" class="muted">Loading queue…</p>

    <Card v-else-if="view === 'table'">
      <template #content>
        <div class="row" style="margin-bottom: 0.9rem">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="q" placeholder="Search client, body, event id…" />
          </IconField>
          <Select v-model="status" :options="TASK_STATUSES" placeholder="All statuses" showClear style="min-width: 180px" />
          <Select v-model="sentiment" :options="sentimentOptions" placeholder="All sentiments" showClear style="min-width: 180px" />
        </div>

        <DataTable
          :value="rows"
          size="small"
          stripedRows
          paginator
          :rows="10"
          selectionMode="single"
          dataKey="id"
          @rowSelect="(e) => router.push('/reply-center/' + e.data.id)"
        >
          <Column field="id" header="#" style="width: 3rem" />
          <Column field="client_label" header="Client" sortable />
          <Column header="Sentiment">
            <template #body="{ data }">
              <Tag v-if="data.sentiment" :value="data.sentiment" :severity="sentimentTone(data.sentiment)" />
              <Tag v-else value="unclassified" severity="secondary" />
            </template>
          </Column>
          <Column header="Intent">
            <template #body="{ data }">
              <Tag v-if="data.intent" :value="data.intent" :severity="sentimentTone(data.intent)" />
              <span v-else class="muted">—</span>
            </template>
          </Column>
          <Column header="Status" sortable field="status">
            <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
          </Column>
          <Column field="assignee" header="Assignee" />
          <Column header="Created" sortable field="created_at">
            <template #body="{ data }"><span class="muted">{{ fmt(data.created_at) }}</span></template>
          </Column>
          <Column header="Reply">
            <template #body="{ data }"><span class="muted clamp">{{ data.body }}</span></template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <div v-else class="board">
      <div
        v-for="col in columns"
        :key="col.status"
        class="col"
        :class="{ over: dragOver === col.status }"
        @dragover.prevent="dragOver = col.status"
        @dragleave="dragOver = dragOver === col.status ? null : dragOver"
        @drop="onDrop(col.status)"
      >
        <div class="col-head">
          <Tag :value="col.status" :severity="statusTone(col.status)" />
          <span class="muted">{{ col.tasks.length }}</span>
        </div>

        <div class="col-body">
          <div
            v-for="t in col.tasks"
            :key="t.id"
            class="kcard"
            :class="{ dragging: draggingId === t.id }"
            draggable="true"
            @dragstart="onDragStart($event, t)"
            @dragend="draggingId = null"
            @click="router.push('/reply-center/' + t.id)"
          >
            <div class="kcard-top">
              <span class="mono muted">#{{ t.id }}</span>
              <Tag
                v-if="t.intent || t.sentiment"
                :value="t.intent || t.sentiment"
                :severity="sentimentTone(t.intent || t.sentiment)"
                style="transform: scale(0.85); transform-origin: right"
              />
              <Tag v-else value="unclassified" severity="secondary" style="transform: scale(0.85); transform-origin: right" />
            </div>
            <div class="kcard-client">{{ clientLabel(t) }}</div>
            <div class="kcard-body">{{ t.body }}</div>
            <div class="kcard-foot muted">
              <span>{{ t.confidence != null ? Number(t.confidence).toFixed(2) : '—' }}</span>
              <span>{{ managerName(t.assignee_id) }}</span>
            </div>
          </div>

          <p v-if="!col.tasks.length" class="col-empty muted">drop here</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.clamp {
  display: inline-block;
  max-width: 320px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: bottom;
}
.board {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(230px, 1fr);
  gap: 0.75rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
}
.col {
  background: var(--surface-100, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--surface-border);
  border-radius: 10px;
  padding: 0.6rem;
  min-height: 200px;
  transition: background 0.12s, border-color 0.12s;
}
.col.over {
  border-color: var(--primary-color);
  background: color-mix(in srgb, var(--primary-color) 8%, transparent);
}
.col-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}
.col-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-height: 40px;
}
.kcard {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  padding: 0.55rem 0.6rem;
  cursor: grab;
  box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
}
.kcard:active {
  cursor: grabbing;
}
.kcard.dragging {
  opacity: 0.4;
}
.kcard-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.4rem;
}
.kcard-client {
  font-weight: 600;
  margin: 0.15rem 0;
  font-size: 13px;
}
.kcard-body {
  font-size: 12px;
  color: var(--text-color-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.kcard-foot {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  margin-top: 0.35rem;
}
.col-empty {
  border: 1px dashed var(--surface-border);
  border-radius: 8px;
  text-align: center;
  padding: 0.75rem;
  font-size: 12px;
}
</style>
