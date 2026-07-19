<?php

namespace justinholtweb\stars\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;
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
        if (Plugin::getInstance()->spam->isSpam()) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('stars', 'Your submission was flagged as spam.'),
                ]);
            }
            Craft::$app->getSession()->setError(Craft::t('stars', 'Your submission was flagged as spam.'));
            return null;
        }

        // Build the review element
        $review = new Review();
        $review->entryId = $request->getBodyParam('entryId') ?: null;
        $review->rating = (int)($request->getBodyParam('rating') ?: $settings->maxRating);
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
        $review->submissionUrl = $settings->captureReferrer ? $request->getReferrer() : null;

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
}
