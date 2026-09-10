<script setup>
import { computed } from 'vue'
import { navGroups, navRoutes } from '../router'

defineProps({ open: Boolean })
defineEmits(['close'])

const model = computed(() =>
  navGroups.map((group) => ({
    label: group,
    items: navRoutes
      .filter((r) => r.meta.group === group)
      .map((r) => ({ label: r.meta.title, icon: r.meta.icon, to: r.path })),
  })),
)
</script>

<template>
  <aside class="sidebar" :class="{ open }">
    <div class="brand">
      <span class="brand-dot" />
      <div>
        <div class="brand-name">Remarketing Console</div>
      </div>
    </div>

    <Menu :model="model" class="side-menu">
      <template #submenuheader="{ item }">
        <span class="side-group">{{ item.label }}</span>
      </template>
      <template #item="{ item, props }">
        <router-link v-slot="{ href, navigate, isActive, isExactActive }" :to="item.to" custom>
          <a
            :href="href"
            v-bind="props.action"
            :class="['side-link', { 'side-link-active': isActive || isExactActive }]"
            @click="navigate($event); $emit('close')"
          >
            <span :class="item.icon" />
            <span>{{ item.label }}</span>
          </a>
        </router-link>
      </template>
    </Menu>

    <div class="sidebar-foot muted">Prototype · mock data · no auth</div>
  </aside>
  <div v-if="open" class="scrim" @click="$emit('close')" />
</template>

<style scoped>
.sidebar {
  width: 250px;
  flex-shrink: 0;
  background: var(--surface-card);
  border-right: 1px solid var(--surface-border);
  padding: 1rem 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
}
.brand {
  display: flex;
  gap: 0.6rem;
  align-items: center;
  padding: 0.25rem 0.5rem 0.5rem;
}
.brand-dot {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(135deg, var(--primary-color), #7c3aed);
}
.brand-name {
  font-weight: 800;
}
.brand-sub {
  font-size: 11px;
  color: var(--text-color-secondary);
}
.side-menu {
  width: 100%;
  border: 0;
  background: transparent;
  padding: 0;
}
.side-group {
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-color-secondary);
}
.side-link {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.65rem;
  border-radius: 8px;
  color: var(--text-color);
  font-size: 13.5px;
  width: 100%;
}
.side-link:hover {
  background: var(--surface-hover);
}
.side-link-active {
  background: color-mix(in srgb, var(--primary-color) 12%, transparent);
  color: var(--primary-color);
  font-weight: 600;
}
.sidebar-foot {
  margin-top: auto;
  font-size: 11px;
  padding: 0.5rem 0.6rem;
}
.scrim {
  display: none;
}
@media (max-width: 900px) {
  .sidebar {
    position: fixed;
    z-index: 40;
    transform: translateX(-100%);
    transition: transform 0.2s ease;
  }
  .sidebar.open {
    transform: translateX(0);
  }
  .scrim {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 30;
  }
}
</style>
