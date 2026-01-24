<?php

namespace App\Controller;

use App\Form\IsbnImportFormType;
use App\Service\NdlImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NdlImportController extends AbstractController
{
    #[Route('/manifestation/import/isbn', name: 'app_manifestation_import_isbn')]
    public function importIsbn(
        Request $request,
        NdlImportService $ndlImportService
    ): Response {
        $form = $this->createForm(IsbnImportFormType::class);
        $form->handleRequest($request);

        $result = null;
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $isbn = $form->get('isbn')->getData();

            $existing = $ndlImportService->findExistingByIsbn((string) $isbn);
            if ($existing !== null) {
                $error = sprintf('すでに同じISBNの資料があります。ISBN (%s)', (string) $isbn);
                return $this->render('import/isbn.html.twig', [
                    'form' => $form->createView(),
                    'error' => $error,
                ]);
            }

            $manifestation = $ndlImportService->importByIsbn((string) $isbn);

            if ($manifestation) {
                $this->addFlash('success', '書籍「' . $manifestation->getTitle() . '」をインポートしました');

                return $this->redirectToRoute('app_manifestation_show', [
                    'id' => $manifestation->getId(),
                ]);
            }

            $error = 'ISBNに該当する書籍が見つかりませんでした';
        }

        return $this->render('import/isbn.html.twig', [
            'form' => $form->createView(),
            'error' => $error,
        ]);
    }
}
