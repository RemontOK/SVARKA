import { Outlet, useLocation } from 'react-router-dom'
import { useEffect, useState } from 'react'
import Header from './Header'
import Footer from './Footer'
import LoadingSpinner from './LoadingSpinner'

const Layout = () => {
  const location = useLocation()
  const [isLoading, setIsLoading] = useState(false)

  useEffect(() => {
    setIsLoading(true)
    const timer = setTimeout(() => {
      setIsLoading(false)
    }, 200)
    return () => clearTimeout(timer)
  }, [location.pathname])

  return (
    <>
      <Header />
      <main className="page-shell">
        {isLoading ? (
          <div style={{ minHeight: '400px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <LoadingSpinner size="medium" />
          </div>
        ) : (
          <Outlet />
        )}
      </main>
      <Footer />
    </>
  )
}

export default Layout

