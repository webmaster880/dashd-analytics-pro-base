#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# 1. Повышаем версию, если передан параметр (patch, minor или major)
BUMP_TYPE="${1:-}"

if [ -n "$BUMP_TYPE" ]; then
    if [[ "$BUMP_TYPE" =~ ^(patch|minor|major)$ ]]; then
        echo "⬆️ Повышение версии ($BUMP_TYPE)..."
        php bin/bump-version.php "$BUMP_TYPE"
    else
        echo "⚠️ Ошибка: Недопустимый параметр '$BUMP_TYPE'."
        echo "💡 Используйте: ./build.sh [patch|minor|major] (или без параметров для пересборки текущей версии)"
        exit 1
    fi
fi

# Настройки плагина
PLUGIN_SLUG="dashd-analytics-pro"
PLUGIN_MAIN_FILE="dashd-analytics-pro.php"
BUILD_DIR="build_temp"
ARCHIVE_DIR="../dashd-archive"

# Читаем версию из заголовка WordPress плагина
VERSION=$(grep -E "^[[:space:]]*\\*[[:space:]]*Version:" "$PLUGIN_MAIN_FILE" | awk '{print $3}' | tr -d '\r')

# Проверка, что версия считалась
if [ -z "$VERSION" ]; then
    echo "❌ Ошибка: Не удалось найти версию в файле $PLUGIN_MAIN_FILE!"
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-v${VERSION}.zip"

echo "🚀 Сборка плагина: $PLUGIN_SLUG, Версия: $VERSION"
echo "📦 Файл архива: $ZIP_NAME"

# 2. Удаляем старые временные файлы и старый архив этой версии (если есть)
rm -rf "$BUILD_DIR"
rm -f "$ARCHIVE_DIR/$ZIP_NAME"

# 3. Создаем временную директорию с правильным именем для WordPress
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# 4. Копируем файлы, игнорируя мусор
echo "📂 Копирование файлов..."
rsync -av --progress ./ "$BUILD_DIR/$PLUGIN_SLUG" \
    --exclude '.git' \
    --exclude '.gitignore' \
    --exclude '.DS_Store' \
    --exclude '*.zip' \
    --exclude 'node_modules' \
    --exclude 'package.json' \
    --exclude 'package-lock.json' \
    --exclude 'build_temp'

# Убеждаемся, что папка для готовых архивов существует
mkdir -p "$ARCHIVE_DIR"

# 5. Упаковываем в ZIP архив
echo "📦 Упаковка в архив $ZIP_NAME..."
cd "$BUILD_DIR"
zip -r "../../dashd-archive/$ZIP_NAME" "$PLUGIN_SLUG"
cd "$ROOT_DIR"

# 6. Очищаем временную директорию
rm -rf "$BUILD_DIR"

echo "✅ Сборка успешно завершена! Готовый файл: $ARCHIVE_DIR/$ZIP_NAME"
