<?php

namespace App\Controller;

use App\Form\MemberFileExportFormType;
use App\Form\MemberFileImportFormType;
use App\Repository\MemberRepository;
use App\Service\MemberFileColumns;
use App\Service\MemberFileService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class MemberExportImportController extends AbstractController
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[Route('/member/file/import', name: 'app_member_file_import', methods: ['GET', 'POST'])]
    public function import(Request $request, MemberFileService $fileService): Response
    {
        $form = $this->createForm(MemberFileImportFormType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadFile = $form->get('uploadFile')->getData();

            if ($uploadFile) {
                $result = $fileService->importMembersFromFile($uploadFile);

                if ($result['errors'] === 0 && $result['success'] > 0) {
                    $this->addFlash('success', sprintf('インポート完了: %d件（スキップ: %d件）', $result['success'], $result['skipped']));
                } elseif ($result['errors'] > 0) {
                    $this->addFlash('danger', 'インポート中にエラーが発生しました。結果を確認してください。');
                } else {
                    $this->addFlash('warning', 'インポート対象がありませんでした。');
                }
            } else {
                $this->addFlash('warning', 'ファイルを選択してください。');
            }
        }

        return $this->render('member/import.html.twig', [
            'form' => $form->createView(),
            'result' => $result,
        ]);
    }

    #[Route('/member/file/export', name: 'app_member_file_export', methods: ['GET', 'POST'])]
    public function export(Request $request, MemberRepository $memberRepository, MemberFileService $fileService): Response
    {
        $form = $this->createForm(MemberFileExportFormType::class);
        $form->handleRequest($request);

        $isAjax = $request->isXmlHttpRequest();

        if (!$form->isSubmitted()) {
            return $this->render('member/export.html.twig', [
                'form' => $form->createView(),
                'exportFields' => MemberFileColumns::getExportFormFields(),
                'requiredColumns' => MemberFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }

        if (!$form->isValid()) {
            if ($isAjax) {
                $errors = [];
                foreach ($form->getErrors(true, true) as $error) {
                    $errors[] = $error->getMessage();
                }

                return new JsonResponse([
                    'message' => '入力内容にエラーがあります。内容を確認してください。',
                    'errors' => $errors,
                ], 422);
            }

            return $this->render('member/export.html.twig', [
                'form' => $form->createView(),
                'exportFields' => MemberFileColumns::getExportFormFields(),
                'requiredColumns' => MemberFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }

        try {
            $this->logger->info('Member export start');
            $format = (string) $form->get('format')->getData();
            $members = $memberRepository->findBy([], ['id' => 'DESC']);
            $columns = $form->get('columns')->getData();

            $tempFile = $fileService->generateExportFile($members, $format, $columns);

            $baseName = 'members_' . date('Y-m-d_H-i-s');
            $fileName = $baseName . ($format === 'csv' ? '.csv' : '.xlsx');

            return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        } catch (\Throwable $e) {
            $this->logger->error('Member export failed', [
                'error' => $e->getMessage(),
            ]);

            if ($isAjax) {
                return new JsonResponse([
                    'message' => 'エクスポート中にエラーが発生しました。時間をおいて再度お試しください。',
                ], 500);
            }

            $this->addFlash('danger', 'エクスポート中にエラーが発生しました。');
            return $this->render('member/export.html.twig', [
                'form' => $form->createView(),
                'exportFields' => MemberFileColumns::getExportFormFields(),
                'requiredColumns' => MemberFileColumns::REQUIRED_EXPORT_KEYS,
            ]);
        }
    }
}
