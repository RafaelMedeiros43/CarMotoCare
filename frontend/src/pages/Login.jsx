import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import AuthAside from '../components/AuthAside.jsx'
import PasswordField from '../components/PasswordField.jsx'
import Icon from '../components/Icons.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { AFTER_LOGIN_PATH, MOCK_AUTH_ENABLED } from '../config.js'
import '../styles/auth.css'
import '../styles/dev.css'

export default function Login() {
  const { login, mockLogin, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (isAuthenticated) return <Navigate to={AFTER_LOGIN_PATH} replace />

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(form.email, form.password)
      navigate(AFTER_LOGIN_PATH)
    } catch (err) {
      setError(err.status === 401 ? 'E-mail ou palavra-passe incorretos.' : err.message)
    } finally {
      setLoading(false)
    }
  }

  // TEMPORÁRIO: entra sem login, com dados de exemplo (só em desenvolvimento).
  const onSkip = () => {
    mockLogin()
    navigate(AFTER_LOGIN_PATH)
  }

  return (
    <section className="reg">
      <div className="reg__card">
        <AuthAside />

        <div className="reg__form">
          <Link to="/" className="reg__back"><Icon name="arrowLeft" size={15} /> Voltar ao início</Link>
          <h2>Bem-vindo à sua garagem</h2>
          <p className="reg__sub">Aceda à sua conta e continue a cuidar dos seus veículos.</p>

          {error && <div className="form-error" role="alert">{error}</div>}

          <form onSubmit={onSubmit}>
            <div className="field">
              <label htmlFor="email">E-mail</label>
              <input id="email" name="email" type="email" autoComplete="email" placeholder="miguel.santos@exemplo.pt" required
                value={form.email} onChange={onChange} />
            </div>

            <PasswordField id="password" name="password" label="Palavra-passe" autoComplete="current-password"
              value={form.password} onChange={onChange} />

            <button className="btn btn--primary reg__submit" disabled={loading}>
              {loading ? 'A entrar…' : <><Icon name="arrowRight" size={16} /> Iniciar sessão</>}
            </button>

            {/* TEMPORÁRIO: entra sem login (dados de exemplo) enquanto o backend não tem login. */}
            {MOCK_AUTH_ENABLED && (
              <button type="button" className="btn btn--ghost reg__submit dev-skip" onClick={onSkip}>
                Seguinte <Icon name="arrowRight" size={16} />
              </button>
            )}
          </form>

          <div className="info-box">
            <Icon name="info" size={18} />
            <div>
              <strong>Acesso à conta pessoal</strong>
              <span>Utilize o e-mail e a palavra-passe que definiu ao criar a conta</span>
            </div>
          </div>

          <p className="reg__alt">Ainda não tem conta? <Link to="/registar">Criar conta</Link></p>
        </div>
      </div>
    </section>
  )
}
