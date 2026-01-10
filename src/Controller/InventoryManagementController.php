<?php

namespace App\Controller;

use App\Entity\InventorySession;
use App\Repository\ManifestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryManagementController extends AbstractController
{
    #[Route('/inventory', name: 'app_inventory')]
    public function index(ManifestationRepository $repository): Response
    {
        // 存在するロケーション(location1)の一覧をDBから取得
        $locations = $repository->createQueryBuilder('m')
            ->select('m.location1')
            ->distinct()
            ->where('m.location1 IS NOT NULL')
            ->orderBy('m.location1', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('inventory/index.html.twig', [
            'locations' => array_filter(array_column($locations, 'location1')),
        ]);
    }

    #[Route('/inventory/add-scan', name: 'app_inventory_add_scan', methods: ['POST'])]
    public function addScan(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $location = $data['location'] ?? null;
        $identifier = $data['identifier'] ?? null;

        if (!$location || !$identifier) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $session = new InventorySession();
        $session->setLocation($location);
        $session->setIdentifier($identifier);

        $em->persist($session);
        $em->flush();

        return new JsonResponse(['status' => 'success']);
    }

    #[Route('/inventory/resume/{location}', name: 'app_inventory_resume', methods: ['GET'])]
    public function resume(string $location, EntityManagerInterface $em): JsonResponse
    {
        $items = $em->getRepository(InventorySession::class)->findBy(
            ['location' => $location],
            ['scannedAt' => 'DESC']
        );

        $identifiers = array_map(fn($item) => $item->getIdentifier(), $items);

        return new JsonResponse(['identifiers' => $identifiers]);
    }

    #[Route('/inventory/check', name: 'app_inventory_check', methods: ['POST'])]
    public function check(Request $request, ManifestationRepository $repository, EntityManagerInterface $em): Response
    {
        $location = $request->request->get('location');
        $scannedIdentifiers = $request->request->all('identifiers');

        // チェックが完了したら、そのロケーションのセッションデータをクリアする
        $oldSessions = $em->getRepository(InventorySession::class)->findBy(['location' => $location]);
        foreach ($oldSessions as $session) {
            $em->remove($session);
        }
        $em->flush();

        // DB上の当該ロケーションにあるべき全アイテムを取得
        $expectedItems = $repository->findBy(['location1' => $location]);
        
        $results = [
            'matched' => [],      // 正常
            'missing' => [],      // 不明（DBにあるが見つからない）
            'extra' => [],        // 余剰（他部署・他ロケーションにある）
            'unregistered' => [], // 未登録（DBに存在しない）
        ];

        $expectedMap = [];
        foreach ($expectedItems as $item) {
            $expectedMap[$item->getIdentifier()] = $item;
        }

        // スキャンされた識別子の重複を除去してチェック
        $uniqueScanned = array_unique($scannedIdentifiers);

        foreach ($uniqueScanned as $id) {
            if (isset($expectedMap[$id])) {
                $results['matched'][] = $expectedMap[$id];
                unset($expectedMap[$id]); // 見つかったのでマップから削除
            } else {
                // ロケーション1が一致しない、または未登録のアイテム
                $foundItem = $repository->findOneBy(['identifier' => $id]);
                if ($foundItem) {
                    $results['extra'][] = $foundItem;
                } else {
                    $results['unregistered'][] = ['identifier' => $id, 'title' => '未登録'];
                }
            }
        }

        // マップに残っているものは「見つからなかった」アイテム
        $results['missing'] = array_values($expectedMap);

        return $this->render('inventory/result.html.twig', [
            'location' => $location,
            'results' => $results,
        ]);
    }
}
