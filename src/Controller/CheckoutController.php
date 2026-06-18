<?php

namespace App\Controller;

use App\Messaging\Application\OutboxRecorder;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Payment\Service\StripeCheckoutService;
use App\Service\CartService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private StripeCheckoutService $stripeCheckoutService,
        private OutboxRecorder $outboxRecorder,
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
    public function pay(): Response
    {
        $cart = $this->cartService->getCart();

        if (empty($cart['items'])) {
            $this->addFlash('error', 'Your cart is empty');

            return $this->redirectToRoute('app_cart');
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        $order = new Order();
        $order->setUserId($user->getId());
        $order->setUserEmail($user->getEmail());
        $order->setStatus('PENDING');
        $order->setTotalAmount($cart['total']);

        foreach ($cart['items'] as $cartItem) {
            $product = $cartItem['product'];
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProductId(Uuid::fromString((string) $product->getId()));
            $orderItem->setProductName((string) $product->getName());
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setPrice((int) $product->getPrice());
            $order->getItems()->add($orderItem);
        }

        $this->entityManager->persist($order);

        // Transactional outbox: OrderCreated commits atomically with the order
        // insert, then the relay ships it to RabbitMQ where the Catalog Saga
        // reserves stock (HELD) for these items.
        $this->outboxRecorder->record('order', 'OrderCreated', [
            'orderId' => (string) $order->getId(),
            'userId' => (string) $user->getId(),
            'items' => $order->toEventItems(),
            'totalAmount' => $order->getTotalAmount(),
        ]);

        $this->entityManager->flush();

        try {
            $session = $this->stripeCheckoutService->createSession($order, $cart['items']);
        } catch (\Exception $e) {
            $this->failOrder($order, 'Payment service is unavailable. Please try again.');

            return $this->redirectToRoute('app_checkout');
        }

        if (null === $session['url']) {
            $this->failOrder($order, 'Could not initiate payment session. Please try again.');

            return $this->redirectToRoute('app_checkout');
        }

        $order->setStripeSessionId($session['id']);
        $this->entityManager->flush();

        return $this->redirect($session['url']);
    }

    /**
     * Marks the order FAILED and emits OrderCancelled so the Catalog Saga
     * releases the HELD reservation; both commit in one flush.
     */
    private function failOrder(Order $order, string $flash): void
    {
        $order->setStatus('FAILED');
        $this->outboxRecorder->record('order', 'OrderCancelled', [
            'orderId' => (string) $order->getId(),
            'items' => $order->toEventItems(),
        ]);
        $this->entityManager->flush();
        $this->addFlash('error', $flash);
    }

    #[Route('/checkout/success', name: 'app_checkout_success')]
    #[IsGranted('ROLE_USER')]
    public function success(): Response
    {
        $this->cartService->clear();

        return $this->render('checkout/success.html.twig');
    }

    #[Route('/checkout/cancel', name: 'app_checkout_cancel')]
    #[IsGranted('ROLE_USER')]
    public function cancel(): Response
    {
        return $this->render('checkout/cancel.html.twig');
    }
}
