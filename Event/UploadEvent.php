<?php
namespace VichImagineBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class UploadEvent extends Event
{
    const NAME = 'vichimagine.uploaded';
    
    protected $mapping;
    protected $entity;
    protected $filePath;
    
    public function __construct(PropertyMapping $mapping, $entity, $filePath)
    {
        $this->mapping = $mapping;
        $this->entity = $entity;
        $this->filePath = $filePath;
    }
    
    /**
     * @return PropertyMapping
     */
    public function getMapping(): PropertyMapping
    {
        return $this->mapping;
    }
    
    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }
    
    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }
}