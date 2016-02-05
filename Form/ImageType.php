<?php

namespace VichImagineBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class ImageType (Sample Form)
 *
 * @package VichImagineBundle\Form
 */
class ImageType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('imageName')
            ->add('updatedAt')
        ;

        $builder->add('imageFile', 'vich_image', array(
            'required'      => false,
            'allow_delete'  => true, // not mandatory, default is true
            'download_link' => true, // not mandatory, default is true
        ));
    }
    
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'VichImagineBundle\Entity\Image'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'vichimaginebundle_image';
    }
}
