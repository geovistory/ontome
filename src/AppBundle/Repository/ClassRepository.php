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

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findAncestorsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "WITH tw1 AS
                (
                  SELECT pk_parent,
                     parent_identifier,
                     DEPTH,
                     ARRAY_TO_STRING(_path,'|') ancestors
                  FROM che.ascendant_class_hierarchy(:class)
                )
                SELECT tw1.pk_parent AS id,
                       tw1.parent_identifier AS identifier,
                       tw1.DEPTH,
                       che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                       (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM tw1,
                     che.associates_namespace asnsp,
                     che.namespace nsp
                WHERE asnsp.fk_class = tw1.pk_parent
                AND   nsp.pk_namespace = asnsp.fk_namespace
                GROUP BY tw1.pk_parent,
                     tw1.parent_identifier,
                     tw1.depth,
                     nsp.pk_namespace
                ORDER BY depth DESC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findDescendantsById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	pk_child AS id,
                        child_identifier AS identifier,
                        depth,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM 	che.descendant_class_hierarchy(:class) cls, 
                    che.associates_namespace asnsp,
                        che.namespace nsp
                WHERE 	asnsp.fk_class = cls.pk_child
                AND   	nsp.pk_namespace = asnsp.fk_namespace
                GROUP BY pk_child, child_identifier, depth, nsp.pk_namespace, che.get_root_namespace(nsp.pk_namespace)
                ORDER BY depth ASC;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @param OntoClass $class
     * @return array
     */
    public function findEquivalencesById(OntoClass $class){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT 	fk_associated_class AS id,
                        identifier_in_namespace AS identifier,
                        che.get_root_namespace(nsp.pk_namespace) AS \"rootNamespaceId\",
                        (SELECT label FROM che.get_namespace_labels(che.get_root_namespace(nsp.pk_namespace)) WHERE language_iso_code = 'en') AS \"rootNamespaceLabel\"
                FROM  che.get_equivalent_classes(:class) cls,
                      che.associates_namespace asnsp,
                      che.namespace nsp
                WHERE 	asnsp.fk_class = cls.fk_associated_class
                 AND   	nsp.pk_namespace = asnsp.fk_namespace;";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array('class' => $class->getId()));

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findClassesTree(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(tree)) AS json FROM che.tree_classes_with_css_color(214) tree";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @return array
     */
    public function findClassesTreeLegend(){
        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = "SELECT array_to_json(array_agg(legend)) AS json FROM (SELECT pk_css_color, css_color, label FROM che.css_color WHERE context = 'ontologies_classes_tree' ORDER BY pk_css_color) legend;";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}