<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import PageHeader from '../components/PageHeader.vue'
import JsonBlock from '../components/JsonBlock.vue'
import { fetchAiRequests, fetchAiRequestSummary } from '../api/aiRequests'
import { fmtNum, fmtMoney } from '../data/mock'

const loading = ref(false)
const error = ref('')

const summary = ref(null)
const rows = ref([])
const total = ref(0)
const first = ref(0)
const perPage = ref(25)
const expanded = ref([])

const filters = reactive({ days: 14, purpose: null, model: null, status: null, search: '' })
const dayOpts = [
  { label: 'Last 24 hours', value: 1 },
  { label: 'Last 7 days', value: 7 },
  { label: 'Last 14 days', value: 14 },
  { label: 'Last 30 days', value: 30 },
]

async function loadSummary() {
  try {
    summary.value = await fetchAiRequestSummary({ days: filters.days })
  } catch (e) {
    error.value = e.message || 'Could not load AI audit summary'
  }
}

async function loadRows() {
  loading.value = true
  error.value = ''
  try {
    const page = first.value / perPage.value + 1
    const res = await fetchAiRequests({
      page,
      per_page: perPage.value,
      purpose: filters.purpose,
      model: filters.model,
      status: filters.status,
      search: filters.search || null,
    })
    rows.value = res.data
    total.value = res.meta?.total ?? res.data.length
  } catch (e) {
    error.value = e.message || 'Could not load AI requests'
    rows.value = []
  } finally {
    loading.value = false
  }
}

function onPage(e) {
  first.value = e.first
  perPage.value = e.rows
  loadRows()
}

let searchTimer
watch(
  () => [filters.purpose, filters.model, filters.status],
  () => {
    first.value = 0
    loadRows()
  },
)
watch(
  () => filters.search,
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
      first.value = 0
      loadRows()
    }, 350)
  },
)
watch(
  () => filters.days,
  () => {
    loadSummary()
  },
)

onMounted(() => {
  loadSummary()
  loadRows()
})

const purposeTone = {
  reply_classification: 'info',
  intent_analysis: 'info',
  sentiment_analysis: 'contrast',
  manager_assistant: 'warning',
  summarization: 'secondary',
}
const statusTone = { completed: 'success', failed: 'danger', pending: 'warning' }

function fmtTs(s) {
  return s ? new Date(s).toLocaleString('en-CA', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
}
function pct(n) {
  return n == null ? '—' : n + '%'
}
</script>

<template>
  <div>
    <PageHeader
      title="AI Requests"
      subtitle="Immutable audit of every LLM call — prompt, prompt version, tokens, cost, latency, structured output."
    >
      <template #actions>
        <Select v-model="filters.days" :options="dayOpts" optionLabel="label" optionValue="value" />
      </template>
    </PageHeader>

    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- summary -->
    <div v-if="summary" class="grid cols-4">
      <Card>
        <template #title>Requests</template>
        <template #content>
          <div class="big">{{ fmtNum(summary.requests.total) }}</div>
          <div class="row" style="gap: 0.4rem; margin-top: 0.3rem">
            <Tag :value="summary.requests.completed + ' ok'" severity="success" />
            <Tag :value="summary.requests.failed + ' failed'" severity="danger" />
            <Tag v-if="summary.requests.pending" :value="summary.requests.pending + ' pending'" severity="warning" />
          </div>
        </template>
      </Card>
      <Card>
        <template #title>Tokens</template>
        <template #content>
          <div class="big">{{ fmtNum(summary.tokens.total) }}</div>
          <div class="muted" style="font-size: 12px">
            in {{ fmtNum(summary.tokens.input) }} · out {{ fmtNum(summary.tokens.output) }}
          </div>
        </template>
      </Card>
      <Card>
        <template #title>Cost</template>
        <template #content>
          <div class="big">${{ summary.cost_usd.toFixed(2) }}</div>
          <div class="muted" style="font-size: 12px">last {{ summary.days }} days</div>
        </template>
      </Card>
      <Card>
        <template #title>Models</template>
        <template #content>
          <div v-for="m in summary.by_model" :key="m.model" class="row" style="justify-content: space-between; font-size: 13px">
            <span>{{ m.model }}</span>
            <span class="muted">{{ m.requests }} · ${{ m.cost_usd.toFixed(3) }}</span>
          </div>
        </template>
      </Card>
    </div>

    <!-- prompt versions -->
    <Card v-if="summary" style="margin-top: 1rem">
      <template #title>Prompt versions</template>
      <template #content>
        <DataTable :value="summary.by_prompt_version" size="small" stripedRows>
          <Column field="prompt_version" header="Version" />
          <Column field="requests" header="Requests" />
          <Column header="Avg latency"><template #body="{ data }">{{ (data.avg_latency_ms / 1000).toFixed(2) }}s</template></Column>
          <Column header="Avg tokens"><template #body="{ data }">{{ fmtNum(data.avg_total_tokens) }}</template></Column>
          <Column header="Cost"><template #body="{ data }">${{ data.cost_usd.toFixed(2) }}</template></Column>
          <Column header="Success">
            <template #body="{ data }">
              <Tag
                :value="pct(data.success_rate)"
                :severity="data.success_rate >= 97 ? 'success' : data.success_rate >= 90 ? 'warning' : 'danger'"
              />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- requests table -->
    <Card style="margin-top: 1rem">
      <template #title>
        <div class="row">
          <span>Requests</span>
          <span class="spacer" />
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" placeholder="Search prompt / error…" />
          </IconField>
          <Select
            v-model="filters.purpose"
            :options="summary?.filters?.purposes || []"
            placeholder="Purpose"
            showClear
            style="min-width: 170px"
          />
          <Select
            v-model="filters.model"
            :options="summary?.filters?.models || []"
            placeholder="Model"
            showClear
            style="min-width: 150px"
          />
          <Select
            v-model="filters.status"
            :options="summary?.filters?.statuses || []"
            placeholder="Status"
            showClear
            style="min-width: 130px"
          />
        </div>
      </template>
      <template #content>
        <DataTable
          v-model:expandedRows="expanded"
          :value="rows"
          :loading="loading"
          lazy
          paginator
          :rows="perPage"
          :rowsPerPageOptions="[25, 50, 100]"
          :totalRecords="total"
          :first="first"
          dataKey="id"
          size="small"
          stripedRows
          @page="onPage"
        >
          <Column expander style="width: 3rem" />
          <Column header="When"><template #body="{ data }"><span class="muted">{{ fmtTs(data.created_at) }}</span></template></Column>
          <Column header="Purpose">
            <template #body="{ data }"><Tag :value="data.purpose" :severity="purposeTone[data.purpose] || 'secondary'" /></template>
          </Column>
          <Column field="model" header="Model" />
          <Column field="prompt_version" header="Prompt" />
          <Column header="Tokens">
            <template #body="{ data }">
              {{ fmtNum(data.total_tokens) }}
              <span class="muted" style="font-size: 11px">({{ data.input_tokens }}/{{ data.output_tokens }})</span>
            </template>
          </Column>
          <Column header="Latency">
            <template #body="{ data }">{{ data.latency_ms != null ? (data.latency_ms / 1000).toFixed(2) + 's' : '—' }}</template>
          </Column>
          <Column header="Cost">
            <template #body="{ data }">{{ data.cost_usd != null ? '$' + data.cost_usd.toFixed(5) : '—' }}</template>
          </Column>
          <Column header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="statusTone[data.status] || 'secondary'" />
            </template>
          </Column>

          <template #expansion="{ data }">
            <div class="detail">
              <div class="row" style="gap: 0.4rem; margin-bottom: 0.5rem">
                <Tag :value="data.provider + ' · ' + data.model" severity="secondary" />
                <Tag :value="'temp ' + (data.temperature ?? '—')" severity="secondary" />
                <Tag :value="'max_tokens ' + (data.max_tokens ?? '—')" severity="secondary" />
                <Tag v-if="data.subject" :value="data.subject.type + ' #' + data.subject.id" severity="secondary" />
                <Tag v-if="data.error_code" :value="data.error_code" severity="danger" />
              </div>
              <div class="grid cols-2">
                <div>
                  <h3>System prompt</h3>
                  <pre class="pre-json">{{ data.system_prompt || '—' }}</pre>
                  <h3 style="margin-top: 0.75rem">User prompt</h3>
                  <pre class="pre-json">{{ data.user_prompt || '—' }}</pre>
                  <template v-if="data.error_message">
                    <h3 style="margin-top: 0.75rem">Error</h3>
                    <pre class="pre-json">{{ data.error_message }}</pre>
                  </template>
                </div>
                <div>
                  <h3>Structured output</h3>
                  <JsonBlock :value="data.structured_output || '—'" />
                  <h3 style="margin-top: 0.75rem">Request payload</h3>
                  <JsonBlock :value="data.request_payload" />
                  <h3 style="margin-top: 0.75rem">Response payload</h3>
                  <JsonBlock :value="data.response_payload" />
                </div>
              </div>
            </div>
          </template>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.big {
  font-size: 1.5rem;
  font-weight: 750;
}
.detail {
  padding: 0.5rem 0.25rem;
}
.detail :deep(.p-inputtext) {
  width: auto;
}
</style>
