import { useParams, Link } from 'react-router-dom'
import { useEffect } from 'react'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY, WELDERS } from '../data/welders'
import Breadcrumbs from '../components/Breadcrumbs'
import ProductCard from '../components/ProductCard'
import '../styles/pages/CategoryView.css'

const CategoryView = () => {
  const { categoryId } = useParams()

  useEffect(() => {
    document.title = `Каталог - ${categoryId} - АльфаСмарт`
  }, [categoryId])

  // Находим категорию
  const allCategories = [...WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY]
  const category = allCategories.find((cat) => cat.id === categoryId)

  // Фильтруем товары по категории
  const categoryProducts = WELDERS.filter((product) => {
    if (!product.category) return false
    return product.category === categoryId || product.category.startsWith(`${categoryId}/`)
  })

  // Генерируем breadcrumbs
  const breadcrumbsItems = category ? [
    { label: 'Главная', to: '/' },
    { label: 'Каталог', to: '/catalog' },
    { label: category.title, to: `/catalog/${categoryId}` },
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
        <nav className="catalog-nav">
          <h3 className="catalog-nav__title">Подкатегории</h3>
          {category.subcategories?.map((subcat, index) => {
            const subcategoryId = `${categoryId}/${subcat.name.toLowerCase().replace(/\s+/g, '-')}`
            return (
              <Link
                key={index}
                to={`/catalog/${subcategoryId}`}
                className="catalog-nav__item"
              >
                <span className="catalog-nav__text">{subcat.name}</span>
                <span className="catalog-nav__count">({subcat.count})</span>
              </Link>
            )
          })}
        </nav>
      </aside>

      <main className="catalog-main">
        <Breadcrumbs items={breadcrumbsItems} />
        
        <div className="section-heading">
          <div>
            <p className="eyebrow">{category.title}</p>
            <h1>{category.title}</h1>
          </div>
          <p className="section-heading__support">
            {category.description}
          </p>
        </div>

        {category.subcategories && category.subcategories.length > 0 && (
          <div className="category-subcategories">
            <h2 className="category-subcategories__title">Подкатегории</h2>
            <div className="category-subcategories__grid">
              {category.subcategories.map((subcat, index) => {
                const subcategoryId = `${categoryId}/${subcat.name.toLowerCase().replace(/\s+/g, '-')}`
                return (
                  <Link
                    key={index}
                    to={`/catalog/${subcategoryId}`}
                    className="category-subcategory-card"
                  >
                    <h3 className="category-subcategory-card__title">{subcat.name}</h3>
                    <p className="category-subcategory-card__count">
                      {subcat.count} {subcat.count === 1 ? 'товар' : subcat.count < 5 ? 'товара' : 'товаров'}
                    </p>
                  </Link>
                )
              })}
            </div>
          </div>
        )}

        {categoryProducts.length > 0 && (
          <div className="category-products">
            <h2 className="category-products__title">Все товары категории</h2>
            <div className="category-products__grid">
              {categoryProducts.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </div>
        )}

        {categoryProducts.length === 0 && (
          <div className="category-empty">
            <p>В этой категории пока нет товаров.</p>
          </div>
        )}
      </main>
    </div>
  )
}

export default CategoryView
