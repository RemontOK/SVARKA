# Инструкция по исправлению деплоя на GitHub Pages

## Возможные причины проблемы:

1. **GitHub Pages не настроен в настройках репозитория**
2. **Незакоммиченные изменения** (workflow запускается только при push в main)
3. **Ошибки в workflow** (можно проверить в разделе Actions на GitHub)

## Шаги для исправления:

### 1. Настройка GitHub Pages в репозитории:

1. Перейдите на GitHub: https://github.com/RemontOK/SVARKA
2. Откройте **Settings** (Настройки)
3. В левом меню найдите **Pages**
4. В разделе **Source** выберите:
   - **Source**: `GitHub Actions` (не Deploy from a branch!)
   - Это позволит использовать workflow для деплоя

### 2. Закоммитьте и запушьте изменения:

```bash
# Добавить все изменения
git add .

# Закоммитить изменения
git commit -m "feat: обновление каталога и фильтров"

# Запушить в main (это запустит workflow)
git push origin main
```

### 3. Проверка workflow:

1. Перейдите в раздел **Actions** на GitHub
2. Найдите последний запуск workflow "Deploy static content to Pages"
3. Если есть ошибки - проверьте логи

### 4. Альтернативный способ (через gh-pages):

Если GitHub Actions не работает, можно использовать команду:

```bash
npm run deploy
```

Это создаст ветку `gh-pages` и задеплоит туда проект.

**ВАЖНО**: После этого в настройках Pages нужно выбрать:
- **Source**: `Deploy from a branch`
- **Branch**: `gh-pages` / `/(root)`

### 5. Проверка базового пути:

Убедитесь, что в `vite.config.js` указан правильный `base`:
```js
base: process.env.NODE_ENV === 'production' ? '/SVARKA/' : '/'
```

Это важно, так как ваш репозиторий называется `SVARKA`, а не `svarka`.

## Проверка результата:

После успешного деплоя сайт должен быть доступен по адресу:
**https://remontok.github.io/SVARKA/**

## Если ничего не помогает:

1. Проверьте логи в разделе **Actions** на GitHub
2. Убедитесь, что репозиторий публичный (GitHub Pages работает только для публичных репозиториев на бесплатном плане)
3. Проверьте, что workflow файл находится в `.github/workflows/static.yml`

