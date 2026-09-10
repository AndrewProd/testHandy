<script setup>
defineProps({ title: String, subtitle: String })
</script>

<template>
  <header class="page-head">
    <div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="muted">{{ subtitle }}</p>
    </div>
    <div class="page-head-actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.page-head {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.page-head p {
  margin: 0.3rem 0 0;
  font-size: 13px;
}
.page-head-actions {
  margin-left: auto;
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}
</style>
