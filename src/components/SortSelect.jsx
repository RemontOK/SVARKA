const SortSelect = ({ value, onChange }) => {
  const sortOptions = [
    { value: 'default', label: 'По умолчанию' },
    { value: 'price-asc', label: 'Цена: по возрастанию' },
    { value: 'price-desc', label: 'Цена: по убыванию' },
    { value: 'rating-desc', label: 'Рейтинг: сначала высокий' },
    { value: 'rating-asc', label: 'Рейтинг: сначала низкий' },
    { value: 'popularity', label: 'По популярности' },
  ]

  return (
    <div className="sort-select">
      <label htmlFor="sort-select" className="sort-select__label">
        Сортировка:
      </label>
      <select
        id="sort-select"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="sort-select__input"
      >
        {sortOptions.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  )
}

export default SortSelect

