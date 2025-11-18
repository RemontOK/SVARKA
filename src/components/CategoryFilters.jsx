const CategoryFilters = ({ categories, selectedCategory, onSelectCategory }) => {
  return (
    <div className="category-bar">
      {categories.map((category) => {
        const isActive = selectedCategory === category.id
        return (
          <button
            key={category.id}
            className={isActive ? 'chip chip--active' : 'chip'}
            style={{ borderColor: category.accent }}
            onClick={() => onSelectCategory(category.id)}
            type="button"
          >
            <span className="chip__dot" style={{ backgroundColor: category.accent }} />
            <div>
              <p className="chip__title">{category.title}</p>
              <p className="chip__subtitle">{category.description}</p>
            </div>
          </button>
        )
      })}
    </div>
  )
}

export default CategoryFilters

