import { useState } from 'react'
import { Link } from 'react-router-dom'
import SearchBar from '../components/SearchBar'
import LeadForm from '../components/LeadForm'
import { FIELD_SHOTS } from '../data/gallery'
import { WELDER_CATEGORIES, WELDERS } from '../data/welders'

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

const Home = ({ onSearch }) => {
  const [heroSearch, setHeroSearch] = useState('')
  const featuredProducts = WELDERS.slice(0, 3)
  const heroCategories = WELDER_CATEGORIES.slice(0, 4)
  const handleHeroSearch = (value) => {
    setHeroSearch(value)
    onSearch?.(value)
  }

  return (
    <div className="home-page">
      <section className="hero hero--minimal">
        <div className="hero__copy">
          <p className="eyebrow">Каталог сварочного оборудования</p>
          <h1>АльфаСмарт — подберите аппараты под ваши задачи</h1>
          <p className="hero__description">
            MIG, TIG, MMA, плазморезы и вентиляция. Мы знаем, что поставки крупного цеха — это процессы, а не красивые
            баннеры.
          </p>
          <div className="hero__actions">
            <Link className="btn btn--primary" to="/catalog">
              Открыть каталог
            </Link>
            <Link className="btn btn--ghost" to="/services">
              Помощь технолога
            </Link>
          </div>
          <div className="hero__stats">
            {heroHighlights.map((item) => (
              <div key={item.label}>
                <p>{item.value}</p>
                <span>{item.label}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="hero__panel">
          <SearchBar
            label="Быстрый поиск"
            supporting="Ведите марку, ток, тип или конкретную задачу"
            placeholder="Например, TIG 200 AC/DC"
            value={heroSearch}
            onChange={handleHeroSearch}
            quickTags={['MIG 250', 'TIG AC/DC', 'Плазморез']}
          />
          <div className="hero__categories">
            {heroCategories.map((category) => (
              <Link key={category.id} to="/catalog" className="hero__category-card">
                <span>{category.title}</span>
                <p>{category.description}</p>
              </Link>
            ))}
          </div>
        </div>
        <div className="hero__visual">
          <video
            className="hero__video"
            playsInline
            autoPlay
            loop
            muted
            poster={heroMedia.poster}
            aria-label="Промышленная сварка"
          >
            <source src={heroMedia.video} type="video/mp4" />
          </video>
          <div className="hero__visual-overlay">
            <p>Live feed</p>
            <h3>Тест сварки перед отгрузкой</h3>
            <span>Проверяем каждый аппарат на нашей станции</span>
          </div>
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

