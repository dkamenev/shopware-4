<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Models
 * @subpackage Banner
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

namespace   Shopware\Models\Banner;
use         Shopware\Components\Model\ModelRepository;

/**
 * Repository for the banner model (Shopware\Models\Banner\Banner).
 * <br>
 * The banner model repository is responsible to load all banner data.
 *
 * @category   Shopware
 * @package    Shopware_Models
 * @subpackage Banner
 * @author     J.Schwehn
 * @copyright  Copyright (c) 2011, shopware AG (http://www.shopware.de)
 * @license    http://enlight.de/license     New BSD License
 */
class Repository extends ModelRepository
{
    /**
     * Loads all banners without any live shopping banners. The $filter parameter can
     * be used to narrow the selection down to a category id.
     *
     * @param null $filter
     * @return \Doctrine\ORM\Query
     */
    public function getBanners($filter=null)
    {
        $builder = $this->getBannerMainQuery($filter);
        $builder->andWhere('banner.liveShoppingId = ?2')->setParameter(2, 0);

        return $builder->getQuery();
    }

    /**
     * Returns all banners for a given category which are still
     * valid including liveshopping banners.
     * The amount of returned banners can be with the $limit parameter.
     *
     *
     * @param integer $filter Category ID
     * @param integer $limit Limit
     * @param bool $randomize
     * @return mixed
     */
    public function getAllActiveBanners($filter=null, $limit=0, $randomize=false)
    {
        $builder = $this->getBannerMainQuery($filter);
        $today = new \DateTime();
        $builder->andWhere(
            $builder->expr()->orX(
                $builder->expr()->lte('banner.validFrom','?3'),
                $builder->expr()->orX(
                    $builder->expr()->eq('banner.validFrom', '?4'),
                    $builder->expr()->isNull('banner.validFrom')
                )
            )
        )->setParameter(3, $today)->setParameter(4, null);
        $builder->andWhere(
            $builder->expr()->orX(
                $builder->expr()->gte('banner.validTo','?5'),
                $builder->expr()->orX(
                    $builder->expr()->eq('banner.validTo', '?6'),
                    $builder->expr()->isNull('banner.validTo')
                )

            )
        )->setParameter(5, $today)
         ->setParameter(6, null);
        $ids = $this->getBannerIds($filter, $limit);
        if (!count($ids)) {
            return false;
        }

        $builder->andWhere($builder->expr()->in('banner.id', '?7'))
                ->setParameter(7, $ids);

        return $builder->getQuery();
    }

    /**
     * Loads all banners without any live shopping banners. The $filter parameter can
     * be used to narrow the selection down to a category id.
     * If the second parameter is set to false only banners which are active will be returned.
     *
     * @param null $filter
     *
     * @return \Doctrine\ORM\Query
     */
    public function getBannerMainQuery($filter=null)
    {
        $builder = $this->createQueryBuilder('banner');
        $builder->select(array('banner', 'attribute'));
        $builder->leftJoin('banner.attribute', 'attribute');
        if (null !== $filter || !empty($filter)) {
            //filter the displayed columns with the passed filter
            $builder->andWhere("banner.categoryId = ?1")
                ->setParameter(1, $filter);
        }

        return $builder;
    }

    /**
     * @param $categoryId
     * @param $limit
     * @return array
     */
    public function getBannerIds($categoryId, $limit=0)
    {
        $builder = $this->createQueryBuilder('banner');
        $today = new \DateTime();
        $builder->andWhere(
            $builder->expr()->orX(
                $builder->expr()->lte('banner.validFrom','?3'),
                $builder->expr()->orX(
                    $builder->expr()->eq('banner.validFrom', '?4'),
                    $builder->expr()->isNull('banner.validFrom')
                )
            )
        )->setParameter(3, $today)->setParameter(4, null);
        $builder->andWhere(
            $builder->expr()->orX(
                $builder->expr()->gte('banner.validTo','?5'),
                $builder->expr()->orX(
                    $builder->expr()->eq('banner.validTo', '?6'),
                    $builder->expr()->isNull('banner.validTo')
                )

            )
        )->setParameter(5, $today)
         ->setParameter(6, null);
        $builder->select(array('banner.id as id'))
            ->andWhere("banner.categoryId = ?1")
            ->setParameter(1, $categoryId);
        $retval = array();
        $data = $builder->getQuery()->getArrayResult();
        foreach ($data as $id) {
            $retval[] = $id['id'];
        }
        shuffle($retval);

        if ($limit > 0) {
            $retval =   array_slice($retval,0,$limit);
        }

        return $retval;
    }
}