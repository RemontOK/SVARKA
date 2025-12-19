import { useState } from 'react'
import { useParams, Link, NavLink, useLocation } from 'react-router-dom'
import { WELDER_CATEGORIES, ACCESSORIES_CATEGORY, PPE_CATEGORY, WELDERS } from '../data/welders'
import LoadingSpinner from '../components/LoadingSpinner'
import Breadcrumbs from '../components/Breadcrumbs'
import ReviewForm from '../components/ReviewForm'
import '../styles/pages/ProductView.css'

const ProductView = () => {
  const { productId } = useParams()
  const location = useLocation()
  const [reviews, setReviews] = useState([
    {
      id: 1,
      name: 'Иван Петров',
      rating: 5,
      text: 'Отличный аппарат! Работаю уже полгода, никаких нареканий. Держит дугу стабильно, даже при просадках напряжения. Рекомендую для профессионального использования.',
      date: '15.03.2024',
    },
    {
      id: 2,
      name: 'Сергей Козлов',
      rating: 4,
      text: 'Хороший аппарат за свои деньги. Компактный, удобно переносить. Единственный минус - немного шумноват вентилятор, но это не критично.',
      date: '08.03.2024',
    },
    {
      id: 3,
      name: 'Алексей Смирнов',
      rating: 5,
      text: 'Покупал для небольшой мастерской. Работает отлично, качество сварки на высоте. Доставка была быстрой, менеджеры помогли с выбором. Всем доволен!',
      date: '22.02.2024',
    },
  ])

  // Находим товар по ID
  const product = WELDERS.find((p) => p.id === productId)

  const handleReviewSubmit = (newReview) => {
    const review = {
      id: Date.now(),
      ...newReview,
    }
    setReviews([review, ...reviews])
  }

  if (!product) {
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

  // Находим категорию товара
  const category =
    WELDER_CATEGORIES.find((cat) => cat.id === product.category) ||
    (product.category === ACCESSORIES_CATEGORY.id ? ACCESSORIES_CATEGORY : null) ||
    (product.category === PPE_CATEGORY.id ? PPE_CATEGORY : null)

  // Хлебные крошки
  const breadcrumbs = [
    { label: 'Главная', to: '/' },
    { label: 'Каталог', to: '/catalog' },
  ]
  if (category) {
    breadcrumbs.push({ label: category.title, to: `/catalog/${product.category}` })
    if (product.subcategory) {
      breadcrumbs.push({ label: product.subcategory, to: `#` })
    }
  }
  breadcrumbs.push({ label: product.name, to: '#' })

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
      <main className="catalog-main">
        <div className="product-view">
          <Breadcrumbs items={breadcrumbs} />

          <div className="product-view__top">
            <div className="product-view__image-section">
              {product.image && (
                <div
                  className="product-view__image"
                  style={{ backgroundImage: `url(${product.image})` }}
                />
              )}
            </div>

            <div className="product-view__info">
              <div className="product-view__rating-stars">
                {[...Array(5)].map((_, i) => (
                  <span
                    key={i}
                    className={`product-view__star ${
                      i < Math.floor(product.rating) ? 'product-view__star--filled' : ''
                    } ${i < product.rating && i >= Math.floor(product.rating) ? 'product-view__star--half' : ''}`}
                  >
                    ★
                  </span>
                ))}
              </div>

              <div className="product-view__header">
                <div className="product-view__meta">
                  <span className="product-view__type">{product.type}</span>
                  <span className="product-view__brand">{product.brand}</span>
                </div>
                <h1 className="product-view__title">{product.name}</h1>
                <p className="product-view__description">{product.description}</p>
                <Link to="#" className="product-view__more-link">
                  Подробнее
                </Link>
              </div>

              <div className="product-view__price-section">
                <p className="product-view__price">
                  {new Intl.NumberFormat('ru-RU', {
                    style: 'currency',
                    currency: 'RUB',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  }).format(product.price)}
                  <span className="product-view__price-unit">/шт</span>
                </p>
              </div>

              <div className="product-view__availability">
                <span className="product-view__availability-icon">✓</span>
                <span className="product-view__availability-text">{product.availability}</span>
                <Link to="#" className="product-view__leasing-link">
                  <span>📅</span> Купить в лизинг
                </Link>
              </div>

              <div className="product-view__actions">
                <div className="product-view__quantity">
                  <button type="button" className="product-view__quantity-btn">−</button>
                  <input type="number" className="product-view__quantity-input" defaultValue="1" min="1" />
                  <button type="button" className="product-view__quantity-btn">+</button>
                </div>
                <button className="btn btn--primary product-view__button-cart" type="button">
                  В корзину
                </button>
                <button className="btn btn--outline product-view__button-quick" type="button">
                  Купить в 1 клик
                </button>
              </div>
            </div>
          </div>

          <div className="product-view__details">
            <div className="product-view__full-description">
              <h2 className="product-view__section-title">Описание</h2>
              <p>
                {product.description} Портативный инверторный сварочный аппарат производства фирмы {product.brand}, 
                предназначен для ручной дуговой сварки {product.type}. Сварочный инвертор {product.name} имеет 
                высокую мощность и надёжность, прост в эксплуатации, благодаря малому весу идеально подходит 
                для эксплуатации на строительных площадках, при проведении ремонтных работ.
              </p>
            </div>

            <div className="product-view__advantages">
              <h2 className="product-view__section-title">
                Преимущества сварочного аппарата {product.name}
              </h2>
              <ul className="product-view__advantages-list">
                <li>Компактный, лёгкий, портативный</li>
                <li>Инверторный источник питания</li>
                <li>Плавная регулировка сварочного тока</li>
                <li>Функция Hot-start: увеличенный ток при поджиге дуги</li>
                <li>Функция Arc-force: принудительная сварка дугой</li>
                <li>Функция Anti-stick предотвращает прилипание электрода при поджиге дуги</li>
                <li>Возможность сварки TIG/WIG (Lift-Arc контактное зажигание дуги)</li>
                <li>Низкошумный вентилятор с активацией датчика температуры</li>
                <li>Защита от тепловой перегрузки</li>
                <li>Стабильная работа при отклонениях входного напряжения ±15%</li>
                <li>Электронные платы и разъёмы в пылезащищённом отсеке</li>
                <li>Удобное обслуживание и ремонтопригодность</li>
              </ul>
            </div>

            <div className="product-view__tech-specs">
              <h2 className="product-view__section-title">
                Технические характеристики {product.name} {product.brand}
              </h2>
              <table className="product-view__specs-table">
                <tbody>
                  <tr>
                    <td>Модель</td>
                    <td>{product.name}</td>
                  </tr>
                  <tr>
                    <td>Напряжение питания</td>
                    <td>{product.inputVoltage}</td>
                  </tr>
                  <tr>
                    <td>Ток сварки</td>
                    <td>{product.dutyCycle}</td>
                  </tr>
                  <tr>
                    <td>Тип сварки</td>
                    <td>{product.type}</td>
                  </tr>
                  <tr>
                    <td>Бренд</td>
                    <td>{product.brand}</td>
                  </tr>
                  <tr>
                    <td>Рейтинг</td>
                    <td>
                      <span className="product-view__rating">
                        <span className="product-view__rating-star">★</span>
                        {product.rating.toFixed(1)}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div className="product-view__reviews">
              <h2 className="product-view__section-title">Отзывы ({reviews.length})</h2>
              
              <ReviewForm onSubmit={handleReviewSubmit} />

              <div className="product-view__reviews-list">
                {reviews.map((review) => (
                  <div key={review.id} className="product-view__review">
                    <div className="product-view__review-header">
                      <div className="product-view__review-author">
                        <span className="product-view__review-avatar">
                          {review.name.charAt(0).toUpperCase()}
                        </span>
                        <div>
                          <p className="product-view__review-name">{review.name}</p>
                          <div className="product-view__review-rating">
                            {[...Array(5)].map((_, i) => (
                              <span
                                key={i}
                                className={i < review.rating ? 'product-view__star--filled' : ''}
                              >
                                ★
                              </span>
                            ))}
                          </div>
                        </div>
                      </div>
                      <span className="product-view__review-date">{review.date}</span>
                    </div>
                    <p className="product-view__review-text">{review.text}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}

export default ProductView

