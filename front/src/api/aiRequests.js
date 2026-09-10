import { api } from './http'

export function fetchAiRequestSummary(params = {}) {
  const qs = new URLSearchParams(params).toString()
  return api('/api/ai-requests/summary' + (qs ? '?' + qs : ''))
}

export function fetchAiRequests(params = {}) {
  const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v != null && v !== ''))
  const qs = new URLSearchParams(clean).toString()
  return api('/api/ai-requests' + (qs ? '?' + qs : ''))
}
