# DashD Analytics Pro Engine

DashD Analytics Pro Engine — WordPress-плагин для импорта, нормализации и визуализации аналитических данных (CSV/JSON) с интерактивным фронтендом, экспортом отчетов и расширенными интеграциями.

## Version Info

- Stable version: `11.7.23`
- Plugin file: `dashd-analytics-pro.php`
- PHP: `7.4+`
- WordPress: `6.4+`

---

## What The Plugin Does

- Подключает несколько источников данных (Google Sheets CSV и REST API JSON).
- Синхронизирует и хранит данные в собственной реляционной схеме.
- Рендерит интерактивные графики (`bar`, `line`, `donut`) через `Chart.js`.
- Строит расширенную таблицу с динамикой (QoQ/YoY), спарклайнами и экспортом.
- Экспортирует отчеты в CSV/PDF с брендингом (логотип, watermark, подпись).
- Поддерживает gated download (email перед экспортом).
- Поддерживает интеграции с Gutenberg, Elementor, YOOtheme Pro.

---

## Core Features

- Data connectors: `CSV` / `JSON`, методы `GET` / `POST`, кастомные headers.
- Formula engine: вычисляемые индикаторы, кросс-страна расчеты, time-shift (`::-1Y`, `::-1Q`).
- Data quality: детекция аномалий при синке, логирование.
- Admin UX: конструктор шорткодов, inline-редактирование raw data, импорт/экспорт словарей и raw data.
- PDF branding: SVG/PNG logo, ширина логотипа, watermark, footer signature.
- Localization: словари переводов (EN/UK/HY/RO/KA).
- Dark mode support: адаптация цветов графиков при переключении темы.

---

## Installation And Upgrade

1. Скопируйте папку плагина в `wp-content/plugins/dashd-analytics-pro-base`.
2. Активируйте плагин в админке WordPress.
3. При обновлениях БД используйте стандартный апгрейд плагина (миграции запускаются автоматически).
4. После крупных обновлений рекомендуется очистить кэш сайта/CDN.

---

## Data Sources

### CSV (Google Sheets style)

Ожидаемая матрица:

- Row 1: пусто, затем страны.
- Row 2: пусто, затем годы.
- Row 3: пусто, затем кварталы (`Q1..Q4`).
- Row 4+: индикатор + значения.

### JSON (REST API)

```json
[
  {
    "indicator": "Revenue",
    "country": "Ukraine",
    "year": 2024,
    "quarter": "Q1",
    "value": 1500.5
  }
]
```

### Calculated Indicator Formula Rules

Формулы для расчетных индикаторов задаются в формате:

```text
Operand [operator Operand]
```

Где `operator` может быть только один из: `+`, `-`, `*`, `/`.

`Operand`:

```text
IndicatorID[:CountryID][:Offset]
```

Правила:

- `IndicatorID` — обязателен, только число `1..999999`.
- `CountryID` — опционален, только число `1..999999`.
- `Offset` — опционален, формат `+N` / `-N` / `N` + единица:
  - `Y` (годы), диапазон: `-30..30`
  - `Q` (кварталы), диапазон: `-120..120`
- Пробелы допускаются в вводе, но при сохранении удаляются.
- Длина всей формулы ограничена.
- Если формула не проходит валидацию, индикатор не активируется как calculated.

Валидные примеры:

- `5`
- `5::-1Y`
- `5:2:-1Q`
- `5::-1Y+7`
- `12:4:2Q/8:4`

Невалидные примеры:

- `abc`
- `5+`
- `5:::+1Y`
- `5::200Q` (слишком большой сдвиг по кварталам)
- `5::40Y` (слишком большой сдвиг по годам)

---

## Shortcode

```text
[dashd_widget indicators="table1:5,table1:7,table1:9" mode="line" scale="linear" colors="#1e87f0,#3e95cd,#7ebae6" gated="true"]
```

Parameters:

- `indicators` — список индикаторов через запятую (формат `source_key:indicator_id`), можно выбрать несколько.
- `table` — legacy fallback source key (используется для обратной совместимости).
- `mode` — `bar | line | donut`.
- `scale` — `linear | logarithmic`.
- `colors` — HEX-палитра через запятую.
- `gated` — `true | false` (email-gate для CSV/PDF).
- `weight` — толщина линий (default: `3`).
- `height` — высота графика (default: `420px`).

---

## FAQ

**Данные обновились во внешнем источнике, но не на графике.**

Запустите manual sync в Dashboard плагина или дождитесь cron-синхронизации.

**Как перевести названия стран и индикаторов?**

Используйте вкладки `Countries Translation` / `Indicators Translation` или CSV-экспорт/импорт словарей.

**Почему gated download отклоняет email?**

Проверяется валидность email, honeypot и домен (включая MX/A проверки).

**Почему PDF не повторяет темную тему сайта?**

PDF рендерится на белом фоне намеренно для читаемости и печати.

---

## Public API Endpoints

Frontend AJAX:

- `wp_ajax_get_dashd_modern_data` (+ `nopriv`)
- `wp_ajax_get_dashd_periods_split` (+ `nopriv`)
- `wp_ajax_dashd_capture_lead` (+ `nopriv`)

Admin/AJAX:

- `wp_ajax_dashd_update_raw_value`
- `admin_post_dashd_manual_sync`
- `admin_post_dashd_export_csv`
- `admin_post_dashd_import_csv`
- `admin_post_dashd_export_raw_data`
- `admin_post_dashd_import_raw_data`
- `admin_post_dashd_export_leads`

---

## Integrations

- Gutenberg block
- Elementor widget
- YOOtheme Pro custom element

---

## Release Build

В проекте используются:

- `bin/bump-version.php` — bump версии в header + `DASHD_VERSION`.
- `build.sh` — сборка ZIP архива в `../dashd-archive`.

Commands:

```bash
chmod +x build.sh
./build.sh
./build.sh patch
./build.sh minor
./build.sh major
./build.sh patch --comment "mobile fixes"
./build.sh minor --yes --comment "release notes short tag"
```

Build options:

- `patch|minor|major` — повышает версию (`bin/bump-version.php`) перед сборкой.
- `--comment "text"` — добавляет текст к commit message после `Release vX.Y.Z - ...`.
- `--yes` — пропускает интерактивное подтверждение перед релизным commit.

Build behavior (when bump type is provided):

- выполняется `git add -A`;
- создается release commit в формате `Release vX.Y.Z` (с optional comment);
- автоматически выполняется `git push` текущей ветки в `origin`;
- создается ZIP архив в `../dashd-archive`;
- в конце печатается цветная строка финального статуса с версией и timestamp.

Output archive pattern:

```text
dashd-analytics-pro-vX.Y.Z.zip
```

---

## Changelog

Full changelog is maintained in:

- `CHANGELOG.md`

Latest release: `11.7.23`
---

## Notes

- Для production рекомендуется включить server/page cache и отдельный мониторинг sync-задач.
- Если на сайте используется aggressive minify/combine, после обновления плагина делайте cache purge.

---

© 2026 DashD Analytics Team. All rights reserved.
