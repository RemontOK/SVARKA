import { Link } from 'react-router-dom'
import '../styles/components/Footer.css'
import { useEffect } from 'react'

const Footer = () => {
  useEffect(() => {
    // Добавляем структурированные данные для SEO
    const script = document.createElement('script')
    script.type = 'application/ld+json'
    script.text = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'АльфаСмарт',
      url: window.location.origin,
      logo: `${window.location.origin}/logo.png`,
      contactPoint: {
        '@type': 'ContactPoint',
        telephone: '+7-800-707-19-55',
        contactType: 'customer service',
        areaServed: 'RU',
        availableLanguage: 'Russian',
      },
      address: {
        '@type': 'PostalAddress',
        streetAddress: 'ул. Промышленная, д. 10',
        addressLocality: 'Москва',
        addressCountry: 'RU',
      },
      sameAs: [
        // Здесь можно добавить ссылки на соцсети
      ],
    })
    document.head.appendChild(script)

    return () => {
      document.head.removeChild(script)
    }
  }, [])

  return (
    <footer className="site-footer" role="contentinfo">
      <div className="site-footer__section">
        <h3 className="site-footer__title">Каталог</h3>
        <nav className="site-footer__nav">
          <Link to="/catalog" className="site-footer__link">Сварочное оборудование</Link>
          <Link to="/catalog/mig" className="site-footer__link">MIG/MAG полуавтоматы</Link>
          <Link to="/catalog/tig" className="site-footer__link">TIG аппараты</Link>
          <Link to="/catalog/mma" className="site-footer__link">MMA инверторы</Link>
          <Link to="/catalog/plasma" className="site-footer__link">Плазморезы</Link>
          <Link to="/catalog/accessories" className="site-footer__link">Аксессуары</Link>
        </nav>
      </div>

      <div className="site-footer__section">
        <h3 className="site-footer__title">Компания</h3>
        <nav className="site-footer__nav">
          <Link to="/about" className="site-footer__link">О компании</Link>
          <Link to="/services" className="site-footer__link">Сервис и гарантия</Link>
          <Link to="/articles" className="site-footer__link">Статьи и новости</Link>
          <Link to="/reviews" className="site-footer__link">Отзывы клиентов</Link>
          <Link to="/contacts" className="site-footer__link">Контакты</Link>
        </nav>
      </div>

      <div className="site-footer__section">
        <h3 className="site-footer__title">Контакты</h3>
        <div className="site-footer__contacts">
          <div className="site-footer__contact-item">
            <span className="site-footer__label">Телефон:</span>
            <a href="tel:+78007071955" className="site-footer__link site-footer__link--contact">
              +7 (800) 707-19-55
            </a>
          </div>
          <div className="site-footer__contact-item">
            <span className="site-footer__label">Email:</span>
            <a href="mailto:info@alfasmart.ru" className="site-footer__link site-footer__link--contact">
              info@alfasmart.ru
            </a>
          </div>
          <div className="site-footer__contact-item">
            <span className="site-footer__label">Режим работы:</span>
            <span className="site-footer__text">Пн-Пт: 9:00 - 18:00</span>
          </div>
          <div className="site-footer__contact-item">
            <span className="site-footer__label">Адрес:</span>
            <span className="site-footer__text">г. Москва, ул. Промышленная, д. 10</span>
          </div>
        </div>
      </div>

      <div className="site-footer__section">
        <h3 className="site-footer__title">Информация</h3>
        <nav className="site-footer__nav">
          <Link to="/delivery" className="site-footer__link">Доставка и оплата</Link>
          <Link to="/warranty" className="site-footer__link">Гарантия</Link>
          <Link to="/privacy" className="site-footer__link">Политика конфиденциальности</Link>
          <Link to="/terms" className="site-footer__link">Пользовательское соглашение</Link>
          <Link to="/sitemap" className="site-footer__link">Карта сайта</Link>
        </nav>
      </div>

      <div className="site-footer__bottom">
        <div className="site-footer__copyright">
          <p>&copy; {new Date().getFullYear()} АльфаСмарт. Все права защищены.</p>
        </div>
        <div className="site-footer__seo">
          <p className="site-footer__seo-text">
            Профессиональное сварочное оборудование: MIG, TIG, MMA аппараты, плазморезы от ведущих производителей. 
            Доставка по России, сервисное обслуживание, гарантия качества.
          </p>
        </div>
      </div>
    </footer>
  )
}

export default Footer
