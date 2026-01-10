<?php

namespace App\Tests\Controller;

use App\Controller\ManifestationController;
use App\Entity\Manifestation;
use App\Repository\ManifestationRepository;
use App\Service\ManifestationSearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class ManifestationControllerTest extends TestCase
{
    private $entityManager;
    private $repository;
    private $twig;
    private $container;
    private $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ManifestationRepository::class);
        $this->twig = $this->createMock(Environment::class);
        
        // AbstractControllerが依存する共通サービスをモック化
        $this->container = $this->createMock(ContainerInterface::class);

        $this->controller = new ManifestationController();
        $this->controller->setContainer($this->container);
    }

    public function testIndex(): void
    {
        $request = new Request(['q' => 'test']);
        
        // Repositoryの動作を定義
        $this->repository->expects($this->once())
            ->method('searchByQuery')
            ->with($this->isInstanceOf(ManifestationSearchQuery::class))
            ->willReturn([]);

        // Twigのレンダリングをモック
        $this->container->method('has')->with('twig')->willReturn(true);
        $this->container->method('get')->willReturnMap([
            ['twig', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->twig],
        ]);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('manifestation/index.html.twig', $this->callback(function($parameters) {
                return isset($parameters['manifestations']) && is_array($parameters['manifestations']);
            }))
            ->willReturn('<html></html>');

        $response = $this->controller->index($request, $this->repository);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNewPersistsData(): void
    {
        $request = new Request();
        $manifestation = new Manifestation();

        // フォームファクトリのモック
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form = $this->createMock(FormInterface::class);

        $this->container->method('get')->willReturnMap([
            ['form.factory', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $formFactory],
            ['twig', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->twig],
        ]);

        $formFactory->method('create')->willReturn($form);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        // EntityManagerの期待値設定
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // リダイレクトのモック設定
        $router = $this->createMock(RouterInterface::class);
        $this->container->method('has')->willReturnMap([
            ['router', true],
            ['twig', true],
        ]);
        $this->container->method('get')->willReturnMap([
            ['router', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $router],
            ['form.factory', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $formFactory],
        ]);
        $router->method('generate')->willReturn('/manifestation');

        $response = $this->controller->new($request, $this->entityManager);

        $this->assertSame(303, $response->getStatusCode()); // Response::HTTP_SEE_OTHER
    }

    public function testDeleteWithValidCsrf(): void
    {
        $request = new Request([], ['_token' => 'valid_token']);
        $manifestation = $this->createMock(Manifestation::class);
        $manifestation->method('getId')->willReturn(123);

        // CSRFチェックのモック
        // AbstractController::isCsrfTokenValid 内で使われる 'security.csrf.token_manager' をモックにするか、
        // コントローラーを継承してメソッドをオーバーライドする等の工夫が必要ですが、
        // ここではシンプルにコンテナから取得する形式を想定します
        $csrfManager = $this->createMock(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(true);

        $router = $this->createMock(RouterInterface::class);
        $this->container->method('get')->willReturnMap([
            ['security.csrf.token_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $csrfManager],
            ['router', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $router],
        ]);

        $this->entityManager->expects($this->once())->method('remove')->with($manifestation);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->delete($request, $manifestation, $this->entityManager);

        $this->assertSame(303, $response->getStatusCode());
    }
}
