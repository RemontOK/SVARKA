import { Link } from 'react-router-dom'

const ProductCard = ({ product }) => {
  return (
    <Link to={`/product/${product.id}`} className="product-card">
      {product.image && (
        <div
          className="product-card__image"
          style={{ backgroundImage: `url(${product.image})` }}
        />
      )}
      <header>
        <div className="product-card__meta">
          <span className="product-card__type">{product.type}</span>
          <span className="product-card__brand">{product.brand}</span>
        </div>
        <h3>{product.name}</h3>
        <p className="product-card__description">{product.description}</p>
      </header>

      <dl className="product-card__specs">
        <div>
          <dt>Ток</dt>
          <dd>{product.dutyCycle}</dd>
        </div>
        <div>
          <dt>Питание</dt>
          <dd>{product.inputVoltage}</dd>
        </div>
        <div>
          <dt>Наличие</dt>
          <dd>{product.availability}</dd>
        </div>
      </dl>

      <div className="product-card__tags">
        {product.tags.map((tag) => (
          <span key={tag}>{tag}</span>
        ))}
      </div>

      <div className="product-card__footer">
        <div className="product-card__price-block">
          <p className="product-card__price">
            {new Intl.NumberFormat('ru-RU', {
              style: 'currency',
              currency: 'RUB',
              minimumFractionDigits: 0,
              maximumFractionDigits: 0,
            }).format(product.price)}
          </p>
          <p className="product-card__rating">
            <span className="product-card__rating-star">★</span>
            {product.rating.toFixed(1)}
          </p>
        </div>
        <button 
          className="btn btn--primary product-card__button" 
          type="button"
          onClick={(e) => {
            e.preventDefault()
            e.stopPropagation()
            // Здесь будет логика добавления в заявку
          }}
        >
          Добавить в заявку
        </button>
      </div>
    </Link>
  )
}

export default ProductCard

