import { useState } from 'react'
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom'
import Field from '../../components/ui/Field.jsx'
import VehicleTypePicker from './components/VehicleTypePicker.jsx'
import useApi from '../../hooks/useApi.js'
import { formatPlateDots } from '../../lib/format.js'
import { buildVehicleUpdate, isoToMonthYear, maskMonthYear, maskPlate } from '../../lib/vehicleForm.js'
import Icon from '../../components/ui/Icons.jsx'
import '../../styles/add-vehicle.css'

// Figma: "11 Editar veículo" / "11b"
// Dados: PATCH /vehicles/{id} (VehicleUpdate: marca, modelo, 1.ª matrícula e matrícula; só envia o que mudou)
// O tipo mostra-se mas não se altera (a API não o permite); a km vem do dispositivo OBD

const FIELDS = ['brand', 'model', 'firstRegistration', 'plate']
const MASKS = { firstRegistration: maskMonthYear, plate: maskPlate }

function EditForm({ vehicle }) {
  const { reload, selectVehicle } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()
  const back = `/veiculos/${vehicle.id}`
  const name = `${vehicle.brand} ${vehicle.model}`

  const [form, setForm] = useState({
    brand: vehicle.brand,
    model: vehicle.model,
    firstRegistration: isoToMonthYear(vehicle.firstRegistration),
    plate: formatPlateDots(vehicle.plate),
  })
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const onChange = (e) => {
    const { name: field, value } = e.target
    setForm((f) => ({ ...f, [field]: MASKS[field] ? MASKS[field](value) : value }))
    setErrors((er) => (er[field] ? { ...er, [field]: undefined } : er))
  }
  const input = (field, describedBy, extra) => ({
    id: field, name: field, value: form[field], onChange, disabled: saving,
    'aria-invalid': errors[field] ? 'true' : undefined, 'aria-describedby': describedBy, ...extra,
  })

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const { errors: found, body } = buildVehicleUpdate(form, vehicle)
    setErrors(found)
    if (!body) {
      document.getElementById(Object.keys(found)[0])?.focus()
      return
    }
    if (Object.keys(body).length === 0) return navigate(back) // nada mudou: o PATCH exige pelo menos um campo

    setSaving(true)
    try {
      await api.updateVehicle(vehicle.id, body)
      await reload()
      selectVehicle(vehicle.id)
      navigate(back)
    } catch (err) {
      const byField = {}
      const general = []
      for (const f of err.fieldErrors ?? []) {
        if (FIELDS.includes(f.field)) byField[f.field] = f.message
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
        <h1>Editar veículo</h1>
        <p>{name} · {formatPlateDots(vehicle.plate)}</p>
      </div>

      <div className="acct__grid">
        <form className="acct-card addv-card" onSubmit={onSubmit} noValidate>
          <div className="addv-intro">
            <h2>Dados do {name}</h2>
            <p>Mantenha a identificação do veículo atualizada.</p>
          </div>

          {formError && <div className="form-error" role="alert">{formError}</div>}

          <div className="addv-fields">
            <VehicleTypePicker value={vehicle.type} disabled onChange={() => {}} hint="O tipo de veículo não pode ser alterado depois de adicionado." />

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
          </div>

          <hr className="acct-sep" />
          <div className="acct-actions">
            <Link to={back} className="acct-btn" aria-disabled={saving} onClick={(e) => saving && e.preventDefault()}>Cancelar</Link>
            <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}>
              {saving ? 'A guardar…' : 'Guardar alterações'}
            </button>
          </div>
        </form>

        <aside className="addv-aside">
          <Icon name="car" size={30} />
          <h2>A mesma garagem. Dados atualizados</h2>
          <p>
            Confirme a marca, o modelo, o mês e o ano de registo e a matrícula antes de guardar. A quilometragem
            é atualizada automaticamente pelo dispositivo OBD
          </p>
        </aside>
      </div>
    </div>
  )
}

export default function EditVehicle() {
  const { vehicleId } = useParams()
  const { vehicles, loading, error } = useOutletContext()
  const vehicle = vehicles.find((v) => v.id === vehicleId)

  if (loading) return <p className="muted">A carregar…</p>
  if (!vehicle) {
    return (
      <div className="acct addv">
        <div className="acct__head">
          <h1>Veículo não encontrado</h1>
          <p>{error || 'Este veículo não existe ou já foi removido.'}</p>
        </div>
        <Link to="/veiculos" className="acct-btn acct-btn--self">Voltar aos veículos</Link>
      </div>
    )
  }
  return <EditForm key={vehicle.id} vehicle={vehicle} />
}
