<?php

namespace App\Service;

use App\Entity\Checkout;
use App\Entity\Manifestation;
use App\Entity\Reservation;
use App\Repository\CheckoutRepository;
use App\Repository\ManifestationRepository;
use App\Repository\MemberRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Registry;

class CirculationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManifestationRepository $manifestationRepository,
        private MemberRepository $memberRepository,
        private ReservationRepository $reservationRepository,
        private CheckoutRepository $checkoutRepository,
        private Registry $workflowRegistry,
    ) {
    }

    public function reserve(string $memberIdentifier, string $manifestationIdentifier, ?int $expiryDate = null): Reservation
    {
        $member = $this->memberRepository->findOneBy(['identifier' => $memberIdentifier]);
        if ($member === null) {
            throw new \InvalidArgumentException('Member not found.');
        }

        $manifestation = $this->manifestationRepository->findOneBy(['identifier' => $manifestationIdentifier]);
        if ($manifestation === null) {
            throw new \InvalidArgumentException('Manifestation not found.');
        }

        $reservation = new Reservation();
        $reservation->setMember($member);
        $reservation->setManifestation($manifestation);
        $reservation->setReservedAt(time());
        $reservation->setExpiryDate($expiryDate);
        $reservation->setStatus(Reservation::STATUS_WAITING);

        $this->entityManager->persist($reservation);

        $workflow = $this->getManifestationWorkflow($manifestation);
        if ($workflow->can($manifestation, 'reserve')) {
            $workflow->apply($manifestation, 'reserve');
        }

        $this->entityManager->flush();

        return $reservation;
    }

    /**
     * @return Checkout[]
     */
    public function checkout(string $memberIdentifier, array $manifestationIdentifiers): array
    {
        $member = $this->memberRepository->findOneBy(['identifier' => $memberIdentifier]);
        if ($member === null) {
            throw new \InvalidArgumentException('Member not found.');
        }

        $results = [];
        $now = new \DateTime();

        foreach ($manifestationIdentifiers as $manifestationIdentifier) {
            $manifestation = $this->manifestationRepository->findOneBy(['identifier' => $manifestationIdentifier]);
            if ($manifestation === null) {
                throw new \InvalidArgumentException('Manifestation not found: ' . $manifestationIdentifier);
            }

            $checkout = new Checkout();
            $checkout->setMember($member);
            $checkout->setManifestation($manifestation);
            $checkout->setCheckedOutAt($now);
            $checkout->setStatus(Checkout::STATUS_CHECKED_OUT);

            $reservation = $this->reservationRepository->findWaitingByManifestationAndMember($manifestation, $member);
            if ($reservation !== null) {
                $reservation->setStatus(Reservation::STATUS_COMPLETED);
            }

            $workflow = $this->getManifestationWorkflow($manifestation);
            if ($workflow->can($manifestation, 'check_out')) {
                $workflow->apply($manifestation, 'check_out');
            }

            $this->entityManager->persist($checkout);
            $results[] = $checkout;
        }

        $this->entityManager->flush();

        return $results;
    }

    public function checkIn(string $manifestationIdentifier): ?Checkout
    {
        $manifestation = $this->manifestationRepository->findOneBy(['identifier' => $manifestationIdentifier]);
        if ($manifestation === null) {
            throw new \InvalidArgumentException('Manifestation not found.');
        }

        $checkout = $this->checkoutRepository->findActiveByManifestation($manifestation);
        $now = new \DateTime();

        if ($checkout !== null) {
            $checkout->setCheckedInAt($now);
            $checkout->setStatus(Checkout::STATUS_RETURNED);
        }

        $workflow = $this->getManifestationWorkflow($manifestation);
        if ($workflow->can($manifestation, 'check_in')) {
            $workflow->apply($manifestation, 'check_in');
        }

        $reservation = $this->reservationRepository->findOldestWaitingByManifestation($manifestation);
        if ($reservation !== null) {
            $reservation->setStatus(Reservation::STATUS_AVAILABLE);
            if ($workflow->can($manifestation, 'reserve')) {
                $workflow->apply($manifestation, 'reserve');
            }
        }

        $this->entityManager->flush();

        return $checkout;
    }

    private function getManifestationWorkflow(Manifestation $manifestation): WorkflowInterface
    {
        return $this->workflowRegistry->get($manifestation, 'manifestation');
    }
}
