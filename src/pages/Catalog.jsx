import { useState, useEffect, useMemo } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import SearchBar from '../components/SearchBar'
import ProductCard from '../components/ProductCard'
import ProductFilters from '../components/ProductFilters'
import SortSelect from '../components/SortSelect'
import Pagination from '../components/Pagination'
import Breadcrumbs from '../components/Breadcrumbs'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY, WELDERS } from '../data/welders'
import '../styles/pages/Catalog.css'

const Catalog = () => {
  const location = useLocation()
  const [expandedCategories, setExpandedCategories] = useState({})
  const [selectedCategory, setSelectedCategory] = useState(null)
  const [selectedSubcategory, setSelectedSubcategory] = useState(null)
  const [searchValue, setSearchValue] = useState('')
  const [filters, setFilters] = useState({})
  const [sortBy, setSortBy] = useState('default')
  const [currentPage, setCurrentPage] = useState(1)
  const itemsPerPage = 12

  // Автоматически раскрываем категорию при выборе подкатегории
  useEffect(() => {
    if (selectedSubcategory && selectedCategory) {
      setExpandedCategories((prevExpanded) => {
        if (!prevExpanded[selectedCategory]) {
          return { ...prevExpanded, [selectedCategory]: true }
        }
        return prevExpanded
      })
    }
  }, [selectedCategory, selectedSubcategory])

  const toggleCategory = (categoryId) => {
    setExpandedCategories((prev) => ({
      ...prev,
      [categoryId]: !prev[categoryId],
    }))
  }

  // Сортировка и фильтрация товаров
  const sortedAndFilteredProducts = useMemo(() => {
    let products = [...WELDERS]

    // Фильтрация по выбранной категории/подкатегории
    if (selectedSubcategory) {
      // Фильтруем по подкатегории
      products = products.filter((product) => {
        if (!product.category) return false
        // Преобразуем selectedSubcategory в читаемое название для сравнения
        const allCategories = [...WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY]
        const category = allCategories.find((cat) => cat.id === selectedCategory)
        const subcategory = category?.subcategories?.find((sub) => {
          const subId = sub.name.toLowerCase().replace(/\s+/g, '-')
          return subId === selectedSubcategory
        })
        
        if (!subcategory) return false
        
        // Сравниваем по category и subcategory
        return product.category === selectedCategory && 
               product.subcategory === subcategory.name
      })
    } else if (selectedCategory) {
      // Фильтруем по категории
      products = products.filter((product) => {
        if (!product.category) return false
        return product.category === selectedCategory
      })
    }

    // Фильтрация по поисковому запросу
    if (searchValue.trim()) {
      const searchLower = searchValue.toLowerCase()
      products = products.filter((product) => {
        return (
          product.name.toLowerCase().includes(searchLower) ||
          product.brand.toLowerCase().includes(searchLower) ||
          product.type.toLowerCase().includes(searchLower) ||
          product.description.toLowerCase().includes(searchLower) ||
          product.tags.some((tag) => tag.toLowerCase().includes(searchLower))
        )
      })
    }

    // Фильтрация по фильтрам
    if (filters.type && filters.type.length > 0) {
      products = products.filter((product) => filters.type.includes(product.type))
    }

    if (filters.brand && filters.brand.length > 0) {
      products = products.filter((product) => filters.brand.includes(product.brand))
    }

    if (filters.voltage && filters.voltage.length > 0) {
      products = products.filter((product) => {
        return filters.voltage.some((voltage) => {
          if (voltage === '220/380V') {
            return product.inputVoltage.includes('220/380V') || product.inputVoltage.includes('220/380')
          }
          return product.inputVoltage.includes(voltage)
        })
      })
    }

    if (filters.current && filters.current.length > 0) {
      products = products.filter((product) => {
        const dutyCycleMatch = product.dutyCycle.match(/(\d+)A/)
        if (!dutyCycleMatch) return false
        const current = parseInt(dutyCycleMatch[1], 10)
        
        return filters.current.some((filterCurrent) => {
          if (filterCurrent === 'low') return current < 200
          if (filterCurrent === 'medium') return current >= 200 && current <= 300
          if (filterCurrent === 'high') return current > 300
          return false
        })
      })
    }

    if (filters.price && filters.price.length > 0) {
      products = products.filter((product) => {
        return filters.price.some((priceFilter) => {
          if (priceFilter === 'budget') return product.price < 50000
          if (priceFilter === 'mid') return product.price >= 50000 && product.price < 150000
          if (priceFilter === 'premium') return product.price >= 150000 && product.price < 300000
          if (priceFilter === 'pro') return product.price >= 300000
          return false
        })
      })
    }

    if (filters.availability && filters.availability.length > 0) {
      products = products.filter((product) => {
        const availabilityLower = product.availability.toLowerCase()
        return filters.availability.some((availFilter) => {
          if (availFilter === 'in-stock') {
            return availabilityLower.includes('в наличии') || availabilityLower.includes('есть самовывоз') || availabilityLower.includes('на складе')
          }
          if (availFilter === 'order') {
            return availabilityLower.includes('под заказ') || availabilityLower.includes('доставка')
          }
          if (availFilter === 'preorder') {
            return availabilityLower.includes('предзаказ') || availabilityLower.includes('в пути')
          }
          return false
        })
      })
    }

    // Сортировка
    switch (sortBy) {
      case 'popularity':
        // По популярности (по рейтингу, затем по цене как вторичный критерий)
        products.sort((a, b) => {
          const ratingDiff = (b.rating || 0) - (a.rating || 0)
          if (ratingDiff !== 0) return ratingDiff
          // Если рейтинг одинаковый, сортируем по цене (более дорогие считаются популярнее)
          return (b.price || 0) - (a.price || 0)
        })
        break
      case 'name-asc':
        products.sort((a, b) => a.name.localeCompare(b.name, 'ru'))
        break
      case 'name-desc':
        products.sort((a, b) => b.name.localeCompare(a.name, 'ru'))
        break
      case 'rating-desc':
        products.sort((a, b) => (b.rating || 0) - (a.rating || 0))
        break
      case 'rating-asc':
        products.sort((a, b) => (a.rating || 0) - (b.rating || 0))
        break
      case 'price-asc':
        products.sort((a, b) => (a.price || 0) - (b.price || 0))
        break
      case 'price-desc':
        products.sort((a, b) => (b.price || 0) - (a.price || 0))
        break
      default:
        // По умолчанию - без сортировки или по ID
        break
    }

    return products
  }, [WELDERS, searchValue, sortBy, filters, selectedCategory, selectedSubcategory])

  // Пагинация
  const totalPages = Math.ceil(sortedAndFilteredProducts.length / itemsPerPage)
  const paginatedProducts = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage
    const endIndex = startIndex + itemsPerPage
    return sortedAndFilteredProducts.slice(startIndex, endIndex)
  }, [sortedAndFilteredProducts, currentPage, itemsPerPage])

  // Сброс страницы при изменении поиска, сортировки или фильтров
  useEffect(() => {
    setCurrentPage(1)
  }, [searchValue, sortBy, filters, selectedCategory, selectedSubcategory])

  // Получаем информацию о выбранной категории и подкатегории
  const selectedCategoryInfo = useMemo(() => {
    if (!selectedCategory) return null
    const allCategories = [...WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY]
    const category = allCategories.find((cat) => cat.id === selectedCategory)
    if (!category) return null
    
    if (selectedSubcategory) {
      const subcategory = category.subcategories?.find((sub) => {
        const subId = sub.name.toLowerCase().replace(/\s+/g, '-')
        return subId === selectedSubcategory
      })
      return { category, subcategory }
    }
    
    return { category, subcategory: null }
  }, [selectedCategory, selectedSubcategory])

  // Генерируем breadcrumbs
  const breadcrumbsItems = useMemo(() => {
    const items = [
      { label: 'Главная', to: '/' },
      { label: 'Каталог', to: '/catalog' },
    ]
    
    if (selectedCategoryInfo?.category) {
      items.push({
        label: selectedCategoryInfo.category.title,
        to: `/catalog/${selectedCategoryInfo.category.id}`,
      })
    }
    
    if (selectedCategoryInfo?.subcategory) {
      items.push({
        label: selectedCategoryInfo.subcategory.name,
        to: `/catalog/${selectedCategory}/${selectedSubcategory}`,
      })
    }
    
    return items
  }, [selectedCategoryInfo, selectedCategory, selectedSubcategory])

  return (
    <div className="catalog-page catalog-page--grid">
      <aside className="catalog-sidebar">
        <nav className="catalog-nav">
          {WELDER_CATEGORIES.map((cat) => {
            const isExpanded = expandedCategories[cat.id]
            const hasSubcategories = cat.subcategories && cat.subcategories.length > 0
            const isActive = selectedCategory === cat.id || location.pathname.startsWith(`/catalog/${cat.id}`)

            return (
              <div key={cat.id} className="catalog-nav__category-wrapper">
                {hasSubcategories ? (
                  <>
                    <div
                      className={`catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''} catalog-nav__item--expandable`}
                      onClick={() => toggleCategory(cat.id)}
                    >
                      <div className="catalog-nav__item-link">
                        <span className="catalog-nav__icon">{cat.icon || '⚡'}</span>
                        <span className="catalog-nav__text">{cat.title}</span>
                        <span className={`catalog-nav__expand-icon ${isExpanded ? 'catalog-nav__expand-icon--expanded' : ''}`}>
                          ▼
                        </span>
                      </div>
                    </div>
                  </>
                ) : (
                  <NavLink
                    to={`/catalog/${cat.id}`}
                    className={({ isActive }) =>
                      `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
                    }
                  >
                    <span className="catalog-nav__icon">{cat.icon || '⚡'}</span>
                    <span className="catalog-nav__text">{cat.title}</span>
                    {isActive && (
                      <span className="catalog-nav__arrow">→</span>
                    )}
                  </NavLink>
                )}
                {hasSubcategories && isExpanded && (
                  <ul className="catalog-nav__subcategories">
                    <li>
                      <button
                        onClick={() => {
                          setSelectedCategory(cat.id)
                          setSelectedSubcategory(null)
                        }}
                        className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && !selectedSubcategory ? 'catalog-nav__subcategory-link--active' : ''}`}
                      >
                        Все товары категории
                      </button>
                    </li>
                    {cat.subcategories.map((sub, idx) => {
                      const subcategoryId = sub.name.toLowerCase().replace(/\s+/g, '-')
                      return (
                        <li key={idx}>
                          <button
                            onClick={() => {
                              setSelectedCategory(cat.id)
                              setSelectedSubcategory(subcategoryId)
                            }}
                            className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && selectedSubcategory === subcategoryId ? 'catalog-nav__subcategory-link--active' : ''}`}
                          >
                            {sub.name}
                            {sub.count && <span className="catalog-nav__count">({sub.count})</span>}
                          </button>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </div>
            )
          })}
          {(() => {
            const cat = ACCESSORIES_CATEGORY
            const isExpanded = expandedCategories[cat.id]
            const hasSubcategories = cat.subcategories && cat.subcategories.length > 0
            const isActive = selectedCategory === cat.id || location.pathname.startsWith(`/catalog/${cat.id}`)

            return (
              <div key={cat.id} className="catalog-nav__category-wrapper">
                {hasSubcategories ? (
                  <>
                    <div
                      className={`catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''} catalog-nav__item--expandable`}
                      onClick={() => toggleCategory(cat.id)}
                    >
                      <div className="catalog-nav__item-link">
                        <span className="catalog-nav__icon">{cat.icon || '⚫'}</span>
                        <span className="catalog-nav__text">{cat.title}</span>
                        <span className={`catalog-nav__expand-icon ${isExpanded ? 'catalog-nav__expand-icon--expanded' : ''}`}>
                          ▼
                        </span>
                      </div>
                    </div>
                    {isExpanded && (
                      <ul className="catalog-nav__subcategories">
                        <li>
                          <button
                            onClick={() => {
                              setSelectedCategory(cat.id)
                              setSelectedSubcategory(null)
                            }}
                            className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && !selectedSubcategory ? 'catalog-nav__subcategory-link--active' : ''}`}
                          >
                            Все товары категории
                          </button>
                        </li>
                        {cat.subcategories.map((sub, idx) => {
                          const subcategoryId = sub.name.toLowerCase().replace(/\s+/g, '-')
                          return (
                            <li key={idx}>
                              <button
                                onClick={() => {
                                  setSelectedCategory(cat.id)
                                  setSelectedSubcategory(subcategoryId)
                                }}
                                className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && selectedSubcategory === subcategoryId ? 'catalog-nav__subcategory-link--active' : ''}`}
                              >
                                {sub.name}
                                {sub.count && <span className="catalog-nav__count">({sub.count})</span>}
                              </button>
                            </li>
                          )
                        })}
                      </ul>
                    )}
                  </>
                ) : (
                  <NavLink
                    to={`/catalog/${cat.id}`}
                    className={({ isActive }) =>
                      `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
                    }
                  >
                    <span className="catalog-nav__icon">{cat.icon || '⚫'}</span>
                    <span className="catalog-nav__text">{cat.title}</span>
                    {isActive && (
                      <span className="catalog-nav__arrow">→</span>
                    )}
                  </NavLink>
                )}
              </div>
            )
          })()}
          {(() => {
            const cat = PPE_CATEGORY
            const isExpanded = expandedCategories[cat.id]
            const hasSubcategories = cat.subcategories && cat.subcategories.length > 0
            const isActive = selectedCategory === cat.id || location.pathname.startsWith(`/catalog/${cat.id}`)

            return (
              <div key={cat.id} className="catalog-nav__category-wrapper">
                {hasSubcategories ? (
                  <>
                    <div
                      className={`catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''} catalog-nav__item--expandable`}
                      onClick={() => toggleCategory(cat.id)}
                    >
                      <div className="catalog-nav__item-link">
                        <span className="catalog-nav__icon">{cat.icon || '🛡️'}</span>
                        <span className="catalog-nav__text">{cat.title}</span>
                        <span className={`catalog-nav__expand-icon ${isExpanded ? 'catalog-nav__expand-icon--expanded' : ''}`}>
                          ▼
                        </span>
                      </div>
                    </div>
                    {isExpanded && (
                      <ul className="catalog-nav__subcategories">
                        <li>
                          <button
                            onClick={() => {
                              setSelectedCategory(cat.id)
                              setSelectedSubcategory(null)
                            }}
                            className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && !selectedSubcategory ? 'catalog-nav__subcategory-link--active' : ''}`}
                          >
                            Все товары категории
                          </button>
                        </li>
                        {cat.subcategories.map((sub, idx) => {
                          const subcategoryId = sub.name.toLowerCase().replace(/\s+/g, '-')
                          return (
                            <li key={idx}>
                              <button
                                onClick={() => {
                                  setSelectedCategory(cat.id)
                                  setSelectedSubcategory(subcategoryId)
                                }}
                                className={`catalog-nav__subcategory-link ${selectedCategory === cat.id && selectedSubcategory === subcategoryId ? 'catalog-nav__subcategory-link--active' : ''}`}
                              >
                                {sub.name}
                                {sub.count && <span className="catalog-nav__count">({sub.count})</span>}
                              </button>
                            </li>
                          )
                        })}
                      </ul>
                    )}
                  </>
                ) : (
                  <NavLink
                    to={`/catalog/${cat.id}`}
                    className={({ isActive }) =>
                      `catalog-nav__item ${isActive ? 'catalog-nav__item--active' : ''}`
                    }
                  >
                    <span className="catalog-nav__icon">{cat.icon || '🛡️'}</span>
                    <span className="catalog-nav__text">{cat.title}</span>
                    {isActive && (
                      <span className="catalog-nav__arrow">→</span>
                    )}
                  </NavLink>
                )}
              </div>
            )
          })()}
        </nav>
      </aside>
      <main className="catalog-main">
        <Breadcrumbs items={breadcrumbsItems} />
        
        <SearchBar
          label="Быстрый поиск"
          supporting="Ведите марку, ток, тип или конкретную задачу"
          placeholder="Например, TIG 200 AC/DC"
          value={searchValue}
          onChange={setSearchValue}
          filters={<ProductFilters filters={filters} onFilterChange={setFilters} />}
        />
        
        <section className="catalog-popular">
          <div className="section-heading">
            <div>
              <p className="eyebrow">
                {selectedCategoryInfo?.subcategory
                  ? selectedCategoryInfo.subcategory.name
                  : selectedCategoryInfo?.category
                  ? selectedCategoryInfo.category.title
                  : 'Каталог товаров'}
              </p>
              <h2>
                {searchValue.trim()
                  ? `Найдено товаров: ${sortedAndFilteredProducts.length}`
                  : selectedCategoryInfo?.subcategory
                  ? `${selectedCategoryInfo.subcategory.name} (${sortedAndFilteredProducts.length})`
                  : selectedCategoryInfo?.category
                  ? `${selectedCategoryInfo.category.title} (${sortedAndFilteredProducts.length})`
                  : `Все товары (${sortedAndFilteredProducts.length})`}
              </h2>
            </div>
            <SortSelect value={sortBy} onChange={setSortBy} />
          </div>
          
          {paginatedProducts.length > 0 ? (
            <>
              <div className="catalog-popular__grid">
                {paginatedProducts.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
              
              {totalPages > 1 && (
                <Pagination
                  currentPage={currentPage}
                  totalPages={totalPages}
                  onPageChange={setCurrentPage}
                />
              )}
            </>
          ) : (
            <div className="catalog-popular__empty">
              <p>Товары не найдены. Попробуйте изменить параметры поиска.</p>
            </div>
          )}
        </section>
      </main>
    </div>
  )
}

export default Catalog

