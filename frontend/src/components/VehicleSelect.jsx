import Icon from './Icons.jsx'
import { vehicleLabel } from '../lib/obd.js'
import '../styles/monitoring.css'

/** Seletor "Veículo" (Figma 13 e 17): escolhe o veículo ativo da garagem */
export default function VehicleSelect({ vehicles, value, onChange }) {
  return (
    <div className="acct-field mon-select">
      <label htmlFor="vehicle-select">Veículo</label>
      <span className="mon-select__box">
        <select id="vehicle-select" value={value} onChange={(e) => onChange(e.target.value)}>
          {vehicles.map((v) => <option key={v.id} value={v.id}>{vehicleLabel(v)}</option>)}
        </select>
        <Icon name="chevronDown" size={18} />
      </span>
    </div>
  )
}
