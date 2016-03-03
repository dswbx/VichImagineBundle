<?php
namespace VichImagineBundle\Upload;

use AppBundle\Library\PN\AbstractServiceSetter;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class File extends AbstractServiceSetter
{
	public function __construct(ContainerInterface $container)
	{
		$this->setContainer($container);
		$this->mapping = $this->getContainer()->get('vich_uploader.property_mapping_factory');
	}

	/**
	 * @param $entity
	 * @param $mapping_name
	 *
	 * @return \Vich\UploaderBundle\Mapping\PropertyMapping
	 */
	protected function getMapping($entity, $mapping_name)
	{
		$mapping = $this->mapping->fromObject($entity, NULL, $mapping_name);

		if (count($mapping) == 0) {
			throw new Exception("error getting property mapping");
		}

		return $mapping[0];
	}

	public function asset($entity, $mapping_name, $default = false)
	{
		$mapping = $this->getMapping($entity, $mapping_name);

		$file = $mapping->getUriPrefix() . '/' . $mapping->getFileName($entity);

		if (!is_file($this->getContainer()->getParameter('kernel.root_dir').'/../web' . $file)) {
			return $default;
		}

		return $file;
	}

	public function fileName($entity, $mapping_name)
	{
		$mapping = $this->getMapping($entity, $mapping_name);

		return $mapping->getFileName($entity);
	}
}