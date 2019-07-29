<?php

/*
 * This file is part of the TaxRuleProblem
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\TaxRuleProblem\Controller;

use Carbon\Carbon;
use Eccube\Application;
use Eccube\Entity\OrderDetail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConfigController
{

    /**
     * TaxRuleProblem用設定画面
     *
     * @param Application $app
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function index(Application $app, Request $request)
    {

        $builder = $app['form.factory']->createBuilder('taxruleproblem_config');

        $form = $builder->getForm();

        $form->handleRequest($request);

        $TaxRule = $app['eccube.repository.tax_rule']->getByRule();

        // 取得した受注明細を計算していく
        $details = null;

        $baseDate = Carbon::create(2016, 10, 24, 0, 0, 0);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $from = $data['from'];
            $to = $data['to'];

            if ($from) {
                if ($baseDate->gt(Carbon::instance($from))) {
                    $from = $baseDate;
                }
            } else {
                $from = $baseDate;
            }

            $form = $builder->getForm();
            $form->get('from')->setData($from);
            $form->get('to')->setData($to);

            $OrderDetails = $app['taxruleproblem.repository.order_detail']->getOrderDetail($TaxRule, $from, $to);

            $app['session']->set('taxrulefrom', $from);
            $app['session']->set('taxruleto', $to);

            $details = array();

            /** @var OrderDetail $OrderDetail */
            foreach ($OrderDetails as $OrderDetail) {

                // 受注明細で設定された金額
                $a = ($OrderDetail->getPrice() + $app['eccube.service.tax_rule']->calcTax($OrderDetail->getPrice(), $OrderDetail->getTaxRate(), $OrderDetail->getTaxRule())) * $OrderDetail->getQuantity();


                // 本来の課税規則に沿った金額
                $b = ($OrderDetail->getPrice() + $app['eccube.service.tax_rule']->calcTax($OrderDetail->getPrice(), $OrderDetail->getTaxRate(), $TaxRule->getCalcRule()->getId())) * $OrderDetail->getQuantity();

                if ($a != $b) {
                    $array1 = array($OrderDetail);
                    $array2 = array($a, $b);
                    $details[] = array_merge($array1, $array2);
                }

            }

        } else {
            $form->get('from')->setData($baseDate);
        }

        return $app->render('TaxRuleProblem/Resource/template/admin/config.twig', array(
            'form' => $form->createView(),
            'TaxRule' => $TaxRule,
            'details' => $details,
        ));
    }


    /**
     *  CSVファイルダウンロード
     *
     * @param Application $app
     * @param Request $request
     * @return StreamedResponse
     * @throws \Doctrine\ORM\NoResultException
     */
    public function download(Application $app, Request $request)
    {

        // タイムアウトを無効にする.
        set_time_limit(0);

        // sql loggerを無効にする.
        $em = $app['orm.em'];
        $em->getConfiguration()->setSQLLogger(null);

        // CSV出力データ取得
        $TaxRule = $app['eccube.repository.tax_rule']->getByRule();

        $from = $app['session']->get('taxrulefrom');
        $to = $app['session']->get('taxruleto');

        $OrderDetails = $app['taxruleproblem.repository.order_detail']->getOrderDetail($TaxRule, $from, $to);

        // 取得した受注明細を計算していく
        $details[] = array('注文番号', '受注明細ID', '会員ID', '購入者名', 'メールアドレス', '電話番号', '商品名', '単価', '数量', '税率', '購入時の課税規則', '受注ステータス', '受注日', '注文時小計', '現在の課税規則で計算された小計');

        /** @var OrderDetail $OrderDetail */
        foreach ($OrderDetails as $OrderDetail) {

            // 受注明細で設定された金額
            $a = ($OrderDetail->getPrice() + $app['eccube.service.tax_rule']->calcTax($OrderDetail->getPrice(), $OrderDetail->getTaxRate(), $OrderDetail->getTaxRule())) * $OrderDetail->getQuantity();


            // 本来の課税規則に沿った金額
            $b = ($OrderDetail->getPrice() + $app['eccube.service.tax_rule']->calcTax($OrderDetail->getPrice(), $OrderDetail->getTaxRate(), $TaxRule->getCalcRule()->getId())) * $OrderDetail->getQuantity();


            if ($a != $b) {
                // 必要な項目だけ抜き出してセット

                $array1[] = $OrderDetail->getOrder()->getId();
                $array1[] = $OrderDetail->getId();
                $Customer = $OrderDetail->getOrder()->getCustomer();
                if ($Customer) {
                    $array1[] = $Customer->getId();
                } else {
                    $array1[] = '非会員';
                }
                $array1[] = $OrderDetail->getOrder()->getName01().' '.$OrderDetail->getOrder()->getName02();
                $array1[] = $OrderDetail->getOrder()->getEmail();
                $array1[] = $OrderDetail->getOrder()->getTel01().'-'.$OrderDetail->getOrder()->getTel02().'-'.$OrderDetail->getOrder()->getTel03();
                $array1[] = $OrderDetail->getProductName();
                $array1[] = $OrderDetail->getPrice();
                $array1[] = $OrderDetail->getQuantity();
                $array1[] = $OrderDetail->getTaxRate();
                $array1[] = $OrderDetail->getTaxRule();
                $array1[] = $OrderDetail->getOrder()->getOrderStatus();
                $array1[] = $OrderDetail->getOrder()->getOrderDate()->format('Y/m/d H:i:s');

                $array2 = array($a, $b);
                $details[] = array_merge($array1, $array2);
                $array1 = array();
            }
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($app, $request, $details) {

            $app['eccube.service.csv.export']->fopen();

            // CSV出力.
            foreach ($details as $rowData) {
                $app['eccube.service.csv.export']->fputcsv((array)$rowData);
            }

            $app['eccube.service.csv.export']->fclose();
        });

        $filename = 'tax'.Carbon::now()->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        return $response;
    }

}
