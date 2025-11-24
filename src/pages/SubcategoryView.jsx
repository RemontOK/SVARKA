import { useState, useMemo } from 'react'
import { useParams, Link, NavLink, useLocation } from 'react-router-dom'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY, WELDERS } from '../data/welders'
import { getFiltersForCategory } from '../data/filters'
import SearchBar from '../components/SearchBar'
import ProductCard from '../components/ProductCard'
import SortSelect from '../components/SortSelect'
import Breadcrumbs from '../components/Breadcrumbs'
import LoadingSpinner from '../components/LoadingSpinner'
import CategoryFilters from '../components/CategoryFilters'

const SubcategoryView = () => {
  const { categoryId, subcategoryId } = useParams()
  const location = useLocation()
  const [searchTerm, setSearchTerm] = useState('')
  const [sortBy, setSortBy] = useState('default')
  const [isLoading, setIsLoading] = useState(false)
  const [activeFilters, setActiveFilters] = useState({})

  // Находим категорию по ID
  const category =
    WELDER_CATEGORIES.find((cat) => cat.id === categoryId) ||
    (categoryId === ACCESSORIES_CATEGORY.id ? ACCESSORIES_CATEGORY : null) ||
    (categoryId === PPE_CATEGORY.id ? PPE_CATEGORY : null)

  // Находим подкатегорию
  const subcategory = category?.subcategories?.find(
    (sub) => sub.name.toLowerCase().replace(/\s+/g, '-') === subcategoryId
  )

  if (!category || !subcategory) {
    return (
      <div className="catalog-page catalog-page--grid">
        <aside className="catalog-sidebar">
          <nav className="catalog-nav">
            {WELDER_CATEGORIES.map((cat) => (
              <NavLink
                key={cat.id}
                to={`/catalog/${cat.id}`}
                className={({ isActive }) =>
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
          </nav>
        </aside>
        <main className="catalog-main">
          <div className="category-view">
            <div className="section-heading">
              <h1>Подкатегория не найдена</h1>
              <Link to="/catalog" className="link">
                Вернуться в каталог →
              </Link>
            </div>
          </div>
        </main>
      </div>
    )
  }

  // Получаем фильтры для категории
  const categoryFilters = useMemo(() => {
    return getFiltersForCategory(categoryId)
  }, [categoryId])

  // Получаем товары конкретной подкатегории
  const products = useMemo(() => {
    // Фильтруем товары по категории и подкатегории
    let filtered = WELDERS.filter((product) => {
      const mappedCategory = product.category
      const matchesCategory = mappedCategory === categoryId
      const matchesSubcategory = product.subcategory === subcategory.name
      return matchesCategory && matchesSubcategory
    })

    // Применяем фильтры
    if (activeFilters.price_min) {
      const minPrice = Number(activeFilters.price_min)
      if (!isNaN(minPrice)) {
        filtered = filtered.filter((product) => product.price >= minPrice)
      }
    }
    if (activeFilters.price_max) {
      const maxPrice = Number(activeFilters.price_max)
      if (!isNaN(maxPrice)) {
        filtered = filtered.filter((product) => product.price <= maxPrice)
      }
    }
    if (activeFilters.brand) {
      filtered = filtered.filter((product) => product.brand === activeFilters.brand)
    }
    if (activeFilters['pipe-diameter-min']) {
      const minDiameter = Number(activeFilters['pipe-diameter-min'])
      if (!isNaN(minDiameter)) {
        filtered = filtered.filter((product) => (product.pipeDiameterMin || 0) >= minDiameter)
      }
    }
    if (activeFilters['pipe-diameter-max']) {
      const maxDiameter = Number(activeFilters['pipe-diameter-max'])
      if (!isNaN(maxDiameter)) {
        filtered = filtered.filter((product) => (product.pipeDiameterMax || 0) <= maxDiameter)
      }
    }
    if (activeFilters['cut-thickness']) {
      const thickness = Number(activeFilters['cut-thickness'])
      if (!isNaN(thickness)) {
        filtered = filtered.filter((product) => (product.cutThickness || 0) >= thickness)
      }
    }
    if (activeFilters['bevel-removal']) {
      filtered = filtered.filter((product) => product.bevelRemoval === true)
    }
    if (activeFilters['load-capacity']) {
      const capacity = Number(activeFilters['load-capacity'])
      if (!isNaN(capacity)) {
        filtered = filtered.filter((product) => (product.loadCapacity || 0) >= capacity)
      }
    }
    if (activeFilters['special-offers']) {
      filtered = filtered.filter((product) => product.specialOffer === true)
    }

    // Применяем поиск
    if (searchTerm) {
      const searchLower = searchTerm.toLowerCase()
      filtered = filtered.filter(
        (product) =>
          product.name.toLowerCase().includes(searchLower) ||
          product.brand.toLowerCase().includes(searchLower) ||
          product.type.toLowerCase().includes(searchLower) ||
          product.description.toLowerCase().includes(searchLower) ||
          product.tags.some((tag) => tag.toLowerCase().includes(searchLower))
      )
    }

    // Применяем сортировку
    const sorted = [...filtered].sort((a, b) => {
      switch (sortBy) {
        case 'price-asc':
          return a.price - b.price
        case 'price-desc':
          return b.price - a.price
        case 'rating-desc':
          return b.rating - a.rating
        case 'rating-asc':
          return a.rating - b.rating
        case 'popularity':
          return b.rating - a.rating
        default:
          return 0
      }
    })

    return sorted
  }, [categoryId, subcategory, searchTerm, sortBy, activeFilters])

  // Хлебные крошки
  const breadcrumbs = useMemo(() => {
    return [
      { label: 'Главная', to: '/' },
      { label: 'Каталог', to: '/catalog' },
      { label: category.title, to: `/catalog/${categoryId}` },
      { label: subcategory.name, to: '#' },
    ]
  }, [category, categoryId, subcategory])

  return (
    <div className="catalog-page catalog-page--grid">
      <aside className="catalog-sidebar">
        <nav className="catalog-nav">
          {WELDER_CATEGORIES.map((cat) => (
            <NavLink
              key={cat.id}
              to={`/catalog/${cat.id}`}
              className={({ isActive }) =>
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
        </nav>
        <div className="catalog-special-section">
          <Link to={`/catalog/${ACCESSORIES_CATEGORY.id}`} className="catalog-category-card">
            <div
              className="catalog-category-card__image"
              style={{ backgroundImage: `url(${ACCESSORIES_CATEGORY.image})` }}
            />
            <div className="catalog-category-card__content">
              <h3 className="catalog-category-card__title">{ACCESSORIES_CATEGORY.title}</h3>
            </div>
          </Link>
        </div>
        <div className="catalog-special-section">
          <Link to={`/catalog/${PPE_CATEGORY.id}`} className="catalog-category-card">
            <div
              className="catalog-category-card__image"
              style={{ backgroundImage: `url(${PPE_CATEGORY.image})` }}
            />
            <div className="catalog-category-card__content">
              <h3 className="catalog-category-card__title">{PPE_CATEGORY.title}</h3>
            </div>
          </Link>
        </div>
      </aside>

      <main className="catalog-main">
        <div className="category-view">
          <Breadcrumbs items={breadcrumbs} />
          <div className="section-heading">
            <div>
              <p className="eyebrow">
                <Link to={`/catalog/${categoryId}`} className="link">
                  {category.title}
                </Link>
              </p>
              <h1>{subcategory.name}</h1>
            </div>
            <p className="section-heading__support">{category.description}</p>
          </div>

          <div className="category-view__products">
            <SearchBar
              label="Поиск товаров"
              supporting="Введите название, бренд или характеристики"
              placeholder="Например, MIG 250, Fronius, TIG AC/DC"
              value={searchTerm}
              onChange={setSearchTerm}
              quickTags={['MIG 250', 'TIG AC/DC', 'Плазморез', 'Fronius']}
            />

            <CategoryFilters
              categoryId={categoryId}
              filters={categoryFilters}
              onFilterChange={setActiveFilters}
            />

            <div className="category-view__controls">
              <p className="category-view__result">
                Найдено {products.length} товаров
              </p>
              <SortSelect value={sortBy} onChange={setSortBy} />
            </div>

            {isLoading ? (
              <div className="category-view__loading">
                <LoadingSpinner size="large" />
              </div>
            ) : products.length > 0 ? (
              <div className="products-grid">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            ) : (
              <div className="category-view__empty">
                <p>Товары не найдены</p>
                {searchTerm && (
                  <button
                    type="button"
                    className="link"
                    onClick={() => setSearchTerm('')}
                  >
                    Очистить поиск
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      </main>
    </div>
  )
}

export default SubcategoryView

