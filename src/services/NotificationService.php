<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\Plugin;

class NotificationService extends Component
{
    /**
     * Send notification email for a new review submission.
     */
    public function sendNewReviewNotification(Review $review): void
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableNotifications) {
            return;
        }

        $emails = $this->_getNotificationEmails();

        if (empty($emails)) {
            return;
        }

        $entry = $review->getEntry();
        $entryTitle = $entry ? $entry->title : 'Unknown';

        $subject = Craft::t('stars', 'New Review: {entryTitle}', ['entryTitle' => $entryTitle]);

        $htmlBody = Craft::$app->getView()->renderTemplate('stars/email/new-review', [
            'review' => $review,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        $textBody = Craft::$app->getView()->renderTemplate('stars/email/new-review.txt', [
            'review' => $review,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        foreach ($emails as $email) {
            try {
                Craft::$app->getMailer()
                    ->compose()
                    ->setTo($email)
                    ->setSubject($subject)
                    ->setHtmlBody($htmlBody)
                    ->setTextBody($textBody)
                    ->send();
            } catch (\Throwable $e) {
                Craft::error("Failed to send review notification to {$this->_maskEmail($email)}: " . $e->getMessage(), 'stars');
            }
        }
    }

    /**
     * Send notification email to moderators for a new comment submission.
     */
    public function sendNewCommentNotification(Comment $comment): void
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableNotifications) {
            return;
        }

        $emails = $this->_getNotificationEmails();

        if (empty($emails)) {
            return;
        }

        $entry = $comment->getEntry();
        $entryTitle = $entry ? $entry->title : 'Unknown';

        $subject = Craft::t('stars', 'New Comment: {entryTitle}', ['entryTitle' => $entryTitle]);

        $htmlBody = Craft::$app->getView()->renderTemplate('stars/email/new-comment', [
            'comment' => $comment,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        $textBody = Craft::$app->getView()->renderTemplate('stars/email/new-comment.txt', [
            'comment' => $comment,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        foreach ($emails as $email) {
            try {
                Craft::$app->getMailer()
                    ->compose()
                    ->setTo($email)
                    ->setSubject($subject)
                    ->setHtmlBody($htmlBody)
                    ->setTextBody($textBody)
                    ->send();
            } catch (\Throwable $e) {
                Craft::error("Failed to send comment notification to {$this->_maskEmail($email)}: " . $e->getMessage(), 'stars');
            }
        }
    }

    /**
     * Notify the author of a parent comment that they've received a reply.
     * Only fires for approved replies, and never notifies a self-reply.
     */
    public function sendReplyNotification(Comment $reply): void
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableNotifications || $reply->commentStatus !== 'approved' || $reply->parentId === null) {
            return;
        }

        /** @var Comment|null $parent */
        $parent = Comment::find()->id($reply->parentId)->status(null)->one();

        if ($parent === null || empty($parent->authorEmail)) {
            return;
        }

        // Don't notify people about replies to their own comment.
        if (strcasecmp($parent->authorEmail, (string)$reply->authorEmail) === 0) {
            return;
        }

        $entry = $reply->getEntry();

        $subject = Craft::t('stars', 'New reply to your comment');

        $htmlBody = Craft::$app->getView()->renderTemplate('stars/email/comment-reply', [
            'reply' => $reply,
            'parent' => $parent,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        $textBody = Craft::$app->getView()->renderTemplate('stars/email/comment-reply.txt', [
            'reply' => $reply,
            'parent' => $parent,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        try {
            Craft::$app->getMailer()
                ->compose()
                ->setTo($parent->authorEmail)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->setTextBody($textBody)
                ->send();
        } catch (\Throwable $e) {
            Craft::error("Failed to send reply notification to {$this->_maskEmail($parent->authorEmail)}: " . $e->getMessage(), 'stars');
        }
    }

    /**
     * Mask an email for logs: "alice@example.com" -> "a***@example.com".
     */
    private function _maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at === 0) {
            return '***';
        }

        return substr($email, 0, 1) . '***' . substr($email, $at);
    }

    private function _getNotificationEmails(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $emailStr = App::parseEnv($settings->notificationEmails);

        if (empty($emailStr)) {
            // Fall back to the system admin email
            $email = Craft::$app->getProjectConfig()->get('email.fromEmail');
            return $email ? [$email] : [];
        }

        $emails = array_map('trim', explode(',', $emailStr));
        return array_filter($emails);
    }
}
