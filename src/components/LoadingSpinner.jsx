const LoadingSpinner = ({ size = 'medium' }) => {
  return (
    <div className={`loading-spinner loading-spinner--${size}`} aria-label="Загрузка">
      <div className="loading-spinner__circle"></div>
    </div>
  )
}

export default LoadingSpinner

