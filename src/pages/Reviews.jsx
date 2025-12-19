import { useEffect } from 'react'
import '../styles/pages/Reviews.css'

const Reviews = () => {
  useEffect(() => {
    document.title = 'Отзывы клиентов о сварочном оборудовании - АльфаСмарт'
  }, [])

  return (
    <div className="reviews-page">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Отзывы клиентов</p>
          <h1>Что говорят о нас наши клиенты</h1>
        </div>
        <p className="section-heading__support">
          Реальные отзывы от предприятий, которые используют наше оборудование
        </p>
      </div>

      <div className="reviews-placeholder">
        <p>Раздел в разработке. Скоро здесь появятся отзывы наших клиентов.</p>
      </div>
    </div>
  )
}

export default Reviews


