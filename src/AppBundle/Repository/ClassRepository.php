<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:15
 */

namespace AppBundle\Repository;


use AppBundle\Entity\CRMClass;
use Doctrine\ORM\EntityRepository;

class ClassRepository extends EntityRepository
{
    /**
     * @return CRMClass[]
     */
    public function findAllOrderedById()
    {
        return $this->createQueryBuilder('class')
            ->orderBy('class.id','DESC')
            ->getQuery()
            ->execute();

    }
}