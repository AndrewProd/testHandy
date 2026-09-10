<script setup>
import { ref, reactive, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import PageHeader from '../components/PageHeader.vue'
import { promptVersions, SENTIMENTS, fmt, statusTone } from '../data/mock'

const toast = useToast()
const versions = reactive(promptVersions.map((p) => ({ reasoning: '', ...p })))
const selected = ref(versions.find((v) => v.status === 'active').version)
const current = computed(() => versions.find((v) => v.version === selected.value))

// what actually gets sent to the model: prompt body + reasoning guidance block
const composedRequest = computed(() => {
  const parts = [current.value.body.trim()]
  if (current.value.reasoning.trim()) {
    parts.push('# Reasoning guidance (model-side, not returned)\n' + current.value.reasoning.trim())
  }
  return parts.join('\n\n')
})

const showComposed = ref(false)
const sample = ref('Hi, can you tell me the price for 8 windows on the second floor? Thanks.')
const testResult = ref(null)

function activate(v) {
  versions.forEach((x) => {
    if (x.status === 'active') x.status = 'archived'
  })
  v.status = 'active'
  toast.add({ severity: 'success', summary: `Activated ${v.version}`, life: 2000 })
}

function runTest() {
  const t = sample.value.toLowerCase()
  let label = 'question'
  if (/unsubscribe|take me off|opt out|remove me/.test(t)) label = 'unsubscribe'
  else if (/wrong person|not the right|never requested/.test(t)) label = 'wrong_person'
  else if (/out of office|automatic reply|on vacation/.test(t)) label = 'auto_reply'
  else if (/next year|not right now|maybe later|budget is spent/.test(t)) label = 'not_now'
  else if (/call me|go ahead|interested|schedule/.test(t)) label = 'interested'
  testResult.value = {
    sentiment: label,
    prompt_version: current.value.version,
    request_chars: composedRequest.value.length,
    reasoning_included: !!current.value.reasoning.trim(),
    latency_ms: 40 + Math.floor(Math.random() * 900),
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Prompt Management"
      subtitle="Versioned prompts for the sentiment classifier — activate, roll back, edit, and test."
    >
      <template #actions>
        <Tag :value="'active: ' + versions.find((v) => v.status === 'active').version" severity="success" />
      </template>
    </PageHeader>

    <div class="grid cols-2">
      <Card>
        <template #title>Versions</template>
        <template #content>
          <DataTable
            :value="versions"
            size="small"
            selectionMode="single"
            dataKey="version"
            :selection="versions.find((v) => v.version === selected)"
            @rowSelect="(e) => (selected = e.data.version)"
          >
            <Column field="version" header="Version" />
            <Column header="Status">
              <template #body="{ data }"><Tag :value="data.status" :severity="statusTone(data.status)" /></template>
            </Column>
            <Column header="Reasoning">
              <template #body="{ data }">
                <i
                  :class="data.reasoning && data.reasoning.trim() ? 'pi pi-check-circle' : 'pi pi-minus'"
                  :style="{ color: data.reasoning && data.reasoning.trim() ? 'var(--green-500, #16a34a)' : 'var(--text-color-secondary)' }"
                />
              </template>
            </Column>
            <Column header="Accuracy">
              <template #body="{ data }">{{ data.accuracy ? (data.accuracy * 100).toFixed(0) + '%' : '—' }}</template>
            </Column>
            <Column header="Updated">
              <template #body="{ data }"><span class="muted">{{ fmt(data.updated_at) }}</span></template>
            </Column>
            <Column header="">
              <template #body="{ data }">
                <Button
                  v-if="data.status !== 'active'"
                  label="Activate"
                  size="small"
                  text
                  @click="activate(data)"
                />
              </template>
            </Column>
          </DataTable>
          <p class="muted" style="margin-top: 0.75rem">
            Rubric: {{ SENTIMENTS.join(', ') }}. Output must be JSON
            <code>{"sentiment": "&lt;label&gt;"}</code>.
          </p>
        </template>
      </Card>

      <div class="stack">
        <Card>
          <template #title>
            <div class="row">
              <span>{{ current.version }}</span>
              <Tag :value="current.status" :severity="statusTone(current.status)" />
              <span class="spacer" />
              <span class="muted" style="font-weight: 400; font-size: 13px">{{ current.author }}</span>
            </div>
          </template>
          <template #content>
            <p class="muted" style="margin: 0 0 0.5rem">{{ current.note }}</p>

            <label class="fld">
              <span>Prompt body</span>
              <Textarea v-model="current.body" rows="9" class="mono" />
            </label>

            <label class="fld" style="margin-top: 0.75rem">
              <span>
                Reasoning guidance
                <span class="muted" style="font-weight: 400">— injected into the model request, not returned</span>
              </span>
              <Textarea
                v-model="current.reasoning"
                rows="6"
                class="mono"
                placeholder="Step-by-step guidance the model should follow silently before answering…"
              />
            </label>

            <div class="row" style="margin-top: 0.6rem">
              <Button label="Save version" icon="pi pi-save" @click="toast.add({ severity: 'success', summary: 'Saved', life: 1500 })" />
              <Button v-if="current.status !== 'active'" label="Activate" outlined @click="activate(current)" />
              <Button label="Duplicate as draft" text />
              <span class="spacer" />
              <Button
                :label="showComposed ? 'Hide composed request' : 'Show composed request'"
                :icon="showComposed ? 'pi pi-eye-slash' : 'pi pi-eye'"
                text
                size="small"
                @click="showComposed = !showComposed"
              />
            </div>

            <div v-if="showComposed">
              <h3 style="margin-top: 0.75rem">Sent to model ({{ composedRequest.length }} chars)</h3>
              <pre class="pre-json">{{ composedRequest }}</pre>
            </div>
          </template>
        </Card>

        <Card>
          <template #title>Test against a sample reply</template>
          <template #content>
            <Textarea v-model="sample" rows="3" style="width: 100%" />
            <div class="row" style="margin-top: 0.5rem">
              <Button label="Classify" icon="pi pi-play" @click="runTest" />
              <span v-if="testResult" class="muted">→ {{ testResult.latency_ms }} ms</span>
            </div>
            <pre v-if="testResult" class="pre-json" style="margin-top: 0.6rem">{{ JSON.stringify(testResult, null, 2) }}</pre>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>
