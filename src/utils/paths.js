/**
 * Получить правильный путь к ресурсу с учетом base path
 * @param {string} path - путь к ресурсу (например, '/welding-1.jpg')
 * @returns {string} - путь с учетом base path
 */
export const getAssetPath = (path) => {
  // Если путь уже начинается с base URL, возвращаем как есть
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  
  // Получаем base URL из Vite (будет '/SVARKA/' в production, '/' в development)
  const baseUrl = import.meta.env.BASE_URL || '/'
  
  // Убираем начальный слэш из пути, если он есть
  const cleanPath = path.startsWith('/') ? path.slice(1) : path
  
  // Объединяем base URL и путь
  return `${baseUrl}${cleanPath}`
}

