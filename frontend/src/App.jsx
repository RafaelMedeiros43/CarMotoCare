import { useEffect } from 'react'
import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import Navbar from './components/Navbar.jsx'
import Footer from './components/Footer.jsx'
import AppShell from './components/app/AppShell.jsx'
import Home from './pages/Home.jsx'
import Login from './pages/Login.jsx'
import Register from './pages/Register.jsx'
import Dashboard from './pages/Dashboard.jsx'
import Vehicles from './pages/Vehicles.jsx'
import AddVehicle from './pages/AddVehicle.jsx'
import VehicleDetail from './pages/VehicleDetail.jsx'
import EditVehicle from './pages/EditVehicle.jsx'
import ComingSoon from './pages/ComingSoon.jsx'
import Account from './pages/Account.jsx'
import { useAuth } from './context/AuthContext.jsx'

// Rota privada: sem sessão, volta ao login
function Private({ children }) {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? children : <Navigate to="/entrar" replace />
}

// Ao navegar para "/#secção" (ex.: "Como funciona" a partir do registo), faz scroll até à secção
function ScrollToHash() {
  const { pathname, hash } = useLocation()
  useEffect(() => {
    if (hash) {
      document.getElementById(hash.slice(1))?.scrollIntoView()
    } else {
      window.scrollTo(0, 0)
    }
  }, [pathname, hash])
  return null
}

// Páginas de entrada/registo não mostram o rodapé como no Figma
const NO_FOOTER = ['/registar', '/entrar']

// Páginas públicas: barra de navegação + rodapé
function PublicLayout() {
  const { pathname } = useLocation()
  return (
    <>
      <Navbar />
      <main>
        <Outlet />
      </main>
      {!NO_FOOTER.includes(pathname) && <Footer />}
    </>
  )
}

export default function App() {
  return (
    <>
      <ScrollToHash />
      <Routes>
        <Route element={<PublicLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/entrar" element={<Login />} />
          <Route path="/registar" element={<Register />} />
        </Route>

        {/* Com sessão iniciada: menu lateral + barra superior (sem Navbar/Footer públicos) */}
        <Route element={<Private><AppShell /></Private>}>
          <Route path="/painel" element={<Dashboard />} />
          <Route path="/veiculos" element={<Vehicles />} />
          <Route path="/veiculos/novo" element={<AddVehicle />} />
          <Route path="/veiculos/:vehicleId" element={<VehicleDetail />} />
          <Route path="/veiculos/:vehicleId/editar" element={<EditVehicle />} />
          <Route path="/conta" element={<Account />} />
          {/* Páginas do Figma ainda por fazer */}
          {['/monitorizacao', '/manutencao', '/custos', '/lembretes', '/inspecao', '/ajuda'].map((path) => (
            <Route key={path} path={path} element={<ComingSoon />} />
          ))}
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}
