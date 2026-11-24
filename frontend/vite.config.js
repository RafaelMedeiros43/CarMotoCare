import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Em desenvolvimento (npm run dev) o /api é reencaminhado para a EAPI Web,
// tal como o Nginx faz em produção (retira o prefixo /api).
// Com o Docker (docker compose up) não é preciso: o Nginx já faz isto.
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
})
