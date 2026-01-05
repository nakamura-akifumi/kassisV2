<?php

namespace App\Controller;

use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request,  ManifestationRepository $manifestationRepository): Response
    {
        $searchQuery = ManifestationSearchQuery::fromRequest($request->query->all());
        $manifestations = $manifestationRepository->searchByQuery($searchQuery);

        return $this->render('manifestation/index.html.twig', [
            'manifestations' => $manifestations,
            'search_params' => $request->query->all(),
        ]);

    }
}