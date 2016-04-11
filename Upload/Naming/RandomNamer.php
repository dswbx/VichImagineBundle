<?php
namespace VichImagineBundle\Upload\Naming;

use Gaufrette\Exception\FileNotFound;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\ConfigurableInterface;
use Vich\UploaderBundle\Naming\NamerInterface;
use VichImagineBundle\Upload\AbstractServiceSetter;

/**
 * Class RandomNamer
 * @package VichImagineBundle\Upload\Naming
 *
 * @author Dennis Senn <info@interface-f.com>
 */
class RandomNamer extends AbstractServiceSetter implements NamerInterface, ConfigurableInterface
{
	private $options;

	/** @var PropertyMapping $mapping */
	private $mapping;

	/**
	 * Injects configuration options.
	 *
	 * @param array $options The options.
	 */
	public function configure(array $options)
	{
		$this->options = $options;
	}

	public function name($entity, PropertyMapping $mapping)
	{
		$this->mapping = $mapping;

		/* @var UploadedFile $file */
		$file = $mapping->getFile($entity);
		$file_name = $file->getClientOriginalName();

		return $this->getRandomFileName($file_name);
	}

	public function getRandomFileName($file_name, PropertyMapping $mapping = null)
	{
		if ($mapping !== null) $this->mapping = $mapping;

		if (!$this->getMapping() instanceof PropertyMapping) {
			throw new \LogicException(sprintf("property mapping must be set to get random file name"));
		}

		// get extension
		$extension = substr($file_name, strrpos($file_name, '.') + 1);

		// create random_string
		$random_string = time() . '_';
		$random_string .= substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVW0123456789"), 0, 20);
		$new_file_name = $random_string . '.' . $extension;

		if ($this->fileExists($new_file_name)) {
			return $this->getRandomFileName($file_name);
		}

		return $new_file_name;
	}

	private function fileExists($fileName)
	{
		$storage = $this->getConfig()['storage'];

		switch($storage) {
			case 'gaufrette':
				$filesystem = $this->getContainer()->get('knp_gaufrette.filesystem_map')->get($this->getMapping()->getUploadDestination());
				$fileName = $this->getDirectory() . '/' . $fileName;

				// try to get file
				try {
					$filesystem->get($fileName);
					return true; // exists

				} catch (FileNotFound $e) {
					return false; // does not exist
				}

				break;

			case 'file_system':
			default:
				return is_file($this->getMapping()->getUriPrefix() . '/' . $fileName);
				break;
		}
	}

	public function getDirectory()
	{
		return @$this->options['directory'];
	}

	/**
	 * @return PropertyMapping
	 */
	private function getMapping()
	{
		return $this->mapping;
	}
}