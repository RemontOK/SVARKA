import { useEffect } from 'react'
import '../styles/pages/Articles.css'

const Articles = () => {
  useEffect(() => {
    document.title = 'Статьи и новости о сварочном оборудовании - АльфаСмарт'
  }, [])

  return (
    <div className="articles-page">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Статьи и новости</p>
          <h1>Полезная информация о сварочном оборудовании</h1>
        </div>
        <p className="section-heading__support">
          Экспертные статьи, обзоры оборудования и новости из мира сварки
        </p>
      </div>

      <div className="articles-placeholder">
        <p>Раздел в разработке. Скоро здесь появятся полезные статьи и новости.</p>
      </div>
    </div>
  )
}

export default Articles


