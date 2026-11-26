import { useCallback, useEffect, useRef, useState } from 'react'

/**
 * Pedido com estado: `load` corre quando `key` muda (key nulo = não pedir)
 * Devolve { loading, data, error, reload }. `load` não precisa de ser estável
 */
export default function useRemote(load, key) {
  const loadRef = useRef(load)
  useEffect(() => { loadRef.current = load })
  const [version, setVersion] = useState(0)
  const [state, setState] = useState({ key: null, data: null, error: null })
  const fullKey = key == null ? null : `${key}#${version}`

  useEffect(() => {
    if (fullKey == null) return
    let cancelled = false
    loadRef.current()
      .then((data) => !cancelled && setState({ key: fullKey, data, error: null }))
      .catch((error) => !cancelled && setState({ key: fullKey, data: null, error }))
    return () => { cancelled = true }
  }, [fullKey])

  const current = state.key === fullKey
  return {
    loading: fullKey != null && !current,
    data: current ? state.data : null,
    error: current ? state.error : null,
    reload: useCallback(() => setVersion((v) => v + 1), []),
  }
}
