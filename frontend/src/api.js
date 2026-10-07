const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api'

export class ApiError extends Error {
  constructor(message, status) {
    super(message)
    this.status = status
  }
}

export async function api(path, { token, method = 'GET', body, formData, raw = false } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  let payload
  if (formData) {
    payload = formData
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: payload,
  })

  const data = await response.json().catch(() => ({}))
  if (!response.ok) {
    const message =
      data.message ||
      data.error ||
      (data.errors && Object.values(data.errors).flat()[0]) ||
      'リクエストに失敗しました。'
    throw new ApiError(message, response.status)
  }

  if (raw) {
    return { status: response.status, data }
  }

  return data
}
