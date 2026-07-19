# Changelog

## 5.0.0 - 2026-07-19

> [!NOTE]
> Stars’ version now tracks the major version of Craft CMS it targets. 5.0.0 is
> the first public release on the Craft Plugin Store, for Craft CMS 5.

### Added
- Privacy controls: `captureIpAddress`, `captureUserAgent`, and `captureReferrer`
  settings to independently disable storing each piece of submission metadata
  (GDPR-style data minimization). All default to on.
- Reviewer email addresses are now masked in error logs (e.g. `a***@example.com`).
- Automated test suite (Codeception + Craft's test framework), runnable via DDEV.

### Fixed
- `craft.reviews.averageRating()` — and the `ratingValue` in the schema.org
  JSON-LD output — returned an incorrect value instead of the true average.
- The per-IP rate limiter flagged legitimate first-time submissions as spam,
  rejecting valid reviews whenever rate limiting was enabled (on by default).

### Changed
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
