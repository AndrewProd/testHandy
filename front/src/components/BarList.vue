<script setup>
import { computed } from 'vue'
const props = defineProps({
  items: { type: Array, required: true }, // [{ label, value }]
  unit: { type: String, default: '' },
})
const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)))
</script>

<template>
  <div class="barlist">
    <div v-for="it in items" :key="it.label" class="bar-row">
      <span class="bar-label">{{ it.label }}</span>
      <ProgressBar
        :value="Math.round((it.value / max) * 100)"
        :showValue="false"
        class="bar-track"
      />
      <span class="bar-value">{{ it.value }}{{ unit }}</span>
    </div>
  </div>
</template>

<style scoped>
.barlist {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.bar-row {
  display: grid;
  grid-template-columns: 130px 1fr 52px;
  align-items: center;
  gap: 0.7rem;
  font-size: 13px;
}
.bar-label {
  color: var(--text-color-secondary);
}
.bar-track {
  height: 10px;
}
.bar-value {
  text-align: right;
  font-variant-numeric: tabular-nums;
}
</style>
