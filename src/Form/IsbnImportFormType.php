<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class IsbnImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('isbn', TextType::class, [
                'label' => 'ISBN',
                'attr' => [
                    'placeholder' => '例: 978-4-7981-7371-6',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'ISBNを入力してください',
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9X\-]+$/',
                        'message' => 'ISBNは数字、X、ハイフンのみ使用できます',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
