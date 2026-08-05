<?php

namespace App\Controller;

use App\Order\Client\OrderClient;
use App\Service\CartService;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Thin checkout entrypoint. The Order domain lives in order-service: this
 * controller reads the cart (still owned by the monolith), then delegates order
 * creation + the Stripe Checkout session to the service and redirects the user
 * to Stripe. The success/cancel pages stay here — they render and clear the cart.
 */
class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private OrderClient $orderClient,
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
    public function pay(Request $request): Response
    {
        $cart = $this->cartService->getCart();

        if (empty($cart['items'])) {
            $this->addFlash('error', 'Your cart is empty');

            return $this->redirectToRoute('app_cart');
        }

        $shippingAddress = $this->shippingAddress($request);
        if (null === $shippingAddress) {
            $this->addFlash('error', 'Please fill in all required delivery details.');

            return $this->redirectToRoute('app_checkout');
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        $items = [];
        foreach ($cart['items'] as $cartItem) {
            $product = $cartItem['product'];
            $items[] = [
                'productId' => (string) $product->getId(),
                'name' => (string) $product->getName(),
                'price' => (int) $product->getPrice(),
                'quantity' => (int) $cartItem['quantity'],
            ];
        }

        // Stripe redirects back to the monolith (it owns the cart + these pages).
        $successUrl = $this->generateUrl('app_checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
            .'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $this->generateUrl('app_checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $result = $this->orderClient->createCheckout([
                'userId' => (string) $user->getId(),
                'userEmail' => (string) $user->getEmail(),
                'items' => $items,
                'shippingAddress' => $shippingAddress,
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Payment service is unavailable. Please try again.');

            return $this->redirectToRoute('app_checkout');
        }

        return $this->redirect($result['url']);
    }

    /**
     * Reads the delivery details from the checkout form. Returns null when a
     * required field is missing so the caller can bounce back to the form.
     * This snapshot is forwarded to order-service and, on payment, carried in
     * the OrderPaid event so delivery-service can create the shipment.
     *
     * @return array{firstName: string, lastName: string, address: string, city: string, country: string, postcode: string, phone: string}|null
     */
    private function shippingAddress(Request $request): ?array
    {
        $fields = ['firstName', 'lastName', 'address', 'city', 'country', 'postcode', 'phone'];

        $address = [];
        foreach ($fields as $field) {
            $value = trim((string) $request->request->get($field, ''));
            if ('' === $value) {
                return null;
            }
            $address[$field] = $value;
        }

        return $address;
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
