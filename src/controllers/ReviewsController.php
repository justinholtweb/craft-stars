<?php

namespace justinholtweb\stars\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use justinholtweb\stars\elements\base\ModeratedElement;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\SpamService;
use yii\web\Response;

class ReviewsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['save'];

    /**
     * Handle frontend review submission.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Plugin::getInstance()->getSettings();

        // Check login requirement
        if ($settings->requireLogin && Craft::$app->getUser()->getIsGuest()) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('stars', 'You must be logged in to submit a review.'),
                ]);
            }
            Craft::$app->getSession()->setError(Craft::t('stars', 'You must be logged in to submit a review.'));
            return null;
        }

        // Blocklist check
        if (Plugin::getInstance()->block->isBlocked(
            $request->getBodyParam('reviewerEmail'),
            $request->getUserIP(),
            Craft::$app->getUser()->getId()
        )) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('stars', 'Your submission was flagged as spam.'),
                ]);
            }
            Craft::$app->getSession()->setError(Craft::t('stars', 'Your submission was flagged as spam.'));
            return null;
        }

        // Spam check
        $spamFailure = Plugin::getInstance()->spam->check();
        if ($spamFailure !== null) {
            $message = $spamFailure === SpamService::FAILURE_RATE_LIMIT
                ? Craft::t('stars', 'You posted here recently. Please wait a little while and try again.')
                : Craft::t('stars', 'Your submission was flagged as spam.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $message,
                ]);
            }
            Craft::$app->getSession()->setError($message);
            return null;
        }

        // A review belongs to a live entry. Without this, any element id — an unpublished entry, a
        // user, an asset — could be posted against.
        $entryId = $this->_liveEntryId($request->getBodyParam('entryId'));
        if ($entryId === null) {
            return $this->_fail(Craft::t('stars', 'That entry can’t be reviewed.'));
        }

        // A missing rating is an error, not the top mark: before 5.1.2 it defaulted to maxRating.
        $rating = $request->getBodyParam('rating');
        if (!is_scalar($rating) || !ctype_digit(trim((string)$rating))) {
            return $this->_fail(Craft::t('stars', 'Please choose a rating.'));
        }

        // Build the review element
        $review = new Review();
        $review->entryId = $entryId;
        $review->rating = (int)$rating;
        $review->reviewText = $request->getBodyParam('reviewText');
        $review->reviewerName = $request->getBodyParam('reviewerName') ?: '';
        $review->reviewerEmail = $request->getBodyParam('reviewerEmail');
        $review->reviewStatus = $settings->defaultStatus;

        // Pros/Cons
        $pros = $request->getBodyParam('pros');
        if (is_array($pros)) {
            $review->pros = json_encode(array_filter($pros));
        } elseif (is_string($pros) && !empty($pros)) {
            $review->pros = $pros;
        }

        $cons = $request->getBodyParam('cons');
        if (is_array($cons)) {
            $review->cons = json_encode(array_filter($cons));
        } elseif (is_string($cons) && !empty($cons)) {
            $review->cons = $cons;
        }

        // Capture metadata — each is independently togglable for GDPR-style data minimization.
        $review->ipAddress = $settings->captureIpAddress ? $request->getUserIP() : null;
        $review->userAgent = $settings->captureUserAgent ? substr($request->getUserAgent() ?? '', 0, 512) : null;
        $review->submissionUrl = $settings->captureReferrer ? ModeratedElement::safeSubmissionUrl($request->getReferrer()) : null;

        // Anonymous check
        if (!$settings->allowAnonymous && empty($review->reviewerName)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('stars', 'Reviewer name is required.'),
                ]);
            }
            Craft::$app->getSession()->setError(Craft::t('stars', 'Reviewer name is required.'));
            return null;
        }

        // Save
        if (!Craft::$app->getElements()->saveElement($review)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $review->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('stars', "Couldn't save review."));
            Craft::$app->getUrlManager()->setRouteParams(['review' => $review]);
            return null;
        }

        // Send notification
        Plugin::getInstance()->notifications->sendNewReviewNotification($review);

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $review->id,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('stars', 'Review saved.'));
        return $this->redirectToPostedUrl($review);
    }

    /**
     * The id of a live entry, or null.
     */
    private function _liveEntryId(mixed $entryId): ?int
    {
        if (!is_scalar($entryId) || !ctype_digit((string)$entryId)) {
            return null;
        }

        return Entry::find()->id((int)$entryId)->status('live')->exists() ? (int)$entryId : null;
    }

    /**
     * Return a JSON error for API requests, or set a session error for form posts.
     */
    private function _fail(string $message): ?Response
    {
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'error' => $message,
            ]);
        }

        Craft::$app->getSession()->setError($message);
        return null;
    }
}
