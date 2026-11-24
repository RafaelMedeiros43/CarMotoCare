import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Icon from '../components/Icons.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { initials } from '../lib/format.js'

// Figma: "05 Conta pessoal" e "05b Conta pessoal — Editar perfil"
// Dados: GET /users/me (já carregado no AuthContext) e PATCH /users/me (guardar alterações)

function Field({ id, label, children }) {
  return (
    <div className="acct-field">
      <label htmlFor={id}>{label}</label>
      {children}
    </div>
  )
}

function EditForm({ user, onCancel, onSaved }) {
  const { updateProfile } = useAuth()
  const [form, setForm] = useState({ name: user.name, email: user.email, currentPassword: '', newPassword: '' })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const onChange = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  const onSubmit = async (e) => {
    e.preventDefault()
    setError('')

    const name = form.name.trim()
    const email = form.email.trim()
    if (!name) return setError('Indique o seu nome')
    if (form.newPassword && form.newPassword.length < 8) return setError('A nova palavra-passe tem de ter pelo menos 8 caracteres')

    // Só envia o que mudou (o PATCH exige pelo menos um campo)
    const changes = {}
    if (name !== user.name) changes.name = name
    if (email !== user.email) changes.email = email
    if (form.newPassword) changes.newPassword = form.newPassword
    if (changes.email || changes.newPassword) {
      if (!form.currentPassword) return setError('Indique a palavra-passe atual para mudar o e-mail ou a palavra-passe')
      changes.currentPassword = form.currentPassword
    }
    if (Object.keys(changes).length === 0) return onCancel()

    setSaving(true)
    try {
      await updateProfile(changes)
      onSaved()
    } catch (err) {
      const details = err.fieldErrors?.map((f) => f.message).join(' ')
      setError(details || err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <form className="acct-form" onSubmit={onSubmit} noValidate>
      {error && <div className="form-error" role="alert">{error}</div>}

      <Field id="name" label="Nome completo">
        <input id="name" name="name" autoComplete="name" value={form.name} onChange={onChange} required />
      </Field>
      <Field id="email" label="E-mail">
        <input id="email" name="email" type="email" autoComplete="email" value={form.email} onChange={onChange} required />
      </Field>
      <Field id="currentPassword" label="Palavra-passe atual">
        <input id="currentPassword" name="currentPassword" type="password" autoComplete="current-password"
          placeholder="Só é necessária para mudar o e-mail ou a palavra-passe" value={form.currentPassword} onChange={onChange} />
      </Field>
      <Field id="newPassword" label="Nova palavra-passe">
        <input id="newPassword" name="newPassword" type="password" autoComplete="new-password"
          placeholder="Mínimo de 8 caracteres" value={form.newPassword} onChange={onChange} />
      </Field>

      <hr className="acct-sep" />
      <div className="acct-actions">
        <button type="button" className="acct-btn" onClick={onCancel}>Cancelar</button>
        <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}>
          {saving ? 'A guardar…' : 'Guardar alterações'}
        </button>
      </div>
    </form>
  )
}

export default function Account() {
  const { user, logout } = useAuth()
  const { vehicles } = useOutletContext()
  const navigate = useNavigate()
  const [editing, setEditing] = useState(false)
  const [saved, setSaved] = useState(false)

  if (!user) return <p className="muted">A carregar…</p>

  const handleLogout = () => {
    logout()
    navigate('/')
  }

  return (
    <div className="acct">
      <header className="acct__head">
        <h1>{editing ? 'Editar perfil' : 'A sua conta pessoal'}</h1>
        <p>{editing ? 'Altere o nome, o e-mail ou a palavra-passe.' : 'Os dados que identificam a sua conta CarMotoCare.'}</p>
      </header>

      {saved && !editing && <div className="acct-ok" role="status">Alterações guardadas.</div>}

      <div className="acct__grid">
        <section className="acct-card">
          <h2>Identificação da conta</h2>

          <div className="acct-profile">
            <span className="acct-profile__avatar">{initials(user.name)}</span>
            <div>
              <p className="acct-profile__name">{user.name}</p>
              <span className="acct-tag">Conta pessoal</span>
            </div>
          </div>

          <hr className="acct-sep" />

          {editing ? (
            <EditForm
              user={user}
              onCancel={() => setEditing(false)}
              onSaved={() => { setEditing(false); setSaved(true) }}
            />
          ) : (
            <>
              <div className="acct-data">
                <small>Nome completo</small>
                <strong>{user.name}</strong>
              </div>
              <div className="acct-data">
                <small>E-mail</small>
                <strong>{user.email}</strong>
              </div>
              <p className="acct-note">Utilize este e-mail para iniciar sessão e aceder aos seus veículos</p>
              <button type="button" className="acct-btn acct-btn--self" onClick={() => { setSaved(false); setEditing(true) }}>
                Editar perfil
              </button>
            </>
          )}
        </section>

        <div className="acct__side">
          <section className="acct-card">
            <h2>Acesso à conta</h2>
            <div className="acct-session">
              <Icon name="userRound" size={24} />
              <div>
                <strong>Sessão iniciada</strong>
                <span>Acesso com e-mail e palavra-passe.</span>
              </div>
            </div>
            <hr className="acct-sep" />
            <button type="button" className="acct-btn acct-btn--block" onClick={handleLogout}>
              <Icon name="logOut" size={17} />
              Sair da conta
            </button>
          </section>

          <section className="acct-garage">
            <Icon name="car" size={30} />
            <h2>A sua garagem</h2>
            <div className="acct-garage__list">
              {vehicles.length === 0 && <p>Ainda não tem veículos</p>}
              {vehicles.map((v) => (
                <p key={v.id}>{v.brand} {v.model} · {v.plate.replaceAll('-', '·')}</p>
              ))}
            </div>
            <Link to="/veiculos" className="acct-btn acct-btn--block">Consultar veículos</Link>
          </section>
        </div>
      </div>
    </div>
  )
}
