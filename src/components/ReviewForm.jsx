import { useState } from 'react'

const ReviewForm = ({ onSubmit }) => {
  const [rating, setRating] = useState(0)
  const [hoveredRating, setHoveredRating] = useState(0)
  const [name, setName] = useState('')
  const [text, setText] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!rating || !name.trim() || !text.trim()) {
      return
    }

    setIsSubmitting(true)
    // Имитация отправки
    await new Promise((resolve) => setTimeout(resolve, 500))
    
    onSubmit({
      name: name.trim(),
      rating,
      text: text.trim(),
      date: new Date().toLocaleDateString('ru-RU'),
    })

    // Сброс формы
    setRating(0)
    setName('')
    setText('')
    setIsSubmitting(false)
  }

  return (
    <form className="review-form" onSubmit={handleSubmit}>
      <h3 className="review-form__title">Оставить отзыв</h3>
      
      <div className="review-form__field">
        <label htmlFor="review-name" className="review-form__label">
          Ваше имя
        </label>
        <input
          id="review-name"
          type="text"
          className="review-form__input"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Введите ваше имя"
          required
        />
      </div>

      <div className="review-form__field">
        <label className="review-form__label">Оценка</label>
        <div className="review-form__rating">
          {[1, 2, 3, 4, 5].map((star) => (
            <button
              key={star}
              type="button"
              className={`review-form__star-btn ${
                star <= (hoveredRating || rating) ? 'review-form__star-btn--active' : ''
              }`}
              onClick={() => setRating(star)}
              onMouseEnter={() => setHoveredRating(star)}
              onMouseLeave={() => setHoveredRating(0)}
              aria-label={`Оценить ${star} звезд${star > 1 ? 'ами' : 'ой'}`}
            >
              ★
            </button>
          ))}
          {rating > 0 && (
            <span className="review-form__rating-text">
              {rating === 1 && 'Ужасно'}
              {rating === 2 && 'Плохо'}
              {rating === 3 && 'Нормально'}
              {rating === 4 && 'Хорошо'}
              {rating === 5 && 'Отлично'}
            </span>
          )}
        </div>
      </div>

      <div className="review-form__field">
        <label htmlFor="review-text" className="review-form__label">
          Ваш отзыв
        </label>
        <textarea
          id="review-text"
          className="review-form__textarea"
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder="Поделитесь своим опытом использования товара..."
          rows={5}
          required
        />
      </div>

      <button
        type="submit"
        className="btn btn--primary review-form__submit"
        disabled={isSubmitting || !rating || !name.trim() || !text.trim()}
      >
        {isSubmitting ? 'Отправка...' : 'Отправить отзыв'}
      </button>
    </form>
  )
}

export default ReviewForm

