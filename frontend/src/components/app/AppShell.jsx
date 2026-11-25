import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import Icon from '../Icons.jsx'
import { useAuth } from '../../context/AuthContext.jsx'
import useGarage from '../../hooks/useGarage.js'
import { formatToday, initials } from '../../lib/format.js'
import '../../styles/app.css'

const NAV = [
  { to: '/painel', label: 'Painel', icon: 'layoutDashboard' },
  { to: '/veiculos', label: 'Veículos', icon: 'car' },
  { to: '/monitorizacao', label: 'Monitorização', icon: 'gauge' },
  { to: '/manutencao', label: 'Manutenção', icon: 'wrench' },
  { to: '/custos', label: 'Custos', icon: 'wallet' },
  { to: '/lembretes', label: 'Lembretes e prazos', icon: 'calendarDays' },
  { to: '/inspecao', label: 'Inspeção', icon: 'clipboard' },
]

const TITLES = Object.fromEntries(NAV.map((n) => [n.to, n.label]))
TITLES['/veiculos/novo'] = 'Adicionar veículo'
TITLES['/conta'] = 'Conta'
TITLES['/ajuda'] = 'Como utilizar'

// Páginas que pertencem a uma área do menu (ex.: Monitorização > Emparelhar dispositivo; Manutenção > Peças e intervalos > Editar intervalo)
const SUBPAGES = {
  '/dispositivo': { area: '/monitorizacao', current: 'Dispositivo OBD' },
  '/dispositivo/emparelhar': { area: '/monitorizacao', current: 'Emparelhar dispositivo' },
  '/localizacao': { area: '/monitorizacao', current: 'Localização' },
  '/oficinas': { area: '/manutencao', current: 'Oficinas próximas' },
}
const EDIT_INTERVAL = /^\/manutencao\/pecas\/[^/]+$/
const subpageOf = (pathname) => {
  if (SUBPAGES[pathname]) return { ...SUBPAGES[pathname], trail: [] }
  if (EDIT_INTERVAL.test(pathname)) {
    return { area: '/manutencao', trail: [{ to: '/manutencao/pecas', label: 'Peças e intervalos' }], current: 'Editar intervalo' }
  }
  return null
}
const areaOf = (pathname) => subpageOf(pathname)?.area

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`

/** Estrutura das páginas com sessão iniciada: menu lateral + barra superior (Figma "04 Painel") */
export default function AppShell() {
  const { user, logout, isMock } = useAuth()
  const { pathname } = useLocation()
  const subpage = subpageOf(pathname)
  const navigate = useNavigate()
  const garage = useGarage()
  const { vehicles, alerts } = garage

  const cars = vehicles.filter((v) => v.type === 'CARRO').length
  const bikes = vehicles.filter((v) => v.type === 'MOTO').length
  const hasOpenAlerts = alerts.some((a) => a.status === 'ABERTO')
  const name = user?.name ?? ''

  const handleLogout = () => {
    logout()
    navigate('/')
  }

  return (
    <div className="app">
      <aside className="app-side">
        <Link to="/painel" className="app-side__brand" aria-label="CarMotoCare, painel">
          <span className="app-side__logo"><Icon name="car" size={21} /></span>
          CarMotoCare
        </Link>

        <div className="app-side__garage">
          <p className="app-side__eyebrow">GARAGEM PESSOAL</p>
          <div className="app-side__garage-row">
            <div>
              <p className="app-side__garage-title">{plural(vehicles.length, 'veículo', 'veículos')}</p>
              <p className="app-side__garage-sub">
                {plural(cars, 'automóvel', 'automóveis')} · {plural(bikes, 'motociclo', 'motociclos')}
              </p>
            </div>
            <Icon name="chevronsUpDown" size={16} />
          </div>
        </div>

        <nav className="app-side__nav" aria-label="Áreas">
          {NAV.map((item) => (
            <NavLink key={item.to} to={item.to} className={({ isActive }) => `app-side__link${isActive || areaOf(pathname) === item.to ? ' is-active' : ''}`}>
              <Icon name={item.icon} size={18} />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="app-side__bottom">
          <div className="app-side__tip">
            <Icon name="shieldCheck" size={20} />
            <p className="app-side__tip-title">Cuidar dos seus veículos começa aqui</p>
            <p className="app-side__tip-text">Manutenção, quilometragem, custos e prazos</p>
          </div>

          <Link to="/ajuda" className="app-side__help">
            <Icon name="circleHelp" size={18} />
            Como utilizar
          </Link>

          <hr className="app-side__sep" />

          <Link to="/conta" className="app-side__account">
            <span className="app-side__avatar">{initials(name)}</span>
            <span className="app-side__who">
              <strong>{name || 'Conta pessoal'}</strong>
              <span>Conta pessoal</span>
            </span>
            <Icon name="chevronRight" size={16} />
          </Link>
        </div>
      </aside>

      <div className="app-main">
        <header className="app-top">
          <nav className="app-top__crumbs" aria-label="Localização">
            <span>CarMotoCare</span>
            <Icon name="chevronRight" size={13} />
            {subpage ? (
              <>
                <Link to={subpage.area}>{TITLES[subpage.area]}</Link>
                {subpage.trail.map((c) => (
                  <span key={c.to} className="app-top__crumb">
                    <Icon name="chevronRight" size={13} />
                    <Link to={c.to}>{c.label}</Link>
                  </span>
                ))}
                <Icon name="chevronRight" size={13} />
                <strong>{subpage.current}</strong>
              </>
            ) : (
              <strong>{TITLES[pathname] ?? (pathname.startsWith('/veiculos') ? 'Veículos' : pathname.startsWith('/manutencao') ? 'Manutenção' : pathname.startsWith('/custos') ? 'Custos' : 'Painel')}</strong>
            )}
          </nav>
          <div className="app-top__tools">
            <Link to="/lembretes" className="app-top__bell" aria-label="Notificações">
              <Icon name="bell" size={20} />
              {hasOpenAlerts && <span className="app-top__dot" aria-hidden="true" />}
            </Link>
            <span className="app-top__date">{formatToday()}</span>
            <button type="button" className="app-top__logout" onClick={handleLogout} aria-label="Terminar sessão" title="Terminar sessão">
              <Icon name="logOut" size={18} />
            </button>
          </div>
        </header>

        <div className="app-content">
          <Outlet context={garage} />
        </div>
      </div>
    </div>
  )
}
