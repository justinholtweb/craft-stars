<?php

namespace justinholtweb\stars\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\Plugin;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CommentsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['save'];

    /**
     * Handle frontend comment submission.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableComments) {
            throw new ForbiddenHttpException(Craft::t('stars', 'Comments are disabled.'));
        }

        $user = Craft::$app->getUser()->getIdentity();

        // Login gating
        if ($settings->commentsRequireLogin && !$user) {
            return $this->_fail($request, Craft::t('stars', 'You must be logged in to comment.'));
        }

        // Spam check (rate-limited against the comments table)
        if (Plugin::getInstance()->spam->isSpam('comments')) {
            return $this->_fail($request, Craft::t('stars', 'Your submission was flagged as spam.'));
        }

        $comment = new Comment();
        $comment->entryId = $request->getBodyParam('entryId') ?: null;
        $comment->parentId = $request->getBodyParam('parentId') ?: null;
        $comment->body = $request->getBodyParam('body');
        $comment->commentStatus = $settings->defaultStatus;

        // Author: taken from the logged-in user when present, otherwise posted.
        if ($user) {
            $comment->authorUserId = $user->id;
            $comment->authorName = $user->getName() ?: ($user->username ?? $user->email);
            $comment->authorEmail = $user->email;
        } else {
            $comment->authorName = $request->getBodyParam('authorName') ?: '';
            $comment->authorEmail = $request->getBodyParam('authorEmail');
        }

        // Capture metadata — each is independently togglable for GDPR-style data minimization.
        $comment->ipAddress = $settings->captureIpAddress ? $request->getUserIP() : null;
        $comment->userAgent = $settings->captureUserAgent ? substr($request->getUserAgent() ?? '', 0, 512) : null;
        $comment->submissionUrl = $settings->captureReferrer ? $request->getReferrer() : null;

        // Anonymous check (guests only)
        if (!$user && !$settings->commentsAllowAnonymous && empty($comment->authorName)) {
            return $this->_fail($request, Craft::t('stars', 'Your name is required.'));
        }

        if (!Craft::$app->getElements()->saveElement($comment)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $comment->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('stars', "Couldn't save comment."));
            Craft::$app->getUrlManager()->setRouteParams(['comment' => $comment]);
            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $comment->id,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('stars', 'Comment saved.'));
        return $this->redirectToPostedUrl($comment);
    }

    /**
     * Return a JSON error for API requests, or set a session error for form posts.
     */
    private function _fail($request, string $message): ?Response
    {
        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'error' => $message,
            ]);
        }

        Craft::$app->getSession()->setError($message);
        return null;
    }
}
