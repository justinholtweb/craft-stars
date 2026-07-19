<?php

namespace justinholtweb\stars\elements;

use Craft;
use craft\elements\Entry;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use justinholtweb\stars\elements\actions\Approve;
use justinholtweb\stars\elements\actions\BlockAuthor;
use justinholtweb\stars\elements\actions\MarkAsSpam;
use justinholtweb\stars\elements\actions\Reject;
use justinholtweb\stars\elements\base\ModeratedElement;
use justinholtweb\stars\elements\db\ReviewQuery;
use justinholtweb\stars\Plugin;

class Review extends ModeratedElement
{
    // Properties (shared columns — entryId, ipAddress, userAgent,
    // submissionUrl — live on ModeratedElement)
    public int $rating = 5;
    public ?string $reviewText = null;
    public string $reviewerName = '';
    public ?string $reviewerEmail = null;
    public ?string $pros = null;
    public ?string $cons = null;
    public ?string $adminResponse = null;
    public ?string $adminResponseDate = null;
    public string $reviewStatus = 'pending';

    public static function displayName(): string
    {
        return Craft::t('stars', 'Review');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('stars', 'Reviews');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('stars', 'review');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stars', 'reviews');
    }

    public static function statusAttribute(): string
    {
        return 'reviewStatus';
    }

    public static function find(): ElementQueryInterface
    {
        return new ReviewQuery(static::class);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('stars', 'All Reviews'),
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'pending',
                'label' => Craft::t('stars', 'Pending'),
                'criteria' => ['reviewStatus' => 'pending'],
                'defaultSort' => ['dateCreated', 'desc'],
                'badgeCount' => self::_countByStatus('pending'),
            ],
            [
                'key' => 'approved',
                'label' => Craft::t('stars', 'Approved'),
                'criteria' => ['reviewStatus' => 'approved'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'rejected',
                'label' => Craft::t('stars', 'Rejected'),
                'criteria' => ['reviewStatus' => 'rejected'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'spam',
                'label' => Craft::t('stars', 'Spam'),
                'criteria' => ['reviewStatus' => 'spam'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];
    }

    protected static function defineActions(string $source): array
    {
        $actions = [];

        $actions[] = Approve::class;
        $actions[] = Reject::class;
        $actions[] = MarkAsSpam::class;
        $actions[] = BlockAuthor::class;

        $actions[] = [
            'type' => \craft\elements\actions\Delete::class,
            'confirmationMessage' => Craft::t('stars', 'Are you sure you want to delete the selected reviews?'),
            'successMessage' => Craft::t('stars', 'Reviews deleted.'),
        ];

        return $actions;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('stars', 'Date Created'),
            'rating' => Craft::t('stars', 'Rating'),
            'reviewerName' => Craft::t('stars', 'Reviewer Name'),
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'reviewerName' => ['label' => Craft::t('stars', 'Reviewer')],
            'entryId' => ['label' => Craft::t('stars', 'Entry')],
            'rating' => ['label' => Craft::t('stars', 'Rating')],
            'reviewStatus' => ['label' => Craft::t('stars', 'Status')],
            'reviewerEmail' => ['label' => Craft::t('stars', 'Email')],
            'dateCreated' => ['label' => Craft::t('stars', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['reviewerName', 'entryId', 'rating', 'reviewStatus', 'dateCreated'];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['reviewerName', 'reviewerEmail', 'reviewText'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'rating':
                return $this->_renderStars();
            case 'entryId':
                $entry = $this->getEntry();
                if ($entry) {
                    return Cp::elementChipHtml($entry);
                }
                return '<span class="light">—</span>';
            case 'reviewStatus':
                $statuses = static::statuses();
                $statusInfo = $statuses[$this->reviewStatus] ?? null;
                if ($statusInfo) {
                    return '<span class="status ' . $statusInfo['color'] . '"></span>' . $statusInfo['label'];
                }
                return $this->reviewStatus;
            default:
                return parent::tableAttributeHtml($attribute);
        }
    }

    public function getUiLabel(): string
    {
        $entry = $this->getEntry();
        $entryTitle = $entry ? $entry->title : Craft::t('stars', 'Unknown Entry');
        return Craft::t('stars', 'Review by {name}', ['name' => $this->reviewerName ?: Craft::t('stars', 'Anonymous')]);
    }

    public function getAuthorEmail(): ?string
    {
        return $this->reviewerEmail;
    }

    public function getProsArray(): array
    {
        if (empty($this->pros)) {
            return [];
        }
        $decoded = json_decode($this->pros, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getConsArray(): array
    {
        if (empty($this->cons)) {
            return [];
        }
        $decoded = json_decode($this->cons, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("stars/reviews/{$this->id}");
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:viewReviews');
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:manageReviews');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:deleteReviews');
    }

    public function canCreateDrafts(\craft\elements\User $user): bool
    {
        return false;
    }

    public function safeAttributes(): array
    {
        // entryId, ipAddress, userAgent, submissionUrl and reviewStatus are
        // contributed by ModeratedElement::safeAttributes().
        return array_merge(parent::safeAttributes(), [
            'rating',
            'reviewText',
            'reviewerName',
            'reviewerEmail',
            'pros',
            'cons',
            'adminResponse',
            'adminResponseDate',
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $maxRating = Plugin::getInstance()->getSettings()->maxRating;

        $rules[] = [['reviewerName'], 'required'];
        $rules[] = [['reviewerName'], 'string', 'max' => 255];
        $rules[] = [['reviewerEmail'], 'email'];
        $rules[] = [['rating'], 'required'];
        $rules[] = [['rating'], 'integer', 'min' => 1, 'max' => $maxRating];
        $rules[] = [['reviewStatus'], 'in', 'range' => ['pending', 'approved', 'rejected', 'spam']];

        return $rules;
    }

    public function metaFieldsHtml(bool $static): string
    {
        $fields = [];
        $settings = Plugin::getInstance()->getSettings();

        // Rating selector
        $ratingOptions = [];
        for ($i = 1; $i <= $settings->maxRating; $i++) {
            $ratingOptions[] = ['label' => str_repeat('★', $i) . str_repeat('☆', $settings->maxRating - $i) . " ({$i})", 'value' => $i];
        }
        $fields[] = Cp::selectFieldHtml([
            'label' => Craft::t('stars', 'Rating'),
            'id' => 'rating',
            'name' => 'rating',
            'value' => $this->rating,
            'options' => $ratingOptions,
            'required' => true,
        ]);

        // Status selector
        $statusOptions = [
            ['label' => Craft::t('stars', 'Pending'), 'value' => 'pending'],
            ['label' => Craft::t('stars', 'Approved'), 'value' => 'approved'],
            ['label' => Craft::t('stars', 'Rejected'), 'value' => 'rejected'],
            ['label' => Craft::t('stars', 'Spam'), 'value' => 'spam'],
        ];
        $fields[] = Cp::selectFieldHtml([
            'label' => Craft::t('stars', 'Status'),
            'id' => 'reviewStatus',
            'name' => 'reviewStatus',
            'value' => $this->reviewStatus,
            'options' => $statusOptions,
        ]);

        // Entry selector
        $fields[] = Cp::elementSelectFieldHtml([
            'label' => Craft::t('stars', 'Entry'),
            'id' => 'entryId',
            'name' => 'entryId',
            'elementType' => Entry::class,
            'elements' => $this->getEntry() ? [$this->getEntry()] : [],
            'limit' => 1,
            'single' => true,
        ]);

        // Reviewer name
        $fields[] = Cp::textFieldHtml([
            'label' => Craft::t('stars', 'Reviewer Name'),
            'id' => 'reviewerName',
            'name' => 'reviewerName',
            'value' => $this->reviewerName,
            'required' => true,
        ]);

        // Reviewer email
        $fields[] = Cp::textFieldHtml([
            'label' => Craft::t('stars', 'Reviewer Email'),
            'id' => 'reviewerEmail',
            'name' => 'reviewerEmail',
            'value' => $this->reviewerEmail,
            'type' => 'email',
        ]);

        // Review text
        $fields[] = Cp::textareaFieldHtml([
            'label' => Craft::t('stars', 'Review Text'),
            'id' => 'reviewText',
            'name' => 'reviewText',
            'value' => $this->reviewText,
            'rows' => 5,
        ]);

        // Pros
        if ($settings->enablePros) {
            $fields[] = Cp::textareaFieldHtml([
                'label' => Craft::t('stars', 'Pros'),
                'id' => 'pros',
                'name' => 'pros',
                'value' => $this->pros,
                'rows' => 3,
                'instructions' => Craft::t('stars', 'JSON array of strings, e.g. ["Great quality", "Fast shipping"]'),
            ]);
        }

        // Cons
        if ($settings->enableCons) {
            $fields[] = Cp::textareaFieldHtml([
                'label' => Craft::t('stars', 'Cons'),
                'id' => 'cons',
                'name' => 'cons',
                'value' => $this->cons,
                'rows' => 3,
                'instructions' => Craft::t('stars', 'JSON array of strings, e.g. ["Expensive", "Slow delivery"]'),
            ]);
        }

        // Admin response
        if ($settings->enableAdminResponse) {
            $fields[] = Cp::textareaFieldHtml([
                'label' => Craft::t('stars', 'Admin Response'),
                'id' => 'adminResponse',
                'name' => 'adminResponse',
                'value' => $this->adminResponse,
                'rows' => 3,
            ]);
        }

        return implode("\n", $fields) . parent::metaFieldsHtml($static);
    }

    protected function metadata(): array
    {
        $metadata = [];

        if ($this->reviewerEmail) {
            $metadata[Craft::t('stars', 'Email')] = Html::mailto($this->reviewerEmail);
        }

        if ($this->ipAddress) {
            $metadata[Craft::t('stars', 'IP Address')] = $this->ipAddress;
        }

        if ($this->submissionUrl) {
            $metadata[Craft::t('stars', 'Submission URL')] = Html::a(
                Html::encode(\craft\helpers\StringHelper::truncate($this->submissionUrl, 50)),
                $this->submissionUrl,
                ['target' => '_blank', 'rel' => 'noopener']
            );
        }

        if ($this->adminResponseDate) {
            $metadata[Craft::t('stars', 'Response Date')] = Craft::$app->getFormatter()->asDatetime($this->adminResponseDate);
        }

        return $metadata;
    }

    protected function customTableName(): string
    {
        return '{{%stars_reviews}}';
    }

    protected function customAttributes(): array
    {
        return [
            'entryId' => $this->entryId,
            'rating' => $this->rating,
            'reviewText' => $this->reviewText,
            'reviewerName' => $this->reviewerName,
            'reviewerEmail' => $this->reviewerEmail,
            'pros' => $this->pros,
            'cons' => $this->cons,
            'adminResponse' => $this->adminResponse,
            'adminResponseDate' => $this->adminResponseDate ? Db::prepareDateForDb($this->adminResponseDate) : null,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'submissionUrl' => $this->submissionUrl,
            'reviewStatus' => $this->reviewStatus,
        ];
    }

    private function _renderStars(): string
    {
        $maxRating = Plugin::getInstance()->getSettings()->maxRating;
        $html = '<span class="stars-rating" title="' . $this->rating . '/' . $maxRating . '">';
        for ($i = 1; $i <= $maxRating; $i++) {
            $html .= $i <= $this->rating ? '<span class="star filled">★</span>' : '<span class="star empty">☆</span>';
        }
        $html .= '</span>';
        return $html;
    }

    private static function _countByStatus(string $status): int
    {
        return static::find()->reviewStatus($status)->count();
    }
}
