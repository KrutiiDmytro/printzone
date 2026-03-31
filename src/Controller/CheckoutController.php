<?php

namespace App\Controller;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Repository\OrderRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository
    ) {
    }

    #[Route('/checkout', name: 'app_checkout')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $cart = $this->cartService->getCart();

        if (empty($cart['items'])) {
            $this->addFlash('warning', 'Your cart is empty');
            return $this->redirectToRoute('app_cart');
        }

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/checkout/place-order', name: 'app_checkout_place_order', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function placeOrder(Request $request): Response
    {
        $user = $this->getUser();
        $cart = $this->cartService->getCart();

        if (empty($cart['items'])) {
            $this->addFlash('error', 'Your cart is empty');
            return $this->redirectToRoute('app_cart');
        }

        // Создаем заказ
        $order = new Order();
        $order->setUser($user);
        $order->setStatus('PENDING');
        $order->setTotalAmount($cart['total']);
        $order->setCreatedAt(new \DateTime());

        // Создаем элементы заказа
        foreach ($cart['items'] as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProduct($cartItem['product']);
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setPrice($cartItem['product']->getPrice());
            $order->getItems()->add($orderItem);
        }

        // Сохраняем заказ
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Очищаем корзину
        $this->cartService->clear();

        $this->addFlash('success', 'Your order has been successfully placed! Order number: #' . $order->getId());

        return $this->redirectToRoute('app_home');
    }
}