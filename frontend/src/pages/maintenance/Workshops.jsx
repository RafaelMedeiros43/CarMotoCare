import { useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import Icon from '../../components/ui/Icons.jsx'
import WorkshopMap from './components/WorkshopMap.jsx'
import useApi from '../../hooks/useApi.js'
import useRemote from '../../hooks/useRemote.js'
import { DEFAULT_PLACE, findPlace, PLACES } from '../../lib/geo.js'
import { formatDistance } from '../../lib/maintenance.js'
import '../../styles/maintenance.css'

// Figma: "21 Oficinas e pneus próximos" (+ variantes "Só oficinas" e "Só lojas de pneus", que são o filtro "Tipo de serviço")
// Dados: GET /workshops?lat&lon&radiusKm&type&q (já ordenado por distância; OFICINA | LOJA_PNEUS). A API pede coordenadas e
// não geocodifica, por isso "Perto de" escolhe uma localidade da lista ou usa a localização do browser

const TYPES = [['', 'Todos'], ['OFICINA', 'Oficinas'], ['LOJA_PNEUS', 'Lojas de pneus']]
const RADII = [10, 25, 50]
const TYPE_LABEL = { OFICINA: 'Oficina', LOJA_PNEUS: 'Loja de pneus' }

const initial = { label: DEFAULT_PLACE[0], lat: DEFAULT_PLACE[1], lon: DEFAULT_PLACE[2], q: '', type: '', radiusKm: 50 }

export default function Workshops() {
  useOutletContext() // mantém a garagem carregada
  const api = useApi()
  const [search, setSearch] = useState(initial) // pesquisa em vigor
  const [qText, setQText] = useState('')
  const [placeText, setPlaceText] = useState(initial.label)
  const [placeError, setPlaceError] = useState('')
  const [locating, setLocating] = useState(false)
  const [sort, setSort] = useState('DISTANCE')
  const [pickedId, setPickedId] = useState(null)

  const key = `${search.lat},${search.lon},${search.radiusKm},${search.type},${search.q}`
  const { loading, data, error, reload } = useRemote(
    () => api.searchWorkshops({ lat: search.lat, lon: search.lon, radiusKm: search.radiusKm, type: search.type, q: search.q }),
    key,
  )

  const results = [...(data ?? [])].sort((a, b) => (sort === 'NAME' ? a.name.localeCompare(b.name, 'pt') : a.distanceKm - b.distanceKm))
  const selectedId = results.some((w) => w.id === pickedId) ? pickedId : results[0]?.id ?? null
  const unavailable = error && [404, 501].includes(error.status)

  const submit = (e) => {
    e.preventDefault()
    const place = findPlace(placeText)
    if (!place) {
      setPlaceError('Escolha uma localidade da lista (por exemplo, Ponta Delgada) ou use a sua localização')
      return
    }
    setPlaceError('')
    setPlaceText(place[0])
    setSearch((s) => ({ ...s, label: place[0], lat: place[1], lon: place[2], q: qText.trim() }))
  }

  const locate = () => {
    if (!navigator.geolocation) { setPlaceError('O seu browser não permite obter a localização'); return }
    setLocating(true)
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setLocating(false)
        setPlaceError('')
        setPlaceText('A sua localização')
        setSearch((s) => ({ ...s, label: 'A sua localização', lat: coords.latitude, lon: coords.longitude, q: qText.trim() }))
      },
      () => { setLocating(false); setPlaceError('Não foi possível obter a sua localização. Verifique a permissão do browser.') },
      { timeout: 10000 },
    )
  }

  return (
    <div className="acct mnt wk">
      <div className="acct__head">
        <h1>Oficinas e lojas de pneus próximas</h1>
        <p>Encontre o serviço de que precisa, perto de si</p>
      </div>

      <form className="wk-search" onSubmit={submit} noValidate>
        <div className="acct-field wk-search__q">
          <label htmlFor="wk-q">Oficina ou serviço</label>
          <span className="mnt-input mnt-input--icon">
            <Icon name="search" size={18} />
            <input id="wk-q" value={qText} onChange={(e) => setQText(e.target.value)} placeholder="Nome da oficina, revisão, pneus…" autoComplete="off" />
          </span>
        </div>
        <div className="acct-field wk-search__place">
          <label htmlFor="wk-place">Perto de</label>
          <span className="mnt-input mnt-input--icon">
            <Icon name="pin" size={18} />
            <input id="wk-place" list="wk-places" value={placeText} onChange={(e) => { setPlaceText(e.target.value); setPlaceError('') }}
              autoComplete="off" aria-invalid={placeError ? 'true' : undefined} aria-describedby={placeError ? 'wk-place-error' : undefined} />
          </span>
          <datalist id="wk-places">{PLACES.map(([label]) => <option key={label} value={label} />)}</datalist>
        </div>
        <button type="submit" className="acct-btn acct-btn--primary wk-search__go"><Icon name="search" size={17} /> Pesquisar</button>
        {placeError && <p id="wk-place-error" className="addv-error wk-search__error" role="alert">{placeError}</p>}
      </form>

      <div className="wk-filters">
        <span>Tipo de serviço</span>
        <div role="group" aria-label="Tipo de serviço">
          {TYPES.map(([value, label]) => (
            <button key={label} type="button" className={`wk-chip${search.type === value ? ' is-on' : ''}`} aria-pressed={search.type === value}
              onClick={() => setSearch((s) => ({ ...s, type: value }))}>{label}</button>
          ))}
        </div>
        <label className="wk-radius">
          Raio de pesquisa
          <span className="mon-select__box">
            <select value={search.radiusKm} onChange={(e) => setSearch((s) => ({ ...s, radiusKm: Number(e.target.value) }))}>
              {RADII.map((r) => <option key={r} value={r}>{r} km</option>)}
            </select>
            <Icon name="chevronDown" size={16} />
          </span>
        </label>
      </div>

      <div className="wk-grid">
        <section className="acct-card wk-mapcard" aria-label="Mapa">
          <header><Icon name="pin" size={18} /> <strong>{search.label}</strong></header>
          <WorkshopMap center={{ lat: search.lat, lon: search.lon, label: search.label }} results={results} selectedId={selectedId} onSelect={setPickedId} onLocate={locate} locating={locating} />
          <ul className="wk-legend">
            <li><i className="wk-dot wk-dot--here" /> A sua localização</li>
            <li><i className="wk-dot wk-dot--shop" /> Oficinas</li>
            <li><i className="wk-dot wk-dot--tyre" /> Lojas de pneus</li>
          </ul>
        </section>

        <section className="acct-card wk-results" aria-live="polite">
          <header>
            <div>
              <h2>Resultados próximos</h2>
              <p>{loading ? 'A pesquisar…' : error ? '—' : `${results.length} ${results.length === 1 ? 'resultado' : 'resultados'}`}</p>
            </div>
            <label className="wk-sort">
              <span className="sr-only">Ordenar por</span>
              <select value={sort} onChange={(e) => setSort(e.target.value)}>
                <option value="DISTANCE">Mais próximos</option>
                <option value="NAME">Nome</option>
              </select>
            </label>
          </header>

          {error ? (
            <div className="wk-state" role="alert">
              <strong>{unavailable ? 'A pesquisa de oficinas ainda não está disponível' : 'Não foi possível pesquisar'}</strong>
              <p>{unavailable ? 'O servidor ainda não tem este serviço ligado. Tente mais tarde.' : error.message}</p>
              {!unavailable && <button type="button" className="acct-btn acct-btn--self" onClick={reload}>Tentar de novo</button>}
            </div>
          ) : loading ? (
            <p className="muted wk-state">A procurar oficinas e lojas de pneus…</p>
          ) : results.length === 0 ? (
            <div className="wk-state">
              <strong>Sem resultados</strong>
              <p>Não encontrámos serviços neste raio. Aumente o raio de pesquisa ou mude o tipo de serviço</p>
            </div>
          ) : (
            <ol className="wk-list">
              {results.map((w, i) => (
                <li key={w.id}>
                  <button type="button" className={`wk-item${w.id === selectedId ? ' is-selected' : ''}`} aria-pressed={w.id === selectedId} onClick={() => setPickedId(w.id)}>
                    <span className="wk-item__n">{i + 1}</span>
                    <span className="wk-item__body">
                      <span className="wk-item__top"><strong>{w.name}</strong><small>{formatDistance(w.distanceKm)}</small></span>
                      {w.address && <span className="wk-item__addr">{w.address}</span>}
                      <span className="wk-item__type"><Icon name={w.type === 'OFICINA' ? 'wrench' : 'circleDot'} size={13} /> {TYPE_LABEL[w.type]}</span>
                      {w.services?.length > 0 && <span className="wk-item__services">{w.services.join(' · ')}</span>}
                    </span>
                  </button>
                </li>
              ))}
            </ol>
          )}
          <p className="wk-results__foot">Distâncias a partir de {search.label}.</p>
        </section>
      </div>

      <p className="wk-back"><Link to="/manutencao" className="link-arrow">Voltar ao histórico de manutenção</Link></p>
    </div>
  )
}
