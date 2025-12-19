import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import CallbackModal from '../components/CallbackModal'
import '../styles/pages/About.css'

const aboutSections = [
  {
    title: 'Наша история',
    icon: '📜',
    content: 'АльфаСмарт — ведущий поставщик сварочного оборудования в России с 2010 года. Мы начали свою деятельность с небольшой команды энтузиастов, которые понимали потребности промышленных предприятий в качественном сварочном оборудовании. За более чем 14 лет работы мы выросли в надежного партнера для сотен промышленных предприятий по всей стране, от небольших производств до крупных машиностроительных заводов.',
  },
  {
    title: 'Наша миссия',
    icon: '🎯',
    content: 'Предоставлять качественное сварочное оборудование и профессиональную поддержку, помогая нашим клиентам повышать эффективность производства и качество продукции. Мы работаем с лучшими производителями сварочного оборудования: Fronius, Kemppi, Svarog, Hypertherm и другими ведущими брендами. Наша цель — не просто продать оборудование, а стать вашим надежным технологическим партнером на долгие годы.',
  },
  {
    title: 'Наши преимущества',
    icon: '⭐',
    content: 'Более 1200 единиц оборудования на складе, собственный сервисный центр с современным диагностическим оборудованием, команда опытных технологов и индивидуальный подход к каждому клиенту. Мы предлагаем полный цикл услуг: от подбора оборудования под ваши задачи до пуско-наладки, обучения персонала и сервисного обслуживания. Наша логистическая сеть охватывает 18 регионов России, что позволяет нам оперативно доставлять оборудование и обеспечивать техническую поддержку в любом уголке страны.',
  },
  {
    title: 'Наша продукция',
    icon: '⚡',
    content: 'Мы специализируемся на поставке всего спектра сварочного оборудования: сварочные инверторы MMA, полуавтоматы MIG/MAG, TIG аппараты для сварки нержавеющей стали и алюминия, плазморезы для резки металла, сварочные трактора и каретки для механизированной сварки, роботизированные сварочные системы, а также сварочные столы, прижимы, упоры и все необходимые аксессуары. Вся продукция сертифицирована и соответствует требованиям ГОСТ и международным стандартам ISO.',
  },
]

const stats = [
  { value: '14+', label: 'лет на рынке', accent: 'yellow' },
  { value: '500+', label: 'довольных клиентов', accent: 'red' },
  { value: '1200+', label: 'единиц на складе', accent: 'yellow' },
  { value: '18', label: 'регионов доставки', accent: 'red' },
  { value: '320+', label: 'брендов в каталоге', accent: 'yellow' },
  { value: '96%', label: 'проектов в срок', accent: 'red' },
]

const values = [
  {
    icon: '✓',
    title: 'Качество',
    description: 'Работаем только с проверенными производителями и гарантируем качество каждого аппарата. Все оборудование проходит предпродажную проверку и тестирование на нашей сертифицированной станции.',
  },
  {
    icon: '⚡',
    title: 'Скорость',
    description: 'Доставка по РФ за 48 часов, выезд мастера в течение 24 часов. Наш собственный склад в Москве позволяет быстро комплектовать заказы и отправлять их клиентам.',
  },
  {
    icon: '🤝',
    title: 'Поддержка',
    description: 'Персональный технолог, обучение персонала, сервисное обслуживание 24/7. Наша команда инженеров готова выехать на ваше производство для консультации, пуско-наладки или решения технических вопросов.',
  },
  {
    icon: '💼',
    title: 'Опыт',
    description: 'Команда инженеров с опытом работы на крупных промышленных объектах: судостроительные верфи, машиностроительные заводы, предприятия нефтегазовой отрасли. Мы понимаем специфику работы в разных отраслях промышленности.',
  },
  {
    icon: '🔧',
    title: 'Сервис',
    description: 'Собственный сервисный центр с современным диагностическим оборудованием, склад оригинальных запчастей, подменный фонд оборудования. Гарантируем оперативный ремонт и минимальные простои производства.',
  },
  {
    icon: '📋',
    title: 'Документация',
    description: 'Полный комплект документации: паспорта, инструкции, схемы подключения. Помогаем с оформлением документов для НАКС, сертификации и аттестации сварочного производства.',
  },
]

// Ключевые сотрудники (фото будут добавлены позже)
const teamMembers = [
  {
    id: 1,
    name: 'Иванов Алексей Сергеевич',
    position: 'Генеральный директор',
    description: 'Опыт работы в сварочной отрасли более 20 лет. Специализируется на автоматизации сварочных процессов и внедрении роботизированных систем на промышленных предприятиях.',
    experience: '20+ лет',
  },
  {
    id: 2,
    name: 'Петрова Мария Владимировна',
    position: 'Технический директор',
    description: 'Инженер-технолог высшей категории. Эксперт по сварочным технологиям и подбору оборудования. Руководит командой технологов и инженеров по сервисному обслуживанию.',
    experience: '15+ лет',
  },
  {
    id: 3,
    name: 'Смирнов Дмитрий Анатольевич',
    position: 'Руководитель сервисного центра',
    description: 'Мастер по ремонту сварочного оборудования с опытом работы более 18 лет. Специализируется на диагностике и ремонте сложных неисправностей сварочных инверторов и полуавтоматов.',
    experience: '18+ лет',
  },
  {
    id: 4,
    name: 'Козлова Елена Игоревна',
    position: 'Менеджер по работе с клиентами',
    description: 'Специалист по подбору сварочного оборудования для промышленных предприятий. Помогает клиентам выбрать оптимальное решение под конкретные производственные задачи.',
    experience: '10+ лет',
  },
  {
    id: 5,
    name: 'Волков Андрей Николаевич',
    position: 'Главный инженер',
    description: 'Инженер-конструктор с опытом работы в машиностроении. Специализируется на механизации и автоматизации сварочных процессов, проектировании сварочных постов и цехов.',
    experience: '16+ лет',
  },
  {
    id: 6,
    name: 'Новикова Ольга Сергеевна',
    position: 'Руководитель отдела логистики',
    description: 'Специалист по организации поставок сварочного оборудования по всей России и в страны СНГ. Обеспечивает оперативную доставку и контроль качества транспортировки.',
    experience: '12+ лет',
  },
]

const About = () => {
  const [isCallbackModalOpen, setIsCallbackModalOpen] = useState(false)

  useEffect(() => {
    // Добавляем структурированные данные для SEO
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.text = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'АльфаСмарт',
      description: 'Ведущий поставщик сварочного оборудования в России. MIG, TIG, MMA аппараты, плазморезы, роботизированная сварка.',
      url: window.location.origin,
      telephone: '+7-800-707-19-55',
      address: {
        '@type': 'PostalAddress',
        addressCountry: 'RU',
        addressLocality: 'Россия',
      },
      foundingDate: '2010',
      numberOfEmployees: {
        '@type': 'QuantitativeValue',
        value: '50+',
      },
      areaServed: {
        '@type': 'Country',
        name: 'Россия',
      },
      knowsAbout: [
        'Сварочное оборудование',
        'Сварочные инверторы',
        'MIG сварка',
        'TIG сварка',
        'Плазменная резка',
        'Роботизированная сварка',
      ],
    })
    document.head.appendChild(script)

    // Меняем title страницы для SEO
    document.title = 'О компании АльфаСмарт - Поставщик сварочного оборудования | История, команда, сервис'

    return () => {
      document.head.removeChild(script)
      document.title = 'АльфаСмарт - Сварочное оборудование'
    }
  }, [])

  return (
    <div className="about-page">
      <article>
        {/* Hero Section */}
        <section className="about-hero">
          <div className="about-hero__content">
            <p className="eyebrow">О компании</p>
            <h1>АльфаСмарт — ваш надежный партнер в сварочном оборудовании</h1>
            <p className="about-hero__description">
              Более 14 лет мы поставляем качественное сварочное оборудование MIG, TIG, MMA, плазморезы и роботизированные системы, обеспечивая профессиональную поддержку промышленным предприятиям по всей России.
            </p>
          </div>
        </section>

        {/* Stats Section */}
        <section className="about-stats" aria-label="Статистика компании">
          <div className="about-stats__grid">
            {stats.map((stat) => (
              <div key={stat.label} className={`about-stat-card about-stat-card--${stat.accent}`}>
                <div className="about-stat-card__value">{stat.value}</div>
                <div className="about-stat-card__label">{stat.label}</div>
              </div>
            ))}
          </div>
        </section>

        {/* Content Sections */}
        <section className="about-content" aria-label="Информация о компании">
          {aboutSections.map((section, index) => (
            <article key={index} className="about-section-card">
              <div className="about-section-card__icon">{section.icon}</div>
              <div className="about-section-card__content">
                <h2 className="about-section-card__title">{section.title}</h2>
                <p className="about-section-card__text">{section.content}</p>
              </div>
            </article>
          ))}
        </section>

        {/* Values Section */}
        <section className="about-values" aria-label="Наши ценности">
          <div className="section-heading">
            <div>
              <p className="eyebrow">Наши ценности</p>
              <h2>Что нас отличает</h2>
            </div>
            <p className="section-heading__support">
              Принципы, которыми мы руководствуемся в работе с каждым клиентом
            </p>
          </div>
          <div className="values-grid">
            {values.map((value) => (
              <div key={value.title} className="value-card">
                <div className="value-card__icon">{value.icon}</div>
                <h3 className="value-card__title">{value.title}</h3>
                <p className="value-card__description">{value.description}</p>
              </div>
            ))}
          </div>
        </section>

        {/* Team Section */}
        <section className="about-team" aria-label="Наша команда">
          <div className="section-heading">
            <div>
              <p className="eyebrow">Команда профессионалов</p>
              <h2>Ключевые сотрудники</h2>
            </div>
            <p className="section-heading__support">
              Наша команда состоит из опытных специалистов, которые помогут вам выбрать и настроить сварочное оборудование под ваши задачи.
            </p>
          </div>
          <div className="team-grid">
            {teamMembers.map((member) => (
              <div key={member.id} className="team-member">
                <div className="team-member__photo">
                  <div className="team-member__photo-placeholder">
                    <span>{member.name.split(' ').map(n => n[0]).join('')}</span>
                  </div>
                </div>
                <div className="team-member__info">
                  <h3 className="team-member__name">{member.name}</h3>
                  <p className="team-member__position">{member.position}</p>
                  <div className="team-member__experience-badge">
                    <span>{member.experience}</span>
                  </div>
                  <p className="team-member__description">{member.description}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* CTA Section */}
        <section className="about-cta">
          <div className="cta-block">
            <div>
              <p className="eyebrow">Связаться с нами</p>
              <h2>Готовы обсудить ваш проект?</h2>
              <p>Наши специалисты помогут подобрать сварочное оборудование под ваши задачи, проведут консультацию и ответят на все вопросы по выбору, установке и обслуживанию оборудования.</p>
            </div>
            <div className="cta-block__actions">
              <Link to="/services" className="btn btn--primary">
                Получить консультацию
              </Link>
              <button
                type="button"
                className="btn btn--ghost"
                onClick={() => setIsCallbackModalOpen(true)}
              >
                Заказать звонок
              </button>
            </div>
          </div>
          </section>
      </article>
      <CallbackModal isOpen={isCallbackModalOpen} onClose={() => setIsCallbackModalOpen(false)} />
    </div>
  )
}

export default About
