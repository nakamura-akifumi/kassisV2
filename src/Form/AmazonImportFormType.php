<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class AmazonImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('zipFile', FileType::class, [
                'label' => 'Amazon購入履歴ファイル (Your Orders.zip)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                        'mimeTypes' => [
                            'application/zip',
                            'application/x-zip-compressed',
                            'multipart/x-zip',
                        ],
                        'mimeTypesMessage' => 'ZIPファイルをアップロードしてください',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'accept' => '.zip'
                ],
                'help' => 'Amazonからダウンロードしたご注文履歴ファイル（Your Orders.zip）をアップロードしてください'
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