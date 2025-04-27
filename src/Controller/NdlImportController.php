<?php

namespace App\Controller;

use App\Entity\Manifestation;
use App\Form\IsbnImportType;
use App\Service\NdlSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NdlImportController extends AbstractController
{
    #[Route('/manifestation/import/isbn', name: 'app_manifestation_import_isbn')]
    public function importIsbn(
        Request $request, 
        NdlSearchService $ndlSearchService, 
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(IsbnImportType::class);
        $form->handleRequest($request);
        
        $result = null;
        $error = null;
        
        if ($form->isSubmitted() && $form->isValid()) {
            $isbn = $form->get('isbn')->getData();
            
            // ISBNをクリーンアップ（ハイフンを削除）
            $isbn = preg_replace('/[^0-9X]/', '', $isbn);
            
            // NDLサーチで検索
            $bookData = $ndlSearchService->searchByIsbn($isbn);
            
            if ($bookData) {
                // Manifestationエンティティを作成
                $manifestation = $ndlSearchService->createManifestation($bookData);
                
                // データベースに保存
                $entityManager->persist($manifestation);
                $entityManager->flush();
                
                $this->addFlash('success', '書籍「' . $manifestation->getTitle() . '」をインポートしました');
                
                // 詳細ページにリダイレクト
                return $this->redirectToRoute('app_manifestation_show', [
                    'id' => $manifestation->getId(),
                ]);
            } else {
                $error = 'ISBNに該当する書籍が見つかりませんでした';
            }
        }
        
        return $this->render('manifestation/import_isbn.html.twig', [
            'form' => $form->createView(),
            'error' => $error,
        ]);
    }
}