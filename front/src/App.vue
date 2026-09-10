<script setup>
import { ref } from 'vue'
import AppSidebar from './components/AppSidebar.vue'

const sidebarOpen = ref(false)
</script>

<template>
  <div class="shell">
    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="main">
      <header class="topbar">
        <Button
          class="only-mobile"
          icon="pi pi-bars"
          text
          rounded
          aria-label="Menu"
          @click="sidebarOpen = !sidebarOpen"
        />
        <span class="topbar-title">Remarketing Console</span>
        <div class="spacer" />
        <Tag value="v.1.0.0" severity="secondary" rounded />
      </header>

      <main class="content">
        <RouterView v-slot="{ Component }">
          <component :is="Component" />
        </RouterView>
      </main>
    </div>

    <Toast position="bottom-right" />
  </div>
</template>

<style scoped>
.topbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0 1.25rem;
  height: 56px;
  border-bottom: 1px solid var(--surface-border);
  background: var(--surface-card);
  position: sticky;
  top: 0;
  z-index: 20;
}
.topbar-title {
  font-weight: 700;
}
.only-mobile {
  display: none !important;
}
@media (max-width: 900px) {
  .only-mobile {
    display: inline-flex !important;
  }
}
</style>
