import { useState } from 'react'
import '../styles/components/Modal.css'

const CATEGORIES = [
  { value: 'equipment', label: 'Подбор оборудования' },
  { value: 'consultation', label: 'Консультация технолога' },
  { value: 'service', label: 'Сервисное обслуживание' },
  { value: 'delivery', label: 'Доставка и логистика' },
  { value: 'parts', label: 'Запчасти и расходники' },
  { value: 'other', label: 'Другое' },
]

const CallbackModal = ({ isOpen, onClose }) => {
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    category: '',
  })

  const [errors, setErrors] = useState({})

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
    // Очищаем ошибку при изменении поля
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }))
    }
  }

  const validateForm = () => {
    const newErrors = {}

    if (!formData.name.trim()) {
      newErrors.name = 'Введите ваше имя'
    }

    if (!formData.phone.trim()) {
      newErrors.phone = 'Введите номер телефона'
    } else if (!/^[\d\s()+-]+$/.test(formData.phone)) {
      newErrors.phone = 'Некорректный номер телефона'
    }

    if (!formData.category) {
      newErrors.category = 'Выберите категорию вопроса'
    }

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = (e) => {
    e.preventDefault()

    if (!validateForm()) {
      return
    }

    // TODO: Здесь будет отправка на почту
    console.log('Callback form submitted:', formData)

    // Показываем успешное сообщение
    alert('Спасибо! Мы свяжемся с вами в ближайшее время.')

    // Очищаем форму и закрываем модальное окно
    setFormData({ name: '', phone: '', category: '' })
    onClose()
  }

  const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) {
      onClose()
    }
  }

  if (!isOpen) return null

  return (
    <div className="modal-overlay" onClick={handleBackdropClick}>
      <div className="modal modal--callback" onClick={(e) => e.stopPropagation()}>
        <button
          className="modal__close"
          type="button"
          onClick={onClose}
          aria-label="Закрыть"
        >
          ×
        </button>

        <div className="modal__header">
          <h2 className="modal__title">Заказать звонок</h2>
          <p className="modal__subtitle">
            Заполните форму, и наш специалист свяжется с вами в ближайшее время
          </p>
        </div>

        <form className="callback-form" onSubmit={handleSubmit}>
          <div className="callback-form__field">
            <label htmlFor="callback-name" className="callback-form__label">
              Ваше имя <span className="callback-form__required">*</span>
            </label>
            <input
              id="callback-name"
              name="name"
              type="text"
              className={`callback-form__input ${errors.name ? 'callback-form__input--error' : ''}`}
              placeholder="Иван Иванов"
              value={formData.name}
              onChange={handleChange}
            />
            {errors.name && <span className="callback-form__error">{errors.name}</span>}
          </div>

          <div className="callback-form__field">
            <label htmlFor="callback-phone" className="callback-form__label">
              Номер телефона <span className="callback-form__required">*</span>
            </label>
            <input
              id="callback-phone"
              name="phone"
              type="tel"
              className={`callback-form__input ${errors.phone ? 'callback-form__input--error' : ''}`}
              placeholder="+7 (___) ___-__-__"
              value={formData.phone}
              onChange={handleChange}
            />
            {errors.phone && <span className="callback-form__error">{errors.phone}</span>}
          </div>

          <div className="callback-form__field">
            <label htmlFor="callback-category" className="callback-form__label">
              Категория вопроса <span className="callback-form__required">*</span>
            </label>
            <select
              id="callback-category"
              name="category"
              className={`callback-form__select ${errors.category ? 'callback-form__select--error' : ''}`}
              value={formData.category}
              onChange={handleChange}
            >
              <option value="">Выберите категорию</option>
              {CATEGORIES.map((cat) => (
                <option key={cat.value} value={cat.value}>
                  {cat.label}
                </option>
              ))}
            </select>
            {errors.category && <span className="callback-form__error">{errors.category}</span>}
          </div>

          <button type="submit" className="btn btn--primary callback-form__submit">
            Заказать звонок
          </button>

          <p className="callback-form__note">
            Нажимая кнопку, вы соглашаетесь с политикой конфиденциальности
          </p>
        </form>
      </div>
    </div>
  )
}

export default CallbackModal



