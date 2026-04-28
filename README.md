# Boomstream Filter for Moodle

Moodle-фильтр, который перехватывает HTML-контент страниц курсов и встроенный плеер Boomstream привязывает к текущему пользователю Moodle через Pay Per View API. Пользователь получает персональный `id_recovery`, по которому Boomstream проверяет доступ к видео.

## Установка

1. Распаковать архив.
2. Скопировать папку `boomstream` в `<moodle>/filter/`.
3. Site administration → Filters → Manage filters → включить **Boomstream video platform**.
4. Site administration → Plugins → Filters → Boomstream — заполнить настройки.

## Настройки (`settings.php`)

| Ключ | Назначение |
|---|---|
| `hostname` | Целевой хост Boomstream. Используется для (а) серверных API-вызовов, (б) подмены домена в `src` плеера, (в) подмены домена SDK-скрипта. По умолчанию `play.boomstream.com`. Опционален — при пустом значении плагин подставляет хост из самого `src` найденного плеера, подмена становится no-op. |
| `key` | API-key проекта (Project Settings → Integration). |
| `subscription` | Код подписки (Subscriptions Tab → Subscription Name). |
| `debug` | Если `Yes` — в HTML добавляется HTML-комментарий с трассой: какие коды нашлись, какие URL дёргались, что вернул API. |

Фильтр работает, если заполнены `key` и `subscription`. Поле `hostname` опционально — если оно пустое, плагин использует хост, обнаруженный в `src` найденного плеера (`$identifier` из регулярки), и в этом режиме подмена домена становится no-op, а API-вызовы идут на тот же хост, который вписал автор.

## Что фильтр заменяет

Фильтр проходит по всему отрендеренному HTML и ищет атрибут `src="..."`, ведущий на плеер Boomstream. Регулярка собирается в `filter()` (`boomstream.php:27-39`):

```
src="https://(play.boomstream.com|.net|.dev|.org|<hostname>)/.../?code=XXXXXXXX..."
```

Из совпадения извлекаются три части:

1. **URL** целиком (то, что в `src`).
2. **Хост** (`play.boomstream.com` и т.п.) — используется как `identifier` в хеше.
3. **Code** — 8-символьный идентификатор медиа (`[a-zA-Z0-9]{8}`).

Поддерживаются обе формы вставки:

- классический iframe — `src="https://play.boomstream.com/<code>/..."`,
- adaptive-режим — `src="https://play.boomstream.com/<code>/config.jsonp"` рядом с `<span data-boomstream-code="<code>" data-boomstream-mode="adaptive">`.

К каждому найденному `src` фильтр:

1. **Подменяет хост** на сконфигурированный `hostname` (см. ниже).
2. **Дописывает параметр** `id_recovery=<hash>` (через `?` или `&`, в зависимости от того, был ли уже query-string).

Дополнительно в `_process` для каждой найденной вставки выполняется подмена SDK-скрипта: после того как для матча определён `$identifier` (host из `src`) и `$this->hostname` (целевой host для API), все URL вида `https://<identifier>/assets/...` (типичный пример — `https://play.boomstream.com/assets/javascripts/biframesdk.js?v=1.0.5`) переписываются на `https://<hostname>/assets/...`. Это нужно, чтобы при кастомном домене SDK-скрипт и плеер загружались с одного и того же хоста. Если `$identifier === $this->hostname` (т.е. `hostname` не сконфигурирован и сделан фолбэк на host из `src`), подмена пропускается.

Span с `data-boomstream-code` фильтр не трогает — там нет хоста.

### Домен в `src` переписывается на сконфигурированный `hostname`

Если в настройках указан, например, `play.boomstream.net`, а автор курса вставил `src="https://play.boomstream.com/<code>/config.jsonp"` — фильтр заменит хост и в HTML страницы окажется `src="https://play.boomstream.net/<code>/config.jsonp?id_recovery=..."`.

Так же используется `hostname` и в других местах:

1. **Регулярка** (`boomstream.php:27-39`) — `hostname` добавляется в whitelist допустимых хостов плеера, чтобы фильтр распознавал в `src` ваш кастомный домен наряду с `play.boomstream.{com,net,dev,org}`.
2. **API-вызовы** (`addbuyer` / `info` / `updatebuyer`) — серверные запросы PPV идут на `$this->hostname`.
3. **SDK-скрипт adaptive-плеера** — URL вида `https://<identifier>/assets/...` (например, `biframesdk.js`) переписывается на `https://<hostname>/assets/...`. Делается per-match внутри `_process` после резолва `$identifier` и `$this->hostname`.

Если `hostname` не задан в настройках, плагин подставляет хост, найденный в самом `src` (`$identifier` из регулярки), — тогда подмена `src` фактически становится no-op, а SDK-скрипт пропускается (потому что `$identifier === $this->hostname`).

## Алгоритм работы

Для каждого найденного `code` (`boomstream.php:_process`):

1. **Формируется hash** в виде `<host>|<userId>|<code>`, где `<host>` — `$_SERVER['HTTP_HOST']` (а в CLI/тесте — хост из найденного URL).
2. **`POST /api/ppv/addbuyer`** — регистрирует/обновляет покупателя:
   - `apikey`, `code` (subscription), `media` (code), `email` (`$USER->email`), `notification=0`, `hash`.
3. Если ответ `Status == "Success"`:
   - Считается `isAccessExpired` по `AccessExpirationDate` (зона `Europe/Moscow`).
   - Если `Recovery == 0` **или** доступ истёк — дёргается **`/api/ppv/info`**, чтобы получить активную подписку пользователя.
   - Если активация найдена и `Recovery == 0` → **`/api/ppv/updatebuyer&activation=...`** — привязывает покупателя к активации.
   - Если доступ истёк → **`/api/ppv/updatebuyer&access_expire=...`** — продлевает доступ либо до `AccessExpirationDate` из подписки, либо до `now + Period дней`.
4. После успешной обработки в `src` подставляется `id_recovery=<hash>` — браузер запрашивает плеер уже с этим параметром, и Boomstream отдаёт видео авторизованному пользователю.
5. Если `addbuyer` вернул не `Success` — URL **не модифицируется**, в debug пишется `Result failed: <Message>`.

## Сетевой слой и ретраи

`_curlBoomstream` (`boomstream.php:55`) — обёртка над `_curlBoomstreamOnce` (`boomstream.php:65`). Если первый вызов вернул `false` (curl-ошибка, пустой/невалидный JSON, исключение) — делается **один повтор**, в debug добавляется `Retrying api/ppv call: ...`. Логически «не Success» (например, `Application not found`) ретраем не считается — повторять смысла нет.

## Debug-режим

При `debug = Yes` к выходу фильтра добавляется HTML-комментарий вида:

```html
<!--Boomstream filter is applied
Start boomstream plugin
hostname: play.boomstream.net          (или: (not set, will fall back to host from src))
key: ...
subscription: ...

Try working with code: ...
Try working with url: ...
Try working with host: play.boomstream.com
API calls to host: play.boomstream.net
Rewrote SDK asset URLs (1) on host play.boomstream.com to: play.boomstream.net
Try to call api/ppv: https://.../addbuyer?...
Result: ...                            (только при Status==Success)
Result failed: <Message>               (только при не-Success)
Used url: ...?id_recovery=...
-->
```

В продакшене (`debug = No`) комментарий не выводится.

## Локальный тест

`test.php` подменяет `moodle_text_filter` и `get_config`, чтобы плагин можно было запустить без Moodle:

```bash
cd boomstream && php test.php
```

В тесте фигурирует фиктивный `$USER` (id=1, email=`obidnov@gmail.com`) и тестовый набор key/subscription. Реальный API ответит `Application not found` — это нормально, просто проверка, что фильтр доходит до сетевого вызова и корректно обрабатывает результат.

## Файлы

| Файл | Назначение |
|---|---|
| `filter.php` | Точка входа Moodle: класс `filter_boomstream extends moodle_text_filter`, делегирует в `boomstream`. |
| `boomstream.php` | Вся логика: регулярка, API-вызовы, ретрай, подстановка `id_recovery`. |
| `settings.php` | Поля админки (hostname / key / subscription / debug). |
| `lang/en/filter_boomstream.php` | Тексты на английском. |
| `version.php` | Версия плагина (Moodle compat). |
| `test.php` | CLI-харнес для локальной проверки фильтра. |
