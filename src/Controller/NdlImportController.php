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
        $session = $request->getSession();
        $importedItems = $session->get('isbn_imported_items', []);
        $clearIsbn = $form->isSubmitted();

        if ($form->isSubmitted() && $form->isValid()) {
            $isbn = $form->get('isbn')->getData();
            $continuousImport = (bool) $form->get('continuousImport')->getData();

            $existing = $ndlImportService->findExistingByIsbn((string) $isbn);
            if ($existing !== null) {
                $error = sprintf('すでに同じISBNの資料があります。ISBN (%s)', (string) $isbn);
                return $this->render('import/isbn.html.twig', [
                    'form' => $form->createView(),
                    'error' => $error,
                    'imported_items' => $importedItems,
                    'clear_isbn' => $clearIsbn,
                ]);
            }

            $manifestation = $ndlImportService->importByIsbn((string) $isbn);

            if ($manifestation) {
                if ($continuousImport) {
                    $importedItems = $session->get('isbn_imported_items', []);
                    $importedIsbn = $manifestation->getExternalIdentifier1() ?? $manifestation->getIdentifier() ?? '';
                    array_unshift($importedItems, [
                        'id' => $manifestation->getId(),
                        'title' => $manifestation->getTitle() ?? '',
                        'isbn' => $importedIsbn,
                    ]);
                    $session->set('isbn_imported_items', $importedItems);
                }

                $this->addFlash('success', '書籍「' . $manifestation->getTitle() . '」をインポートしました');

                if ($continuousImport) {
                    return $this->render('import/isbn.html.twig', [
                        'form' => $form->createView(),
                        'error' => null,
                        'imported_items' => $importedItems,
                        'clear_isbn' => $clearIsbn,
                    ]);
                }

                return $this->redirectToRoute('app_manifestation_show', [
                    'id' => $manifestation->getId(),
                ]);
            }

            $error = sprintf('ISBNに該当する書籍が見つかりませんでした。ISBN (%s)', (string) $isbn);
        }

        return $this->render('import/isbn.html.twig', [
            'form' => $form->createView(),
            'error' => $error,
            'imported_items' => $importedItems,
            'clear_isbn' => $clearIsbn,
        ]);
    }
}
