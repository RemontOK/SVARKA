// Конфигурация фильтров для каждой категории
export const CATEGORY_FILTERS = {
  // Общие фильтры для большинства категорий
  default: [
    { id: 'price', type: 'price', label: 'Цена', min: 0, max: 3000000 },
    { id: 'brand', type: 'brand', label: 'Бренд', options: ['Fronius', 'Kemppi', 'Aurora', 'Svarog', 'Resanta', 'Fubag', 'Hypertherm', 'Svarline', 'Elitech'] },
  ],
  
  // Труборезы и фаскорезы
  'pipe-cutters': [
    { id: 'special-offers', type: 'checkbox', label: 'Наши предложения' },
    { id: 'brand', type: 'brand', label: 'Бренд', options: ['Fronius', 'Kemppi', 'Aurora', 'Svarog', 'Resanta', 'Fubag', 'Hypertherm'] },
    { id: 'pipe-diameter-min', type: 'number', label: 'Диаметр труб минимальный, мм', placeholder: 'мм' },
    { id: 'pipe-diameter-max', type: 'number', label: 'Диаметр труб максимальный, мм', placeholder: 'мм' },
    { id: 'cut-thickness', type: 'number', label: 'Толщина реза, мм', placeholder: 'мм' },
    { id: 'bevel-removal', type: 'checkbox', label: 'Снятие фаски' },
  ],
  
  // Перемещение станков и грузов
  'material-handling': [
    { id: 'price', type: 'price', label: 'Цена', min: 0, max: 3000000 },
    { id: 'brand', type: 'brand', label: 'Бренд', options: ['Отечественный', 'Fronius', 'Kemppi'] },
    { id: 'load-capacity', type: 'number', label: 'Грузоподъемность, т.', placeholder: 'т.' },
  ],
}

// Получить фильтры для категории
export const getFiltersForCategory = (categoryId) => {
  return CATEGORY_FILTERS[categoryId] || CATEGORY_FILTERS.default
}

