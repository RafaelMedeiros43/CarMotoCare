import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Field from '../components/Field.jsx'
import Icon from '../components/Icons.jsx'
import VehicleTypePicker from '../components/VehicleTypePicker.jsx'
import useApi from '../hooks/useApi.js'
import {
  buildVehicleBody, maskDate, maskKm, maskMonthYear, maskPlate,
} from '../lib/vehicleForm.js'
import '../styles/add-vehicle.css'

// Figma: "09 Adicionar veículo"
// Dados: POST /vehicles (VehicleCreate). Depois de guardar, volta a pedir GET /vehicles e abre "Os meus veículos".

const EMPTY = {
  type: 'CARRO', brand: '', model: '', firstRegistration: '', plate: '',
  iucValidUntil: '', insuranceValidUntil: '', inspectionValidUntil: '', km: '',
}

// Máscara de cada campo (os que não estão aqui guardam o texto tal como é escrito)
const MASKS = {
  firstRegistration: maskMonthYear,
  plate: maskPlate,
  iucValidUntil: maskDate,
  insuranceValidUntil: maskDate,
  inspectionValidUntil: maskDate,
  km: maskKm,
}

export default function AddVehicle() {
  const { reload, selectVehicle } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()

  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const setValue = (name, value) => {
    setForm((f) => ({ ...f, [name]: value }))
    setErrors((e) => (e[name] ? { ...e, [name]: undefined } : e))
  }
  const onChange = (e) => {
    const { name, value } = e.target
    setValue(name, MASKS[name] ? MASKS[name](value) : value)
  }

  // Props comuns dos <input>
  const input = (name, describedBy, extra) => ({
    id: name, name, value: form[name], onChange, disabled: saving,
    'aria-invalid': errors[name] ? 'true' : undefined, 'aria-describedby': describedBy, ...extra,
  })

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const { errors: found, body } = buildVehicleBody(form)
    setErrors(found)
    if (!body) {
      document.getElementById(Object.keys(found)[0])?.focus()
      return
    }

    setSaving(true)
    try {
      const created = await api.createVehicle(body)
      await reload()
      selectVehicle(created.id)
      navigate('/veiculos')
    } catch (err) {
      // Erros por campo da API (Problem.errors[]) aparecem junto ao campo; os restantes, no topo do formulário
      const byField = {}
      const general = []
      for (const f of err.fieldErrors ?? []) {
        if (f.field in EMPTY) byField[f.field] = f.message
        else general.push(f.message)
      }
      setErrors(byField)
      setFormError(Object.keys(byField).length && !general.length ? 'Corrija os campos assinalados.' : general.join(' ') || err.message)
      setSaving(false)
    }
  }

  return (
    <div className="acct addv">
      <div className="acct__head">
        <h1>Adicionar veículo</h1>
        <p>Registe um automóvel ou motociclo na sua garagem pessoal.</p>
      </div>

      <div className="acct__grid">
        <form className="acct-card addv-card" onSubmit={onSubmit} noValidate>
          <div className="addv-intro">
            <h2>Dados do veículo</h2>
            <p>Preencha os campos para identificar o seu veículo.</p>
          </div>

          {formError && <div className="form-error" role="alert">{formError}</div>}

          <div className="addv-fields">
            <VehicleTypePicker value={form.type} onChange={onChange} disabled={saving} />

            <div className="addv-row">
              <Field name="brand" label="Marca" error={errors.brand}>
                {(d) => <input {...input('brand', d, { placeholder: 'Introduza a marca', autoComplete: 'off', maxLength: 60 })} />}
              </Field>
              <Field name="model" label="Modelo" error={errors.model}>
                {(d) => <input {...input('model', d, { placeholder: 'Introduza o modelo', autoComplete: 'off', maxLength: 60 })} />}
              </Field>
            </div>

            <div className="addv-row">
              <Field name="firstRegistration" label="1.ª matrícula (mês e ano)" error={errors.firstRegistration}>
                {(d) => <input {...input('firstRegistration', d, { placeholder: 'MM / AAAA', inputMode: 'numeric', autoComplete: 'off' })} />}
              </Field>
              <Field name="plate" label="Matrícula" error={errors.plate}>
                {(d) => <input {...input('plate', d, { placeholder: '00·00·AA', autoComplete: 'off', autoCapitalize: 'characters', spellCheck: false })} />}
              </Field>
            </div>

            <div className="addv-row">
              <Field name="iucValidUntil" label="IUC válido até (opcional)" error={errors.iucValidUntil}>
                {(d) => <input {...input('iucValidUntil', d, { placeholder: 'DD / MM / AAAA', inputMode: 'numeric', autoComplete: 'off' })} />}
              </Field>
              <Field name="insuranceValidUntil" label="Seguro válido até (opcional)" error={errors.insuranceValidUntil}>
                {(d) => <input {...input('insuranceValidUntil', d, { placeholder: 'DD / MM / AAAA', inputMode: 'numeric', autoComplete: 'off' })} />}
              </Field>
            </div>

            <div className="addv-row">
              <Field name="inspectionValidUntil" label="Inspeção válida até (opcional)" error={errors.inspectionValidUntil}>
                {(d) => <input {...input('inspectionValidUntil', d, { placeholder: 'DD / MM / AAAA', inputMode: 'numeric', autoComplete: 'off' })} />}
              </Field>
            </div>

            <Field name="km" label="Quilometragem atual" error={errors.km} hint="Use o valor que consta no conta-quilómetros do veículo.">
              {(d) => (
                <span className="addv-km">
                  <input {...input('km', d, { placeholder: 'Introduza a quilometragem', inputMode: 'numeric', autoComplete: 'off' })} />
                  <span aria-hidden="true">km</span>
                </span>
              )}
            </Field>
          </div>

          <hr className="acct-sep" />
          <div className="acct-actions">
            <Link to="/veiculos" className="acct-btn" aria-disabled={saving} onClick={(e) => saving && e.preventDefault()}>Cancelar</Link>
            <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}>
              {saving ? 'A guardar…' : 'Guardar veículo'}
            </button>
          </div>
        </form>

        <aside className="addv-aside">
          <Icon name="car" size={30} />
          <h2>Carro ou moto, o mesmo cuidado.</h2>
          <p>
            Guarde a marca, o modelo, o mês e o ano de registo e a matrícula. A quilometragem atual é a
            referência para acompanhar as próximas intervenções.
          </p>
        </aside>
      </div>
    </div>
  )
}
