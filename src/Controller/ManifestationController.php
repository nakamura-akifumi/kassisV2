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
}

