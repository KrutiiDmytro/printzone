<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService
    ) {}

    #[Route('', name: 'app_cart')]
    public function index(): Response
    {
        $cart = $this->cartService->getCart();

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $this->cartService->add($id, $quantity);

        $this->addFlash('success', 'Product added to cart!');
        
        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('app_home'));
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function remove(int $id): Response
    {
        $this->cartService->remove($id);
        
        $this->addFlash('success', 'Product removed from cart!');
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/update/{id}', name: 'app_cart_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $this->cartService->update($id, $quantity);
        
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        $this->cartService->clear();
        
        $this->addFlash('success', 'Cart cleared!');
        return $this->redirectToRoute('app_cart');
    }
}