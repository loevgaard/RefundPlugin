<?php

declare(strict_types=1);

namespace Sylius\RefundPlugin\Action\Admin;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\RefundPlugin\Checker\OrderRefundingAvailabilityCheckerInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class OrderRefundsListAction
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var OrderRefundingAvailabilityCheckerInterface */
    private $orderRefundingAvailabilityChecker;

    /** @var RefundPaymentMethodsProviderInterface */
    private $refundPaymentMethodsProvider;

    /** @var Environment */
    private $twig;

    /** @var Session */
    private $session;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderRefundingAvailabilityCheckerInterface $orderRefundingAvailabilityChecker,
        RefundPaymentMethodsProviderInterface $refundPaymentMethodsProvider,
        Environment $twig,
        Session $session,
        UrlGeneratorInterface $router
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderRefundingAvailabilityChecker = $orderRefundingAvailabilityChecker;
        $this->refundPaymentMethodsProvider = $refundPaymentMethodsProvider;
        $this->twig = $twig;
        $this->session = $session;
        $this->router = $router;
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByNumber($request->attributes->get('orderNumber'));

        if (!$this->orderRefundingAvailabilityChecker->__invoke($request->attributes->get('orderNumber'))) {
            return $this->redirectToReferer($order);
        }

        return new Response(
            $this->twig->render('@SyliusRefundPlugin/orderRefunds.html.twig', [
                'order' => $order,
                'payment_methods' => $this->refundPaymentMethodsProvider->findForChannel($order->getChannel()),
            ])
        );
    }

    private function redirectToReferer(OrderInterface $order): Response
    {
        $this->session->getFlashBag()->add('error', 'sylius_refund.order_should_be_paid');

        return new RedirectResponse($this->router->generate('sylius_admin_order_show', ['id' => $order->getId()]));
    }
}
