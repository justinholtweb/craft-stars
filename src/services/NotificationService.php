<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\web\View;
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

        $bodies = $this->_renderEmail('stars/email/new-review', [
            'review' => $review,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        if ($bodies === null) {
            return;
        }

        [$htmlBody, $textBody] = $bodies;

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

        $bodies = $this->_renderEmail('stars/email/new-comment', [
            'comment' => $comment,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        if ($bodies === null) {
            return;
        }

        [$htmlBody, $textBody] = $bodies;

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

        $bodies = $this->_renderEmail('stars/email/comment-reply', [
            'reply' => $reply,
            'parent' => $parent,
            'entry' => $entry,
            'settings' => $settings,
        ]);

        if ($bodies === null) {
            return;
        }

        [$htmlBody, $textBody] = $bodies;

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
     * Render an email template's HTML and text bodies.
     *
     * These templates live in the plugin's own `src/templates` directory, which
     * Craft only registers as a *control panel* template root. They must
     * therefore be rendered in CP template mode, or they can't be resolved
     * during a frontend submission.
     *
     * Returns null (having logged the failure) if either body fails to render,
     * so that a broken email template never takes down a submission.
     *
     * @return string[]|null [$htmlBody, $textBody]
     */
    private function _renderEmail(string $template, array $variables): ?array
    {
        $view = Craft::$app->getView();

        try {
            return [
                $view->renderTemplate($template, $variables, View::TEMPLATE_MODE_CP),
                $view->renderTemplate("$template.txt", $variables, View::TEMPLATE_MODE_CP),
            ];
        } catch (\Throwable $e) {
            Craft::error("Failed to render email template \"$template\": " . $e->getMessage(), 'stars');
            return null;
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
