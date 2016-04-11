<?php
namespace VichImagineBundle\Upload;

use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class Provider extends AbstractServiceSetter
{
    /** @var PropertyMapping $mapping */
    private $mapping;

    public function setMapping(PropertyMapping $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * @return PropertyMapping
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    private function needsMappingSet(PropertyMapping $mapping = null)
    {
        if ($mapping !== null) $this->setMapping($mapping);

        if (!$this->getMapping() instanceof PropertyMapping) {
            throw new \LogicException("Property Mapping needs to be set");
        }
    }

    public function getFilesystem(PropertyMapping $mapping = null)
    {
        $this->needsMappingSet($mapping);

        switch($this->getConfig()['storage']) {
            case 'gaufrette':
                return $this->getContainer()->get('knp_gaufrette.filesystem_map')->get($this->getMapping()->getUploadDestination());
                break;

            case 'file_system':
            default:
                $adapter = new Local($this->getContainer()->getParameter('kernel.root_dir').'/../web');
                return new Filesystem($adapter);
                break;
        }
    }

    public function getRelativeDir(PropertyMapping $mapping = null)
    {
        $this->needsMappingSet($mapping);

        switch($this->getConfig()['storage']) {
            case 'gaufrette':
                $namer = $this->getMapping()->getNamer();

                if (method_exists($namer, 'getDirectory')) {
                    return $namer->getDirectory();
                }

                break;
        }

        return $this->getMapping()->getUriPrefix();
    }
}