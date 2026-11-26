import { Link } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import './help.css'

// Figma: "06 Como utilizar" (página estática, sem chamadas à API)

const TOPICS = [
  ['car', 'Veículos', '/veiculos'],
  ['wrench', 'Manutenção', '/manutencao'],
  ['gauge', 'OBD e quilometragem', '/monitorizacao'],
  ['wallet', 'Custos', '/custos'],
  ['calendarDays', 'Lembretes e prazos', '/lembretes'],
]

const QUESTIONS = [
  ['Como registo uma manutenção?',
    'Selecione o veículo, abra Manutenção e escolha Adicionar manutenção. Indique a intervenção, a data, a quilometragem e o custo associado'],
  ['Como é atualizada a quilometragem?',
    'Automaticamente, através do dispositivo OBD emparelhado. Sem dispositivo, mantém o valor indicado ao adicionar o veículo e os lembretes por quilometragem ficam inativos'],
  ['Porque faltam parâmetros do OBD ou a posição GPS?',
    'A disponibilidade depende do veículo e do dispositivo. Consulte o estado da ligação e a última atualização. O GPS só é apresentado se o dispositivo disponibilizar uma posição'],
  ['Como são somados os custos de manutenção?',
    'Os custos ficam associados às manutenções e são acumulados por veículo e período. Um custo já incluído numa manutenção não deve ser registado novamente'],
  ['Como acompanho uma revisão ou um prazo legal?',
    'Em Lembretes e prazos, configure o veículo, o tipo de lembrete e uma condição por data ou limite de quilometragem. Consulte os próximos cuidados em Alertas e lembretes'],
]

export default function Help() {
  return (
    <div className="acct hlp">
      <div className="acct__head">
        <h1>Como utilizar o CarMotoCare</h1>
        <p>Respostas práticas para cuidar dos seus carros e motos na aplicação</p>
      </div>

      <div className="hlp-grid">
        <aside className="hlp-topics">
          <Icon name="shieldCheck" size={34} />
          <h2>O essencial, por área</h2>
          <p>Comece por adicionar um veículo. Depois, mantenha os registos associados a esse carro ou moto</p>
          <ul>
            {TOPICS.map(([icon, label, to]) => (
              <li key={to}><Link to={to}><Icon name={icon} size={19} />{label}</Link></li>
            ))}
          </ul>
        </aside>

        <section className="acct-card hlp-faq">
          <h2>Perguntas de utilização</h2>
          {QUESTIONS.map(([question, answer]) => (
            <div key={question} className="hlp-faq__item">
              <h3>{question}</h3>
              <p>{answer}</p>
            </div>
          ))}
        </section>
      </div>
    </div>
  )
}
