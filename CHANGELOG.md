# Changelog

## 5.0.2 - 2026-08-04

### Fixed
- Every setting on the settings page rendered a bare warning icon with no text
  next to it. The "overridden by config" macro returned the whitespace between
  its own tags when a setting *wasn't* overridden, and Twig treats that
  non-empty string as a real warning. Only genuinely overridden settings show a
  warning now.

### Changed
- Reworked the Review and Comment editor screens. Every field used to be packed
  into the sidebar, leaving the main body empty. The submission's own content —
  rating, name, email, review/comment text, pros, cons, and admin response — now
  renders in the body via an in-code field layout, laid out two-up where it
  reads better. The sidebar keeps just what's *about* the submission: its
  moderation status and the entry it belongs to, above the existing metadata.
- Validation errors now appear inline on the field that caused them, instead of
  only in the error banner.
- A reply's parent comment is now shown in the Comment sidebar as a "Reply To"
  link.

## 5.0.1 - 2026-08-04

### Fixed
- The Reviews and Comments CP sections rendered their element index as escaped
  HTML instead of a working index. Both templates now extend Craft's
  `_layouts/elementindex` rather than echoing the deprecated
  `_elements/indexcontainer` partial through `renderTemplate()`.
- Frontend review and comment submissions returned a 500 error whenever
  notifications were enabled. The element saved, but rendering the notification
  email threw, because the plugin's email templates live in a control-panel-only
  template root and were being rendered in site template mode. They're now
  rendered in CP template mode, and a template failure is logged instead of
  breaking the submission.
- Review and Comment index columns showed raw values — the entry ID instead of
  the entry, the rating number instead of stars, and the lowercase status key
  instead of a labelled status. The custom column rendering was declared as
  `tableAttributeHtml()`, which Craft 5 renamed to `attributeHtml()`, so it was
  never called.
- Fixed the frontend form and review display examples in the README, which used
  a `repeat` filter that doesn't exist in Twig or Craft.

### Known issues
- Reply notifications only fire when a reply is created with `approved` status,
  so with the default `defaultStatus` of `pending` they never send. Approving a
  reply later does not trigger them.

## 5.0.0 - 2026-07-19

First public release on the Craft Plugin Store, for Craft CMS 5. Builds on the
initial review system with a full comments system, a submitter blocklist,
pluggable captcha, privacy controls, and an automated test suite.

### Added
- **Comments** — a threaded comment system for entries, as a first-class element
  alongside reviews:
  - Four-state moderation, CP element index, and bulk actions (shared with
    reviews via a `ModeratedElement` base).
  - Threaded replies with a configurable maximum depth (`maxCommentDepth`);
    over-deep replies attach at the deepest allowed level.
  - Frontend submission (`actions/stars/comments/save`), optional login gating
    (`commentsRequireLogin` / `commentsAllowAnonymous`), and auto-filled author
    details for logged-in users.
  - `craft.comments` Twig API (`tree`, `forEntry`, `topLevel`, `replies`,
    `count`) and a bundled recursive `stars/_comments/thread.twig` macro.
  - Email notifications to moderators, plus reply notifications to the parent
    comment's author.
  - Comment permissions: view / manage / moderate / reply / delete.
- **Blocklist** — block submitters by email, IP, or user id. Enforced on both
  review and comment submission, with a CP management section, a "Block Author"
  bulk action, and a `stars:manageBlocklist` permission.
- **Pluggable captcha** — reCAPTCHA v3, reCAPTCHA v2, hCaptcha, and Cloudflare
  Turnstile, selectable per site. Applies to reviews and comments alike.
  `craft.reviews.captcha()` / `craft.comments.captcha()` expose the provider and
  site key for the frontend widget.
- **Privacy controls** — `captureIpAddress`, `captureUserAgent`, and
  `captureReferrer` settings to independently disable storing each piece of
  submission metadata (GDPR-style data minimization). All default to on.
- Reviewer/commenter email addresses are masked in error logs (e.g. `a***@example.com`).
- Automated test suite (Codeception + Craft's test framework), runnable via DDEV.

### Fixed
- `craft.reviews.averageRating()` — and the `ratingValue` in the schema.org
  JSON-LD output — returned an incorrect value instead of the true average.
- The per-IP rate limiter flagged legitimate first-time submissions as spam,
  rejecting valid reviews whenever rate limiting was enabled (on by default).

### Changed
- Spam rate limiting is context-aware (reviews vs. comments count against their
  own tables).
- The legacy `enableRecaptcha` / `recaptcha*` settings are superseded by
  `captchaProvider` / `captcha*` but are still honored (mapped to reCAPTCHA v3).
- New plugin icon.
- Licensed under the standard Craft License.

## 1.0.0 - 2026-02-15

### Added
- Initial release
- Review element type with star ratings (1-5 configurable up to 10)
- Four-state moderation: pending, approved, rejected, spam
- Pros/cons fields
- Admin response support
- Spam protection: honeypot, reCAPTCHA v3, rate limiting, submission time check
- Schema.org JSON-LD output (Review + AggregateRating)
- Email notifications on new submissions
- Twig variable `craft.reviews` for frontend queries
- User permissions for view, manage, moderate, respond, delete
- CP element index with bulk actions (approve, reject, mark as spam)
