const ProductCard = ({ product }) => {
  return (
    <article className="product-card">
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
        <div>
          <p className="product-card__price">
            {product.price.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}
          </p>
          <p className="product-card__rating">★ {product.rating.toFixed(1)}</p>
        </div>
        <button className="btn btn--primary" type="button">
          Добавить в заявку
        </button>
      </div>
    </article>
  )
}

export default ProductCard

