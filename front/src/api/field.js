import { api } from './http'

export function fetchFieldMeta() {
  return api('/api/field/meta')
}

export function fetchFieldTeams() {
  return api('/api/field/teams')
}

export function updateFieldTeam(id, payload) {
  return api(`/api/field/teams/${id}`, { method: 'PATCH', body: payload })
}

export function fetchFieldRequests() {
  return api('/api/field/requests')
}

export function fetchFieldVisits() {
  return api('/api/field/visits')
}

export function fetchFieldRequest(id) {
  return api(`/api/field/requests/${id}`)
}

export function fetchDispatchOffers(id, scheduledAt) {
  const qs = scheduledAt ? `?scheduled_at=${encodeURIComponent(scheduledAt)}` : ''
  return api(`/api/field/requests/${id}/offers${qs}`)
}

export function scheduleFieldVisit(id, payload) {
  return api(`/api/field/requests/${id}/schedule`, { method: 'POST', body: payload })
}
