<?php
namespace VichImagineBundle\Upload\Naming;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractNamer
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

    protected function getConfig()
    {
        return $this->getContainer()->getParameter('vich_imagine');
    }
}