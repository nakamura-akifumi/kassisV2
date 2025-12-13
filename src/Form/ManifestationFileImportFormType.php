<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ManifestationFileImportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('uploadFile', FileType::class, [
            'label' => 'インポートファイル（.xlsx / .csv）',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'maxSize' => '50M',
                    'mimeTypes' => [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ],
                    'mimeTypesMessage' => 'Excel（.xlsx）またはCSV（.csv）ファイルをアップロードしてください',
                ]),
            ],
            'attr' => [
                'class' => 'form-control form-control-sm',
                'accept' => '.xlsx,.csv',
            ],
            'help' => '「/file/export」で出力できる列構成（ID/タイトル/著者/出版社/出版年/ISBN）のファイルをアップロードしてください。',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
