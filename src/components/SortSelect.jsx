import '../styles/components/SortSelect.css'

const SortSelect = ({ value, onChange }) => {
  const sortOptions = [
    { value: 'default', label: 'По умолчанию' },
    { value: 'popularity', label: 'По популярности' },
    { value: 'name-asc', label: 'По имени: А-Я' },
    { value: 'name-desc', label: 'По имени: Я-А' },
    { value: 'rating-desc', label: 'По рейтингу: сначала высокий' },
    { value: 'rating-asc', label: 'По рейтингу: сначала низкий' },
    { value: 'price-asc', label: 'По цене: по возрастанию' },
    { value: 'price-desc', label: 'По цене: по убыванию' },
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





