<?php

namespace App\Controller;

use App\Entity\Manifestation;
use App\Form\ManifestationType;
use App\Repository\ManifestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/manifestation')]
final class ManifestationController extends AbstractController
{
    #[Route('/manifestation_search', name: 'app_manifestation_search', methods: ['GET'])]
    public function search(Request $request, ManifestationRepository $manifestationRepository): Response
    {
        $title = $request->query->get('title');
        $identifier = $request->query->get('identifier');
        $external_id1 = $request->query->get('external_id1');
        $external_id2 = $request->query->get('external_id2');
        $external_id3 = $request->query->get('external_id3');
        $description = $request->query->get('description');
        $purchase_date_from = $request->query->get('purchase_date_from');
        $purchase_date_to = $request->query->get('purchase_date_to');

        // 単純な検索
        $q = $request->query->get('q');

        if ($q || $title || $identifier || $external_id1 || $external_id2 || $external_id3 || $description || $purchase_date_from || $purchase_date_to) {
            $manifestations = $manifestationRepository->advancedSearch(
                $q, $title, $identifier, $external_id1, $external_id2, $external_id3,
                $description, $purchase_date_from, $purchase_date_to
            );
        } else {
            $manifestations = $manifestationRepository->findAll();
        }

        return $this->render('manifestation/search.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
        ]);
    }

    #[Route('/manifestation', name: 'app_manifestation_index', methods: ['GET'])]
    public function index(Request $request, ManifestationRepository $manifestationRepository): Response
    {
        $title = $request->query->get('title');
        $identifier = $request->query->get('identifier');
        $external_id1 = $request->query->get('external_id1');
        $external_id2 = $request->query->get('external_id2');
        $external_id3 = $request->query->get('external_id3');
        $description = $request->query->get('description');
        $purchase_date_from = $request->query->get('purchase_date_from');
        $purchase_date_to = $request->query->get('purchase_date_to');

        // 単純な検索
        $q = $request->query->get('q');

        if ($q || $title || $identifier || $external_id1 || $external_id2 || $external_id3 || $description || $purchase_date_from || $purchase_date_to) {
            $manifestations = $manifestationRepository->advancedSearch(
                $q, $title, $identifier, $external_id1, $external_id2, $external_id3,
                $description, $purchase_date_from, $purchase_date_to
            );
        } else {
            $manifestations = $manifestationRepository->findAll();
        }

        return $this->render('manifestation/index.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
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

    #[Route('/{id}', name: 'app_manifestation_show', methods: ['GET'])]
    public function show(Manifestation $manifestation): Response
    {
        return $this->render('manifestation/show.html.twig', [
            'manifestation' => $manifestation,
        ]);
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

