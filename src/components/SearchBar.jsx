const SearchBar = ({ label, supporting, value, onChange, placeholder, quickTags = [] }) => {
  return (
    <div className="search-panel">
      <div className="search-panel__header">
        <div>
          <p className="search-panel__label">{label}</p>
          {supporting && <p className="search-panel__supporting">{supporting}</p>}
        </div>
        <span className="search-panel__hint">Поиск по бренду, току или типу аппарата</span>
      </div>
      <div className="search-panel__input-wrapper">
        <input
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={placeholder}
          className="search-panel__input"
        />
        <button className="btn btn--accent" type="button">
          Найти
        </button>
      </div>
      {quickTags.length > 0 && (
        <div className="search-panel__tags">
          {quickTags.map((tag) => (
            <button
              key={tag}
              className="chip chip--ghost"
              type="button"
              onClick={() => onChange(tag)}
            >
              {tag}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

export default SearchBar

