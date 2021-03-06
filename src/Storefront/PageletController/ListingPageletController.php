<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ListingPageletController extends StorefrontController
{
    /**
     * @var ListingPageletLoader
     */
    private $listingPageletLoader;

    public function __construct(ListingPageletLoader $listingPageletLoader)
    {
        $this->listingPageletLoader = $listingPageletLoader;
    }

    /**
     * @Route("/widgets/listing/list/{categoryId}", name="widgets_listing_list", methods={"GET"})
     */
    public function listAction(InternalRequest $request, CheckoutContext $context): JsonResponse
    {
        $request->addParam('no-aggregations', true);

        /** @var StorefrontSearchResult $page */
        $page = $this->listingPageletLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        $pagination = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getTotal(),
        ]);
    }

    /**
     * @Route("/widgets/listing/search", name="widgets_listing_search", methods={"GET"})
     */
    public function searchAction(InternalRequest $request, CheckoutContext $context): JsonResponse
    {
        $request->addParam('no-aggregations', true);

        /** @var StorefrontSearchResult $page */
        $page = $this->listingPageletLoader->load($request, $context);

        $template = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        $pagination = $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);

        return new JsonResponse([
            'listing' => $template->getContent(),
            'pagination' => $pagination->getContent(),
            'totalCount' => $page->getTotal(),
        ]);
    }
}
