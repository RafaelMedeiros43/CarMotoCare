// Localidades para a pesquisa de oficinas (a API pede latitude/longitude e não tem geocodificação)
export const PLACES = [
  ['Ponta Delgada, Açores', 37.7412, -25.6756],
  ['Angra do Heroísmo, Açores', 38.655, -27.2195],
  ['Horta, Açores', 38.5333, -28.6333],
  ['Funchal, Madeira', 32.6669, -16.9241],
  ['Lisboa', 38.7223, -9.1393],
  ['Porto', 41.1579, -8.6291],
  ['Braga', 41.5454, -8.4265],
  ['Coimbra', 40.2033, -8.4103],
  ['Setúbal', 38.5244, -8.8882],
  ['Évora', 38.5714, -7.9135],
  ['Faro', 37.0194, -7.9304],
]
export const DEFAULT_PLACE = PLACES[0]

const norm = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()

/** "ponta delgada" -> ['Ponta Delgada, Açores', lat, lon] (ou undefined) */
export const findPlace = (text) => {
  const n = norm(text)
  if (!n) return undefined
  return PLACES.find(([label]) => norm(label) === n) ?? PLACES.find(([label]) => norm(label).startsWith(n))
}

const TILE = 256

/** Projeção Web Mercator (a das tiles do OpenStreetMap): lat/lon -> píxeis do mundo no zoom z */
export function project(lat, lon, z) {
  const world = TILE * 2 ** z
  const sin = Math.sin((lat * Math.PI) / 180)
  return {
    x: ((lon + 180) / 360) * world,
    y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * world,
  }
}

/** Maior zoom em que o ponto mais distante (km) fica a menos de `maxPx` píxeis do centro */
export function fitZoom(maxKm, lat, maxPx = 190) {
  for (let z = 16; z >= 7; z--) {
    const metersPerPx = (156543.03 * Math.cos((lat * Math.PI) / 180)) / 2 ** z
    if ((maxKm * 1000) / metersPerPx <= maxPx) return z
  }
  return 7
}

export const TILE_SIZE = TILE
