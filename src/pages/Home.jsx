import { Link } from 'react-router-dom'
import LeadForm from '../components/LeadForm'
import ProductCard from '../components/ProductCard'
import { FIELD_SHOTS } from '../data/gallery'
import { WELDER_CATEGORIES, WELDERS } from '../data/welders'
import '../styles/pages/Home.css'

const heroHighlights = [
  { value: '1200+', label: 'аппаратов на складе' },
  { value: '48 часов', label: 'доставка по РФ' },
  { value: '3 года', label: 'гарантия на pro-линейку' },
]

const trustBadges = [
  { title: 'ГОСТ / ISO', subtitle: 'Паспорта, НАКС, комплектация под аттестацию' },
  { title: 'Технолог 24/7', subtitle: 'Поддержка смен и выезд на производство' },
  { title: 'Финансирование', subtitle: 'Лизинг, отсрочка, консигнация расходников' },
]

const proofMetrics = [
  { value: '96%', label: 'проектов запускаем в срок' },
  { value: '18 регионов', label: 'логистика с нашими инженерами' },
  { value: '320+ брендов', label: 'оригинальные аппараты и расходники' },
]

// Утилита для получения правильных путей к изображениям
const getImagePath = (path) => {
  const baseUrl = import.meta.env.BASE_URL || '/'
  const cleanPath = path.startsWith('/') ? path.slice(1) : path
  return `${baseUrl}${cleanPath}`
}

const heroMedia = {
  video: 'https://storage.googleapis.com/coverr-main/mp4/Welding.mp4',
  poster: getImagePath('/welding-1.jpg'),
}

const Home = () => {
  const featuredProducts = WELDERS.slice(0, 3)
  const heroProducts = WELDERS.slice(0, 6)
  const heroCategories = WELDER_CATEGORIES.slice(0, 3)

  return (
    <div className="home-page">
      {/* Баннер с акцией */}
      <section className="hero-banner">
        <div className="hero-banner__content">
          <div className="hero-banner__text">
            <p className="eyebrow">Специальное предложение</p>
            <h1>Профессиональное сварочное оборудование</h1>
            <p className="hero-banner__description">
              Более 1200 аппаратов на складе. Доставка по России за 48 часов. Гарантия до 3 лет на профессиональную линейку.
            </p>
            <div className="hero-banner__actions">
              <Link className="btn btn--primary btn--large" to="/catalog">
                Смотреть каталог
              </Link>
              <Link className="btn btn--outline btn--large" to="/services">
                Консультация технолога
              </Link>
            </div>
            <div className="hero-banner__stats">
              {heroHighlights.map((item) => (
                <div key={item.label} className="hero-banner__stat">
                  <p>{item.value}</p>
                  <span>{item.label}</span>
                </div>
              ))}
            </div>
          </div>
          <div className="hero-banner__visual">
            <video
              className="hero-banner__video"
              playsInline
              autoPlay
              loop
              muted
              poster={heroMedia.poster}
              aria-label="Промышленная сварка"
            >
              <source src={heroMedia.video} type="video/mp4" />
            </video>
            <div className="hero-banner__overlay">
              <p>Live feed</p>
              <h3>Тест сварки перед отгрузкой</h3>
              <span>Проверяем каждый аппарат на нашей станции</span>
            </div>
          </div>
        </div>
      </section>

      {/* Популярные категории */}
      <section className="hero-shop">
        <div className="hero-shop__categories">
          <h2 className="hero-shop__section-title">Популярные категории</h2>
          <div className="hero-shop__categories-grid">
            {heroCategories.map((category) => (
              <Link key={category.id} to={`/catalog/${category.id}`} className="hero-shop__category-card">
                <div className="hero-shop__category-icon">{category.icon}</div>
                <h3>{category.title}</h3>
                <p>{category.description}</p>
                <span className="hero-shop__category-count">{category.count} товаров</span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Популярные товары */}
      <section className="hero-products">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Популярные товары</p>
            <h2>Хиты продаж</h2>
          </div>
          <Link to="/catalog" className="link">
            Весь каталог →
          </Link>
        </div>
        <div className="hero-products__grid">
          {heroProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>

      <section className="trust-band">
        {trustBadges.map((item) => (
          <div key={item.title}>
            <p>{item.title}</p>
            <span>{item.subtitle}</span>
          </div>
        ))}
      </section>

      <section className="field-gallery">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Полевые съемки</p>
            <h2>Мы показываем реальную работу оборудования</h2>
          </div>
          <p className="section-heading__support">Инженеры SVARKA.PRO сопровождают проекты от тендера до сдачи НАКС.</p>
        </div>
        <div className="field-gallery__grid">
          {FIELD_SHOTS.map((shot) => (
            <article key={shot.id} className="field-gallery__card" style={{ backgroundImage: `url(${shot.image})` }}>
              <div className="field-gallery__overlay">
                <p className="field-gallery__subtitle">{shot.subtitle}</p>
                <h3>{shot.title}</h3>
                <div className="field-gallery__stats">
                  {shot.stats.map((stat) => (
                    <span key={stat}>{stat}</span>
                  ))}
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="featured-products">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Хиты поставок</p>
            <h2>Лучшие аппараты недели</h2>
          </div>
          <Link to="/catalog" className="link">
            Весь каталог →
          </Link>
        </div>
        <div className="featured-products__grid">
          {featuredProducts.map((product) => (
            <div key={product.id} className="featured-products__card">
              <p className="featured-products__type">{product.type}</p>
              <h3>{product.name}</h3>
              <p>{product.description}</p>
              <div className="featured-products__specs">
                <span>{product.dutyCycle}</span>
                <span>{product.inputVoltage}</span>
              </div>
              <p className="featured-products__price">
                {product.price.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}
              </p>
            </div>
          ))}
        </div>
      </section>

      <section className="proof-grid">
        {proofMetrics.map((item) => (
          <div key={item.value}>
            <p>{item.value}</p>
            <span>{item.label}</span>
          </div>
        ))}
      </section>

      <section className="lead-section">
        <div className="lead-section__copy">
          <p className="eyebrow">Получить расчёт</p>
          <h2>Опишите задачу — соберём комплект и привезём инженера</h2>
          <ul>
            <li>Подбор аппаратов и расходников под ваши чертежи и режимы</li>
            <li>Прозрачный расчёт владения: запуск, расход, сервис</li>
            <li>Выезд технолога с демонстрацией шва на ваших материалах</li>
          </ul>
        </div>
        <LeadForm />
      </section>
    </div>
  )
}

export default Home

