# Implementation Plan — Comments for the Stars plugin

Adds a first-class **Comment** system alongside Reviews: moderation, threaded
replies, a blocklist, login gating, and pluggable captcha providers. Built on a
shared element base so Reviews and Comments share moderation, author capture,
and spam protection instead of duplicating it.

Target: ships in Stars **5.0.0** (schema change → `Plugin::$schemaVersion` bump).

---

## 1. Architecture decision (locked)

**Shared abstract base, two concrete element types.**

Extract the moderation + author + entry-attachment + spam-metadata behavior out
of `Review` into an abstract base, then have `Review` and `Comment` extend it.
This avoids duplicating `Review`'s logic and is a good moment to clean up its
hand-written `afterSave()` INSERT/UPDATE.

```
elements/
  base/
    ModeratedElement.php     # abstract: statuses, author, entry, ip/ua/url, canX()
    ModeratedQuery.php       # abstract: entryId/status/author/ip params + status map
  Review.php                 # extends ModeratedElement (rating, pros/cons, adminResponse)
  Comment.php                # extends ModeratedElement (body, parentId/threading)
  db/
    ReviewQuery.php          # extends ModeratedQuery
    CommentQuery.php         # extends ModeratedQuery (parentId, topLevel, thread tree)
```

**What moves into `ModeratedElement`:**
- Four-state status system: `hasStatuses()`, `statuses()`, `getStatus()`
  (currently `Review.php:60-78`)
- `entryId` + `getEntry()`/`setEntry()` + the array-unwrap in `setAttributes()`
  (`Review.php:214-284`)
- Author fields generalized: `reviewerName`/`reviewerEmail` →
  `authorName`/`authorEmail` (kept as aliases on `Review` for BC)
- Spam metadata: `ipAddress`, `userAgent`, `submissionUrl`
- `canView/canSave/canDelete` scaffolding driven by a per-type permission prefix

**How the per-type table write is handled:** each concrete type declares
`customTableName(): string` and `customAttributes(): array` (column → value map);
a shared `saveCustomAttributes(bool $isNew)` in the base runs the INSERT/UPDATE.
This replaces the two duplicated blocks in `Review::afterSave()`
(`Review.php:459-504`) with one generic path.

> **Safety net:** the Review refactor is covered by the 27 existing tests
> (`ReviewServiceTest`, `SchemaServiceTest`, `SpamServiceTest`, install). Keep
> them green through Phase 0 — behavior must not change.

---

## 2. Data model & migrations

New migration `migrations/mYYMMDD_HHMMSS_add_comments.php` (runs on update for
existing installs). Bump `Plugin::$schemaVersion` to `2.0.0`.

### `{{%stars_comments}}`
| Column | Type | Notes |
|---|---|---|
| `id` | pk, FK→elements.id CASCADE | element lifecycle |
| `entryId` | int, FK→elements.id SET NULL | comment survives entry deletion |
| `parentId` | int, FK→stars_comments.id CASCADE | null = top-level; **indexed** |
| `commentStatus` | string(20), default `pending` | pending/approved/rejected/spam |
| `authorName` | string(255) notNull | |
| `authorEmail` | string(255) | |
| `authorUserId` | int, FK→elements.id SET NULL | set when submitted logged-in |
| `body` | text notNull | |
| `ipAddress` / `userAgent` / `submissionUrl` | | reuse Review's capture + privacy toggles |
| `dateCreated`/`dateUpdated`/`uid` | | standard |

Indexes: `entryId`, `parentId`, `commentStatus`, `[entryId, commentStatus]`.

### `{{%stars_blocklist}}`
| Column | Type | Notes |
|---|---|---|
| `id` | pk | |
| `type` | string(10) | `email` \| `ip` \| `user` |
| `value` | string(255) | normalized (lowercased email, etc.) |
| `reason` | string(255) | optional moderator note |
| `createdBy` | int, FK→elements.id SET NULL | which admin blocked |
| `dateCreated`/`uid` | | |

Unique index on `[type, value]`.

---

## 3. Services

### `SpamService` → captcha provider abstraction
Refactor the hard-coded reCAPTCHA v3 (`SpamService.php:51-78`) into a provider
interface. Honeypot / submission-time / rate-limit stay in `SpamService` (they're
already provider-agnostic).

```
services/
  SpamService.php                 # orchestrates: honeypot, time, rate-limit, captcha
  captcha/
    CaptchaProviderInterface.php  # verify(): bool ; frontendHtml(): string
    RecaptchaV3Provider.php       # existing logic, score threshold
    RecaptchaV2Provider.php       # checkbox
    HcaptchaProvider.php          # hcaptcha.com/siteverify
    TurnstileProvider.php         # Cloudflare challenges.cloudflare.com/turnstile/v0/siteverify
    CaptchaProviderFactory.php    # from settings.captchaProvider
```

All four verify a token server-side against a provider URL — same shape, just
different endpoints/params. Keys resolved via `App::parseEnv()` (as today).

**Generalize rate limiting:** `checkRateLimit()` currently queries
`{{%stars_reviews}}` directly (`SpamService.php:100-109`). Parameterize it by
context (`table` + `entryId`) so comments rate-limit against `stars_comments`.
Signature: `checkRateLimit(string $context = 'reviews'): bool`.

### `CommentService` (mirror of `ReviewService`)
- `getCommentsForEntry(Entry|int, bool $threaded = true): array`
- `getCommentThread(Comment|int): array` — parent + descendants, depth-capped
- `getCommentCount(Entry|int): int`
- `approve/reject/markAsSpam/saveComment` — reuse the moderation helpers
- `getReplies(Comment|int): array`

### `BlockService`
- `isBlocked(?string $email, ?string $ip, ?int $userId): bool`
- `block(string $type, string $value, ?string $reason)` / `unblock(...)`
- Enforced in **both** submission controllers before saving.

### `NotificationService` (extend)
- `sendNewCommentNotification(Comment)` — to moderators
- `sendReplyNotification(Comment $reply)` — to the parent comment's author
- New templates `email/new-comment.(twig|txt)`, `email/comment-reply.(twig|txt)`

---

## 4. Threaded replies

- `CommentQuery::parentId(?int)`, `::topLevel()`.
- Depth **capped** (setting `maxCommentDepth`, default `2`). Enforced on save:
  a reply whose parent is already at max depth attaches to the parent's level.
- Tree building in `CommentService::getCommentsForEntry(threaded: true)`: one flat
  query ordered by `dateCreated`, assembled into a nested array in PHP (avoids
  N+1). `parentId` is indexed for the flat fetch.
- Frontend renders recursively via a Twig macro (`_comments/thread.twig`).

---

## 5. Login gating

Extends the existing `requireLogin`/`allowAnonymous` pattern
(`Settings.php:11-12`, checked in `ReviewsController`).

New settings: `commentsRequireLogin`, `commentsAllowAnonymous`. When logged-in:
`CommentsController` auto-fills `authorUserId`, `authorName`, `authorEmail` from
`Craft::$app->getUser()->getIdentity()` and ignores posted author fields.

---

## 6. Frontend

- `controllers/CommentsController.php` — mirrors `ReviewsController`
  (anonymous POST allowed, honeypot/time fields, JSON + redirect responses),
  plus blocklist + login-gate checks.
- `twig/StarsVariable.php` gains `comments*` methods, **or** a dedicated
  `craft.comments` variable (recommend the latter for clarity).
- Templates: comment form partial + recursive thread macro; AJAX pattern reused
  from the README's review example.

---

## 7. Settings, permissions, CP

- **Settings** (`Settings.php` + `settings/_index.twig`): new **Comments**
  section (enable, require login, allow anonymous, max depth, notifications) and
  a reworked **Captcha** section (provider dropdown + per-provider keys) that
  supersedes the reCAPTCHA-only fields. Old `recaptcha*` keys map to the new
  `RecaptchaV3` provider for BC.
- **Permissions** (`Plugin::_registerPermissions()`): add a comments tier
  mirroring reviews — `stars:viewComments` › `manageComments`, `moderateComments`,
  `replyToComments`, `deleteComments` — plus `stars:manageBlocklist`.
- **CP nav**: add `Comments` and `Blocklist` sub-items to `getCpNavItem()`.
  Comments reuse the element index (sources/bulk actions) for free.
- **Bulk action** `BlockAuthor` on both indexes → adds email + IP to blocklist.

---

## 8. Schema.org (optional, low priority)

Reviews already emit `Review`/`AggregateRating`. Comments can optionally emit
schema.org `Comment` nodes on the entry. Defer unless there's SEO demand.

---

## 9. Testing (harness already in place)

New suites, same DDEV + Codeception setup:
- `CommentServiceTest` — threading assembly, counts, moderation transitions
- `ThreadingTest` — depth cap, parent/child integrity, orphan handling on delete
- `BlockServiceTest` — email/ip/user matching, normalization
- `CaptchaProviderTest` — each provider's verify path with a stubbed Guzzle client
- `LoginGatingTest` + a functional `CommentsController` submit test
- Regression: existing Review tests stay green through the Phase 0 refactor.

---

## 10. Delivery phases (each independently shippable & tested)

| Phase | Scope | Effort |
|---|---|---|
| **0** | Extract `ModeratedElement`/`ModeratedQuery` from `Review`; no behavior change | S–M |
| **1** | `Comment` element + tables + migration + moderation + CP index | M |
| **2** | Frontend submission + login gating + spam reuse (rate-limit generalization) | M |
| **3** | Threaded replies + reply notifications | S–M |
| **4** | Blocklist subsystem (service, table, CP UI, BlockAuthor action) | M |
| **5** | Captcha provider abstraction (reCAPTCHA v2/v3, hCaptcha, Turnstile) — also upgrades Reviews | M |
| **6** | Settings/CP polish, schema.org (optional), docs, translations | S–M |

Ballpark total: **~1.5–2 weeks** for someone fluent in Craft. Phases 0–3 deliver
a usable comments feature; 4–5 are the differentiators; 6 is polish.

---

## 11. Risks & mitigations

- **Review refactor regressions** → covered by existing tests; Phase 0 must keep
  them green before any new code.
- **Rate-limit generalization** touches shared `SpamService` used by Reviews →
  keep the default context `'reviews'` so existing behavior is unchanged.
- **Captcha secrets** → resolved via `App::parseEnv()`, never stored plaintext in
  project config; document env setup per provider.
- **Threading performance** → single flat query + PHP assembly, `parentId`
  indexed; depth cap bounds recursion.
- **BC on settings** → migrate `recaptcha*` → provider config; alias
  `reviewerName`/`reviewerEmail` on `Review`.
- **Scope/positioning** → this shifts Stars from "reviews" to "reviews + community";
  a product call, not just engineering.

---

## 12. Open questions

1. Comments on **entries only** (like reviews today) or any element type?
2. Separate `craft.comments` Twig variable, or fold into `craft.reviews`?
3. Do Reviews and Comments share one CP moderation section, or stay separate?
   (Plan assumes separate sections, shared element-index machinery.)
4. Ship captcha refactor (Phase 5) as part of this, or pull it forward as a
   standalone 5.x improvement to Reviews first?
