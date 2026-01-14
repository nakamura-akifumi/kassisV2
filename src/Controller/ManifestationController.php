<?php

namespace App\Controller;

use App\Entity\Manifestation;
use App\Entity\ManifestationAttachment;
use App\Form\AttachmentUploadFormType;
use App\Form\ManifestationType;
use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/manifestation')]
final class ManifestationController extends AbstractController
{
    #[Route('/manifestation_search', name: 'app_manifestation_search', methods: ['GET'])]
    public function search(Request $request, ManifestationRepository $manifestationRepository): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);
        $viewMode = $request->query->get('view_mode', 'list') ?: 'list';

        if ($request->isXmlHttpRequest() && $viewMode === 'grid') {
            $data = [];
            foreach ($manifestations as $m) {
                $data[] = [
                    'id' => $m->getId(),
                    'title' => $m->getTitle(),
                    'identifier' => $m->getIdentifier(),
                    'externalIdentifier1' => $m->getExternalIdentifier1(),
                    'purchaseDate' => $m->getPurchaseDate()?->format('Y-m-d'),
                ];
            }
            return $this->json($data);
        }

        return $this->render('manifestation/search.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
            'view_mode' => $viewMode,
        ]);
    }

    #[Route('/manifestation', name: 'app_manifestation_index', methods: ['GET'])]
    public function index(Request $request, ManifestationRepository $manifestationRepository): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);
        $viewMode = $request->query->get('view_mode', 'list');
        if ($viewMode === null) {
            $viewMode = 'list';
        }

        return $this->render('manifestation/index.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
            'view_mode' => $viewMode,
        ]);
    }

    #[Route('/new', name: 'app_manifestation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $manifestation = new Manifestation();
        $form = $this->createForm(ManifestationType::class, $manifestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($manifestation);
            $entityManager->flush();

            return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/new.html.twig', [
            'manifestation' => $manifestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_manifestation_show', methods: ['GET', 'POST'])]
    public function show(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(AttachmentUploadFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $attachmentFile = $form->get('attachment')->getData();

            if ($attachmentFile) {
                $originalFilename = pathinfo($attachmentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $attachmentFile->guessExtension();

                // ファイルを移動する前に情報を取得する
                $fileSize = $attachmentFile->getSize();
                $mimeType = $attachmentFile->getMimeType();

                try {
                    $attachmentFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/attachments',
                        $newFilename
                    );

                    $attachment = new ManifestationAttachment();
                    $attachment->setManifestation($manifestation);
                    $attachment->setFileName($attachmentFile->getClientOriginalName());
                    $attachment->setFilePath('uploads/attachments/' . $newFilename);
                    $attachment->setFileSize($fileSize);
                    $attachment->setMimeType($mimeType);

                    $entityManager->persist($attachment);
                    $entityManager->flush();

                    $this->addFlash('success', 'ファイルを添付しました。');
                } catch (FileException $e) {
                    $this->addFlash('error', 'ファイルのアップロードに失敗しました。');
                }
            }

            return $this->redirectToRoute('app_manifestation_show', ['id' => $manifestation->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/show.html.twig', [
            'manifestation' => $manifestation,
            'attachment_form' => $form->createView(),
        ]);
    }

    #[Route('/attachment/{id}/delete', name: 'app_manifestation_attachment_delete', methods: ['POST'])]
    public function deleteAttachment(Request $request, ManifestationAttachment $attachment, EntityManagerInterface $entityManager): Response
    {
        $manifestationId = $attachment->getManifestation()->getId();

        if ($this->isCsrfTokenValid('delete' . $attachment->getId(), $request->request->get('_token'))) {
            // ファイルの削除
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $attachment->getFilePath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $entityManager->remove($attachment);
            $entityManager->flush();
            $this->addFlash('success', '添付ファイルを削除しました。');
        }

        return $this->redirectToRoute('app_manifestation_show', ['id' => $manifestationId]);
    }

    #[Route('/{id}/edit', name: 'app_manifestation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ManifestationType::class, $manifestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manifestation/edit.html.twig', [
            'manifestation' => $manifestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_manifestation_delete', methods: ['POST'])]
    public function delete(Request $request, Manifestation $manifestation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $manifestation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($manifestation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_manifestation_index', [], Response::HTTP_SEE_OTHER);
    }
}
