import { useEffect, useState } from 'react'
import ReactMarkdown from 'react-markdown'
import { api, ApiError } from '../api'
import { useAuth } from '../auth/AuthContext'

const IN_PROGRESS = new Set(['pending', 'preprocess', 'transcribing', 'formatting'])

const STAGE_LABELS = {
  pending: '待機',
  preprocess: '前処理',
  transcribing: '文字起こし',
  formatting: '整形',
  success: '完了',
  failed: '失敗',
  cancelled: 'キャンセル済み',
}

function stageOf(task) {
  return typeof task?.stage === 'string' ? task.stage : ''
}

function stageLabel(stage) {
  return STAGE_LABELS[stage] || stage || '状態不明'
}

function failureNote(task) {
  if (stageOf(task) !== 'failed') {
    return ''
  }
  if (typeof task?.error === 'string' && task.error.trim()) {
    return task.error
  }
  const events = Array.isArray(task?.preview_events) ? task.preview_events : []
  const latest = events.find((event) => event?.event_type === 'failure')
  const message = latest?.payload?.error || latest?.payload?.detail
  return typeof message === 'string' ? message : ''
}

function markdownSource(value) {
  return typeof value === 'string' ? value : ''
}

function isFallback(minutes) {
  return markdownSource(minutes).startsWith('[FALLBACK]')
}

function minutesBody(minutes) {
  return markdownSource(minutes).replace(/^\[FALLBACK\]\s*/, '')
}

function unavailable(err) {
  return err instanceof ApiError && err.status === 404
}

export default function ProtectedPage() {
  const { token, user, logout } = useAuth()
  const [page, setPage] = useState(null)
  const [error, setError] = useState('')
  const [tasks, setTasks] = useState([])
  const [file, setFile] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [selection, setSelection] = useState(null)
  const [activeTask, setActiveTask] = useState(null)
  const [result, setResult] = useState(null)

  useEffect(() => {
    let cancelled = false
    api('/dashboard', { token })
      .then((data) => {
        if (!cancelled) {
          setPage(data)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : 'ページを取得できませんでした。')
        }
      })

    return () => {
      cancelled = true
    }
  }, [token])

  useEffect(() => {
    let cancelled = false
    api('/minutes/tasks', { token })
      .then((data) => {
        if (!cancelled) {
          setTasks(data.tasks ?? [])
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof ApiError ? err.message : '一覧を取得できませんでした。')
        }
      })

    return () => {
      cancelled = true
    }
  }, [token])

  useEffect(() => {
    if (!selection) {
      return undefined
    }

    const { id, stage } = selection
    let cancelled = false
    let timer

    async function refreshList() {
      const data = await api('/minutes/tasks', { token })
      if (!cancelled) {
        setTasks(data.tasks ?? [])
      }
      return data.tasks ?? []
    }

    async function showResult() {
      const { status, data } = await api(`/minutes/tasks/${id}/result`, { token, raw: true })
      if (cancelled) {
        return
      }
      if (status === 202 || data.status !== 'success') {
        setResult(null)
        const nextTasks = await refreshList()
        const current = nextTasks.find((task) => task.id === id)
        if (current) {
          setActiveTask(current)
        }
        return
      }
      setResult(data.result ?? null)
      await refreshList()
    }

    async function poll() {
      const status = await api(`/minutes/tasks/${id}`, { token })
      if (cancelled) {
        return
      }
      setActiveTask(status)
      const next = stageOf(status)
      if (next === 'success') {
        await showResult()
        return
      }
      if (next === 'failed' || next === 'cancelled') {
        setResult(null)
        await refreshList()
        return
      }
      timer = window.setTimeout(poll, 3000)
    }

    const run = stage === 'success' ? showResult : IN_PROGRESS.has(stage) || stage === '' ? poll : async () => {
      setActiveTask(selection.task)
      setResult(null)
    }

    run().catch((err) => {
      if (cancelled) {
        return
      }
      if (unavailable(err)) {
        setResult(null)
        setActiveTask(null)
        setError('このタスクは表示できない')
        return
      }
      setError(err instanceof ApiError ? err.message : '状態を取得できませんでした。')
    })

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [selection, token])

  async function handleUpload(event) {
    event.preventDefault()
    if (!file) {
      return
    }
    setError('')
    setResult(null)
    setActiveTask(null)
    setUploading(true)
    try {
      const formData = new FormData()
      formData.append('file', file)
      const created = await api('/minutes/tasks', { token, method: 'POST', formData })
      setSelection({ id: created.task_id, stage: 'pending', task: null })
      setFile(null)
      event.target.reset()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : '音声を送れませんでした。')
    } finally {
      setUploading(false)
    }
  }

  const shown = activeTask
  const shownStage = stageOf(shown)
  const minutes = markdownSource(result?.minutes)
  const transcript = markdownSource(result?.transcript)

  return (
    <main className="layout">
      <section className="card card-wide">
        <div className="card-header">
          <p className="eyebrow">認証済み</p>
          <button type="button" className="ghost" onClick={logout}>
            ログアウト
          </button>
        </div>
        <h1>{page?.title ?? '保護されたページ'}</h1>
        {user ? (
          <p className="lead">
            {user.name}（{user.email}）として表示しています。
          </p>
        ) : null}
        {error ? <p className="error">{error}</p> : null}
        {page ? <p>{page.message}</p> : !error ? <p className="hint">読み込み中…</p> : null}

        <form onSubmit={handleUpload}>
          <label>
            音声ファイル
            <input
              type="file"
              name="file"
              accept=".wav,.mp3,.m4a,.flac,.ogg,.opus,audio/*"
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          </label>
          <button type="submit" disabled={uploading || !file}>
            {uploading ? '送信中…' : '議事録を作る'}
          </button>
        </form>

        {shown ? (
          <p className="hint">
            {shown.name || shown.task_id || selection?.id}: {stageLabel(shownStage)}
          </p>
        ) : null}
        {shownStage === 'failed' && failureNote(shown) ? (
          <p className="error">{failureNote(shown)}</p>
        ) : null}

        {result ? (
          <article className="result">
            {isFallback(minutes) ? <p className="hint">簡易整形</p> : null}
            {minutesBody(minutes) ? (
              <div className="markdown">
                <ReactMarkdown>{minutesBody(minutes)}</ReactMarkdown>
              </div>
            ) : null}
            {transcript ? (
              <section>
                <h2>文字起こし</h2>
                <pre className="transcript">{transcript}</pre>
              </section>
            ) : null}
          </article>
        ) : null}

        <h2>これまでのタスク</h2>
        {tasks.length === 0 ? <p className="hint">まだありません。</p> : null}
        <ul className="task-list">
          {tasks.map((task) => {
            const stage = stageOf(task)
            return (
              <li key={task.id}>
                <button
                  type="button"
                  className="ghost"
                  onClick={() => {
                    setError('')
                    setResult(null)
                    setActiveTask(task)
                    setSelection({ id: task.id, stage, task })
                  }}
                >
                  {task.name || task.id}（{stageLabel(stage)}）
                </button>
              </li>
            )
          })}
        </ul>
      </section>
    </main>
  )
}
