import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 3000,
    proxy: {
      // 本地开发时把 /api 请求代理到 PHP 后端（Docker: http://localhost:8891）
      '/api': {
        target: process.env.VITE_API_TARGET || 'http://localhost:8891',
        changeOrigin: true,
      },
    },
  },
})
