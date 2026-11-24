import { Link } from 'react-router-dom'
import Icon from '../components/Icons.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import '../styles/home.css'

const STRIP = [
  { icon: 'car', label: 'Veículos' },
  { icon: 'wrench', label: 'Manutenção' },
  { icon: 'wallet', label: 'Custos' },
  { icon: 'bell', label: 'Lembretes' },
  { icon: 'pin', label: 'Oficinas' },
]

const FEATURES = [
  { icon: 'car', title: 'Adicione e consulte vários veículos', text: 'Registe marca, modelo, ano, matrícula e quilometragem de cada automóvel ou motociclo.', tag: 'Vários veículos na mesma conta' },
  { icon: 'wrench', title: 'Registe e acompanhe manutenções', text: 'Acompanhe intervenções, datas e quilómetros para ter o histórico de cada veículo sempre atualizado.', tag: 'Histórico por veículo' },
  { icon: 'gauge', title: 'Acompanhe a quilometragem', text: 'A quilometragem é atualizada automaticamente pelo dispositivo OBD, quando emparelhado.', tag: 'Automática com OBD' },
  { icon: 'wallet', title: 'Registe e consulte custos', text: 'Registe custos associados às manutenções e consulte os valores acumulados por veículo.', tag: 'Custos por veículo' },
  { icon: 'bell', title: 'Configure lembretes e prazos', text: 'Defina lembretes por data ou quilometragem para manutenções e obrigações legais.', tag: 'Data ou quilómetros' },
  { icon: 'clipboard', title: 'Acompanhe obrigações legais', text: 'Acompanhe inspeção, seguro e IUC com lembretes para não perder prazos.', tag: 'Prazos organizados' },
]

const STEPS = [
  { icon: 'userPlus', title: 'Crie a sua conta', text: 'Registe-se para reunir a gestão dos seus veículos no seu espaço pessoal.' },
  { icon: 'car', title: 'Adicione os seus veículos', text: 'Introduza os dados essenciais de cada automóvel ou motociclo e a quilometragem atual.' },
  { icon: 'clipboard', title: 'Registe e acompanhe', text: 'Adicione manutenções e despesas, defina lembretes e ligue um dispositivo OBD, se o pretender.' },
]

const BENEFITS = [
  { icon: 'folderCheck', title: 'Tudo num só lugar', text: 'Veículos, manutenção, quilometragem, custos e lembretes organizados.' },
  { icon: 'calendarClock', title: 'Planeie com antecedência', text: 'Consulte prazos, quilometragem e lembretes para antecipar os próximos cuidados.' },
  { icon: 'trending', title: 'Acompanhe custos e manutenção', text: 'Consulte custos acumulados e acompanhe a manutenção de cada veículo.' },
]

const FAQ = [
  { q: 'Posso gerir carros e motos na mesma conta?', a: 'Sim. Pode adicionar vários automóveis e motociclos à sua conta pessoal e consultar os dados, a manutenção, os custos e os lembretes de cada um.' },
  { q: 'Preciso de um dispositivo OBD para começar?', a: 'Não. Pode registar veículos, manutenções, custos e prazos por data sem OBD. A quilometragem e os lembretes por quilometragem só são atualizados com um dispositivo OBD.' },
  { q: 'Que dados consigo ver através de OBD?', a: 'Apenas os parâmetros suportados pelo veículo e pelo dispositivo. A plataforma apresenta o estado da ligação e a última atualização; não é garantida compatibilidade universal.' },
  { q: 'A localização GPS está sempre disponível?', a: 'Sim, se o dispositivo OBD disponibilizar uma posição. A posição é atualizada sempre que entra na aplicação; sem leituras recentes, é apresentada a última posição recebida.' },
  { q: 'Como funcionam os lembretes?', a: 'Pode definir lembretes por data ou quilometragem para a manutenção e para obrigações legais, como inspeção, seguro e IUC, conforme aplicável ao veículo.' },
]

export default function Home() {
  const { isAuthenticated } = useAuth()

  return (
    <>
      {/* ---------- Hero ---------- */}
      <section className="hero">
        <div className="container hero__inner">
          <div className="hero__copy">
            <p className="eyebrow eyebrow--dot">Automóveis e motociclos</p>
            <h1>Crie a sua conta e organize veículos, manutenção, quilometragem e custos num só lugar.</h1>
            <p className="hero__lead">
              Veja como a sua área pessoal reúne carros e motos, manutenções, quilometragem,
              monitorização OBD, custos e lembretes, com a quilometragem atualizada pelo dispositivo OBD, quando disponível.
            </p>
            <div className="hero__cta">
              <Link to={isAuthenticated ? '/painel' : '/registar'} className="btn btn--primary">
                {isAuthenticated ? 'Ir para o painel' : 'Criar conta'} <Icon name="arrowRight" size={18} />
              </Link>
              <a href="#como-funciona" className="btn btn--ghost">Ver como funciona</a>
            </div>
            <p className="hero__note">
              <Icon name="checkCircle" size={16} className="text-primary" />
              A quilometragem é atualizada automaticamente por um dispositivo OBD, quando emparelhado.
            </p>
          </div>

          <figure className="demo" aria-label="Exemplo de organização de veículos, manutenção, quilometragem e custos">
            <div className="demo__window">
              <div className="demo__bar">
                <span className="demo__dots"><i /><i /><i /></span>
                <span className="demo__path">CarMotoCare / Visão geral</span>
                <span className="demo__tag">Demonstração</span>
              </div>
              <div className="demo__body">
                <div className="demo__head">
                  <div>
                    <h2>Veículos, manutenção e custos.</h2>
                    <p>Marca, modelo, matrícula, quilometragem e custos acumulados.</p>
                  </div>
                  <span className="demo__plus"><Icon name="plus" size={16} /></span>
                </div>

                <div className="demo__vehicles">
                  <div className="vcard">
                    <div className="vcard__top">
                      <Icon name="car" size={22} className="text-primary" />
                      <div><strong>Volkswagen Golf</strong><small>AB · 24 · CD &nbsp;/&nbsp; 2021</small></div>
                    </div>
                    <div className="vcard__km">68 420 km</div>
                    <small className="text-ok">Atualizado ao abrir a app · há 12 min</small>
                  </div>
                  <div className="vcard">
                    <div className="vcard__top">
                      <Icon name="bike" size={22} className="text-primary" />
                      <div><strong>Yamaha MT-07</strong><small>12 · 34 · EF &nbsp;/&nbsp; 2024</small></div>
                    </div>
                    <div className="vcard__km">24 180 km</div>
                    <small>Sem dispositivo OBD</small>
                  </div>
                </div>

                <div className="demo__row">
                  <div className="box">
                    <small className="box__label">CUSTOS ACUMULADOS</small>
                    <div className="box__value">1 248,60 €</div>
                    <div className="bars"><i /><i /><i /></div>
                    <small>Registos de 2026</small>
                  </div>
                  <div className="box">
                    <small className="box__label">LEMBRETES E PRAZOS</small>
                    <div className="reminder">
                      <Icon name="calendarCheck" size={20} className="text-warn" />
                      <div><strong>Inspeção periódica</strong><small className="text-warn">24 nov. 2026</small></div>
                    </div>
                    <div className="reminder">
                      <Icon name="wrench" size={20} className="text-primary" />
                      <div><strong>Próxima manutenção</strong><small>aos 75 000 km</small></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <figcaption>Exemplo de organização de veículos, manutenção, quilometragem e custos.</figcaption>
          </figure>
        </div>
      </section>

      {/* ---------- Faixa de áreas ---------- */}
      <div className="strip">
        <div className="container strip__inner">
          {STRIP.map((s) => (
            <span key={s.label} className="strip__item"><Icon name={s.icon} size={18} className="text-primary" />{s.label}</span>
          ))}
        </div>
      </div>

      {/* ---------- Funcionalidades ---------- */}
      <section id="funcionalidades" className="section features">
        <div className="container">
          <p className="eyebrow">Funcionalidades principais</p>
          <h2 className="h2 h2--narrow">O essencial para acompanhar os seus veículos.</h2>
          <p className="lead">Acompanhe veículos, manutenção, quilometragem, custos e lembretes num só lugar.</p>

          <div className="features__grid">
            {FEATURES.map((f) => (
              <article key={f.title} className="fcard">
                <span className="ibox"><Icon name={f.icon} size={18} /></span>
                <h3>{f.title}</h3>
                <p>{f.text}</p>
                <span className="fcard__tag">{f.tag}</span>
              </article>
            ))}
          </div>

          <article className="fwide">
            <span className="ibox ibox--soft"><Icon name="pin" size={18} /></span>
            <div>
              <h3>Encontre oficinas próximas</h3>
              <p>Pesquise oficinas próximas à sua localização atual para manutenção, reparação ou inspeção periódica.</p>
            </div>
            <span className="fcard__tag">Por localização GPS</span>
          </article>
        </div>
      </section>

      {/* ---------- OBD ---------- */}
      <section id="obd" className="obd">
        <div className="container obd__inner">
          <div>
            <p className="eyebrow eyebrow--light">Monitorização com OBD</p>
            <h2 className="h2 h2--light">Acompanhe os parâmetros suportados pelo veículo e pelo dispositivo.</h2>
            <p className="obd__lead">
              A aplicação apresenta os parâmetros suportados pelo veículo e pelo dispositivo, o estado da ligação e a última atualização.
            </p>
            <ul className="obd__list">
              <li><Icon name="pin" size={18} /> A localização GPS é enviada pelo dispositivo e apresentada no mapa.</li>
              <li><Icon name="pencil" size={18} /> Sem dispositivo? Acompanhe manutenção, custos e prazos por data.</li>
            </ul>
          </div>

          <div className="obdcard">
            <div className="obdcard__head">
              <div><h3>Monitorização OBD</h3><small>Volkswagen Golf · Exemplo</small></div>
              <Icon name="signal" size={20} className="text-primary" />
            </div>
            <div className="obdcard__status">
              <span><i className="dot" /> Dispositivo emparelhado</span>
              <small>Última atualização: há 12 min</small>
            </div>
            <div className="obdcard__metrics">
              <div className="metric"><small>Quilometragem</small><strong>68 420 km</strong><small className="text-ok">Parâmetro suportado</small></div>
              <div className="metric"><small>Temperatura do óleo</small><strong>89 °C</strong><small className="text-ok">Parâmetro suportado</small></div>
            </div>
            <div className="obdcard__gps">
              <Icon name="pin" size={20} />
              <div><strong>Localização GPS</strong><small>Ponta Delgada · recebida às 10:48</small></div>
            </div>
            <p className="obdcard__note">
              Os dados disponíveis dependem do veículo e do dispositivo. A ligação OBD não garante acesso a todos os parâmetros.
            </p>
          </div>
        </div>
      </section>

      {/* ---------- Como funciona + Benefícios ---------- */}
      <section id="como-funciona" className="section how">
        <div className="container">
          <p className="eyebrow">Como funciona</p>
          <h2 className="h2">Da primeira entrada ao próximo cuidado.</h2>

          <ol className="steps">
            {STEPS.map((s, i) => (
              <li key={s.title} className="step">
                <div className="step__head">
                  <span className="step__num">{String(i + 1).padStart(2, '0')}</span>
                  <span className="step__line" />
                  <Icon name={s.icon} size={20} className="text-primary" />
                </div>
                <h3>{s.title}</h3>
                <p>{s.text}</p>
              </li>
            ))}
          </ol>

          <div className="benefits">
            <div>
              <p className="eyebrow">Benefícios</p>
              <h2 className="h2 h2--sm">O essencial, organizado.</h2>
            </div>
            {BENEFITS.map((b) => (
              <div key={b.title} className="benefit">
                <Icon name={b.icon} size={22} className="text-primary" />
                <h3>{b.title}</h3>
                <p>{b.text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ---------- FAQ ---------- */}
      <section id="duvidas" className="section faq">
        <div className="container faq__inner">
          <div>
            <p className="eyebrow">Dúvidas frequentes</p>
            <h2 className="h2">Antes de começar.</h2>
            <p className="lead lead--left">O essencial sobre a sua garagem digital, sem complicações.</p>
            <p className="faq__note"><Icon name="user" size={16} className="text-primary" /> Pensado para a gestão dos seus veículos.</p>
          </div>

          <div className="faq__list">
            {FAQ.map((item) => (
              <details key={item.q} open>
                <summary>
                  {item.q}
                  <Icon name="minus" size={16} className="faq__minus" />
                  <Icon name="plus" size={16} className="faq__plus" />
                </summary>
                <p>{item.a}</p>
              </details>
            ))}
          </div>
        </div>
      </section>
    </>
  )
}
