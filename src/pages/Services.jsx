import { Link } from 'react-router-dom'
import { getAssetPath } from '../utils/paths'
import '../styles/pages/Services.css'

const serviceItems = [
  {
    icon: '🔧',
    title: 'Пуско-наладка',
    description: 'Настраиваем режимы, обучаем персонал, делаем эталонные швы прямо на вашем производстве. Наши инженеры помогут запустить оборудование и оптимизировать процессы сварки.',
  },
  {
    icon: '⚡',
    title: 'Сервис 24/7',
    description: 'Собственный склад плат и расходников, подменный фонд в Москве и Санкт-Петербурге. Круглосуточная поддержка для критически важных производственных линий.',
  },
  {
    icon: '🚚',
    title: 'Логистика',
    description: 'Доставка по РФ за 48 часов, обрешётка и страхование. Экспортные поставки в СНГ. Собственная логистическая сеть обеспечивает быструю доставку в любой регион.',
  },
  {
    icon: '🎓',
    title: 'Обучение персонала',
    description: 'Проводим обучение операторов и технологов работе с оборудованием. Организуем семинары и мастер-классы на вашем производстве или в нашем учебном центре.',
  },
  {
    icon: '📋',
    title: 'Техническая документация',
    description: 'Предоставляем полный комплект документации: паспорта, инструкции, схемы подключения. Помогаем с оформлением документов для НАКС и сертификации.',
  },
  {
    icon: '🛠️',
    title: 'Модернизация оборудования',
    description: 'Помогаем модернизировать существующее оборудование, добавляем новые функции и повышаем производительность. Консультируем по вопросам оптимизации производства.',
  },
]

const warrantyFeatures = [
  {
    icon: '✓',
    title: 'Оперативная диагностика',
    description: 'Неисправности выявляются на современном диагностическом оборудовании в кратчайшие сроки. Наши специалисты используют профессиональные инструменты для точного определения проблемы.',
  },
  {
    icon: '✓',
    title: 'Оригинальные запчасти',
    description: 'Ремонт любой сложности выполняется только с использованием оригинальных запасных частей, которые всегда в наличии на нашем складе. Это гарантирует долговечность и надежность ремонта.',
  },
  {
    icon: '✓',
    title: 'Высокое качество работ',
    description: 'Наши мастера имеют многолетний опыт работы со сварочным оборудованием. Каждый ремонт выполняется с соблюдением всех технических стандартов и требований производителя.',
  },
]

const Services = () => {
  return (
    <div className="services-page">
      <section className="services-intro">
        <div className="services-intro__wrapper">
          <div className="services-intro__header">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Сервис и сопровождение</p>
                <h1>Гарантия и сервисное обслуживание оборудования</h1>
              </div>
            </div>
          </div>
          <div className="services-intro__content">
            <p>
              Для обеспечения квалифицированного сервиса компания АльфаСмарт развивает сеть авторизованных сервисных центров. Наш специализированный центр по ремонту сварочного оборудования укомплектован современным диагностическим комплексом и оригинальными запасными частями, что гарантирует точную диагностику, оперативное проведение работ и безупречное качество ремонта.
            </p>
            <p>
              Мы ценим ваше время, поэтому предлагаем быстрый и надежный сервис, который поможет минимизировать простои производства и обеспечить непрерывность работы вашего оборудования.
            </p>
          </div>
          <div className="services-intro__image">
            <img
              src={getAssetPath('/service-repair.jpg')}
              alt="Ремонт сварочного инвертора в сервисном центре АльфаСмарт"
              loading="lazy"
            />
          </div>
          <div className="services-intro__footer">
            <p className="services-intro__footer-text">
              Гарантийные обязательства начинают действовать с даты покупки оборудования. В течение гарантийного срока покупатель имеет право на бесплатное устранение любых дефектов, возникших по вине производителя, путем ремонта или замены неисправных компонентов на новые.
            </p>
          </div>
        </div>
      </section>

      <section className="services-features">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Преимущества нашего сервиса</p>
            <h2>Почему выбирают нас</h2>
          </div>
        </div>
        <div className="warranty-features">
          {warrantyFeatures.map((feature, index) => (
            <div key={index} className="warranty-feature">
              <div className="warranty-feature__icon">{feature.icon}</div>
              <h3 className="warranty-feature__title">{feature.title}</h3>
              <p className="warranty-feature__description">{feature.description}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="services-grid-section">
        <div className="section-heading">
          <div>
            <p className="eyebrow">Наши услуги</p>
            <h2>Полный спектр сервисных услуг</h2>
          </div>
        </div>
        <div className="services-grid">
          {serviceItems.map((item) => (
            <article key={item.title} className="service-card">
              <div className="service-card__icon">{item.icon}</div>
              <h3 className="service-card__title">{item.title}</h3>
              <p className="service-card__description">{item.description}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="service-guarantee">
        <div className="service-guarantee__content">
          <h2>Гарантийные обязательства</h2>
          <div className="service-guarantee__text">
            <p>
              Гарантийные обязательства начинают действовать с даты покупки оборудования. В течение гарантийного срока покупатель имеет право на бесплатное устранение любых дефектов, возникших по вине производителя, путем ремонта или замены неисправных компонентов на новые.
            </p>
            <p>
              Наша компания гарантирует, что все оборудование проходит предпродажную проверку и тестирование. Мы предоставляем полную техническую поддержку на протяжении всего гарантийного периода и помогаем решить любые вопросы, связанные с эксплуатацией оборудования.
            </p>
            <p>
              В случае возникновения неисправности в гарантийный период, вы можете обратиться в любой из наших авторизованных сервисных центров. Наши специалисты проведут диагностику и устранят проблему в кратчайшие сроки, используя только оригинальные запчасти и компоненты.
            </p>
          </div>
        </div>
      </section>

      <section className="service-info-grid">
        <div className="service-network">
          <div className="service-network__card">
            <div className="service-network__header">
              <p className="eyebrow">Сеть сервисных центров</p>
              <h2>Мы рядом с вами</h2>
            </div>
            <div className="service-network__content">
              <p>
                АльфаСмарт развивает сеть авторизованных сервисных центров по всей России. Наши партнеры проходят обязательное обучение и сертификацию, что гарантирует единые стандарты качества обслуживания во всех регионах.
              </p>
              <p>
                Каждый сервисный центр укомплектован современным диагностическим оборудованием и имеет доступ к складу оригинальных запасных частей. Это позволяет нам обеспечивать быстрый и качественный ремонт независимо от вашего местоположения.
              </p>
            </div>
          </div>
        </div>

        <div className="service-schedule">
          <div className="service-schedule__card">
            <div className="service-schedule__header">
              <p className="eyebrow">Режим работы</p>
              <h2>Сервисный центр</h2>
            </div>
            <div className="service-schedule__content">
              <div className="service-schedule__hours">
                <p className="service-schedule__days">Понедельник – Пятница</p>
                <p className="service-schedule__time">с 9:00 до 18:00</p>
              </div>
              <p className="service-schedule__note">
                Обращайтесь в наши авторизованные сервисные центры — мы вернем ваше оборудование к жизни в кратчайшие сроки!
              </p>
            </div>
          </div>
        </div>
      </section>

      <div className="cta-block">
        <div>
          <p className="eyebrow">Нужна помощь?</p>
          <h2>Свяжитесь с нашим сервисным центром</h2>
          <p>Наши специалисты готовы помочь вам с любыми вопросами по обслуживанию и ремонту оборудования.</p>
        </div>
        <div className="cta-block__actions">
          <a href="tel:+78007071955" className="btn btn--primary">
            Позвонить в сервис
          </a>
          <Link to="/about" className="btn btn--ghost">
            О компании
          </Link>
        </div>
      </div>
    </div>
  )
}

export default Services

