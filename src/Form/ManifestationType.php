<?php

namespace App\Form;

use App\Entity\Manifestation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManifestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('title_transcription')
            ->add('identifier')
            ->add('external_identifier1')
            ->add('external_identifier2')
            ->add('external_identifier3')
            ->add('description')
            ->add('buyer')
            ->add('buyer_identifier')
            ->add('purchase_date', null, [
                'widget' => 'single_text',
            ])
            ->add('type1')
            ->add('type2')
            ->add('type3')
            ->add('type4')
            ->add('location1')
            ->add('location2')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Manifestation::class,
        ]);
    }
}
