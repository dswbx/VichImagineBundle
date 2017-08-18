<?php
namespace VichImagineBundle\Entity\Interfaces;

interface CustomLiipFilterInterface
{
    /**
     * Returns custom filter name for given entity
     *
     * @return string
     */
    public function getFilterName();
}