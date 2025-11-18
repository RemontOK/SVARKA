const serviceItems = [
  {
    title: 'Пуско-наладка',
    description: 'Настраиваем режимы, обучаем персонал, делаем эталонные швы прямо на вашем производстве.',
  },
  {
    title: 'Сервис 24/7',
    description: 'Собственный склад плат и расходников, подменный фонд в Москве и Санкт-Петербурге.',
  },
  {
    title: 'Логистика',
    description: 'Доставка по РФ за 48 часов, обрешётка и страхование. Экспортные поставки в СНГ.',
  },
]

const Services = () => {
  return (
    <div className="services-page">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Сервис и сопровождение</p>
          <h1>Берём на себя внедрение и поддержку оборудования</h1>
        </div>
        <p className="section-heading__support">
          Персональный технолог, выезд мастера в течение 24 часов, контракты SLA для производственных площадок.
        </p>
      </div>

      <div className="services-grid">
        {serviceItems.map((item) => (
          <article key={item.title}>
            <h3>{item.title}</h3>
            <p>{item.description}</p>
          </article>
        ))}
      </div>

      <div className="cta-block">
        <div>
          <p className="eyebrow">Оставить заявку</p>
          <h2>Получите подбор оборудования и коммерческое предложение за 20 минут</h2>
          <p>Пришлём расчёт по почте и свяжемся для уточнения задач.</p>
        </div>
        <div className="cta-block__actions">
          <button className="btn btn--primary" type="button">
            Получить КП
          </button>
          <button className="btn btn--ghost" type="button">
            Скачать прайс
          </button>
        </div>
      </div>
    </div>
  )
}

export default Services

