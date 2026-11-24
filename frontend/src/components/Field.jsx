// Campo de formulário (etiqueta + controlo + erro/ajuda)
export default function Field({ name, label, error, hint, children }) {
  const describedBy = error ? `${name}-error` : hint ? `${name}-hint` : undefined
  return (
    <div className="acct-field addv-field">
      <label htmlFor={name}>{label}</label>
      {children(describedBy)}
      {error ? <p id={`${name}-error`} className="addv-error" role="alert">{error}</p>
        : hint && <p id={`${name}-hint`} className="addv-hint">{hint}</p>}
    </div>
  )
}
