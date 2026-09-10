import { api } from './http'

export function fetchReplyTasks(params = {}) {
  const qs = new URLSearchParams()
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') qs.set(k, v)
  })
  const suffix = qs.toString() ? `?${qs}` : ''
  return api(`/api/reply-tasks${suffix}`)
}

export function fetchReplyTask(id) {
  return api(`/api/reply-tasks/${id}`)
}

export function updateReplyTask(id, payload) {
  return api(`/api/reply-tasks/${id}`, { method: 'PATCH', body: payload })
}

export function scheduleMeasurement(id, payload) {
  return api(`/api/reply-tasks/${id}/schedule-measurement`, { method: 'POST', body: payload })
}

export function sendReply(id, payload) {
  return api(`/api/reply-tasks/${id}/messages`, { method: 'POST', body: payload })
}

export function runCopilot(id, payload) {
  return api(`/api/reply-tasks/${id}/copilot`, { method: 'POST', body: payload })
}
