<?php
namespace VichImagineBundle\Upload;

use AppBundle\Library\PN\AbstractServiceSetter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class Upload extends AbstractServiceSetter
{
	private $liip_data_manager;
	private $liip_filter_manager;

	public function __construct(ContainerInterface $container)
	{
		$this->setContainer($container);
		$this->liip_data_manager = $container->get('liip_imagine.data.manager');
		$this->liip_filter_manager = $container->get('liip_imagine.filter.manager');
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
		$file_absolute = $propertyMapping->getUploadDestination() . '/' . $filename;

		if (is_file($file_absolute)) {
			return unlink($file_absolute);
		}

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
		$upload_dir = $propertyMapping->getUploadDestination();
		$relative_dir = $propertyMapping->getUriPrefix();
		$file_name = $file->getClientOriginalName();

		// if namer set
		$mapping_config = $this->vichGetMappingConfig($propertyMapping->getMappingName());
		if ($mapping_config) {
			$namer = $mapping_config['namer'];

			if ($this->getContainer()->has($namer)) {
				$namer = $this->getContainer()->get($namer);
				if (method_exists($namer, 'getRandomFileName')) {
					$file_name = $namer->getRandomFileName($file_name, $propertyMapping->getUploadDestination());
				}
			}
		}

		// upload
		$uploaded = $file->move($upload_dir, $file_name);
		if ($uploaded) {
			// apply filter
			return $this->applyFilter($relative_dir, $file_name, $filter_name);
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

		$oldFileName = $m[0];
		$newFileName = '1452203621_BlL1EnvM04RTbSQW76e8.jpg'; //$this->getContainer()->get('vich.custom.random_namer')->getRandomFileName($oldFileName);
		$fileLocationAbsolute = $this->getWebDirectory() . $propertyMapping->getUriPrefix() . '/' . $newFileName;

		// save file in upload_destination
		$handle = fopen($fileLocationAbsolute, 'w');
		fwrite($handle, $imageRaw);
		fclose($handle);

		// apply filter
		if ($this->applyFilter($propertyMapping->getUriPrefix(), $newFileName, $propertyMapping->getMappingName())) {
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
}