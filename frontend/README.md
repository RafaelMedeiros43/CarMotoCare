# Frontend

Web App em React + Vite (JavaScript), PWA. Fala só com a EAPI Web: REST com JWT e WebSocket para
os alertas em tempo real. Ecrãs a partir dos mockups no Figma (ficheiro "ES").

**Contrato que consome:** [`docs/api/eapi-web.yaml`](../docs/api/eapi-web.yaml)

Pastas em `src/`:

```
src/
├── api/            chamadas à EAPI (client.js, auth.js, vehicles.js)
├── components/
│   ├── layout/     estrutura da app (AppShell, NotificationsMenu, Navbar, Footer, AuthAside) e o seu CSS
│   └── ui/         componentes reutilizáveis (Field, PasswordField, SelectField, VehicleSelect, Icons)
├── context/        sessão (AuthContext)
├── hooks/          hooks partilhados (useApi, useRemote, useGarage, useVehicleDetail)
├── lib/            funções puras partilhadas (format, obd, maintenance, costs, inspection, geo, vehicleForm)
├── mocks/          dados de exemplo no formato do contrato (apagar quando o backend estiver completo)
├── pages/          um ecrã por ficheiro, agrupado por área; cada pasta leva o seu CSS e componentes/hooks próprios
│   ├── home/  auth/  account/  dashboard/
│   ├── vehicles/       Vehicles, AddVehicle, EditVehicle, VehicleDetail (+ components/, useMaintenanceCosts)
│   ├── monitoring/     Monitoring, ObdDevice, PairObd, Location (+ useObdDevices, obd.css)
│   ├── maintenance/    Maintenance, PartsPlan, EditInterval, RegisterMaintenance, Workshops (+ components/WorkshopMap)
│   ├── costs/  reminders/  inspection/  help/
└── styles/         CSS partilhado: tokens, global, app (.acct*), monitoring (.mon-*), maintenance (.mnt-*), add-vehicle (.addv-*), costs (.cst-*)
```

```bash
npm install
npm run dev
```
