<?php
/**
 * Created by PhpStorm.
 * User: Djamel
 * Date: 12/06/2017
 * Time: 17:15
 */

namespace AppBundle\Repository;


use AppBundle\Entity\OntoClass;
use Doctrine\ORM\EntityRepository;

class ClassRepository extends EntityRepository
{
    /**
     * @return OntoClass[]
     */
    public function findAllOrderedById()
    {
        return $this->createQueryBuilder('class')
            ->join('class.namespaces','nspc')
            ->addSelect('nspc')
            ->leftJoin('nspc.referencedVersion', 'referencedVersion')
            ->addSelect('referencedVersion')
            ->orderBy('class.id','DESC')
            ->getQuery()
            ->execute();

    }
}