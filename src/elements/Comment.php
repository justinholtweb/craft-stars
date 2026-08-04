<?php

namespace justinholtweb\stars\elements;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\db\ElementQueryInterface;
use craft\fieldlayoutelements\TextareaField;
use craft\fieldlayoutelements\TextField;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use justinholtweb\stars\elements\actions\Approve;
use justinholtweb\stars\elements\actions\BlockAuthor;
use justinholtweb\stars\elements\actions\MarkAsSpam;
use justinholtweb\stars\elements\actions\Reject;
use justinholtweb\stars\elements\base\ModeratedElement;
use justinholtweb\stars\elements\db\CommentQuery;
use justinholtweb\stars\Plugin;

class Comment extends ModeratedElement
{
    // Properties (entryId, ipAddress, userAgent, submissionUrl live on ModeratedElement)
    public ?int $parentId = null;
    public ?int $authorUserId = null;
    public string $authorName = '';
    public ?string $authorEmail = null;
    public ?string $body = null;
    public string $commentStatus = 'pending';

    /**
     * The in-code editor field layout, built once per request.
     * @see getFieldLayout()
     */
    private static ?FieldLayout $_starsFieldLayout = null;

    /**
     * Transient nested replies, populated by CommentService::getCommentTree().
     * Not persisted.
     *
     * @var Comment[]
     */
    public array $children = [];

    public static function displayName(): string
    {
        return Craft::t('stars', 'Comment');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('stars', 'Comments');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('stars', 'comment');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('stars', 'comments');
    }

    public static function statusAttribute(): string
    {
        return 'commentStatus';
    }

    public static function find(): ElementQueryInterface
    {
        return new CommentQuery(static::class);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('stars', 'All Comments'),
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'pending',
                'label' => Craft::t('stars', 'Pending'),
                'criteria' => ['commentStatus' => 'pending'],
                'defaultSort' => ['dateCreated', 'desc'],
                'badgeCount' => self::_countByStatus('pending'),
            ],
            [
                'key' => 'approved',
                'label' => Craft::t('stars', 'Approved'),
                'criteria' => ['commentStatus' => 'approved'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'rejected',
                'label' => Craft::t('stars', 'Rejected'),
                'criteria' => ['commentStatus' => 'rejected'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'spam',
                'label' => Craft::t('stars', 'Spam'),
                'criteria' => ['commentStatus' => 'spam'],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];
    }

    protected static function defineActions(string $source): array
    {
        return [
            Approve::class,
            Reject::class,
            MarkAsSpam::class,
            BlockAuthor::class,
            [
                'type' => \craft\elements\actions\Delete::class,
                'confirmationMessage' => Craft::t('stars', 'Are you sure you want to delete the selected comments?'),
                'successMessage' => Craft::t('stars', 'Comments deleted.'),
            ],
        ];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('stars', 'Date Created'),
            'authorName' => Craft::t('stars', 'Author Name'),
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'authorName' => ['label' => Craft::t('stars', 'Author')],
            'entryId' => ['label' => Craft::t('stars', 'Entry')],
            'body' => ['label' => Craft::t('stars', 'Comment')],
            'commentStatus' => ['label' => Craft::t('stars', 'Status')],
            'authorEmail' => ['label' => Craft::t('stars', 'Email')],
            'dateCreated' => ['label' => Craft::t('stars', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['authorName', 'entryId', 'body', 'commentStatus', 'dateCreated'];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['authorName', 'authorEmail', 'body'];
    }

    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'entryId':
                $entry = $this->getEntry();
                if ($entry) {
                    return Cp::elementChipHtml($entry);
                }
                return '<span class="light">—</span>';
            case 'commentStatus':
                $statuses = static::statuses();
                $statusInfo = $statuses[$this->commentStatus] ?? null;
                if ($statusInfo) {
                    return '<span class="status ' . $statusInfo['color'] . '"></span>' . $statusInfo['label'];
                }
                return $this->commentStatus;
            case 'body':
                return Html::encode(StringHelper::truncate($this->body ?? '', 60));
            default:
                return parent::attributeHtml($attribute);
        }
    }

    public function getUiLabel(): string
    {
        return Craft::t('stars', 'Comment by {name}', [
            'name' => $this->authorName ?: Craft::t('stars', 'Anonymous'),
        ]);
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("stars/comments/{$this->id}");
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function canView(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:viewComments');
    }

    public function canSave(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:manageComments');
    }

    public function canDelete(\craft\elements\User $user): bool
    {
        return $user->admin || $user->can('stars:deleteComments');
    }

    public function canCreateDrafts(\craft\elements\User $user): bool
    {
        return false;
    }

    public function safeAttributes(): array
    {
        // entryId, ipAddress, userAgent, submissionUrl and commentStatus are
        // contributed by ModeratedElement::safeAttributes().
        return array_merge(parent::safeAttributes(), [
            'parentId',
            'authorUserId',
            'authorName',
            'authorEmail',
            'body',
        ]);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['authorName'], 'required'];
        $rules[] = [['authorName'], 'string', 'max' => 255];
        $rules[] = [['authorEmail'], 'email'];
        $rules[] = [['body'], 'required'];
        $rules[] = [['parentId', 'authorUserId'], 'integer'];
        $rules[] = [['commentStatus'], 'in', 'range' => ['pending', 'approved', 'rejected', 'spam']];

        return $rules;
    }

    /**
     * The comment's own content is rendered in the editor's main body via an
     * in-code field layout. Nothing here is persisted to project config —
     * Craft only needs the layout to build the form.
     *
     * Craft asks for this on every chip it renders (index rows, breadcrumbs),
     * so the layout is built once per request and shared.
     */
    public function getFieldLayout(): ?FieldLayout
    {
        if (self::$_starsFieldLayout !== null) {
            return self::$_starsFieldLayout;
        }

        $layout = new FieldLayout(['type' => static::class]);

        $tab = new FieldLayoutTab([
            'name' => Craft::t('stars', 'Comment'),
            'sortOrder' => 1,
        ]);

        // The tab must know its layout before it accepts elements, and the
        // layout must know its tabs after they're populated.
        $tab->setLayout($layout);

        $tab->setElements([
            new TextField([
                'attribute' => 'authorName',
                'label' => Craft::t('stars', 'Author Name'),
                'required' => true,
                'maxlength' => 255,
                'width' => 50,
            ]),
            new TextField([
                'attribute' => 'authorEmail',
                'label' => Craft::t('stars', 'Author Email'),
                'inputType' => 'email',
                'width' => 50,
            ]),
            new TextareaField([
                'attribute' => 'body',
                'label' => Craft::t('stars', 'Comment'),
                'required' => true,
                'rows' => 8,
            ]),
        ]);

        $layout->setTabs([$tab]);

        return self::$_starsFieldLayout = $layout;
    }

    /**
     * The sidebar keeps only what's *about* the comment rather than part of it:
     * its moderation status, the entry it belongs to, and — for a reply — the
     * comment it answers.
     */
    public function metaFieldsHtml(bool $static): string
    {
        $fields = [];

        $fields[] = Cp::selectFieldHtml([
            'label' => Craft::t('stars', 'Status'),
            'id' => 'commentStatus',
            'name' => 'commentStatus',
            'value' => $this->commentStatus,
            'options' => [
                ['label' => Craft::t('stars', 'Pending'), 'value' => 'pending'],
                ['label' => Craft::t('stars', 'Approved'), 'value' => 'approved'],
                ['label' => Craft::t('stars', 'Rejected'), 'value' => 'rejected'],
                ['label' => Craft::t('stars', 'Spam'), 'value' => 'spam'],
            ],
            'disabled' => $static,
        ]);

        $fields[] = Cp::elementSelectFieldHtml([
            'label' => Craft::t('stars', 'Entry'),
            'id' => 'entryId',
            'name' => 'entryId',
            'elementType' => Entry::class,
            'elements' => $this->getEntry() ? [$this->getEntry()] : [],
            'limit' => 1,
            'single' => true,
            'disabled' => $static,
        ]);

        return implode("\n", $fields) . parent::metaFieldsHtml($static);
    }

    protected function metadata(): array
    {
        $metadata = [];

        if ($this->parentId) {
            /** @var self|null $parent */
            $parent = self::find()->id($this->parentId)->status(null)->one();
            $metadata[Craft::t('stars', 'Reply To')] = $parent
                ? Html::a(Html::encode($parent->getUiLabel()), $parent->getCpEditUrl())
                : Craft::t('stars', 'Deleted comment');
        }

        if ($this->authorEmail) {
            $metadata[Craft::t('stars', 'Email')] = Html::mailto($this->authorEmail);
        }

        if ($this->ipAddress) {
            $metadata[Craft::t('stars', 'IP Address')] = $this->ipAddress;
        }

        if ($this->submissionUrl) {
            $metadata[Craft::t('stars', 'Submission URL')] = Html::a(
                Html::encode(StringHelper::truncate($this->submissionUrl, 50)),
                $this->submissionUrl,
                ['target' => '_blank', 'rel' => 'noopener']
            );
        }

        return $metadata;
    }

    /**
     * The nesting depth of this comment. Top-level comments are depth 1, a
     * reply is depth 2, and so on.
     */
    public function getDepth(): int
    {
        $depth = 1;
        $parentId = $this->parentId;
        $guard = 0;

        while ($parentId !== null && $guard++ < 50) {
            $depth++;
            $next = (new Query())
                ->select(['parentId'])
                ->from('{{%stars_comments}}')
                ->where(['id' => $parentId])
                ->scalar();
            $parentId = ($next !== false && $next !== null) ? (int)$next : null;
        }

        return $depth;
    }

    /**
     * Enforce the configured maximum nesting depth: a reply whose parent is
     * already at the limit is promoted to sit alongside that parent instead.
     */
    public function beforeSave(bool $isNew): bool
    {
        if ($this->parentId !== null) {
            $maxDepth = Plugin::getInstance()->getSettings()->maxCommentDepth;

            /** @var Comment|null $parent */
            $parent = static::find()->id($this->parentId)->status(null)->one();

            if ($parent === null) {
                // Parent no longer exists — make this a top-level comment.
                $this->parentId = null;
            } elseif ($parent->getDepth() >= $maxDepth) {
                // Parent is already at max depth — attach to the parent's level.
                $this->parentId = $parent->parentId;
            }
        }

        return parent::beforeSave($isNew);
    }

    protected function customTableName(): string
    {
        return '{{%stars_comments}}';
    }

    protected function customAttributes(): array
    {
        return [
            'entryId' => $this->entryId,
            'parentId' => $this->parentId,
            'authorUserId' => $this->authorUserId,
            'authorName' => $this->authorName,
            'authorEmail' => $this->authorEmail,
            'body' => $this->body,
            'commentStatus' => $this->commentStatus,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'submissionUrl' => $this->submissionUrl,
        ];
    }

    private static function _countByStatus(string $status): int
    {
        return static::find()->commentStatus($status)->count();
    }
}
