import { Link, useLocation } from 'react-router-dom'

const TITLES = {
  '/veiculos/novo': 'Adicionar veículo',
  '/monitorizacao': 'Monitorização',
  '/manutencao': 'Manutenção',
  '/custos': 'Custos',
  '/lembretes': 'Lembretes e prazos',
  '/inspecao': 'Inspeção',
  '/ajuda': 'Como utilizar',
}

// Placeholder das páginas do Figma que ainda não foram implementadas
export default function ComingSoon() {
  const { pathname } = useLocation()
  return (
    <div className="app-soon">
      <h1>{TITLES[pathname] ?? 'Em breve'}</h1>
      <p>Esta página ainda não foi implementada.</p>
      <Link to="/painel" className="btn btn--ghost">Voltar ao painel</Link>
    </div>
  )
}
