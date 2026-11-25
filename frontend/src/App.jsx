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
import Monitoring from './pages/Monitoring.jsx'
import ObdDevice from './pages/ObdDevice.jsx'
import PairObd from './pages/PairObd.jsx'
import Location from './pages/Location.jsx'
import Maintenance from './pages/Maintenance.jsx'
import PartsPlan from './pages/PartsPlan.jsx'
import RegisterMaintenance from './pages/RegisterMaintenance.jsx'
import EditInterval from './pages/EditInterval.jsx'
import Workshops from './pages/Workshops.jsx'
import Costs from './pages/Costs.jsx'
import RegisterCost from './pages/RegisterCost.jsx'
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
          <Route path="/monitorizacao" element={<Monitoring />} />
          <Route path="/dispositivo" element={<ObdDevice />} />
          <Route path="/dispositivo/emparelhar" element={<PairObd />} />
          <Route path="/localizacao" element={<Location />} />
          {/* Páginas do Figma ainda por fazer */}
          <Route path="/manutencao" element={<Maintenance />} />
          <Route path="/manutencao/pecas" element={<PartsPlan />} />
          <Route path="/manutencao/pecas/:partId" element={<EditInterval />} />
          <Route path="/manutencao/nova" element={<RegisterMaintenance />} />
          <Route path="/oficinas" element={<Workshops />} />
          <Route path="/custos" element={<Costs />} />
          <Route path="/custos/registar" element={<RegisterCost />} />
          {['/lembretes', '/inspecao', '/ajuda'].map((path) => (
            <Route key={path} path={path} element={<ComingSoon />} />
          ))}
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}
