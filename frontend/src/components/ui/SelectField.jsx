import Icon from './Icons.jsx'
import '../../styles/monitoring.css'

/** Lista pendente com etiqueta (Figma 18, 19, 20). `options`: [[valor, texto] */
export default function SelectField({ id, label, value, onChange, options, placeholder, disabled, error, describedBy, className = '' }) {
  return (
    <div className={`acct-field mon-select mnt-select ${className}`}>
      <label htmlFor={id}>{label}</label>
      <span className={`mon-select__box${error ? ' has-error' : ''}`}>
        <select id={id} name={id} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)}
          aria-invalid={error ? 'true' : undefined} aria-describedby={error ? `${id}-error` : describedBy}>
          {placeholder && <option value="">{placeholder}</option>}
          {options.map(([v, text]) => <option key={v} value={v}>{text}</option>)}
        </select>
        <Icon name="chevronDown" size={18} />
      </span>
      {error && <p id={`${id}-error`} className="addv-error" role="alert">{error}</p>}
    </div>
  )
}
