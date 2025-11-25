import { Navigate, Route, Routes } from 'react-router-dom'
import './App.css'
import Layout from './components/Layout'
import Catalog from './pages/Catalog'
import CategoryView from './pages/CategoryView'
import SubcategoryView from './pages/SubcategoryView'
import ProductView from './pages/ProductView'
import Home from './pages/Home'
import Services from './pages/Services'
import About from './pages/About'

const App = () => {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Home />} />
        <Route path="catalog" element={<Catalog />} />
        <Route path="catalog/:categoryId" element={<CategoryView />} />
        <Route path="catalog/:categoryId/:subcategoryId" element={<SubcategoryView />} />
        <Route path="product/:productId" element={<ProductView />} />
        <Route path="services" element={<Services />} />
        <Route path="about" element={<About />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  )
}

export default App
