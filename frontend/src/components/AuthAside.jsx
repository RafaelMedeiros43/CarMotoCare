import Icon from './Icons.jsx'

// Painel escuro da esquerda, igual no registo e no login
export default function AuthAside() {
  return (
    <aside className="reg__aside">
      <span className="reg__tag">Automóveis e motociclos</span>
      <h1>O cuidado de sempre. Tudo organizado.</h1>
      <p className="reg__lead">Uma conta pessoal para acompanhar a manutenção dos seus carros e motos.</p>

      <div className="garage">
        <Icon name="car" size={72} strokeWidth={1.2} />
        <span className="garage__sep" />
        <Icon name="bike" size={72} strokeWidth={1.2} />
        <div className="garage__text">
          <strong>A sua garagem</strong>
          <small>Carros e motos, num só lugar.</small>
        </div>
      </div>

      <ul className="reg__list">
        <li><Icon name="wrench" size={18} /> Registe manutenções e os seus custos.</li>
        <li><Icon name="gauge" size={18} /> Mantenha a quilometragem atualizada.</li>
        <li><Icon name="calendar" size={18} /> Organize lembretes e prazos legais.</li>
      </ul>
    </aside>
  )
}
