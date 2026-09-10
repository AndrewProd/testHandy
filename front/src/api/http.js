const TENANT_ID = '42'

export async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch(path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Tenant-Id': TENANT_ID,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (res.status === 204) return null

  const data = await res.json().catch(() => null)
  if (!res.ok) {
    const err = new Error(data?.message || res.statusText)
    err.status = res.status
    err.errors = data?.errors || {}
    throw err
  }
  return data
}
