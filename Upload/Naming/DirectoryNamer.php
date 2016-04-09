<?php
namespace VichImagineBundle\Upload\Naming;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

class DirectoryNamer extends AbstractNamer implements DirectoryNamerInterface
{
    /**
     * Creates a directory name for the file being uploaded.
     *
     * @param object          $entity  The object the upload is attached to.
     * @param PropertyMapping $mapping The mapping to use to manipulate the given object.
     *
     * @return string The directory name.
     */
    public function directoryName($entity, PropertyMapping $mapping)
    {
        $namer = $mapping->getNamer();

        if (method_exists($namer, 'getDirectory')) {
            return $namer->getDirectory();
        }

        return null;
    }
}