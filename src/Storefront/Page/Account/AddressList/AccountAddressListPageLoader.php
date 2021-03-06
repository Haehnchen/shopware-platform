<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\AddressList;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Shopware\Storefront\Pagelet\Account\AddressList\AccountAddressListPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountAddressListPageLoader
{
    /**
     * @var AccountAddressListPageletLoader
     */
    private $accountAddressPageletLoader;

    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        AccountAddressListPageletLoader $accountAddressPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
        $this->accountAddressPageletLoader = $accountAddressPageletLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountAddressListPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountAddressListPage::createFrom($page);

        $page->setAddresses(
            $this->accountAddressPageletLoader->load($request, $context)
        );

        $this->eventDispatcher->dispatch(
            AccountAddressListPageLoadedEvent::NAME,
            new AccountAddressListPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
