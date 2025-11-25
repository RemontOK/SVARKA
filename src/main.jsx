import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import './index.css'
import App from './App.jsx'
import { ThemeProvider } from './contexts/ThemeContext'

// Определяем basename для GitHub Pages
// Используем BASE_URL из Vite конфигурации
const basename = import.meta.env.BASE_URL || '/'
// Убираем завершающий слэш, если есть (BrowserRouter не любит двойные слэши)
const cleanBasename = basename.endsWith('/') ? basename.slice(0, -1) : basename

// Обработка hash из 404.html при перезагрузке (только если путь еще не установлен)
// Это резервная обработка на случай, если index.html не обработал hash
if (window.location.hash && window.location.hash.startsWith('#/') && window.location.pathname.includes('index.html')) {
  const hashPath = window.location.hash.substring(1)
  // Убеждаемся, что путь начинается с /
  const cleanHashPath = hashPath.startsWith('/') ? hashPath : '/' + hashPath
  // Устанавливаем правильный путь
  const expectedPath = cleanBasename + cleanHashPath
  window.history.replaceState(null, '', expectedPath + window.location.search)
  // Очищаем hash
  window.location.hash = ''
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename={cleanBasename || undefined}>
      <ThemeProvider>
        <App />
      </ThemeProvider>
    </BrowserRouter>
  </StrictMode>,
)
