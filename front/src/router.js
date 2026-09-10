import { createRouter, createWebHistory } from 'vue-router'

/**
 * Sections of the Remarketing / Reply Center console.
 * `meta.group` drives the sidebar grouping, `meta.icon` is a primeicons class.
 */
const routes = [
  { path: '/', redirect: '/overview' },

  { path: '/overview', name: 'overview', component: () => import('./views/OverviewView.vue'),
    meta: { title: 'Overview', group: 'Remarketing', icon: 'pi pi-chart-bar' } },
  { path: '/analytics', name: 'analytics', component: () => import('./views/AnalyticsView.vue'),
    meta: { title: 'Analytics', group: 'Remarketing', icon: 'pi pi-chart-pie' } },
  { path: '/reply-center', name: 'reply-center', component: () => import('./views/ReplyCenterView.vue'),
    meta: { title: 'Reply Center', group: 'Remarketing', icon: 'pi pi-inbox' } },
  { path: '/reply-center/:id', name: 'reply-task', component: () => import('./views/ReplyTaskView.vue'),
    meta: { title: 'Reply Task', group: 'Remarketing', hidden: true } },
  { path: '/field-teams', name: 'field-teams', component: () => import('./views/FieldTeamsView.vue'),
    meta: { title: 'Field Teams', group: 'Remarketing', icon: 'pi pi-map-marker' } },
  { path: '/campaigns', name: 'campaigns', component: () => import('./views/DripCampaignsView.vue'),
    meta: { title: 'Drip Campaigns', group: 'Remarketing', icon: 'pi pi-send' } },
  { path: '/campaigns/new', name: 'campaign-new', component: () => import('./views/DripCampaignEditorView.vue'),
    meta: { title: 'New campaign', group: 'Remarketing', hidden: true } },
  { path: '/campaigns/:id', name: 'campaign-edit', component: () => import('./views/DripCampaignEditorView.vue'),
    meta: { title: 'Edit campaign', group: 'Remarketing', hidden: true } },

  { path: '/ai/classification', name: 'ai-classification', component: () => import('./views/AiClassificationView.vue'),
    meta: { title: 'Classification Metrics', group: 'AI', icon: 'pi pi-gauge' } },
  { path: '/ai/prompts', name: 'ai-prompts', component: () => import('./views/AiPromptsView.vue'),
    meta: { title: 'Prompt Management', group: 'AI', icon: 'pi pi-file-edit' } },
  { path: '/ai/requests', name: 'ai-requests', component: () => import('./views/AiRequestsView.vue'),
    meta: { title: 'AI Requests', group: 'AI', icon: 'pi pi-server' } },

  { path: '/events', name: 'events', component: () => import('./views/EventHistoryView.vue'),
    meta: { title: 'Event History', group: 'Data & Sync', icon: 'pi pi-history' } },
  { path: '/inbound-requests', name: 'inbound-requests', component: () => import('./views/InboundRequestsView.vue'),
    meta: { title: 'Inbound Requests', group: 'Data & Sync', icon: 'pi pi-envelope' } },
  { path: '/suppression', name: 'suppression', component: () => import('./views/SuppressionView.vue'),
    meta: { title: 'Suppression & Blacklist', group: 'Data & Sync', icon: 'pi pi-ban' } },
  { path: '/crm-sync', name: 'crm-sync', component: () => import('./views/CrmSyncView.vue'),
    meta: { title: 'CRM Sync', group: 'Data & Sync', icon: 'pi pi-sync' } },

  { path: '/nats-harness', name: 'nats-harness', component: () => import('./views/NatsHarnessView.vue'),
    meta: { title: 'NATS Test Harness', group: 'Operations', icon: 'pi pi-bolt' } },
  { path: '/settings', name: 'settings', component: () => import('./views/SettingsView.vue'),
    meta: { title: 'Settings', group: 'Operations', icon: 'pi pi-cog' } },

  { path: '/:pathMatch(.*)*', redirect: '/overview' },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

export const navGroups = ['Remarketing', 'AI', 'Data & Sync', 'Operations']
export const navRoutes = routes.filter((r) => r.meta && r.meta.title && !r.meta.hidden)
