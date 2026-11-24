import { Link } from 'react-router-dom'
import Icon from './Icons.jsx'
import { Brand } from './Navbar.jsx'
import '../styles/layout.css'

export default function Footer() {
  return (
    <footer className="footer">
      <div className="container">
        <div className="footer__top">
          <div>
            <Brand />
            <p className="footer__about">
              Veículos, manutenção e custos, num só lugar. Para cuidar dos seus automóveis e motociclos com mais clareza
            </p>
          </div>

          <div>
            <h4>Plataforma</h4>
            <ul>
              <li><a href="/#funcionalidades">Veículos e manutenção</a></li>
              <li><a href="/#obd">Monitorização OBD</a></li>
              <li><a href="/#funcionalidades">Oficinas próximas</a></li>
            </ul>
          </div>

          <div>
            <h4>Começar</h4>
            <ul>
              <li><Link to="/registar">Criar conta</Link></li>
              <li><Link to="/entrar">Iniciar sessão</Link></li>
              <li><a href="/#duvidas">Dúvidas frequentes</a></li>
            </ul>
          </div>

          <div>
            <h4>Informação legal</h4>
            <ul>
              <li><a href="/#">Privacidade</a></li>
              <li><a href="/#">Termos de utilização</a></li>
              <li><a href="/#">Cookies</a></li>
            </ul>
          </div>
        </div>

        <div className="footer__bottom">
          <span>© 2026 CarMotoCare. Todos os direitos reservados</span>
          <span className="footer__lang"><Icon name="globe" size={15} /> Português (Portugal)</span>
        </div>
      </div>
    </footer>
  )
}
