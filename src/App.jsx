import { Navigate, Route, Routes } from 'react-router-dom'
import './App.css'
import Layout from './components/Layout'
import Catalog from './pages/Catalog'
import Home from './pages/Home'
import Services from './pages/Services'

const App = () => {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="catalog" element={<Catalog />} />
        <Route path="services" element={<Services />} />
        <Route path="*" element={<Navigate to="/SVARKA/" replace />} />
      </Route>
    </Routes>
  )
}

export default App
