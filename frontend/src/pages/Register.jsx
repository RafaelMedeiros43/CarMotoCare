import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import AuthAside from '../components/AuthAside.jsx'
import PasswordField from '../components/PasswordField.jsx'
import Icon from '../components/Icons.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import '../styles/auth.css'

export default function Register() {
  const { register, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ name: '', email: '', password: '', confirm: '' })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [loading, setLoading] = useState(false)

  if (isAuthenticated) return <Navigate to="/painel" replace />

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})

    if (form.password !== form.confirm) {
      setFieldErrors({ confirm: 'As palavras-passe não coincidem.' })
      return
    }

    setLoading(true)
    try {
      await register({ name: form.name, email: form.email, password: form.password })
      navigate('/painel') // conta criada e sessão iniciada
    } catch (err) {
      if (err.status === 409) {
        setFieldErrors({ email: 'Já existe uma conta com este e-mail.' })
      } else {
        setError(err.message)
        // erros de validação por campo (400): [{ field, message }]
        setFieldErrors(Object.fromEntries(err.fieldErrors.map((f) => [f.field, f.message])))
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <section className="reg">
      <div className="reg__card">
        <AuthAside />

        <div className="reg__form">
          <Link to="/" className="reg__back"><Icon name="arrowLeft" size={15} /> Voltar ao início</Link>
          <h2>Crie a sua conta pessoal</h2>
          <p className="reg__sub">Comece a organizar o cuidado dos seus veículos.</p>

          {error && <div className="form-error" role="alert">{error}</div>}

          <form onSubmit={onSubmit}>
            <div className="field">
              <label htmlFor="name">Nome completo</label>
              <input id="name" name="name" autoComplete="name" placeholder="Miguel Santos" required
                value={form.name} onChange={onChange} aria-invalid={!!fieldErrors.name} />
              {fieldErrors.name && <span className="field__error">{fieldErrors.name}</span>}
            </div>

            <div className="field">
              <label htmlFor="email">E-mail</label>
              <input id="email" name="email" type="email" autoComplete="email" placeholder="miguel.santos@exemplo.pt" required
                value={form.email} onChange={onChange} aria-invalid={!!fieldErrors.email} />
              {fieldErrors.email && <span className="field__error">{fieldErrors.email}</span>}
            </div>

            <PasswordField id="password" name="password" label="Palavra-passe" autoComplete="new-password"
              value={form.password} onChange={onChange} error={fieldErrors.password} />

            <PasswordField id="confirm" name="confirm" label="Confirmar palavra-passe" autoComplete="new-password"
              value={form.confirm} onChange={onChange} error={fieldErrors.confirm} />

            <button className="btn btn--primary reg__submit" disabled={loading}>
              {loading ? 'A criar conta…' : 'Criar conta'}
            </button>
          </form>

          <p className="reg__alt">Já tem conta? <Link to="/entrar">Iniciar sessão</Link></p>
        </div>
      </div>
    </section>
  )
}
