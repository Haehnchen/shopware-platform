<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactory;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Checkout\Test\Payment\Handler\TestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class PaymentServiceTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var JWTFactory
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    protected function setUp()
    {
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
        $this->tokenFactory = $this->getContainer()->get(JWTFactory::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function testHandlePaymentByOrderWithInvalidOrderId(): void
    {
        $orderId = Uuid::uuid4()->getHex();
        $checkoutContext = Generator::createCheckoutContext();
        $this->expectException(InvalidOrderException::class);
        $this->paymentService->handlePaymentByOrder(
            $orderId,
            $checkoutContext
        );
    }

    /**
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function testHandlePaymentByOrder(): void
    {
        $paymentMethodId = $this->createPaymentMethod($this->context);
        $customerId = $this->createCustomer($this->context);
        $orderId = $this->createOrder($customerId, $paymentMethodId, $this->context);
        $this->createTransaction($orderId, $paymentMethodId, $this->context);

        $checkoutContext = $this->getCheckoutContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder(
            $orderId,
            $checkoutContext
        );

        static::assertEquals(TestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());
    }

    /**
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function testFinalizeTransactionWithInvalidToken(): void
    {
        $token = Uuid::uuid4()->getHex();
        $request = new Request();
        $this->expectException(InvalidTokenException::class);
        $this->paymentService->finalizeTransaction(
            $token,
            $request,
            Context::createDefaultContext()
        );
    }

    /**
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function testFinalizeTransactionWithExpiredToken(): void
    {
        $request = new Request();
        $transaction = JWTFactoryTest::createTransaction();

        $token = $this->tokenFactory->generateToken($transaction, $this->context, null, -1);

        $this->expectException(TokenExpiredException::class);
        $this->paymentService->finalizeTransaction($token, $request, $this->context);
    }

    private function getCheckoutContext(string $paymentMethodId): CheckoutContext
    {
        return Generator::createCheckoutContext(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            (new PaymentMethodEntity())->assign(['id' => $paymentMethodId])
        );
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::uuid4()->getHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => $this->stateMachineRegistry->getInitialState(Defaults::ORDER_TRANSACTION_STATE_MACHINE, $context)->getId(),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(
        string $customerId,
        string $paymentMethodId,
        Context $context
    ): string {
        $orderId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();
        $stateId = $this->stateMachineRegistry->getInitialState(Defaults::ORDER_STATE_MACHINE, $context)->getId();

        $order = [
            'id' => $orderId,
            'date' => (new \DateTime())->format(Defaults::DATE_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $paymentMethodId,
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutation' => 'mr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => Defaults::COUNTRY,
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }

    private function createPaymentMethod(Context $context): string
    {
        $id = Uuid::uuid4()->getHex();
        $paypal = [
            'id' => $id,
            'technicalName' => TestPaymentHandler::TECHNICAL_NAME,
            'name' => 'Test Payment',
            'additionalDescription' => 'Test payment handler',
            'class' => TestPaymentHandler::class,
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$paypal], $context);

        return $id;
    }
}
