import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import LoginPage from './pages/LoginPage'
import ProtectedPage from './pages/ProtectedPage'

function ProtectedRoute() {
  const { token, ready } = useAuth()

  if (!ready) {
    return (
      <main className="layout">
        <p className="hint">確認中…</p>
      </main>
    )
  }

  if (!token) {
    return <Navigate to="/login" replace />
  }

  return <ProtectedPage />
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<ProtectedRoute />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
