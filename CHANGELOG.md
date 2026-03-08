# Changelog

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
