<script setup>
defineProps({
  label: String,
  value: [String, Number],
  hint: String,
  icon: String,
  severity: { type: String, default: '' }, // '', 'success', 'warn', 'danger'
})
</script>

<template>
  <Card class="stat">
    <template #content>
      <div class="stat-top">
        <span class="stat-label">{{ label }}</span>
        <i v-if="icon" :class="icon" class="stat-icon" />
      </div>
      <div class="stat-value" :class="severity">{{ value }}</div>
      <div v-if="hint" class="stat-hint muted">{{ hint }}</div>
    </template>
  </Card>
</template>

<style scoped>
.stat :deep(.p-card-body) {
  padding: 1rem 1.1rem;
}
.stat :deep(.p-card-content) {
  padding: 0;
}
.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.stat-label {
  font-size: 12px;
  color: var(--text-color-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.stat-icon {
  color: var(--text-color-secondary);
}
.stat-value {
  font-size: 1.7rem;
  font-weight: 750;
  margin: 0.2rem 0;
}
.stat-value.success {
  color: #16a34a;
}
.stat-value.warn {
  color: #d97706;
}
.stat-value.danger {
  color: #dc2626;
}
.stat-hint {
  font-size: 12px;
}
</style>
