<?php
namespace VichImagineBundle\Upload\Gaufrette;

use Gaufrette\File;

class MetadataFilesystemWriteAdapter
{
    /** @var \Gaufrette\Filesystem */
    private $filesystem;

    /** @var array */
    private $metadata = array();

    /**
     * MetadataFilesystemWriteAdapter constructor.
     *
     * @param \Gaufrette\Filesystem $filesystem
     */
    public function __construct(\Gaufrette\Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * write file
     *
     * @param $key
     * @param $content
     * @param bool $overwrite
     */
    public function write($key, $content, $overwrite = true)
    {
        $file = new File($key, $this->filesystem);
        $file->setContent($content, $this->getMetadata());
    }

    /**
     * @return \Gaufrette\Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     *
     * @return MetadataFilesystemWriteAdapter
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set metadata by given format (suffix)
     *
     * @param $format
     *
     * @return $this
     */
    public function setMetadataByFormat($format)
    {
        $application = 'binary';
        $type = 'octet-stream';

        switch($format) {
            case 'jpg': /* if jpg -> jpeg */
                $application = 'image';
                $type = 'jpeg';
                break;
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'bmp':
            case 'tiff':
                $application = 'image';
                break;
        }

        $this->setMetadata(array(
            'contentType' => $application . '/' . $type
        ));

        return $this;
    }

}