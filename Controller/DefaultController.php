<?php
namespace VichImagineBundle\Controller;

use AppBundle\Entity\PostMedia;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class DefaultController
 * @package VichImagineBundle\Controller
 *
 *
 * @Route("/vichimagine/fineuploader/{bundle}/{entity}/{property}/{id}/{fk_bundle}/{fk_entity}", defaults={
 *     "bundle" = "app", "fk_bundle" = "app", "fk_entity" = "NULL"
 * })
 */
class DefaultController extends Controller
{
	/**
	 * @Route("/test")
	 */
	public function test()
	{
		/*$em = $this->getDoctrine()->getManager();
		$media = $em->getRepository('AppBundle:PostMedia')->findOneBy(array());
		$validator = $this->get('validator');

		$violations = $validator->validatePropertyValue($media, 'video_url', NULL);

		$v_meta = $validator->getMetadataFor(get_class($media));
		foreach($v_meta->members['image_name_file'])
		pre($v_meta->members['image_name_file']);

		$result = array('success' => NULL, 'message' => NULL);

		if ($violations->count() > 0) {
			 @var \Symfony\Component\Validator\ConstraintViolation $violation
			foreach ($violations as $violation) {
				pre($violation->getConstraint());
				$result['success'] = false;
				$result['message'] .= $violation->getMessage();
			}

			pre($result);
		}*/

		exit;

	}

	protected function createFileList($entities, PropertyMapping $propertyMapping)
	{
		$array = array();
		foreach($entities as $entity) {
			$array[] = array(
				'uuid' => uniqid(),
				'name' => $this->get('vichimagine.file')->fileName($entity, $propertyMapping->getMappingName()),
				'thumbnailUrl' => $this->get('vichimagine.file')->asset($entity, $propertyMapping->getMappingName())
			);
		}

		return $array;
	}

	/**
	 * @Route("/filelist", name="vi_fineuploader_list")
	 *
	 * @param Request $request
	 * @param $bundle
	 * @param $entity
	 * @param $property
	 * @param $id
	 * @param $fk_bundle
	 * @param $fk_entity
	 *
	 * @return JsonResponse
	 */
	public function getFileList(Request $request, $bundle, $entity, $property, $id, $fk_bundle, $fk_entity)
	{
		$em = $this->getDoctrine()->getManager();

		// entity
		$entity_info = $this->get('itf.admin_helper')->getEntityInfo($entity);
		$entity = $em->getRepository($entity_info['repository'])->find($id);

		// if not foreign joined
		if ($fk_entity == 'NULL') {
			$property_mapping = $this->get('vichimagine.uploader')->vichGetPropertyMapping($entity, $property);

			return new JsonResponse($this->createFileList(array($entity), $property_mapping));
		} else {
			// fk_entity
			$fk_entity_info = $this->get('itf.admin_helper')->getEntityInfo($fk_entity);
			$fk_entity = $this->get('itf.admin_helper')->getEntityInstanceByParam($fk_bundle, $fk_entity);
			$fk_entities = $em->getRepository($fk_entity_info['repository'])->findBy(array(
				strtolower($entity_info['entity_short']) => $entity
			));
			$property_mapping = $this->get('vichimagine.uploader')->vichGetPropertyMapping($fk_entity, $property);

			return new JsonResponse($this->createFileList($fk_entities, $property_mapping));
		}
	}

	/**
	 * @Route("/upload", name="vi_fineuploader_upload")
	 *
	 * @param Request $request
	 * @param $bundle
	 * @param $entity
	 * @param $property
	 * @param $id
	 * @param $fk_bundle
	 * @param $fk_entity
	 *
	 * @return JsonResponse
	 */
	public function doUpload(Request $request, $bundle, $entity, $property, $id, $fk_bundle, $fk_entity)
	{
		$attribute = 'qqfile';
		$qquuid = $request->request->get('qquuid');
		$em = $this->getDoctrine()->getManager();
		$accessor = $this->get('property_accessor');

		/* @var UploadedFile $file */
		$file = $request->files->get($attribute);

		// prepare result
		$result = array(
			'success' => false,
			'file_name' => NULL,
			'error' => NULL,
			'qquuid' => $qquuid
		);

		// info about current entity
		$entity_info = $this->get('itf.admin_helper')->getEntityInfo($entity);
		$entity = $em->getRepository($entity_info['repository'])->find($id);

		// if not foreign joined
		if ($fk_entity == 'NULL') {
			// nothing yet
		} else {
			$parent = $entity;
			$entity = $this->get('itf.admin_helper')->getEntityInstanceByParam($fk_bundle, $fk_entity);

			// set parent
			$accessor->setValue($entity, strtolower($entity_info['entity_short']), $parent);
			unset($parent);
		}

		// get property name
		$property_mapping = $this->get('vichimagine.uploader')->vichGetPropertyMapping($entity, $property);
		$property_name = $property_mapping->getFileNamePropertyName();

		/* validate */
		$validator = $this->get('validator');
		$violations = $validator->validatePropertyValue($entity, $property, $file);
		if ($violations->count() > 0) {
			/* @var \Symfony\Component\Validator\ConstraintViolation $violation */
			foreach ($violations as $violation) {
				//pre($violation->getConstraint());
				$result['success'] = false;
				$result['error'] .= $violation->getMessage();
			}

			return new JsonResponse($result);
		}

		// upload
		try {
			$file_name = $this->get('vichimagine.uploader')->upload($file, $property_mapping);

			$result['success'] = true;
			$result['name'] = $file_name;

			if ($file_name !== NULL) {
				$accessor->setValue($entity, $property_name, $file_name);
				$em->persist($entity);
				$em->flush();
			}

			// if error, remove file
			if (!$result['success'] || $file_name == NULL) {
				$this->get('vichimagine.uploader')->delete($file_name, $property_mapping);
			}

		} catch (\Exception $e) {
			$result['error'] = $e->getMessage();
		}


		return new JsonResponse($result);
	}

	/**
	 * @Route("/delete", name="vi_fineuploader_delete")
	 *
	 * @param Request $request
	 * @param $bundle
	 * @param $entity
	 * @param $property
	 * @param $id
	 * @param $fk_bundle
	 * @param $fk_entity
	 *
	 * @return JsonResponse
	 */
	public function doDelete(Request $request, $bundle, $entity, $property, $id, $fk_bundle, $fk_entity)
	{
		/*if (!$request->isMethod('DELETE')) {
			return new JsonResponse(array(
				'success' => false,
				'error' => 'Method not allowed'
			));
		}*/

		$result = array(
			'success' => false,
			'message' => NULL
		);

		$em = $this->getDoctrine()->getManager();
		$accessor = $this->get('property_accessor');

		// entity
		$entity_info = $this->get('itf.admin_helper')->getEntityInfo($entity);
		$entity = $em->getRepository($entity_info['repository'])->find($id);

		// if not foreign joined
		if ($fk_entity == 'NULL') {
			$property_mapping = $this->get('vichimagine.uploader')->vichGetPropertyMapping($entity, $property);

			$filename = $this->get('vichimagine.file')->fileName($entity, $property_mapping->getMappingName());
			$property_name = $property_mapping->getFileNamePropertyName();

			try {
				$result['success'] = $this->get('vichimagine.uploader')->delete($filename, $property_mapping);

				if ($result['success']) {
					$accessor->setValue($entity, $property_name, NULL);
					$em->persist($entity);
					$em->flush();
				}

			} catch (\Exception $e) {
				$result['message'] = $e->getMessage();
			}

			return new JsonResponse($result);
		} else {
			// get filename
			$filename = $request->request->get('filename');
			//pre($filename);exit;
			//$filename = $request->query->get('filename'); // (debug)

			// fk_entity
			$fk_entity_info = $this->get('itf.admin_helper')->getEntityInfo($fk_entity);
			$fk_entity = $this->get('itf.admin_helper')->getEntityInstanceByParam($fk_bundle, $fk_entity);
			$property_mapping = $this->get('vichimagine.uploader')->vichGetPropertyMapping($fk_entity, $property);

			// renew fk_entity with real instance
			$fk_entity = $em->getRepository($fk_entity_info['repository'])->findOneBy(array(
				strtolower($entity_info['entity_short']) => $entity->getId(),
				$property_mapping->getFileNamePropertyName() => $filename
			));

			if ($fk_entity === NULL) {
				$result['success'] = false;
				$result['message'] = sprintf('Entry not found with post_id:%s and %s:%s', $entity->getId(), $property_mapping->getFileNamePropertyName(), $filename);
				return new JsonResponse($result);
			}

			// remove
			$result['success'] = $this->get('vichimagine.uploader')->delete($filename, $property_mapping);
			$em->remove($fk_entity);
			$em->flush();

			return new JsonResponse($result);
		}
	}
}