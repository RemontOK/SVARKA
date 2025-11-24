import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.jsx'

// Определяем basename для GitHub Pages
// Используем BASE_URL из Vite конфигурации
const basename = import.meta.env.BASE_URL || '/'
// Убираем завершающий слэш, если есть (BrowserRouter не любит двойные слэши)
const cleanBasename = basename.endsWith('/') ? basename.slice(0, -1) : basename

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename={cleanBasename || undefined}>
      <App />
    </BrowserRouter>
  </StrictMode>,
)
