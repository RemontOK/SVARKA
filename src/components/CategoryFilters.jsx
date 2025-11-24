import { useState } from 'react'

const CategoryFilters = ({ categoryId, filters, onFilterChange }) => {
  const [filterValues, setFilterValues] = useState({})

  const handleFilterChange = (filterId, value) => {
    const newValues = { ...filterValues, [filterId]: value }
    setFilterValues(newValues)
    onFilterChange(newValues)
  }

  const handleReset = () => {
    setFilterValues({})
    onFilterChange({})
  }

  if (!filters || filters.length === 0) {
    return null
  }

  const renderFilter = (filter) => {
    switch (filter.type) {
      case 'price':
        const priceMin = filter.min || 0
        const priceMax = filter.max || 3000000
        const currentMin = Number(filterValues[`${filter.id}_min`]) || priceMin
        const currentMax = Number(filterValues[`${filter.id}_max`]) || priceMax

        return (
          <div key={filter.id} className="filter-group filter-group--price">
            <label className="filter-group__label">{filter.label}</label>
            <div className="filter-group__price-slider">
              <div className="filter-group__price-inputs">
                <input
                  type="number"
                  className="filter-group__input filter-group__input--compact"
                  placeholder="От"
                  value={filterValues[`${filter.id}_min`] || ''}
                  onChange={(e) => {
                    const val = e.target.value === '' ? '' : Number(e.target.value)
                    if (val === '' || (val >= priceMin && val <= currentMax)) {
                      handleFilterChange(`${filter.id}_min`, val)
                    }
                  }}
                  min={priceMin}
                  max={priceMax}
                />
                <span className="filter-group__separator">—</span>
                <input
                  type="number"
                  className="filter-group__input filter-group__input--compact"
                  placeholder="До"
                  value={filterValues[`${filter.id}_max`] || ''}
                  onChange={(e) => {
                    const val = e.target.value === '' ? '' : Number(e.target.value)
                    if (val === '' || (val >= currentMin && val <= priceMax)) {
                      handleFilterChange(`${filter.id}_max`, val)
                    }
                  }}
                  min={priceMin}
                  max={priceMax}
                />
              </div>
              <div 
                className="filter-group__range-wrapper"
                style={{
                  '--range-min-percent': `${((currentMin - priceMin) / (priceMax - priceMin)) * 100}%`,
                  '--range-max-percent': `${((currentMax - priceMin) / (priceMax - priceMin)) * 100}%`,
                }}
              >
                <div className="filter-group__range-value filter-group__range-value--min">
                  {new Intl.NumberFormat('ru-RU').format(currentMin)}
                </div>
                <div className="filter-group__range-value filter-group__range-value--max">
                  {new Intl.NumberFormat('ru-RU').format(currentMax)}
                </div>
                <input
                  type="range"
                  className="filter-group__range filter-group__range--min"
                  min={priceMin}
                  max={priceMax}
                  value={currentMin}
                  onChange={(e) => {
                    const val = Number(e.target.value)
                    if (val <= currentMax) {
                      handleFilterChange(`${filter.id}_min`, val)
                    }
                  }}
                />
                <input
                  type="range"
                  className="filter-group__range filter-group__range--max"
                  min={priceMin}
                  max={priceMax}
                  value={currentMax}
                  onChange={(e) => {
                    const val = Number(e.target.value)
                    if (val >= currentMin) {
                      handleFilterChange(`${filter.id}_max`, val)
                    }
                  }}
                />
              </div>
            </div>
          </div>
        )

      case 'brand':
        return (
          <div key={filter.id} className="filter-group filter-group--inline">
            <label className="filter-group__label">{filter.label}</label>
            <select
              className="filter-group__select"
              value={filterValues[filter.id] || ''}
              onChange={(e) => handleFilterChange(filter.id, e.target.value)}
            >
              <option value="">Все бренды</option>
              {filter.options?.map((brand) => (
                <option key={brand} value={brand}>
                  {brand}
                </option>
              ))}
            </select>
          </div>
        )

      case 'checkbox':
        return (
          <div key={filter.id} className="filter-group filter-group--inline">
            <label className="filter-group__checkbox">
              <input
                type="checkbox"
                checked={filterValues[filter.id] || false}
                onChange={(e) => handleFilterChange(filter.id, e.target.checked)}
              />
              <span>{filter.label}</span>
            </label>
          </div>
        )

      case 'number':
        return (
          <div key={filter.id} className="filter-group filter-group--inline">
            <label className="filter-group__label">{filter.label}</label>
            <input
              type="number"
              className="filter-group__input filter-group__input--compact"
              placeholder={filter.placeholder || ''}
              value={filterValues[filter.id] || ''}
              onChange={(e) => handleFilterChange(filter.id, e.target.value)}
            />
          </div>
        )

      default:
        return null
    }
  }

  return (
    <div className="category-filters">
      <div className="category-filters__header">
        <h3 className="category-filters__title">Фильтры</h3>
        <button type="button" className="category-filters__reset" onClick={handleReset}>
          Сбросить
        </button>
      </div>
      <div className="category-filters__content">
        <div className="category-filters__row">
          {filters.map((filter) => renderFilter(filter))}
        </div>
      </div>
    </div>
  )
}

export default CategoryFilters
