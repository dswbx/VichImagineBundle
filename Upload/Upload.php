<?php
namespace VichImagineBundle\Upload;

use Gaufrette\Adapter\MetadataSupporter;
use Gaufrette\Exception\FileNotFound;
use Gaufrette\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use VichImagineBundle\Upload\Gaufrette\MetadataFilesystemWriteAdapter;

class Upload extends AbstractServiceSetter
{
	private $liip_data_manager;
	private $liip_filter_manager;

	public function __construct(ContainerInterface $container)
	{
		parent::setContainer($container);

		$this->liip_data_manager = $container->get('liip_imagine.data.manager');
		$this->liip_filter_manager = $container->get('liip_imagine.filter.manager');
	}

	/**
	 * @param PropertyMapping $mapping
	 *
	 * @return Provider
	 */
	public function getProvider(PropertyMapping $mapping)
	{
		$provider = $this->getContainer()->get('vichimagine.provider');
		$provider->setMapping($mapping);

		return $provider;
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

	public function vichGetPropertyMapping($entity, $property_name)
	{
		return $this->getContainer()->get('vich_uploader.property_mapping_factory')->fromField($entity, $property_name);
	}

	public function vichGetMappingConfig($mapping_name)
	{
		$mapping = $this->getContainer()->getParameter('vich_uploader.mappings');

		return (isset($mapping[$mapping_name])) ? $mapping[$mapping_name] : false;
	}

	public function delete($filename, PropertyMapping $propertyMapping)
	{
		$provider = $this->getProvider($propertyMapping);
		$filesystem = $provider->getFilesystem();
		$file = $provider->getRelativeDir() . '/' . $filename;

		// ignore FileNotFound Exception, but \RuntimeException
		try {
			if ($filesystem->read($file)) {
				$filesystem->delete($file);
			}
		} catch (FileNotFound $e) {}

		return true;
	}

	public function deleteEntityFile($entity, PropertyMapping $propertyMapping)
	{

	}

	public function upload(UploadedFile $file, PropertyMapping $propertyMapping, $filter_name = NULL)
	{
		if ($filter_name === NULL) {
			$filter_name = $propertyMapping->getMappingName();
		}

		// vars
		$provider = $this->getProvider($propertyMapping);

		$filesystem = $provider->getFilesystem();
		$relative_dir = $provider->getRelativeDir(); //$propertyMapping->getUriPrefix();
		$file_name = $file->getClientOriginalName();

		// if namer set
		$mapping_config = $this->vichGetMappingConfig($propertyMapping->getMappingName());
		if ($mapping_config) {
			$namer = $mapping_config['namer'];

			// illegal offset warning (backwards compatibility)
			if (is_array($namer) && isset($namer['service'])) {
				$namer = $namer['service'];
			}

			if ($this->getContainer()->has($namer)) {
				$namer = $this->getContainer()->get($namer);
				if (method_exists($namer, 'getRandomFileName')) {
					$file_name = $namer->getRandomFileName($file_name, $propertyMapping);
				}
			}
		}

		// upload
		$uploaded = $filesystem->write($relative_dir . '/' . $file_name, file_get_contents($file->getPathname()));
		//$uploaded = $file->move($upload_dir, $file_name);

		if ($uploaded) {
			// apply filter
			return $this->doApplyFilter($file_name, $propertyMapping);
		}

		return false;
	}

	public function uploadByUrl($url, $entity, $file_property_name, $set_file = false)
	{
		$propertyMapping = $this->vichGetPropertyMapping($entity, $file_property_name);

		// get image
		if (function_exists('curl_init')){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$imageRaw = curl_exec($ch);
			curl_close($ch);
		} else {
			$imageRaw = file_get_contents($url);
		}

		// get old filename
		if (!preg_match('/([0-9a-zA-Z\_\-]*?\.jpe?g|png|gif)/', $url, $m)) {
			throw new \Exception("cannot extract filename from url: ".$url);
		}

		// init
		$provider = $this->getProvider($propertyMapping);
		$filesystem = $provider->getFilesystem();
		$relative_dir = $provider->getRelativeDir();

		$oldFileName = $m[0];
		$newFileName = $this->getContainer()->get('vich.custom.random_namer')->getRandomFileName($oldFileName, $propertyMapping);
		$fileLocationAbsolute = $relative_dir . '/' . $newFileName;

		// save file in upload_destination
		$filesystem->write($fileLocationAbsolute, $imageRaw, true);

		// apply filter
		if ($this->doApplyFilter($newFileName, $propertyMapping)) {
			// set file name to entity
			if ($set_file) {
				$this->getContainer()->get('property_accessor')->setValue($entity, $propertyMapping->getFileNamePropertyName(), $newFileName);
				$this->getEntityManager()->persist($entity);
				$this->getEntityManager()->flush();
			}

			return $newFileName;
		}

		return false;
	}

	protected function getWebDirectory()
	{
		return $this->getContainer()->getParameter('kernel.root_dir').'/../web';
	}

	/**
	 * @param string $relative_path        without ending /
	 * @param string $file_name            filename
	 * @param string $filter               imagine filter name
	 *
	 * @return string
	 * @deprecated
	 */
	public function applyFilter($relative_path, $file_name, $filter)
	{
		$file_relative = $relative_path . '/' . $file_name;
		$file_absolute = $_file_absolute = $this->getWebDirectory() . $file_relative;

		// resize
		$image = $this->liip_data_manager->find($filter, $file_relative);
		$response = $this->liip_filter_manager->applyFilter($image, $filter);
		$format = $response->getFormat();

		// replace extension & update
		$file_absolute = $this->replaceExtension($file_absolute, $format);
		$file_name = $this->replaceExtension($file_name, $format);
		//$this->updateFileName($file_name);

		// write
		unlink($_file_absolute);
		$handle = fopen($file_absolute, 'w');
		fwrite($handle, $response->getContent());
		fclose($handle);

		return $file_name;
	}

	public function doApplyFilter($file_name, PropertyMapping $mapping)
	{
		// init
		$provider = $this->getProvider($mapping);
		$filesystem = $provider->getFilesystem();
		$file_relative = $provider->getRelativeDir() . '/' . $file_name;
		$filter = $mapping->getMappingName();

		// resize
		$image = $this->liip_data_manager->find($filter, $file_relative);
		$response = $this->liip_filter_manager->applyFilter($image, $filter);
		$format = $response->getFormat();

		// replace extension & update
		$file_name = $this->replaceExtension($file_name, $format);

		// remove if exists
		if ($filesystem->read($file_relative)) {
			$filesystem->delete($file_relative);
		}
		
		// if metadata supported, replace filesystem and set metadata
		if ($filesystem->getAdapter() instanceof MetadataSupporter) {
			$filesystem = new MetadataFilesystemWriteAdapter($filesystem);
			$filesystem->setMetadataByFormat($format);
		}
		
		// write
		$filesystem->write($provider->getRelativeDir() . '/' . $file_name, $response->getContent());

		return $file_name;
	}
}
