# Changelog

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
