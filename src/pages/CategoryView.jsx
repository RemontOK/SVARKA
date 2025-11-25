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

const CategoryView = () => {
  const { categoryId } = useParams()
  const location = useLocation()
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedSubcategory, setSelectedSubcategory] = useState(null)
  const [sortBy, setSortBy] = useState('default')
  const [isLoading, setIsLoading] = useState(false)
  const [activeFilters, setActiveFilters] = useState({})

  // Находим категорию по ID
  const category =
    WELDER_CATEGORIES.find((cat) => cat.id === categoryId) ||
    (categoryId === ACCESSORIES_CATEGORY.id ? ACCESSORIES_CATEGORY : null) ||
    (categoryId === PPE_CATEGORY.id ? PPE_CATEGORY : null)

  // Получаем фильтры для категории
  const categoryFilters = useMemo(() => {
    return getFiltersForCategory(categoryId)
  }, [categoryId])

  // Получаем товары из всех подкатегорий или конкретной подкатегории
  const products = useMemo(() => {
    if (!category) return []
    
    // Фильтруем товары по категории
    let filtered = WELDERS.filter((product) => {
      const mappedCategory = product.category
      return mappedCategory === categoryId
    })

    // Если выбрана подкатегория, фильтруем по ней
    if (selectedSubcategory) {
      filtered = filtered.filter(
        (product) => product.subcategory === selectedSubcategory
      )
    }

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
        // Предполагаем, что у товара есть поле pipeDiameterMin
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
          // Популярность = рейтинг * количество отзывов (используем рейтинг как основу)
          return b.rating - a.rating
        default:
          return 0
      }
    })

    return sorted
  }, [category, categoryId, selectedSubcategory, searchTerm, sortBy, activeFilters])

  // Хлебные крошки
  const breadcrumbs = useMemo(() => {
    const items = [
      { label: 'Главная', to: '/' },
      { label: 'Каталог', to: '/catalog' },
    ]
    if (category) {
      items.push({ label: category.title, to: `/catalog/${categoryId}` })
    }
    if (selectedSubcategory) {
      items.push({ label: selectedSubcategory, to: '#' })
    }
    return items
  }, [category, categoryId, selectedSubcategory])

  const handleSubcategoryClick = (subcategoryName) => {
    if (selectedSubcategory === subcategoryName) {
      // Если уже выбрана, снимаем выбор
      setSelectedSubcategory(null)
    } else {
      setSelectedSubcategory(subcategoryName)
    }
  }

  if (!category) {
    return (
      <div className="catalog-page catalog-page--grid">
        <main className="catalog-main">
          <div className="category-view">
            <div className="section-heading">
              <h1>Категория не найдена</h1>
              <Link to="/catalog" className="link">
                Вернуться в каталог →
              </Link>
            </div>
          </div>
        </main>
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
            <NavLink
              to={`/catalog/${ACCESSORIES_CATEGORY.id}`}
              className={({ isActive }) =>
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
              className={({ isActive }) =>
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

  return (
    <div className="catalog-page catalog-page--grid">
      <main className="catalog-main">
        <div className="category-view">
          <Breadcrumbs items={breadcrumbs} />
          <div className="section-heading">
            <div>
              <p className="eyebrow">Категория</p>
              <h1>{category.title}</h1>
            </div>
            <p className="section-heading__support">{category.description}</p>
          </div>

          {category.subcategories && category.subcategories.length > 0 && (
            <div className="subcategory-grid">
              {category.subcategories.map((sub, idx) => {
                const isSelected = selectedSubcategory === sub.name
                return (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => handleSubcategoryClick(sub.name)}
                    className={`subcategory-card ${isSelected ? 'subcategory-card--active' : ''}`}
                  >
                    <div
                      className="subcategory-card__image"
                      style={{ backgroundImage: `url(${category.image})` }}
                    />
                    <div className="subcategory-card__content">
                      <h3 className="subcategory-card__title">{sub.name}</h3>
                      {isSelected && (
                        <span className="subcategory-card__arrow">↓</span>
                      )}
                    </div>
                  </button>
                )
              })}
            </div>
          )}

          {selectedSubcategory && (
            <div className="subcategory-products">
              <div className="subcategory-products__header">
                <h2 className="subcategory-products__title">
                  Товары категории: {selectedSubcategory}
                </h2>
                <button
                  type="button"
                  className="link"
                  onClick={() => setSelectedSubcategory(null)}
                >
                  Показать все товары
                </button>
              </div>
            </div>
          )}

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

export default CategoryView

