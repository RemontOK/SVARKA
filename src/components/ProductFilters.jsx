import { useState, useRef, useEffect } from 'react'
import { createPortal } from 'react-dom'
import '../styles/components/ProductFilters.css'

const ProductFilters = ({ filters, onFilterChange }) => {
  const [expandedGroups, setExpandedGroups] = useState({})
  const buttonRefs = useRef({})
  const [menuPositions, setMenuPositions] = useState({})
  const filtersRef = useRef(null)
  const filterGroups = [
    {
      id: 'type',
      label: 'Тип сварки',
      options: [
        { value: 'MMA', label: 'MMA (Ручная дуговая)' },
        { value: 'TIG DC', label: 'TIG DC (Аргонодуговая DC)' },
        { value: 'TIG AC/DC', label: 'TIG AC/DC (Аргонодуговая AC/DC)' },
        { value: 'MIG/MAG', label: 'MIG/MAG (Полуавтомат)' },
        { value: 'Плазморез', label: 'Плазменная резка' },
      ],
    },
    {
      id: 'brand',
      label: 'Бренд',
      options: [
        { value: 'Resanta', label: 'Resanta' },
        { value: 'Kemppi', label: 'Kemppi' },
        { value: 'Fubag', label: 'Fubag' },
        { value: 'Svarog', label: 'Svarog' },
        { value: 'Aurora', label: 'Aurora' },
        { value: 'Fronius', label: 'Fronius' },
        { value: 'Svarline', label: 'Svarline' },
        { value: 'Hypertherm', label: 'Hypertherm' },
        { value: 'Elitech', label: 'Elitech' },
      ],
    },
    {
      id: 'voltage',
      label: 'Напряжение',
      options: [
        { value: '220V', label: '220V (Однофазное)' },
        { value: '380V', label: '380V (Трехфазное)' },
        { value: '220/380V', label: '220/380V (Универсальное)' },
      ],
    },
    {
      id: 'current',
      label: 'Ток',
      options: [
        { value: 'low', label: 'До 200A' },
        { value: 'medium', label: '200-300A' },
        { value: 'high', label: '300A и выше' },
      ],
    },
    {
      id: 'price',
      label: 'Цена',
      options: [
        { value: 'budget', label: 'До 50 000 ₽' },
        { value: 'mid', label: '50 000 - 150 000 ₽' },
        { value: 'premium', label: '150 000 - 300 000 ₽' },
        { value: 'pro', label: '300 000 ₽ и выше' },
      ],
    },
    {
      id: 'availability',
      label: 'Наличие',
      options: [
        { value: 'in-stock', label: 'В наличии' },
        { value: 'order', label: 'Под заказ' },
        { value: 'preorder', label: 'Предзаказ' },
      ],
    },
  ]

  const handleFilterToggle = (groupId, value) => {
    const currentFilters = filters[groupId] || []
    const newFilters = currentFilters.includes(value)
      ? currentFilters.filter((f) => f !== value)
      : [...currentFilters, value]
    
    onFilterChange({
      ...filters,
      [groupId]: newFilters.length > 0 ? newFilters : undefined,
    })
  }

  const clearAllFilters = () => {
    onFilterChange({})
  }

  const hasActiveFilters = Object.values(filters).some((f) => f && f.length > 0)

  const toggleGroup = (groupId) => {
    setExpandedGroups((prev) => {
      const isCurrentlyExpanded = prev[groupId]
      // Если открываем эту группу, закрываем все остальные
      if (!isCurrentlyExpanded) {
        // Обновляем позицию меню
        if (buttonRefs.current[groupId]) {
          const rect = buttonRefs.current[groupId].getBoundingClientRect()
          setMenuPositions((pos) => ({
            ...pos,
            [groupId]: {
              top: rect.bottom + 4,
              left: rect.left,
            },
          }))
        }
        return {
          [groupId]: true,
        }
      }
      // Если закрываем, просто убираем из состояния
      const newState = { ...prev }
      delete newState[groupId]
      setMenuPositions((pos) => {
        const newPos = { ...pos }
        delete newPos[groupId]
        return newPos
      })
      return newState
    })
  }

  // Обновляем позиции при скролле и ресайзе
  useEffect(() => {
    const updatePositions = () => {
      Object.keys(expandedGroups).forEach((groupId) => {
        if (buttonRefs.current[groupId]) {
          const rect = buttonRefs.current[groupId].getBoundingClientRect()
          setMenuPositions((pos) => ({
            ...pos,
            [groupId]: {
              top: rect.bottom + 4,
              left: rect.left,
            },
          }))
        }
      })
    }

    updatePositions()
    window.addEventListener('scroll', updatePositions, true)
    window.addEventListener('resize', updatePositions)

    return () => {
      window.removeEventListener('scroll', updatePositions, true)
      window.removeEventListener('resize', updatePositions)
    }
  }, [expandedGroups])

  // Закрываем все меню при клике вне области фильтров
  useEffect(() => {
    const handleClickOutside = (event) => {
      // Проверяем, был ли клик вне области фильтров
      if (filtersRef.current && !filtersRef.current.contains(event.target)) {
        // Проверяем, не был ли клик на само меню (которое рендерится через портал)
        const clickedElement = event.target
        const isMenuClick = clickedElement.closest('.product-filters__options--portal')
        
        if (!isMenuClick && Object.keys(expandedGroups).length > 0) {
          setExpandedGroups({})
          setMenuPositions({})
        }
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [expandedGroups])

  return (
    <div className="product-filters" ref={filtersRef}>
      <div className="product-filters__header">
        <h3 className="product-filters__title">Фильтры</h3>
      </div>
      
      <div className="product-filters__content">
        <div className="product-filters__groups">
        {filterGroups.map((group) => {
          const isExpanded = expandedGroups[group.id]
          const hasActiveFiltersInGroup = filters[group.id] && filters[group.id].length > 0
          
          return (
            <div 
              key={group.id} 
              className={`product-filters__group ${isExpanded ? 'product-filters__group--expanded' : ''}`}
            >
              <button
                ref={(el) => (buttonRefs.current[group.id] = el)}
                type="button"
                className={`product-filters__group-header ${
                  hasActiveFiltersInGroup ? 'product-filters__group-header--active' : ''
                }`}
                onClick={() => toggleGroup(group.id)}
              >
                <span className="product-filters__group-label">{group.label}</span>
                {hasActiveFiltersInGroup && (
                  <span className="product-filters__group-count">
                    ({filters[group.id].length})
                  </span>
                )}
                <span className={`product-filters__group-icon ${
                  isExpanded ? 'product-filters__group-icon--expanded' : ''
                }`}>
                  ▼
                </span>
              </button>
              {isExpanded && menuPositions[group.id] && createPortal(
                <div 
                  className="product-filters__options product-filters__options--portal"
                  style={{
                    top: `${menuPositions[group.id].top}px`,
                    left: `${menuPositions[group.id].left}px`,
                  }}
                >
                  {group.options.map((option) => {
                    const isActive = filters[group.id]?.includes(option.value)
                    return (
                      <button
                        key={option.value}
                        type="button"
                        className={`product-filters__option ${
                          isActive ? 'product-filters__option--active' : ''
                        }`}
                        onClick={() => handleFilterToggle(group.id, option.value)}
                      >
                        {option.label}
                      </button>
                      
                    )
                  })}
                </div>,
                document.body
              )}
            </div>
          )
        })}
        </div>
        
        {hasActiveFilters && (
          <button
            type="button"
            className="product-filters__reset-btn"
            onClick={clearAllFilters}
          >
            <span>✕</span>
            Сбросить все фильтры
          </button>
        )}
      </div>
    </div>
  )
}

export default ProductFilters

