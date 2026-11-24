import { useState } from 'react'
import Icon from './Icons.jsx'

// Campo de palavra-passe com o botão "olho" para mostrar/esconder
export default function PasswordField({ id, label, name, value, onChange, error, autoComplete }) {
  const [visible, setVisible] = useState(false)
  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <div className="field__wrap">
        <input
          id={id}
          name={name}
          type={visible ? 'text' : 'password'}
          autoComplete={autoComplete}
          placeholder="••••••••••••"
          minLength={8}
          required
          value={value}
          onChange={onChange}
          aria-invalid={!!error}
        />
        <button
          type="button"
          className="field__eye"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? 'Esconder palavra-passe' : 'Mostrar palavra-passe'}
        >
          <Icon name={visible ? 'eyeOff' : 'eye'} size={18} />
        </button>
      </div>
      {error && <span className="field__error">{error}</span>}
    </div>
  )
}
