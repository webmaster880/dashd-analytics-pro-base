#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

VERSION="n/a"

# Final colored status line (always printed).
print_final_status() {
    local exit_code=$?
    local ts
    ts="$(date '+%Y-%m-%d %H:%M:%S %Z')"
    if [ "$exit_code" -eq 0 ]; then
        printf '\n\033[1;32m%s\033[0m\n' "✅ STATUS: SUCCESS | VERSION: ${VERSION} | CREATED: ${ts}"
    else
        printf '\n\033[1;31m%s\033[0m\n' "❌ STATUS: FAILED | VERSION: ${VERSION} | TIME: ${ts}"
    fi
}
trap print_final_status EXIT

# 1. Повышаем версию, если передан параметр (patch, minor или major)
# Дополнительно:
# --yes  => пропустить подтверждение перед git commit
BUMP_TYPE=""
AUTO_YES=0
RELEASE_COMMENT=""
while [ "$#" -gt 0 ]; do
    case "$1" in
        patch|minor|major)
            if [ -n "$BUMP_TYPE" ]; then
                echo "⚠️ Ошибка: Параметр версии указан более одного раза."
                echo "💡 Используйте: ./build.sh [patch|minor|major] [--yes] [--comment \"text\"]"
                exit 1
            fi
            BUMP_TYPE="$1"
            shift
            ;;
        --yes)
            AUTO_YES=1
            shift
            ;;
        --comment)
            if [ "$#" -lt 2 ]; then
                echo "⚠️ Ошибка: Для --comment нужно передать текст комментария."
                echo "💡 Пример: ./build.sh patch --comment \"mobile fixes\""
                exit 1
            fi
            RELEASE_COMMENT="$2"
            shift 2
            ;;
        --comment=*)
            RELEASE_COMMENT="${1#--comment=}"
            shift
            ;;
        *)
            echo "⚠️ Ошибка: Недопустимый параметр '$1'."
            echo "💡 Используйте: ./build.sh [patch|minor|major] [--yes] [--comment \"text\"] (или без параметров для пересборки текущей версии)"
            exit 1
            ;;
    esac
done

if [ -n "$BUMP_TYPE" ]; then
    echo "⬆️ Повышение версии ($BUMP_TYPE)..."
    php bin/bump-version.php "$BUMP_TYPE"
elif [ -n "${RELEASE_COMMENT// }" ]; then
    echo "ℹ️ Параметр --comment передан без bump-уровня. Комментарий будет проигнорирован."
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
            if [ -n "${RELEASE_COMMENT// }" ]; then
                RELEASE_MSG="${RELEASE_MSG} - ${RELEASE_COMMENT}"
            fi
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

        current_branch="$(git rev-parse --abbrev-ref HEAD)"
        echo "🚀 Git push: origin/$current_branch"
        git push -u origin "$current_branch"
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
