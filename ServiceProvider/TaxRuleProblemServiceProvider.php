<?php

/*
 * This file is part of the TaxRuleProblem
 *
 * Copyright(c) EC-CUBE
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\TaxRuleProblem\ServiceProvider;

use Eccube\Common\Constant;
use Plugin\TaxRuleProblem\Form\Type\TaxRuleProblemConfigType;
use Plugin\TaxRuleProblem\Repository\TaxRuleProblemRepository;
use Silex\Application as BaseApplication;
use Silex\ServiceProviderInterface;

class TaxRuleProblemServiceProvider implements ServiceProviderInterface
{

    public function register(BaseApplication $app)
    {

        // 管理画面定義
        $admin = $app['controllers_factory'];
        // 強制SSL
        if ($app['config']['force_ssl'] == Constant::ENABLED) {
            $admin->requireHttps();
        }

        // プラグイン用設定画面
        $admin->match('/plugin/TaxRuleProblem/config', 'Plugin\TaxRuleProblem\Controller\ConfigController::index')->bind('plugin_TaxRuleProblem_config');

        $admin->match('/plugin/TaxRuleProblem/download', 'Plugin\TaxRuleProblem\Controller\ConfigController::download')->bind('plugin_TaxRuleProblem_download');

        $app->mount('/'.trim($app['config']['admin_route'], '/').'/', $admin);

        // Form
        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new TaxRuleProblemConfigType();

            return $types;
        }));

        // 既存Repositoryを継承したRepository定義
        $app['taxruleproblem.repository.order_detail'] = $app->share(function () use ($app) {
            return new TaxRuleProblemRepository($app['orm.em'], $app['orm.em']->getMetadataFactory()->getMetadataFor('Eccube\Entity\OrderDetail'));
        });

    }

    public function boot(BaseApplication $app)
    {
    }

}
