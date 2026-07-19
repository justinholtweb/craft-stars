# Stars — Craft CMS 5 Plugin

## Project Overview

Stars is a review and testimonial management plugin for Craft CMS 5. It provides star ratings, four-state moderation, pros/cons, admin responses, spam protection, and schema.org JSON-LD markup. Distributed as `justinholtweb/craft-stars` on the Craft Plugin Store.

## Tech Stack

- **PHP 8.2+** with strict types where applicable
- **Craft CMS 5** — uses the native element system, element editor, CP asset bundles, and Yii2 components
- **Yii2** — underlying framework (validation, events, ActiveRecord, controllers)
- **Twig** — CP and email templates

## Architecture

### Namespace & Package

- Namespace: `justinholtweb\stars`
- Composer package: `justinholtweb/craft-stars`
- Plugin handle: `stars`

### Custom Element Types

Reviews (`elements\Review`) and Comments (`elements\Comment`) are first-class
Craft elements sharing an abstract base (`elements\base\ModeratedElement` +
`elements\base\ModeratedQuery`) that provides the four-state status system,
entry attachment, captured submission metadata, and a generic custom-table
write. Concrete types declare their status column, table, and column map. They use:
- Native CP element index with sources sidebar, table columns, sort options, and search
- Craft 5 native element editor (no custom edit templates) via `metaFieldsHtml()` + `metadata()`
- Standard element queries via `ReviewQuery` / `CommentQuery`
- Shared, status-attribute-driven bulk actions (`Approve`, `Reject`, `MarkAsSpam`, `BlockAuthor`)

Comments support threaded replies (`parentId`, capped by `maxCommentDepth`,
enforced in `Comment::beforeSave()`); `CommentService::getCommentTree()` builds
the nested tree from one flat query.

### Database

- `{{%stars_reviews}}` — `id` FK→elements (CASCADE), `entryId` FK→elements
  (SET NULL), custom `reviewStatus` column (pending/approved/rejected/spam).
- `{{%stars_comments}}` — same lifecycle, plus `parentId` (self-FK, SET NULL),
  `authorUserId`, and `commentStatus`.
- `{{%stars_blocklist}}` — `type` (email/ip/user) + `value`, unique per pair.

Fresh installs build all tables in `migrations/Install.php`; existing installs
get incremental `mYYMMDD_*` migrations. `Plugin::$schemaVersion` tracks the DB
schema independently of the release version.

### Services (registered as plugin components)

- `Plugin::$reviews` → `ReviewService` — CRUD, aggregations (average, count, distribution)
- `Plugin::$comments` → `CommentService` — queries, threaded tree, moderation helpers
- `Plugin::$spam` → `SpamService` — honeypot, captcha (via provider abstraction), rate limiting, submission time
- `Plugin::$block` → `BlockService` — blocklist by email/IP/user
- `Plugin::$schema` → `SchemaService` — JSON-LD generation
- `Plugin::$notifications` → `NotificationService` — email via Craft Mailer

Captcha uses a provider abstraction (`services\captcha\*`): reCAPTCHA v2/v3,
hCaptcha, Turnstile, selected by `CaptchaProviderFactory` from settings.

### Frontend

- Controller actions: `stars/reviews/save` and `stars/comments/save` (anonymous POST allowed)
- Twig variables: `craft.reviews` → `StarsVariable`, `craft.comments` → `CommentsVariable` (registered via `CraftVariable::EVENT_INIT`)
- Supports both traditional form POST (redirect) and JSON API responses

## File Structure

```
src/
├── Plugin.php                  # Main plugin class — registers everything
├── models/Settings.php         # 17 plugin settings with validation
├── migrations/Install.php      # Table creation/teardown
├── records/ReviewRecord.php    # Yii2 ActiveRecord (used sparingly)
├── elements/
│   ├── Review.php              # Element type — statuses, sources, table attrs, editor, afterSave
│   ├── db/ReviewQuery.php      # Element query with custom params
│   └── actions/                # Bulk actions: Approve, Reject, MarkAsSpam
├── services/                   # ReviewService, SpamService, SchemaService, NotificationService
├── controllers/ReviewsController.php  # Frontend submission handler
├── twig/StarsVariable.php      # craft.reviews Twig API
├── templates/                  # CP layouts, element index, settings, email
├── web/assets/cp/              # CpAsset bundle with CSS/JS
└── translations/en/stars.php   # English translations
```

## Key Patterns

### Element Lifecycle
- `Review::afterSave()` handles INSERT/UPDATE to `stars_reviews` table
- `Review::setAttributes()` handles `entryId` from element select (posted as array)
- `Review::safeAttributes()` whitelists all custom attributes

### Status System
Four custom statuses with colors: pending (orange), approved (green), rejected (red), spam (light). The `reviewStatus` column maps directly to element status via `ReviewQuery::statusCondition()`.

### Permissions
Hierarchical: `stars:viewReviews` is the parent, with nested `manageReviews`, `moderateReviews`, `respondToReviews`, `deleteReviews`. Admin users bypass all checks.

### Settings
Settings are managed via Craft's built-in `plugins/save-plugin-settings` action. The settings page is rendered by `Plugin::settingsHtml()`. Config file overrides are supported via `config/stars.php`.

## Development Commands

```bash
# Install plugin in a Craft project (symlink for local dev)
composer config repositories.stars path /path/to/craft-stars
composer require justinholtweb/craft-stars:@dev

# Install/uninstall plugin
php craft plugin/install stars
php craft plugin/uninstall stars

# Clear Craft caches after changes
php craft clear-caches/all
```

## Coding Conventions

- Follow Craft CMS and Yii2 conventions
- Use `Craft::t('stars', '...')` for all user-facing strings
- Use `Craft::$app->getElements()->saveElement()` for saving elements (never direct DB inserts for element data)
- Use `Cp::` helpers for generating CP form HTML in `metaFieldsHtml()`
- Prefix private methods with underscore: `_registerElementTypes()`
- Keep controllers thin — business logic goes in services
- Use union types for entry params: `Entry|int $entry`

## Testing Checklist

When making changes, verify:
1. Plugin installs/uninstalls cleanly (table created/dropped)
2. Element index loads with correct sources, badge counts, table columns, star rendering
3. Edit page works with sidebar fields, validation, save
4. Bulk actions work (approve, reject, spam)
5. Frontend form POSTs to `actions/stars/reviews/save` successfully
6. Spam checks work (honeypot, rate limit, submission time)
7. `craft.reviews.forEntry()`, `.averageRating()`, `.count()`, `.distribution()`, `.schemaOrg()` return correct data
8. JSON-LD validates in Google Rich Results Test
9. Email notifications fire on new review
10. Permissions are enforced per level
11. Edge cases: deleted entry (review survives with null entryId), zero reviews (no divide-by-zero)
