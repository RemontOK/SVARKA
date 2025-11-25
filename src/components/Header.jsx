import { NavLink, Link } from 'react-router-dom'
import ThemeToggle from './ThemeToggle'

const navItems = [
  { to: '/', label: 'Главная' },
  { to: '/catalog', label: 'Каталог' },
  { to: '/services', label: 'Сервис' },
  { to: '/about', label: 'О компании' },
]

const Header = () => {
  return (
    <header className="site-header site-header--minimal">
      <div className="site-header__bar site-header__bar--minimal">
        <Link to="/" className="site-header__brand">
          <div className="site-header__logo">
            <span className="site-header__logo-top">Альфа</span>
            <span className="site-header__logo-bottom">Смарт</span>
          </div>
        </Link>

        <nav className="site-nav site-nav--minimal">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                isActive ? 'site-nav__link site-nav__link--minimal-active' : 'site-nav__link'
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="site-header__cta site-header__cta--minimal">
          <ThemeToggle />
          <span>+7 (800) 707-19-55</span>
          <button className="btn btn--outline" type="button">
            Каталог
          </button>
        </div>
      </div>
    </header>
  )
}

export default Header

