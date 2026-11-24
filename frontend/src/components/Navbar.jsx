import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'
import Icon from './Icons.jsx'
import '../styles/layout.css'

export function Brand() {
  return (
    <Link to="/" className="brand" aria-label="CarMotoCare, página inicial">
      <span className="brand__logo"><Icon name="car" size={20} /></span>
      CarMotoCare
    </Link>
  )
}

export default function Navbar() {
  const { isAuthenticated, logout } = useAuth()
  const { pathname } = useLocation()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate('/')}

  return (
    <header className="navbar">
      <div className="container navbar__inner">
        <Brand />
        <nav className="navbar__actions" aria-label="Principal">
          {!isAuthenticated && pathname !== '/' && (
            <Link to="/#como-funciona" className="navbar__link navbar__link--muted">Como funciona</Link>
          )}
          {isAuthenticated ? (
            <>
              <button type="button" className="navbar__link" onClick={handleLogout}>Terminar sessão</button>
              <Link to="/painel" className="btn btn--primary btn--sm">Ir para o painel</Link>
            </>
          ) : (
            <>
              <Link to="/entrar" className="navbar__link">Iniciar sessão</Link>
              <Link to="/registar" className="btn btn--primary btn--sm">Criar conta</Link>
            </>
          )}
        </nav>
      </div>
    </header>
  )
}
