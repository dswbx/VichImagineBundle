<?php
namespace VichImagineBundle\Upload;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractServiceSetter
{
    /** @var ContainerInterface $container */
    private $container;

    public function setContainer(ContainerInterface $containerInterface)
    {
        $this->container = $containerInterface;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }

    protected function getConfig()
    {
        return $this->getContainer()->getParameter('vich_imagine');
    }
}