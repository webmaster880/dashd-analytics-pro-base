#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

# 1. Повышаем версию, если передан параметр (patch, minor или major)
# Дополнительно:
# --yes  => пропустить подтверждение перед git commit
BUMP_TYPE=""
AUTO_YES=0

for arg in "$@"; do
    case "$arg" in
        patch|minor|major)
            if [ -n "$BUMP_TYPE" ]; then
                echo "⚠️ Ошибка: Параметр версии указан более одного раза."
                echo "💡 Используйте: ./build.sh [patch|minor|major] [--yes]"
                exit 1
            fi
            BUMP_TYPE="$arg"
            ;;
        --yes)
            AUTO_YES=1
            ;;
        *)
            echo "⚠️ Ошибка: Недопустимый параметр '$arg'."
            echo "💡 Используйте: ./build.sh [patch|minor|major] [--yes] (или без параметров для пересборки текущей версии)"
            exit 1
            ;;
    esac
done

if [ -n "$BUMP_TYPE" ]; then
    echo "⬆️ Повышение версии ($BUMP_TYPE)..."
    php bin/bump-version.php "$BUMP_TYPE"
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

# 1.1 Автокоммит версии в git после bump (если bump был запрошен)
if [ -n "$BUMP_TYPE" ]; then
    if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        # Commit full project snapshot for the release, not only version files.
        # This prevents drift where code changes stay local but version is pushed.
        git add -A
        if ! git diff --cached --quiet; then
            RELEASE_MSG="Release v${VERSION}"
            echo "📋 Файлы, которые войдут в релизный коммит:"
            git diff --cached --name-status

            if [ "$AUTO_YES" -ne 1 ]; then
                read -r -p "Подтвердить git commit \"$RELEASE_MSG\"? [y/N]: " CONFIRM_RELEASE_COMMIT
                case "$CONFIRM_RELEASE_COMMIT" in
                    y|Y|yes|YES)
                        ;;
                    *)
                        echo "⏹️ Коммит отменен пользователем. Сборка остановлена."
                        exit 1
                        ;;
                esac
            fi

            echo "📝 Git commit: $RELEASE_MSG"
            git commit -m "$RELEASE_MSG"
        else
            echo "ℹ️ Изменений версии для коммита не обнаружено."
        fi
    else
        echo "⚠️ Git-репозиторий не найден. Автокоммит пропущен."
    fi
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
