import { useMemo, useState } from 'react'
import SearchBar from '../components/SearchBar'
import CategoryFilters from '../components/CategoryFilters'
import ProductCard from '../components/ProductCard'
import { FEATURE_FILTERS, WELDER_CATEGORIES, WELDERS } from '../data/welders'

const Catalog = () => {
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedCategory, setSelectedCategory] = useState(WELDER_CATEGORIES[0].id)
  const [activeFilters, setActiveFilters] = useState([])

  const filteredProducts = useMemo(() => {
    return WELDERS.filter((product) => {
      const matchesCategory = selectedCategory ? product.category === selectedCategory : true
      const matchesSearch =
        searchTerm.length === 0 ||
        [product.name, product.brand, product.type, product.description]
          .join(' ')
          .toLowerCase()
          .includes(searchTerm.toLowerCase())
      const matchesFeature =
        activeFilters.length === 0 || activeFilters.every((flag) => product.featureFlags.includes(flag))

      return matchesCategory && matchesSearch && matchesFeature
    })
  }, [searchTerm, selectedCategory, activeFilters])

  const toggleFilter = (filterId) => {
    setActiveFilters((prev) => (prev.includes(filterId) ? prev.filter((id) => id !== filterId) : [...prev, filterId]))
  }

  return (
    <div className="catalog-page">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Каталог</p>
          <h1>Подберите сварочный аппарат под задачу</h1>
        </div>
        <p className="section-heading__support">Склад и сервис в 3 регионах, бесплатный тест-драйв</p>
      </div>

      <SearchBar
        label="Умный поиск"
        supporting="Введите марку, тип или требуемый ток"
        placeholder="Например, Fronius 200 Pulse"
        value={searchTerm}
        onChange={setSearchTerm}
        quickTags={['MIG 250', 'TIG AC/DC', 'Плазморез', 'Resanta']}
      />

      <CategoryFilters
        categories={WELDER_CATEGORIES}
        selectedCategory={selectedCategory}
        onSelectCategory={setSelectedCategory}
      />

      <div className="filter-chips">
        {FEATURE_FILTERS.map((filter) => {
          const isActive = activeFilters.includes(filter.id)
          return (
            <button
              key={filter.id}
              type="button"
              className={isActive ? 'chip chip--small chip--active' : 'chip chip--small'}
              onClick={() => toggleFilter(filter.id)}
            >
              {filter.label}
            </button>
          )
        })}
      </div>

      <p className="catalog-page__result">
        Найдено {filteredProducts.length} моделей · показать в наличии{' '}
        <span className="link">({filteredProducts.length})</span>
      </p>

      <div className="catalog-grid">
        {filteredProducts.map((product) => (
          <ProductCard key={product.id} product={product} />
        ))}
      </div>
    </div>
  )
}

export default Catalog

