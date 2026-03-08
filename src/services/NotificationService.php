<?php

namespace justinholtweb\stars\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
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
                Craft::error("Failed to send review notification to {$email}: " . $e->getMessage(), 'stars');
            }
        }
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
