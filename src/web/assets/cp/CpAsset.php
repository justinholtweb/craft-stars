<?php

namespace justinholtweb\stars\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

class CpAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CraftCpAsset::class];

        $this->css = [
            'css/stars.css',
        ];

        $this->js = [
            'js/stars.js',
        ];

        parent::init();
    }
}
