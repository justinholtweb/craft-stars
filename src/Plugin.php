<?php

namespace justinholtweb\stars;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use justinholtweb\stars\elements\Comment;
use justinholtweb\stars\elements\Review;
use justinholtweb\stars\models\Settings;
use justinholtweb\stars\services\BlockService;
use justinholtweb\stars\services\CommentService;
use justinholtweb\stars\services\NotificationService;
use justinholtweb\stars\services\ReviewService;
use justinholtweb\stars\services\SchemaService;
use justinholtweb\stars\services\SpamService;
use justinholtweb\stars\twig\CommentsVariable;
use justinholtweb\stars\twig\StarsVariable;
use justinholtweb\stars\web\assets\cp\CpAsset;
use yii\base\Event;

/**
 * Stars plugin for Craft CMS 5
 *
 * @property-read ReviewService $reviews
 * @property-read CommentService $comments
 * @property-read SpamService $spam
 * @property-read SchemaService $schema
 * @property-read NotificationService $notifications
 * @property-read BlockService $block
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '3.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'reviews' => ReviewService::class,
                'comments' => CommentService::class,
                'spam' => SpamService::class,
                'schema' => SchemaService::class,
                'notifications' => NotificationService::class,
                'block' => BlockService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_registerElementTypes();
        $this->_registerCpRoutes();
        $this->_registerVariables();
        $this->_registerPermissions();
        $this->_registerCpAssets();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('stars', 'Stars');

        $item['subnav'] = [
            'reviews' => [
                'label' => Craft::t('stars', 'Reviews'),
                'url' => 'stars/reviews',
            ],
        ];

        if ($this->getSettings()->enableComments) {
            $item['subnav']['comments'] = [
                'label' => Craft::t('stars', 'Comments'),
                'url' => 'stars/comments',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('stars:manageBlocklist')) {
            $item['subnav']['blocklist'] = [
                'label' => Craft::t('stars', 'Blocklist'),
                'url' => 'stars/blocklist',
            ];
        }

        $item['subnav']['settings'] = [
            'label' => Craft::t('stars', 'Settings'),
            'url' => 'settings/plugins/stars',
        ];

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('stars/settings/_index', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }

    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Review::class;
                $event->types[] = Comment::class;
            }
        );
    }

    private function _registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['stars'] = ['template' => 'stars/reviews/_index'];
                $event->rules['stars/reviews'] = ['template' => 'stars/reviews/_index'];
                $event->rules['stars/reviews/new'] = 'elements/edit';
                $event->rules['stars/reviews/<elementId:\\d+>'] = 'elements/edit';
                $event->rules['stars/comments'] = ['template' => 'stars/comments/_index'];
                $event->rules['stars/comments/new'] = 'elements/edit';
                $event->rules['stars/comments/<elementId:\\d+>'] = 'elements/edit';
                $event->rules['stars/blocklist'] = 'stars/blocklist/index';
            }
        );
    }

    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('reviews', StarsVariable::class);
                $event->sender->set('comments', CommentsVariable::class);
            }
        );
    }

    private function _registerCpAssets(): void
    {
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Craft::$app->getView()->registerAssetBundle(CpAsset::class);
        }
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('stars', 'Stars'),
                    'permissions' => [
                        'stars:viewReviews' => [
                            'label' => Craft::t('stars', 'View reviews'),
                            'nested' => [
                                'stars:manageReviews' => [
                                    'label' => Craft::t('stars', 'Create and edit reviews'),
                                ],
                                'stars:moderateReviews' => [
                                    'label' => Craft::t('stars', 'Moderate reviews (approve, reject, spam)'),
                                ],
                                'stars:respondToReviews' => [
                                    'label' => Craft::t('stars', 'Respond to reviews'),
                                ],
                                'stars:deleteReviews' => [
                                    'label' => Craft::t('stars', 'Delete reviews'),
                                ],
                            ],
                        ],
                        'stars:viewComments' => [
                            'label' => Craft::t('stars', 'View comments'),
                            'nested' => [
                                'stars:manageComments' => [
                                    'label' => Craft::t('stars', 'Create and edit comments'),
                                ],
                                'stars:moderateComments' => [
                                    'label' => Craft::t('stars', 'Moderate comments (approve, reject, spam)'),
                                ],
                                'stars:replyToComments' => [
                                    'label' => Craft::t('stars', 'Reply to comments'),
                                ],
                                'stars:deleteComments' => [
                                    'label' => Craft::t('stars', 'Delete comments'),
                                ],
                            ],
                        ],
                        'stars:manageBlocklist' => [
                            'label' => Craft::t('stars', 'Manage the blocklist'),
                        ],
                    ],
                ];
            }
        );
    }
}
