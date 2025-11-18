import { NavLink } from 'react-router-dom'

const navItems = [
  { to: '/', label: 'Главная' },
  { to: '/catalog', label: 'Каталог' },
  { to: '/services', label: 'Сервис' },
]

const Header = () => {
  return (
    <header className="site-header site-header--minimal">
      <div className="site-header__bar site-header__bar--minimal">
        <div className="site-header__brand">
          <p className="site-header__title">АльфаСмарт</p>

        </div>

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

