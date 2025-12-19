import '../styles/components/Pagination.css'

const Pagination = ({ currentPage, totalPages, onPageChange }) => {
  if (totalPages <= 1) return null

  const getPageNumbers = () => {
    const pages = []
    const maxVisible = 7
    
    if (totalPages <= maxVisible) {
      // Показываем все страницы, если их немного
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i)
      }
    } else {
      // Показываем первую страницу
      pages.push(1)
      
      if (currentPage > 3) {
        pages.push('...')
      }
      
      // Показываем страницы вокруг текущей
      const start = Math.max(2, currentPage - 1)
      const end = Math.min(totalPages - 1, currentPage + 1)
      
      for (let i = start; i <= end; i++) {
        if (i !== 1 && i !== totalPages) {
          pages.push(i)
        }
      }
      
      if (currentPage < totalPages - 2) {
        pages.push('...')
      }
      
      // Показываем последнюю страницу
      pages.push(totalPages)
    }
    
    return pages
  }

  const pageNumbers = getPageNumbers()

  return (
    <nav className="pagination" aria-label="Навигация по страницам">
      <button
        className="pagination__button pagination__button--prev"
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        aria-label="Предыдущая страница"
      >
        ←
      </button>
      
      <div className="pagination__pages">
        {pageNumbers.map((page, index) => {
          if (page === '...') {
            return (
              <span key={`ellipsis-${index}`} className="pagination__ellipsis">
                ...
              </span>
            )
          }
          
          return (
            <button
              key={page}
              className={`pagination__button pagination__button--page ${
                page === currentPage ? 'pagination__button--active' : ''
              }`}
              onClick={() => onPageChange(page)}
              aria-label={`Страница ${page}`}
              aria-current={page === currentPage ? 'page' : undefined}
            >
              {page}
            </button>
          )
        })}
      </div>
      
      <button
        className="pagination__button pagination__button--next"
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === totalPages}
        aria-label="Следующая страница"
      >
        →
      </button>
    </nav>
  )
}

export default Pagination

