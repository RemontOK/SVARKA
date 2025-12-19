import { useTheme } from '../contexts/ThemeContext'

const ThemeToggle = () => {
  const { isDark, toggleTheme } = useTheme()

  return (
    <button
      className="theme-toggle"
      onClick={toggleTheme}
      type="button"
      aria-label={isDark ? 'Переключить на светлую тему' : 'Переключить на темную тему'}
      title={isDark ? 'Светлая тема' : 'Темная тема'}
    >
      <span className="theme-toggle__icon">
        {isDark ? '☀️' : '🌙'}
      </span>
    </button>
  )
}

export default ThemeToggle







