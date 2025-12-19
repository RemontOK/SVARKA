import { NavLink, Link } from 'react-router-dom'
import { useState } from 'react'
import ThemeToggle from './ThemeToggle'
import CallbackModal from './CallbackModal'
import '../styles/components/Header.css'

const navItems = [
  { to: '/', label: 'Главная' },
  { to: '/catalog', label: 'Каталог' },
  { to: '/services', label: 'Сервис' },
  { to: '/about', label: 'О компании' },
]

const Header = () => {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)
  const [isCallbackModalOpen, setIsCallbackModalOpen] = useState(false)

  const toggleMobileMenu = () => {
    setIsMobileMenuOpen(!isMobileMenuOpen)
  }

  const closeMobileMenu = () => {
    setIsMobileMenuOpen(false)
  }

  return (
    <header className="site-header site-header--minimal">
      <div className="site-header__bar site-header__bar--minimal">
        <Link to="/" className="site-header__brand" onClick={closeMobileMenu}>
          <div className="site-header__logo">
            <span className="site-header__logo-top">Альфа</span>
            <span className="site-header__logo-bottom">Смарт</span>
          </div>
        </Link>

        <nav className={`site-nav site-nav--minimal ${isMobileMenuOpen ? 'site-nav--mobile-open' : ''}`}>
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                isActive ? 'site-nav__link site-nav__link--minimal-active' : 'site-nav__link'
              }
              onClick={closeMobileMenu}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="site-header__cta site-header__cta--minimal">
          <ThemeToggle />
          <span className="site-header__phone">+7 (800) 707-19-55</span>
          <button
            className="btn btn--outline site-header__catalog-btn"
            type="button"
            onClick={() => setIsCallbackModalOpen(true)}
          >
            Заказать звонок
          </button>
          <button
            className="site-header__mobile-menu-toggle"
            type="button"
            onClick={toggleMobileMenu}
            aria-label="Открыть меню"
          >
            <span className={`site-header__menu-icon ${isMobileMenuOpen ? 'site-header__menu-icon--open' : ''}`}>
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>
        </div>
      </div>
      <CallbackModal isOpen={isCallbackModalOpen} onClose={() => setIsCallbackModalOpen(false)} />
    </header>
  )
}

export default Header

