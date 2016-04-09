<?php
namespace VichImagineBundle\EventListener;

use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use Knp\Bundle\GaufretteBundle\DependencyInjection\Factory\LocalAdapterFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

/**
 * Class VichUploadListener
 * @package VichImagineBundle\EventListener
 *
 * @author Dennis Senn <info@interface-f.com>
 */
class VichUploadListener extends Event
{
	private $container;
	private $mapping;
	private $object;
	private $liip_data_manager;
	private $liip_filter_manager;

	/**
	 * VichUploadListener constructor.
	 *
	 * @param ContainerInterface $container
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
		$this->liip_data_manager = $container->get('liip_imagine.data.manager');
		$this->liip_filter_manager = $container->get('liip_imagine.filter.manager');
	}


	/**
	 * @return ContainerInterface
	 */
	private function getContainer()
	{
		return $this->container;
	}

	/**
	 * @return string
	 */
	private function getRelativeDir()
	{
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


	/**
	 * @return string
	 */
	private function getAbsoluteDir()
	{
		return $this->getContainer()->getParameter('kernel.root_dir').'/../web' . $this->getRelativeDir();
	}


	/**
	 * @return string
	 */
	private function getFileName()
	{
		return $this->getMapping()->getFileName($this->getObject());
	}


	/**
	 * @return string
	 */
	private function getFilterName()
	{
		return $this->getMapping()->getMappingName();
	}


	/**
	 * @return \Symfony\Component\HttpFoundation\File\UploadedFile
	 */
	private function getFile()
	{
		return $this->getMapping()->getFile($this->getObject());
	}


	/**
	 * @param $file_name
	 * @param $extension
	 *
	 * @return string
	 */
	private function replaceExtension($file_name, $extension)
	{
		$file_name = substr($file_name, 0, strrpos($file_name, '.'));
		return $file_name . '.' . $extension;
	}


	/**
	 * @param $file_name
	 */
	private function updateFileName($file_name)
	{
		$this->getMapping()->setFileName($this->getObject(), $file_name);
	}

	/**
	 * @return Filesystem
	 */
	private function getFilesystem()
	{
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

	/**
	 * @param \Vich\UploaderBundle\Event\Event $event
	 */
	public function onPostUpload(\Vich\UploaderBundle\Event\Event $event)
	{

		// set event data
		$this->mapping = $event->getMapping();
		$this->object = $event->getObject();

		$filesystem = $this->getFilesystem();

		// if image/*
		if (preg_match('/^image\//', $this->getFile()->getMimeType())) {
			// filter
			$filter = $this->getFilterName();

			// file and paths
			$file_name = $this->getFileName();
			$file_relative = $this->getRelativeDir() . '/' . $file_name;

			// resize
			$image = $this->liip_data_manager->find($filter, $file_relative);
			$response = $this->liip_filter_manager->applyFilter($image, $filter);
			$format = $response->getFormat();

			// replace extension & update
			$file_name = $this->replaceExtension($file_name, $format);
			$this->updateFileName($file_name);

			// write
			$filesystem->write($file_relative, $response->getContent(), true);
		}
	}

	
	private function getConfig()
	{
		return $this->getContainer()->getParameter('vich_imagine');
	}


	/**
	 * @return PropertyMapping
	 */
	private function getMapping()
	{
		return $this->mapping;
	}


	/**
	 * @return \Vich\UploaderBundle\Mapping\PropertyMapping
	 */
	private function getObject()
	{
		return $this->object;
	}
}