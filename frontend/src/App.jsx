import { useEffect } from 'react'
import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import Navbar from './components/layout/Navbar.jsx'
import Footer from './components/layout/Footer.jsx'
import AppShell from './components/layout/AppShell.jsx'
import Home from './pages/home/Home.jsx'
import Login from './pages/auth/Login.jsx'
import Register from './pages/auth/Register.jsx'
import Dashboard from './pages/dashboard/Dashboard.jsx'
import Vehicles from './pages/vehicles/Vehicles.jsx'
import AddVehicle from './pages/vehicles/AddVehicle.jsx'
import VehicleDetail from './pages/vehicles/VehicleDetail.jsx'
import EditVehicle from './pages/vehicles/EditVehicle.jsx'
import Monitoring from './pages/monitoring/Monitoring.jsx'
import ObdDevice from './pages/monitoring/ObdDevice.jsx'
import PairObd from './pages/monitoring/PairObd.jsx'
import Location from './pages/monitoring/Location.jsx'
import Maintenance from './pages/maintenance/Maintenance.jsx'
import PartsPlan from './pages/maintenance/PartsPlan.jsx'
import RegisterMaintenance from './pages/maintenance/RegisterMaintenance.jsx'
import EditInterval from './pages/maintenance/EditInterval.jsx'
import Workshops from './pages/maintenance/Workshops.jsx'
import Costs from './pages/costs/Costs.jsx'
import RegisterCost from './pages/costs/RegisterCost.jsx'
import Inspection from './pages/inspection/Inspection.jsx'
import InspectionDetail from './pages/inspection/InspectionDetail.jsx'
import RegisterInspection from './pages/inspection/RegisterInspection.jsx'
import Help from './pages/help/Help.jsx'
import Reminders from './pages/reminders/Reminders.jsx'
import Account from './pages/account/Account.jsx'
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
          {/* Manutenção, oficinas, custos, lembretes, inspeção e ajuda */}
          <Route path="/manutencao" element={<Maintenance />} />
          <Route path="/manutencao/pecas" element={<PartsPlan />} />
          <Route path="/manutencao/pecas/:partId" element={<EditInterval />} />
          <Route path="/manutencao/nova" element={<RegisterMaintenance />} />
          <Route path="/oficinas" element={<Workshops />} />
          <Route path="/custos" element={<Costs />} />
          <Route path="/custos/registar" element={<RegisterCost />} />
          <Route path="/lembretes" element={<Reminders />} />
          <Route path="/inspecao" element={<Inspection />} />
          <Route path="/inspecao/registar" element={<RegisterInspection />} />
          <Route path="/inspecao/:vehicleId/:inspectionId" element={<InspectionDetail />} />
          <Route path="/ajuda" element={<Help />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}
