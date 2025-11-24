# Инструкция по деплою на GitHub Pages

## Проверка перед деплоем

1. **Убедитесь, что все изменения закоммичены:**
   ```bash
   git add .
   git commit -m "Update: готово к деплою"
   git push origin main
   ```

2. **Проверьте настройки GitHub Pages:**
   - Перейдите в Settings → Pages
   - Source должен быть: "GitHub Actions"
   - Если там указана ветка `gh-pages`, измените на "GitHub Actions"

3. **Проверьте workflow:**
   - Перейдите в Actions
   - Убедитесь, что workflow "Deploy to GitHub Pages" запускается при push в main
   - Если workflow не запускается, проверьте, что файл `.github/workflows/deploy.yml` существует и закоммичен

## Ручной запуск деплоя

1. Перейдите в **Actions** → **Deploy to GitHub Pages**
2. Нажмите **Run workflow**
3. Выберите ветку `main`
4. Нажмите **Run workflow**

## Возможные проблемы

### Workflow не запускается
- Убедитесь, что файл `.github/workflows/deploy.yml` закоммичен
- Проверьте, что в Settings → Actions включены workflows

### Ошибка при сборке
- Проверьте логи в Actions
- Убедитесь, что все зависимости установлены (`npm ci`)
- Проверьте, что нет синтаксических ошибок в коде

### Сайт не открывается
- Убедитесь, что в `vite.config.js` указан `base: './'`
- Проверьте, что в `main.jsx` используется `basename` из `import.meta.env.BASE_URL`
- Подождите несколько минут после деплоя (GitHub Pages может обновляться с задержкой)

## URL сайта

После успешного деплоя сайт будет доступен по адресу:
`https://remontok.github.io/SVARKA/`

