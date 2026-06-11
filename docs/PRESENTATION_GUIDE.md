# osu! Personal Tracker — конспект для презентации

> Стек: **Laravel 13 / PHP 8.3**, Blade-шаблоны, Tailwind, Laravel Breeze (auth-скелет),
> Laravel Socialite (OAuth), SQLite/MySQL. Аутентификация — двойная: классическая
> email+пароль (Breeze) **и** вход через osu! OAuth2.

## 1. Что это и зачем (одна фраза для слайда)

Веб-приложение, которое забирает твои недавние скоры из osu! API, хранит их и строит
персональную статистику. Главная фича — **«Almost FC»**: список карт, где ты был ближе
всего к фулкомбо (меньше всего миссов), отсортированный по возрастанию числа миссов.

---

## 2. Архитектура по слоям (как это работает)

```
Браузер
  │  HTTP-запрос
  ▼
routes/web.php  ──►  Middleware (auth / admin / CheckBlocked)
  │                       │
  ▼                       ▼
Controllers  ──►  Services (OsuSyncService)  ──►  osu! API v2
  │                       │
  ▼                       ▼
Models (Eloquent)  ──►  База данных (миграции)
  │
  ▼
Blade-шаблоны (resources/views) ──► HTML пользователю
```

Главная мысль для защиты: **контроллеры тонкие**, вся работа с внешним API вынесена
в сервис (`OsuSyncService`), доступ к данным — через модели Eloquent, права доступа —
через middleware и Policy. Это и есть «разделение ответственности».

---

## 3. Разбор по фичам (что → какой файл → ключевые строки)

### Фича A. Вход через osu! (OAuth2)
**Проблема:** залогинить пользователя его аккаунтом osu! без пароля и получить токен для API.

- `app/Services/OsuSocialiteProvider.php` — **кастомный драйвер OAuth для osu!**
  (готового пакета нет, написан вручную поверх Socialite).
  - `$scopes = ['identify','public']` (стр. 29) — какие права просим у osu!.
  - `getAuthUrl()` (стр. 45) — куда отправить юзера авторизоваться.
  - `getTokenUrl()` (стр. 58) — куда обменять код на токен.
  - `getUserByToken()` (стр. 70) — дёргает `GET /api/v2/me`, получает профиль.
  - `mapUserToObject()` (стр. 91) — кладёт id/username/avatar в объект Socialite.
- `app/Providers/AppServiceProvider.php`
  - `boot()` → `Socialite::extend('osu', …)` (стр. 56) — **регистрирует** наш драйвер.
  - тут же `Gate::policy(Comment::class, CommentPolicy::class)` (стр. 54) — подключение политики.
- `app/Http/Controllers/Auth/OsuAuthController.php` — **две ноги OAuth**:
  - `redirect()` (стр. 32) — отправляет браузер на osu!.
  - `callback()` (стр. 49) — возвращается с кодом; **ключевое** —
    `User::updateOrCreate(['osu_id' => …], [...токены...])` (стр. 78): ищем юзера по `osu_id`,
    создаём или обновляем токены. Проверка `is_blocked` (стр. 104), затем `Auth::login()` +
    `session()->regenerate()` (защита от session fixation, стр. 114-115).
- `config/services.php` (стр. 46) — ключи `client_id/secret/redirect` из `.env`.
- `routes/web.php` стр. 32-33 — маршруты `/osu/redirect` и `/osu/callback`.

### Фича B. Синхронизация скоров из osu! API ★ (ядро проекта)
**Проблема:** забрать недавние плеи игрока и сохранить новые, не дублируя.

- `app/Services/OsuSyncService.php` — **весь обмен с osu! API**:
  - `syncUserScores()` (стр. 30) — `GET /users/{id}/scores/recent?include_fails=1`
    (стр. 40-43). Обработка ошибок API: 401 «токен истёк» (стр. 46), 429 «rate limit»
    (стр. 52), общая ошибка (стр. 58). Перебор скоров → `storeScoreIfNew()`.
    В конце пишет `last_sync_at` (стр. 84).
  - `storeScoreIfNew()` (стр. 97) — **самый важный метод**:
    - `Beatmap::updateOrCreate()` (стр. 107) — создаёт/обновляет карту.
      **Тут был баг:** `title/artist` лежат в `beatmapset`, который является *соседом*
      `beatmap`, а не вложен в него → бралось из `$scoreData['beatmapset']`.
    - дедупликация: проверка `user+beatmap+played_at+score` (стр. 123-127).
    - `miss_count` (стр. 134) — критичен для Almost FC.
- `app/Http/Controllers/SyncController.php` — кнопка «Sync»:
  - `manualSync()` (стр. 26) — проверяет, что у юзера есть `osu_id` (стр. 31),
    зовёт сервис, возвращает flash-сообщение.
- `routes/web.php` стр. 53 — `POST /sync-scores`.

### Фича C. Дашборд и «Almost FC» ★ (фича, которую показываешь на скрине)
**Проблема:** посчитать персональную статистику и список «почти фулкомбо».

- `app/Http/Controllers/DashboardController.php`
  - `index()` (стр. 19) — агрегаты: всего плеев, уникальных карт, лучшая точность,
    pass rate (стр. 24-28).
  - **Almost FC** — сырой SQL (стр. 36-59): группировка по карте, `MIN(miss_count)` —
    лучший результат, коррелированный подзапрос для `best_score`,
    `ORDER BY best_miss_count ASC` — меньше миссов = ближе к FC. `LIMIT 20`.
  - последние 10 скоров (стр. 62) с `with('beatmap')` (защита от N+1).
- `app/Models/Score.php`
  - scope `scopeForUser()` (стр. 52) и `scopeAlmostFc()` (стр. 61, `miss_count > 0`).
- `resources/views/dashboard.blade.php` — сам экран.

### Фича D. Каталог карт: поиск и фильтры
**Проблема:** публичный список карт с поиском по названию/артисту и фильтрами.

- `app/Http/Controllers/BeatmapController.php`
  - `index()` (стр. 24) — динамический query builder:
    поиск LIKE по title/artist (стр. 30), фильтр по тегу через `whereHas` (стр. 39),
    фильтр по коллекции (стр. 44), `paginate(20)->withQueryString()` (стр. 49,
    сохраняет фильтры в пагинации).
  - `show()` (стр. 68) — деталь карты, `with(['tags','comments.user'])` (стр. 71,
    eager loading), скоры только текущего юзера (стр. 74).

### Фича E. Коллекции (плейлисты карт) — CRUD + many-to-many
**Проблема:** пользователь группирует карты в свои приватные подборки.

- `app/Http/Controllers/CollectionController.php` — полный CRUD.
  - **Ключевой приём безопасности:** каждый запрос скоупится
    `Collection::where('user_id', auth()->id())->findOrFail($id)` — чужую коллекцию
    нельзя открыть/изменить (вернёт 404). См. `edit()` стр. 77, `update()` 92, `destroy()` 114.
  - `addBeatmap()` (стр. 146) — `syncWithoutDetaching()` (добавить, не стирая остальные).
  - `removeBeatmap()` (стр. 167) — `detach()` (убрать из пивота, карту не удаляет).
- `routes/web.php` стр. 71-76 — `Route::resource` (кроме show) + кастомные пивот-маршруты.

### Фича F. Теги (глобальные ярлыки на карты)
**Проблема:** любой может тегать карты (#dt, #jumps), удаляют только админы.

- `app/Http/Controllers/TagController.php`
  - `attach()` (стр. 25) — **парсит `#tag` регуляркой** `preg_match_all('/#(\w+)/'…)`
    (стр. 35), `Tag::firstOrCreate()` (стр. 45), `syncWithoutDetaching()` (стр. 50).
  - `detach()` / `destroy()` — `abort_unless(...isAdmin(), 403)` (стр. 65, 82) — только админ.

### Фича G. Комментарии — CRUD + авторизация через Policy
**Проблема:** комментить может любой, но редактировать/удалять — автор или админ.

- `app/Http/Controllers/CommentController.php` — store/edit/update/destroy.
  - `$this->authorize('update', $comment)` (стр. 54, 72) и `'delete'` (стр. 97).
- `app/Policies/CommentPolicy.php`
  - `update()` (стр. 35) и `delete()` (стр. 48): `owner_id === user->id || isAdmin()`.

### Фича H. Админка и модерация
**Проблема:** управление юзерами и удаление контента.

- `app/Http/Controllers/AdminController.php`
  - `users()` (стр. 30), `block()` (стр. 46, нельзя забанить себя — `abort_if` стр. 51),
    `unblock()` (стр. 68), `deleteBeatmap/Comment/Tag` (стр. 86-118).
- `app/Http/Middleware/AdminMiddleware.php`
  - `handle()` (стр. 23) — 403, если не админ.
- `app/Http/Middleware/CheckBlocked.php`
  - `handle()` (стр. 32) — если юзера забанили во время сессии, при следующем запросе
    `Auth::logout()` + `invalidate()` + `regenerateToken()` (стр. 43-45).
- `bootstrap/app.php`
  - регистрация `CheckBlocked` в группу `web` (стр. 21) и алиаса `admin` (стр. 31).
- `routes/web.php` стр. 84-94 — группа `middleware(['auth','admin'])->prefix('admin')`.

---

## 4. Модели и связи (для слайда «схема данных»)

| Модель | Файл | Связи |
|--------|------|-------|
| **User** | `app/Models/User.php` | hasMany Score, Collection, Comment; `isAdmin()`, `isBlocked()`, `syncNeeded()` (10 мин, стр. 88) |
| **Beatmap** | `app/Models/Beatmap.php` | hasMany Score/Comment; belongsToMany Collection, Tag |
| **Score** | `app/Models/Score.php` | belongsTo User, Beatmap; scopes `forUser`, `almostFc` |
| **Collection** | `app/Models/Collection.php` | belongsTo User; belongsToMany Beatmap |
| **Tag** | `app/Models/Tag.php` | belongsToMany Beatmap |
| **Comment** | `app/Models/Comment.php` | belongsTo User, Beatmap |

Миграции — `database/migrations/2026_06_11_*`. Важное:
- `scores`: `unique(['user_id','beatmap_id','played_at','score'])` — антидубли;
  `miss_count` — для Almost FC. FK с `onDelete('cascade')`.
- Seeder `database/seeders/DatabaseSeeder.php` создаёт админа (`admin@osu.test`),
  демо-игрока (`player@osu.test`), 20 карт и скоры с миссами для демо Almost FC.

---

## 5. Шпаргалка для навигации (держать перед глазами)

```
ВОПРОС НА ЗАЩИТЕ                         → СМОТРИ СЮДА
──────────────────────────────────────────────────────────────────
«Где входная точка / маршруты?»          → routes/web.php
«Как работает вход через osu!?»          → OsuAuthController + OsuSocialiteProvider
«Как тянутся скоры из API?»              → app/Services/OsuSyncService.php
«Где логика Almost FC?»                  → DashboardController::index (SQL стр. 36)
«Где поиск/фильтры карт?»                → BeatmapController::index
«Как устроены права доступа?»            → AdminMiddleware, CheckBlocked, CommentPolicy
«Где CRUD коллекций?»                    → CollectionController
«Где модель данных / таблицы?»           → app/Models/*  +  database/migrations/*
«Где регистрируется middleware?»         → bootstrap/app.php
«Где HTML / интерфейс?»                  → resources/views/*.blade.php
```

**Поток одного запроса (рассказать за 30 сек):**
`routes/web.php` → middleware (auth/admin/CheckBlocked) → Controller →
(при синке) OsuSyncService → osu! API → Model/Eloquent → БД → Blade-вид → HTML.

**3 «фишки», которые стоит подчеркнуть:**
1. Кастомный OAuth-драйвер osu! написан вручную (нет готового пакета).
2. Almost FC — нетривиальный SQL с агрегацией и коррелированным подзапросом.
3. Многослойная защита: middleware (admin/blocked) + Policy (комменты) + скоуп по `user_id` (коллекции).
