import { useParams, Link } from 'react-router-dom'
import { useEffect } from 'react'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY, WELDERS } from '../data/welders'
import Breadcrumbs from '../components/Breadcrumbs'
import ProductCard from '../components/ProductCard'
import CategoryFilters from '../components/CategoryFilters'
import SortSelect from '../components/SortSelect'
import { getFiltersForCategory } from '../data/filters'
import '../styles/pages/CategoryView.css'

const SubcategoryView = () => {
  const { categoryId, subcategoryId } = useParams()

  useEffect(() => {
    document.title = `Каталог - ${categoryId} - ${subcategoryId} - АльфаСмарт`
  }, [categoryId, subcategoryId])

  // Находим категорию
  const allCategories = [...WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY]
  const category = allCategories.find((cat) => cat.id === categoryId)

  // Находим подкатегорию
  const subcategory = category?.subcategories?.find((sub) => {
    const subPath = sub.name.toLowerCase().replace(/\s+/g, '-')
    return subPath === subcategoryId
  })

  // Преобразуем subcategoryId обратно в читаемое название
  const subcategoryName = subcategory?.name || (subcategoryId
    ? subcategoryId
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ')
    : '')

  // Фильтруем товары по категории и подкатегории
  const filteredProducts = WELDERS.filter((product) => {
    if (!product.category) return false
    const productCategoryPath = product.category
    // Проверяем, что товар относится к нужной категории и подкатегории
    return productCategoryPath === `${categoryId}/${subcategoryId}` || 
           productCategoryPath.startsWith(`${categoryId}/${subcategoryId}/`)
  })

  // Получаем фильтры для категории
  const filters = getFiltersForCategory(categoryId)

  // Генерируем breadcrumbs
  const breadcrumbsItems = category ? [
    { label: 'Главная', to: '/' },
    { label: 'Каталог', to: '/catalog' },
    { label: category.title, to: `/catalog/${categoryId}` },
    ...(subcategory ? [{ label: subcategory.name, to: `/catalog/${categoryId}/${subcategoryId}` }] : []),
  ] : [
    { label: 'Главная', to: '/' },
    { label: 'Каталог', to: '/catalog' },
  ]

  if (!category) {
    return (
      <div className="catalog-page">
        <Breadcrumbs items={breadcrumbsItems} />
        <div className="section-heading">
          <h1>Категория не найдена</h1>
        </div>
        <Link to="/catalog" className="btn btn--primary">
          Вернуться в каталог
        </Link>
      </div>
    )
  }

  return (
    <div className="catalog-page catalog-page--grid">
      <aside className="catalog-sidebar">
        {filters && filters.length > 0 && (
          <CategoryFilters
            categoryId={categoryId}
            filters={filters}
            onFilterChange={() => {}}
          />
        )}
      </aside>

      <main className="catalog-main">
        <Breadcrumbs items={breadcrumbsItems} />
        
        <div className="section-heading">
          <div>
            <p className="eyebrow">
              <Link to={`/catalog/${categoryId}`}>{category.title}</Link>
            </p>
            <h1>{subcategoryName || 'Подкатегория'}</h1>
          </div>
          <p className="section-heading__support">
            {category.description}
          </p>
        </div>

        <div className="category-view__toolbar">
          <p className="category-view__result">
            Найдено товаров: {filteredProducts.length}
          </p>
          <SortSelect value="default" onChange={() => {}} />
        </div>

        {filteredProducts.length > 0 ? (
          <div className="category-products__grid">
            {filteredProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <div className="category-empty">
            <p>В этой подкатегории пока нет товаров.</p>
            <Link to={`/catalog/${categoryId}`} className="btn btn--primary">
              Вернуться к категории
            </Link>
          </div>
        )}
      </main>
    </div>
  )
}

export default SubcategoryView
