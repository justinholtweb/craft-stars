<?php

namespace justinholtweb\stars\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\stars\Plugin;
use justinholtweb\stars\services\BlockService;
use yii\web\Response;

class BlocklistController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('stars:manageBlocklist');
        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('stars/blocklist/_index', [
            'entries' => Plugin::getInstance()->block->getAll(),
        ]);
    }

    public function actionAdd(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $type = (string)$request->getBodyParam('type');
        $value = (string)$request->getBodyParam('value');
        $reason = $request->getBodyParam('reason');

        $validTypes = [BlockService::TYPE_EMAIL, BlockService::TYPE_IP, BlockService::TYPE_USER];
        if (!in_array($type, $validTypes, true) || trim($value) === '') {
            Craft::$app->getSession()->setError(Craft::t('stars', 'A type and value are required.'));
            return null;
        }

        Plugin::getInstance()->block->block($type, $value, $reason ?: null, Craft::$app->getUser()->getId());
        Craft::$app->getSession()->setNotice(Craft::t('stars', 'Blocklist entry added.'));

        return $this->redirectToPostedUrl();
    }

    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('id');
        Plugin::getInstance()->block->unblockById($id);
        Craft::$app->getSession()->setNotice(Craft::t('stars', 'Blocklist entry removed.'));

        return $this->redirectToPostedUrl();
    }
}
