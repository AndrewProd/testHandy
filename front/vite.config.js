import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  // one CSS file — avoids a flash of unstyled content when a lazy route loads
  build: { cssCodeSplit: false },
  server: {
    port: 5173,
    // Reply Center Laravel harness runs on :8000 - proxy so the NATS Test
    // Harness view can POST there without CORS once you wire it up.
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
