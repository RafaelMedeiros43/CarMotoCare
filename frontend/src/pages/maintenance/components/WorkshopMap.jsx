import { useState } from 'react'
import Icon from '../../../components/ui/Icons.jsx'
import { fitZoom, project, TILE_SIZE } from '../../../lib/geo.js'
import { formatDistance } from '../../../lib/maintenance.js'

const COLS = 5
const ROWS = 3

/**
 * Mapa com tiles do OpenStreetMap e marcadores numerados (sem bibliotecas nem chave de API).
 * Mostra uma grelha 5×3 de tiles centrada na pesquisa; o zoom ajusta-se para caberem os resultados.
 */
export default function WorkshopMap({ center, results, selectedId, onSelect, onLocate, locating }) {
  const [zoomDelta, setZoomDelta] = useState(0)
  const farthest = results.reduce((m, w) => Math.max(m, w.distanceKm), 0.5)
  const z = Math.min(18, Math.max(5, fitZoom(farthest, center.lat) + zoomDelta))

  const c = project(center.lat, center.lon, z)
  const tx0 = Math.floor(c.x / TILE_SIZE) - Math.floor(COLS / 2)
  const ty0 = Math.floor(c.y / TILE_SIZE) - Math.floor(ROWS / 2)
  const world = 2 ** z
  const at = (lat, lon) => { const p = project(lat, lon, z); return { left: p.x - tx0 * TILE_SIZE, top: p.y - ty0 * TILE_SIZE } }

  const tiles = []
  for (let r = 0; r < ROWS; r++) {
    for (let col = 0; col < COLS; col++) {
      const x = ((tx0 + col) % world + world) % world
      const y = ty0 + r
      if (y < 0 || y >= world) continue
      tiles.push({ key: `${r}-${col}`, x, y, left: col * TILE_SIZE, top: r * TILE_SIZE })
    }
  }

  const selected = results.find((w) => w.id === selectedId)
  const here = at(center.lat, center.lon)

  return (
    <div className="wk-map">
      <div className="wk-map__plane" style={{ width: COLS * TILE_SIZE, height: ROWS * TILE_SIZE, marginLeft: -(c.x - tx0 * TILE_SIZE), marginTop: -(c.y - ty0 * TILE_SIZE) }}>
        {tiles.map((t) => (
          <img key={t.key} src={`https://tile.openstreetmap.org/${z}/${t.x}/${t.y}.png`} alt="" width={TILE_SIZE} height={TILE_SIZE}
            loading="lazy" referrerPolicy="no-referrer" style={{ left: t.left, top: t.top }} draggable="false" />
        ))}
        <span className="wk-map__here" style={here} title={`Está aqui · ${center.label}`} />
        {results.map((w, i) => (
          <button key={w.id} type="button" style={at(w.latitude, w.longitude)} onClick={() => onSelect(w.id)}
            className={`wk-pin wk-pin--${w.type === 'OFICINA' ? 'shop' : 'tyre'}${w.id === selectedId ? ' is-selected' : ''}`}
            aria-label={`${i + 1}. ${w.name}, a ${formatDistance(w.distanceKm)}`} aria-pressed={w.id === selectedId}>
            {i + 1}
          </button>
        ))}
        {selected && (
          <span className="wk-map__label" style={at(selected.latitude, selected.longitude)}>
            <b>{selected.name}</b> {formatDistance(selected.distanceKm)}
          </span>
        )}
        <span className="wk-map__label wk-map__label--here" style={here}>Está aqui · {center.label.split(',')[0]}</span>
      </div>

      <div className="wk-map__tools">
        <button type="button" aria-label="Aproximar" onClick={() => setZoomDelta((d) => d + 1)} disabled={z >= 18}><Icon name="plus" size={18} /></button>
        <button type="button" aria-label="Afastar" onClick={() => setZoomDelta((d) => d - 1)} disabled={z <= 5}><Icon name="minus" size={18} /></button>
        <button type="button" aria-label="Usar a minha localização" onClick={onLocate} disabled={locating}><Icon name="locate" size={18} /></button>
      </div>
      <p className="wk-map__credit">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a></p>
    </div>
  )
}
