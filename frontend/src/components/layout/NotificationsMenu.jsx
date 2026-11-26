import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import Icon from '../ui/Icons.jsx'
import { daysUntil, formatDate, formatKm, timeAgo } from '../../lib/format.js'
import './notifications.css'

// Figma: "27 Inspeção — Notificações abertas". Alertas abertos (GET /alerts) com o veículo e há quanto tempo criadas
// "Marcar todas como lidas" só guarda no browser (a API não tem endpoint para fechar alertasq fecham-se ao registar a intervenção)

const READ_KEY = 'cmc-alerts-read'
const readIds = () => { try { return JSON.parse(localStorage.getItem(READ_KEY) ?? '[]') } catch { return [] } }

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`
const dueIn = (days) => (days < 0 ? `em atraso há ${plural(-days, 'dia', 'dias')}` : `em ${plural(days, 'dia', 'dias')}`)

function describe(alert) {
  const days = alert.dueDate ? daysUntil(alert.dueDate) : null
  switch (alert.type) {
    case 'INSPECAO': return { icon: 'clipboard', warn: true, title: `Alerta: inspeção ${days < 0 ? 'expirada' : 'a expirar'} ${dueIn(days)}`, detail: `válida até ${formatDate(alert.dueDate)}` }
    case 'SEGURO': return { icon: 'shieldCheck', title: `Seguro a renovar ${dueIn(days)}`, detail: formatDate(alert.dueDate) }
    case 'IUC': return { icon: 'wallet', title: `IUC a pagar ${dueIn(days)}`, detail: formatDate(alert.dueDate) }
    default: return { icon: 'wrench', title: `${alert.title} aos ${formatKm(alert.targetKm)} a aproximar-se`, detail: `faltam ${formatKm(alert.kmRemaining)}` }
  }
}

/** Sino da barra superior com o painel de notificações */
export default function NotificationsMenu({ alerts, vehicles }) {
  const [open, setOpen] = useState(false)
  const [read, setRead] = useState(readIds)
  const box = useRef(null)
  const open_ = alerts.filter((a) => a.status === 'ABERTO')
  const unread = open_.some((a) => !read.includes(a.id))

  useEffect(() => {
    if (!open) return undefined
    const onClick = (e) => { if (!box.current?.contains(e.target)) setOpen(false) }
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onClick); document.removeEventListener('keydown', onKey) }
  }, [open])

  const markAll = () => {
    const ids = open_.map((a) => a.id)
    setRead(ids)
    try { localStorage.setItem(READ_KEY, JSON.stringify(ids)) } catch { /* sem armazenamento: só nesta sessão */ }
  }
  const vehicleName = (id) => { const v = vehicles.find((x) => x.id === id); return v ? `${v.brand} ${v.model}` : '' }

  return (
    <div className="ntf" ref={box}>
      <button type="button" className="app-top__bell" aria-label="Notificações" aria-expanded={open} aria-haspopup="true" onClick={() => setOpen((o) => !o)}>
        <Icon name="bell" size={20} />
        {unread && <span className="app-top__dot" aria-hidden="true" />}
      </button>
      {open && (
        <div className="ntf__panel" role="dialog" aria-label="Notificações">
          <div className="ntf__head">
            <h2>Notificações</h2>
            {open_.length > 0 && <button type="button" onClick={markAll}>Marcar todas como lidas</button>}
          </div>
          {open_.length === 0 ? (
            <p className="ntf__empty">Sem alertas abertos</p>
          ) : (
            <ul>
              {open_.map((a) => {
                const d = describe(a)
                return (
                  <li key={a.id} className={`${d.warn ? 'is-warn' : ''}${read.includes(a.id) ? ' is-read' : ''}`}>
                    <Icon name={d.icon} size={18} />
                    <div>
                      <strong>{d.title}</strong>
                      <small>{vehicleName(a.vehicleId)} · {d.detail} · {timeAgo(a.createdAt)}</small>
                    </div>
                  </li>
                )
              })}
            </ul>
          )}
          <Link to="/lembretes" className="ntf__all" onClick={() => setOpen(false)}>Ver todos os alertas</Link>
        </div>
      )}
    </div>
  )
}
