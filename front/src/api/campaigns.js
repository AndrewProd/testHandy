import { api } from './http'

export function fetchCampaignMeta() {
  return api('/api/campaigns/meta')
}

export function fetchCampaigns() {
  return api('/api/campaigns')
}

export function fetchCampaign(id) {
  return api(`/api/campaigns/${id}`)
}

export function createCampaign(payload) {
  return api('/api/campaigns', { method: 'POST', body: payload })
}

export function updateCampaign(id, payload) {
  return api(`/api/campaigns/${id}`, { method: 'PUT', body: payload })
}

export function updateCampaignStatus(id, status) {
  return api(`/api/campaigns/${id}/status`, { method: 'PATCH', body: { status } })
}

export function deleteCampaign(id) {
  return api(`/api/campaigns/${id}`, { method: 'DELETE' })
}
