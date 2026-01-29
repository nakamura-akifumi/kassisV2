<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberFileExportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('format', ChoiceType::class, [
            'label' => 'エクスポート形式',
            'choices' => [
                'Excel（.xlsx）' => 'xlsx',
                'CSV（.csv）' => 'csv',
            ],
            'expanded' => true,
            'multiple' => false,
            'data' => 'xlsx',
            'attr' => [
                'class' => 'form-check',
            ],
        ]);

        $builder->add('columns', CollectionType::class, [
            'entry_type' => TextType::class,
            'allow_add' => true,
            'mapped' => false,
            'required' => false,
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
