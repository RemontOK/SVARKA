import { Link, NavLink, useLocation } from 'react-router-dom'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY } from '../data/welders'

const Catalog = () => {
  const location = useLocation()

  return (
    <div className="catalog-page catalog-page--grid">
      <main className="catalog-main">
        <div className="catalog-categories">
          {WELDER_CATEGORIES.map((category) => (
            <Link key={category.id} to={`/catalog/${category.id}`} className="catalog-category-card">
              <div
                className="catalog-category-card__image"
                style={{ backgroundImage: `url(${category.image})` }}
              />
              <div className="catalog-category-card__content">
                <h3 className="catalog-category-card__title">{category.title}</h3>
                {category.subcategories && category.subcategories.length > 0 ? (
                  <ul className="catalog-category-card__subcategories">
                    {category.subcategories.map((sub, idx) => (
                      <li key={idx}>
                        <Link
                          to={`/catalog/${category.id}/${sub.name.toLowerCase().replace(/\s+/g, '-')}`}
                          onClick={(e) => e.stopPropagation()}
                        >
                          {sub.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <div className="catalog-category-card__empty">
                    <span className="link">
                      Перейти в категорию →
                    </span>
                  </div>
                )}
              </div>
            </Link>
          ))}
          
          <Link key={ACCESSORIES_CATEGORY.id} to={`/catalog/${ACCESSORIES_CATEGORY.id}`} className="catalog-category-card">
            <div
              className="catalog-category-card__image"
              style={{ backgroundImage: `url(${ACCESSORIES_CATEGORY.image})` }}
            />
            <div className="catalog-category-card__content">
              <h3 className="catalog-category-card__title">{ACCESSORIES_CATEGORY.title}</h3>
              {ACCESSORIES_CATEGORY.subcategories && ACCESSORIES_CATEGORY.subcategories.length > 0 ? (
                <ul className="catalog-category-card__subcategories">
                  {ACCESSORIES_CATEGORY.subcategories.map((sub, idx) => (
                    <li key={idx}>
                      <Link
                        to={`/catalog/${ACCESSORIES_CATEGORY.id}/${sub.name.toLowerCase().replace(/\s+/g, '-')}`}
                        onClick={(e) => e.stopPropagation()}
                      >
                        {sub.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (
                <div className="catalog-category-card__empty">
                  <span className="link">
                    Перейти в категорию →
                  </span>
                </div>
              )}
            </div>
          </Link>

          <Link key={PPE_CATEGORY.id} to={`/catalog/${PPE_CATEGORY.id}`} className="catalog-category-card">
            <div
              className="catalog-category-card__image"
              style={{ backgroundImage: `url(${PPE_CATEGORY.image})` }}
            />
            <div className="catalog-category-card__content">
              <h3 className="catalog-category-card__title">{PPE_CATEGORY.title}</h3>
              {PPE_CATEGORY.subcategories && PPE_CATEGORY.subcategories.length > 0 ? (
                <ul className="catalog-category-card__subcategories">
                  {PPE_CATEGORY.subcategories.map((sub, idx) => (
                    <li key={idx}>
                      <Link
                        to={`/catalog/${PPE_CATEGORY.id}/${sub.name.toLowerCase().replace(/\s+/g, '-')}`}
                        onClick={(e) => e.stopPropagation()}
                      >
                        {sub.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (
                <div className="catalog-category-card__empty">
                  <span className="link">
                    Перейти в категорию →
                  </span>
                </div>
              )}
            </div>
          </Link>

        </div>
      </main>

      <aside className="catalog-sidebar">
        <nav className="catalog-nav">
          {WELDER_CATEGORIES.map((cat) => (
            <NavLink
              key={cat.id}
              to={`/catalog/${cat.id}`}
              className={({ isActive, isPending }) =>
                `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
              }
            >
              <span className="catalog-nav__icon">{cat.icon || '⚡'}</span>
              <span className="catalog-nav__text">{cat.title}</span>
              {location.pathname.startsWith(`/catalog/${cat.id}`) && (
                <span className="catalog-nav__arrow">→</span>
              )}
            </NavLink>
          ))}
          <NavLink
            to={`/catalog/${ACCESSORIES_CATEGORY.id}`}
            className={({ isActive, isPending }) =>
              `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
            }
          >
            <span className="catalog-nav__icon">{ACCESSORIES_CATEGORY.icon || '⚫'}</span>
            <span className="catalog-nav__text">{ACCESSORIES_CATEGORY.title}</span>
            {location.pathname.startsWith(`/catalog/${ACCESSORIES_CATEGORY.id}`) && (
              <span className="catalog-nav__arrow">→</span>
            )}
          </NavLink>
          <NavLink
            to={`/catalog/${PPE_CATEGORY.id}`}
            className={({ isActive, isPending }) =>
              `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
            }
          >
            <span className="catalog-nav__icon">{PPE_CATEGORY.icon || '🛡️'}</span>
            <span className="catalog-nav__text">{PPE_CATEGORY.title}</span>
            {location.pathname.startsWith(`/catalog/${PPE_CATEGORY.id}`) && (
              <span className="catalog-nav__arrow">→</span>
            )}
          </NavLink>
        </nav>
      </aside>
    </div>
  )
}

export default Catalog

