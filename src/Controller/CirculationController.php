<?php

namespace App\Controller;

use App\Service\CirculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CirculationController extends AbstractController
{
    #[Route('/circulation', name: 'app_circulation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('circulation/index.html.twig');
    }

    #[Route('/circulation/reserve', name: 'app_circulation_reserve_page', methods: ['GET'])]
    public function reservePage(): Response
    {
        return $this->render('circulation/reserve.html.twig');
    }

    #[Route('/circulation/reserve', name: 'app_circulation_reserve', methods: ['POST'])]
    public function reserve(Request $request, CirculationService $service): JsonResponse
    {
        $data = $this->getRequestData($request);
        $memberIdentifier = $data['memberIdentifier'] ?? null;
        $manifestationIdentifier = $data['manifestationIdentifier'] ?? null;
        $expiryDate = isset($data['expiryDate']) ? (int) $data['expiryDate'] : null;

        if (!$memberIdentifier || !$manifestationIdentifier) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        try {
            $reservation = $service->reserve($memberIdentifier, $manifestationIdentifier, $expiryDate);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'reservationId' => $reservation->getId(),
            'reservationStatus' => $reservation->getStatus(),
        ]);
    }

    #[Route('/circulation/checkout', name: 'app_circulation_checkout', methods: ['POST'])]
    public function checkout(Request $request, CirculationService $service): JsonResponse
    {
        $data = $this->getRequestData($request);
        $memberIdentifier = $data['memberIdentifier'] ?? null;
        $manifestationIdentifiers = $data['manifestationIdentifiers'] ?? null;

        if (!$memberIdentifier || !$manifestationIdentifiers) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $identifierList = is_array($manifestationIdentifiers)
            ? $manifestationIdentifiers
            : [$manifestationIdentifiers];

        try {
            $checkouts = $service->checkout($memberIdentifier, $identifierList);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'checkoutCount' => count($checkouts),
        ]);
    }

    #[Route('/circulation/checkin', name: 'app_circulation_checkin', methods: ['POST'])]
    public function checkIn(Request $request, CirculationService $service): JsonResponse
    {
        $data = $this->getRequestData($request);
        $manifestationIdentifier = $data['manifestationIdentifier'] ?? null;

        if (!$manifestationIdentifier) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        try {
            $checkout = $service->checkIn($manifestationIdentifier);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return new JsonResponse([
            'status' => 'success',
            'checkedIn' => $checkout !== null,
        ]);
    }

    private function getRequestData(Request $request): array
    {
        $data = [];
        if ($request->getContent() !== '') {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                $data = [];
            }
        }

        if ($request->request->count() > 0) {
            $data = array_merge($data, $request->request->all());
        }

        return $data;
    }
}
