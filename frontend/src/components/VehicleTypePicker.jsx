import Icon from './Icons.jsx'

const VEHICLE_TYPES = [
  { value: 'CARRO', label: 'Automóvel', icon: 'car' },
  { value: 'MOTO', label: 'Motociclo', icon: 'bike' },
]

/** Escolha entre Automóvel e Motociclo (Figma: "Cartão / Opção de tipo") */
export default function VehicleTypePicker({ value, onChange, disabled, hint }) {
  return (
    <fieldset className="addv-type" disabled={disabled}>
      <legend>Tipo de veículo</legend>
      <div className="addv-type__options">
        {VEHICLE_TYPES.map((t) => (
          <label key={t.value} className={`addv-type__option${value === t.value ? ' is-selected' : ''}`}>
            <input type="radio" name="type" value={t.value} checked={value === t.value} onChange={onChange} />
            <Icon name={t.icon} size={24} />
            <span>{t.label}</span>
            {value === t.value ? <Icon name="checkCircle" size={17} /> : <span className="addv-type__dot" aria-hidden="true" />}
          </label>
        ))}
      </div>
      {hint && <p className="addv-hint">{hint}</p>}
    </fieldset>
  )
}
