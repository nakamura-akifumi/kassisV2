<?php

namespace App\Controller;

use App\Entity\Member;
use App\Form\MemberType;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/member')]
class MemberController extends AbstractController
{
    #[Route('/', name: 'app_member_index', methods: ['GET'])]
    public function index(MemberRepository $memberRepository): Response
    {
        $members = $memberRepository->findBy([], ['id' => 'DESC']);
        $gridData = array_map(static function (Member $member): array {
            return [
                'id' => $member->getId(),
                'identifier' => $member->getIdentifier(),
                'fullName' => $member->getFullName(),
                'group1' => $member->getGroup1(),
                'role' => $member->getRole(),
                'status' => $member->getStatusLabel(),
                'expiryDate' => $member->getExpiryDate()?->format('Y-m-d'),
                'updatedAt' => $member->getUpdatedAt()?->format('Y-m-d H:i'),
                'note' => $member->getNote(),
            ];
        }, $members);

        return $this->render('member/index.html.twig', [
            'members' => $members,
            'gridData' => $gridData,
        ]);
    }

    #[Route('/new', name: 'app_member_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ParameterBagInterface $params): Response
    {
        $member = new Member();
        if ($params->has('app.member.expiry_days')) {
            $expiryDays = $params->get('app.member.expiry_days');
            $expiryDays = is_numeric($expiryDays) ? (int) $expiryDays : null;
            if ($member->getExpiryDate() === null && $expiryDays !== null && $expiryDays > 0 && $expiryDays !== 9999) {
                $member->setExpiryDate((new \DateTime())->modify('+' . $expiryDays . ' days'));
            }
        }
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($member);
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/new.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Member $member): Response
    {
        return $this->render('member/show.html.twig', [
            'member' => $member,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_member_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('member/edit.html.twig', [
            'member' => $member,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_member_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Member $member, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $member->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($member);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_member_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/check', name: 'app_member_check', methods: ['POST'])]
    public function check(Request $request, MemberRepository $memberRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $identifier = $data['identifier'] ?? null;

        if (!$identifier) {
            return new JsonResponse(['exists' => false], 400);
        }

        $member = $memberRepository->findOneBy(['identifier' => $identifier]);

        return new JsonResponse([
            'exists' => $member !== null,
            'fullName' => $member?->getFullName(),
        ]);
    }
}
