import { useState } from 'react'
import { Link, useNavigate, useOutletContext } from 'react-router-dom'
import Field from '../components/Field.jsx'
import Icon from '../components/Icons.jsx'
import SelectField from '../components/SelectField.jsx'
import useApi from '../hooks/useApi.js'
import useRemote from '../hooks/useRemote.js'
import { dateToIso, maskDate, maskKm } from '../lib/vehicleForm.js'
import { formatEuro } from '../lib/format.js'
import { MAINTENANCE_TYPES, parseEuro, todayIso } from '../lib/maintenance.js'
import { vehicleLabel } from '../lib/obd.js'
import '../styles/maintenance.css'

// Figma: "19 Registar manutenção" e "19 Registar manutenção — Erro de validação"
// Dados: POST /vehicles/{id}/maintenances (MaintenanceCreate) e GET /vehicles/{id}/parts para a lista de peças
// A API recusa datas no futuro e peças que não sejam do veículo (400 com `errors[{field,message}]`)
// O custo da manutenção é o total; o custo de cada peça é só informativo

const NOTES_MAX = 1000
const todayField = () => { const [y, m, d] = todayIso().split('-'); return `${d} / ${m} / ${y}` }
const newRow = () => ({ key: crypto.randomUUID(), partId: '', cost: '' })

// Campos de erro da API -> campos do formulário ('parts.1.partId' -> chave da linha)
const FIELDS = new Set(['type', 'date', 'km', 'cost', 'notes'])

export default function RegisterMaintenance() {
  const { vehicles, loading: garageLoading, selected, selectVehicle, reload } = useOutletContext()
  const api = useApi()
  const navigate = useNavigate()

  const [form, setForm] = useState({ type: '', date: todayField(), km: '', cost: '', notes: '' })
  const [rows, setRows] = useState([newRow()])
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)

  const vehicleId = selected?.id ?? null
  const { data: parts, error: partsError } = useRemote(() => api.listParts(vehicleId), vehicleId)

  if (garageLoading) return <p className="muted">A carregar…</p>
  if (!selected) {
    return (
      <div className="acct mnt">
        <div className="acct__head"><h1>Registar manutenção</h1><p>Ainda não tem veículos na garagem</p></div>
        <Link to="/veiculos/novo" className="acct-btn acct-btn--primary acct-btn--self">Adicionar veículo</Link>
      </div>
    )
  }

  const plan = parts ?? []
  const setField = (name, value) => {
    setForm((f) => ({ ...f, [name]: value }))
    setErrors((e) => (e[name] ? { ...e, [name]: undefined } : e))
  }
  const setRow = (key, patch) => {
    setRows((list) => list.map((r) => (r.key === key ? { ...r, ...patch } : r)))
    setErrors((e) => (e[key] ? { ...e, [key]: undefined } : e))
  }
  const changeVehicle = (id) => { selectVehicle(id); setRows([newRow()]) } // as peças são do veículo

  const partsTotal = rows.reduce((sum, r) => sum + (r.partId ? parseEuro(r.cost) ?? 0 : 0), 0)

  const onSubmit = async (e) => {
    e.preventDefault()
    setFormError('')
    const found = {}

    if (!form.type) found.type = 'Selecione o tipo de intervenção'
    const date = dateToIso(form.date)
    if (!form.date) found.date = 'Indique a data da manutenção'
    else if (!date) found.date = 'Use o formato DD / MM / AAAA com uma data real'
    else if (date > todayIso()) found.date = 'A data não pode ser no futuro'
    if (form.km === '') found.km = 'Introduza a quilometragem (por exemplo, 24 180 km)'
    const cost = form.cost.trim() === '' ? 0 : parseEuro(form.cost)
    if (cost === null) found.cost = 'Indique um valor em euros (por exemplo, 146,50)'

    const chosen = rows.filter((r) => r.partId || r.cost.trim() !== '')
    const body = []
    for (const r of chosen) {
      const partCost = r.cost.trim() === '' ? null : parseEuro(r.cost)
      if (!r.partId) found[r.key] = 'Escolha a peça'
      else if (partCost === null && r.cost.trim() !== '') found[r.key] = 'Custo da peça inválido'
      else body.push({ partId: r.partId, ...(partCost !== null && { cost: partCost }) })
    }

    setErrors(found)
    if (Object.keys(found).length) {
      setFormError('Corrija os campos assinalados e tente novamente')
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return
    }

    setSaving(true)
    try {
      await api.createMaintenance(selected.id, {
        type: form.type, date, km: Number(form.km), cost,
        ...(form.notes.trim() && { notes: form.notes.trim() }),
        ...(body.length && { parts: body }),
      })
      await reload() // o veículo traz a "próxima manutenção" e os alertas abertos
      navigate('/manutencao')
    } catch (err) {
      const mapped = {}
      for (const { field, message } of err.fieldErrors ?? []) {
        const m = /^parts\.(\d+)\./.exec(field)
        if (m) mapped[chosen[Number(m[1])]?.key ?? 'parts'] = message
        else if (FIELDS.has(field)) mapped[field] = message
      }
      setErrors(mapped)
      setFormError(Object.keys(mapped).length ? 'Corrija os campos assinalados e tente novamente' : err.message)
      setSaving(false)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }

  return (
    <div className="acct mnt">
      <div className="acct__head">
        <h1>Registar manutenção</h1>
        <p>Guarde uma intervenção no histórico do veículo</p>
      </div>

      {formError && (
        <div className="mnt-alert" role="alert">
          <strong>Não foi possível guardar a manutenção</strong>
          <span>{formError}</span>
        </div>
      )}

      <form className="mnt-form" onSubmit={onSubmit} noValidate>
        <section className="acct-card mnt-form__main">
          <h2>Dados da intervenção</h2>

          <SelectField id="vehicle-select" label="Veículo" value={selected.id} onChange={changeVehicle} disabled={saving} options={vehicles.map((v) => [v.id, vehicleLabel(v)])} />
          <SelectField id="type" label="Tipo de intervenção" value={form.type} onChange={(v) => setField('type', v)} disabled={saving}
            placeholder="Selecione o tipo de intervenção" options={MAINTENANCE_TYPES} error={errors.type}
            describedBy="type-hint" />
          <p id="type-hint" className="addv-hint mnt-form__hint">Opções: {MAINTENANCE_TYPES.map(([, t]) => t).join(' · ')}</p>

          <div className="mnt-form__two">
            <Field name="date" label="Data da manutenção" error={errors.date}>
              {(d) => <input id="date" name="date" value={form.date} onChange={(e) => setField('date', maskDate(e.target.value))} inputMode="numeric" placeholder="DD / MM / AAAA" disabled={saving} aria-invalid={errors.date ? 'true' : undefined} aria-describedby={d} />}
            </Field>
            <Field name="km" label="Quilometragem na intervenção" error={errors.km}>
              {(d) => (
                <span className="mnt-input">
                  <input id="km" name="km" value={form.km} onChange={(e) => setField('km', maskKm(e.target.value))} inputMode="numeric" placeholder="Introduza a leitura" disabled={saving} aria-invalid={errors.km ? 'true' : undefined} aria-describedby={d} />
                  <i>km</i>
                </span>
              )}
            </Field>
          </div>

          <Field name="cost" label="Custo da manutenção" error={errors.cost} hint="Este valor fica associado à intervenção e entra nos custos do veículo.">
            {(d) => (
              <span className="mnt-input">
                <input id="cost" name="cost" value={form.cost} onChange={(e) => setField('cost', e.target.value)} inputMode="decimal" placeholder="0,00" disabled={saving} aria-invalid={errors.cost ? 'true' : undefined} aria-describedby={d} />
                <i>€</i>
              </span>
            )}
          </Field>

          <fieldset className="mnt-parts">
            <legend>Peças substituídas</legend>
            {partsError ? (
              <p className="mnt-parts__off" role="status">O plano de peças deste veículo não está disponível no servidor 
              ({partsError.message}) Pode guardar a manutenção sem indicar peças</p>) : (<>
                <div className="mnt-parts__labels"><span>Peça</span><span>Custo da peça</span></div>
                {rows.map((r, i) => {
                  const taken = rows.filter((x) => x.key !== r.key).map((x) => x.partId)
                  return (
                    <div key={r.key} className="mnt-parts__row">
                      <SelectField id={`part-${r.key}`} label={`Peça ${i + 1}`} className="mnt-parts__select" value={r.partId} disabled={saving || parts == null}
                        onChange={(v) => setRow(r.key, { partId: v })} placeholder={parts == null ? 'A carregar peças…' : 'Escolha a peça'}
                        options={plan.filter((p) => !taken.includes(p.id)).map((p) => [p.id, p.name])} error={errors[r.key]} />
                      
                      <label className="mnt-parts__cost">
                        <span className="sr-only">Custo da peça {i + 1}</span>
                        <span className="mnt-input">
                          <input value={r.cost} onChange={(e) => setRow(r.key, { cost: e.target.value })} inputMode="decimal" placeholder="0,00" disabled={saving} />
                          <i>€</i>
                        </span>
                      </label>
                      <button type="button" className="mnt-parts__remove" aria-label={`Remover a peça ${i + 1}`} disabled={saving}
                        onClick={() => setRows((list) => (list.length > 1 ? list.filter((x) => x.key !== r.key) : [newRow()]))}>
                        <Icon name="x" size={17} />
                      </button>
                    </div>
                  )
                })}
                <button type="button" className="mnt-parts__add" disabled={saving || rows.length >= plan.length} onClick={() => setRows((l) => [...l, newRow()])}>+ Adicionar peça</button>
                <div className="mnt-parts__total"><strong>Total de peças</strong><b>{formatEuro(partsTotal)}</b></div>
                <p className="addv-hint">Escolha a peça na lista do plano deste veículo e indique o custo. O último serviço da peça atualiza-se sozinho. 
                  Use «Adicionar peça» para acrescentar mais linhas</p>
              </>
            )}
          </fieldset>

          <section className="mnt-notes">
            <h3>Notas técnicas</h3>
            <p>Anote recomendações do mecânico para trabalhos a realizar no futuro</p>
            <Field name="notes" label="Recomendações para o futuro" error={errors.notes}>
              {(d) => (
                <textarea id="notes" name="notes" rows={4} maxLength={NOTES_MAX} value={form.notes} disabled={saving} aria-describedby={d}
                  onChange={(e) => setField('notes', e.target.value)}
                  placeholder="Ex.: o mecânico recomendou verificar o ABS e as emissões de gases na próxima revisão. Ou escreva aqui outra observação ou recomendação." />
              )}
            </Field>
          </section>

          <div className="mnt-form__actions">
            <Link to="/manutencao" className="acct-btn" aria-disabled={saving}>Cancelar</Link>
            <button type="submit" className="acct-btn acct-btn--primary" disabled={saving}>{saving ? 'A guardar…' : 'Guardar manutenção'}</button>
          </div>
        </section>

        <aside className="mnt-form__side">
          <div className="mnt-tip mnt-tip--blue">
            <Icon name="wrench" size={30} />
            <h2>Um histórico que acompanha o veículo</h2>
            <p>Registe a intervenção realizada, a data e os quilómetros desse momento. Assim, pode consultar os cuidados anteriores do seu carro ou moto</p>
          </div>
          <div className="mnt-tip">
            <Icon name="wallet" size={30} />
            <h3>O custo só conta uma vez</h3>
            <p>Se indicar o custo aqui, não precisa de o registar novamente na área de custos</p>
          </div>
        </aside>
      </form>
    </div>
  )
}
