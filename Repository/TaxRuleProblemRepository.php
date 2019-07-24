<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Plugin\TaxRuleProblem\Repository;

use Eccube\Entity\TaxRule;
use Eccube\Repository\OrderDetailRepository;

/**
 * TaxRuleProblemRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TaxRuleProblemRepository extends OrderDetailRepository
{

    /**
     * 該当する受注明細を取得(この関数自体、金額に差異があるかどうかの条件は含まれていない)
     *
     * @param TaxRule $taxRule
     * @param \DateTime|null $from
     * @param \DateTime|null $to
     * @return array
     */
    public function getOrderDetail(TaxRule $taxRule, \DateTime $from = null, \DateTime $to = null)
    {

        $qb = $this->createQueryBuilder('od')
            ->innerJoin('od.Order', 'o')
            ->andWhere('o.OrderStatus <> 7 AND o.OrderStatus <> 8')
            ->andWhere('od.tax_rule <> :taxRule')
            ->setParameter('taxRule', $taxRule->getCalcRule()->getId());

        if ($from) {
            $qb
                ->andWhere('o.create_date >= :from')
                ->setParameter('from', $from);
        } else {
            $qb
                ->andWhere('o.create_date >= :from')
                ->setParameter('from', '2016/10/24');
        }

        if ($to) {
            $date = clone $to;
            $date->modify('+1 days')->format('Y-m-d H:i:s');

            $qb
                ->andWhere('o.create_date < :to')
                ->setParameter('to', $date);
        }


        $OrderDetails = $qb
            ->getQuery()
            ->getResult();

        return $OrderDetails;

    }

}