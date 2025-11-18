const LeadForm = () => {
  return (
    <form className="lead-form">
      <div>
        <label htmlFor="lead-name">Имя и компания</label>
        <input id="lead-name" name="name" placeholder="Алексей · Цех сварки №3" />
      </div>
      <div>
        <label htmlFor="lead-phone">Телефон</label>
        <input id="lead-phone" name="phone" placeholder="+7 (___) ___-__-__" />
      </div>
      <div>
        <label htmlFor="lead-task">Задача</label>
        <textarea id="lead-task" name="task" placeholder="Нужен подбор TIG для алюминия 5 мм" rows={3} />
      </div>
      <button className="btn btn--primary" type="submit">
        Получить расчёт
      </button>
      <p className="lead-form__note">Отвечаем в течение 15 минут. Без спама.</p>
    </form>
  )
}

export default LeadForm

