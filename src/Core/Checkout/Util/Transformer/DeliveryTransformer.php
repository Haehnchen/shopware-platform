<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Util\Transformer;

use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\StateMachine\StateMachineRegistry;
use Shopware\Core\Framework\Struct\Struct;

class DeliveryTransformer
{
    public static function transformCollection(
        DeliveryCollection $deliveries,
        array $lineItems,
        StateMachineRegistry $stateMachineRegistry,
        Context $context,
        array $addresses = []
    ): array {
        $output = [];
        foreach ($deliveries as $delivery) {
            $output[] = self::transform($delivery, $lineItems, $stateMachineRegistry, $context, $addresses);
        }

        return $output;
    }

    public static function transform(Delivery $delivery,
                                     array $lineItems,
                                     StateMachineRegistry $stateMachineRegistry,
                                     Context $context,
                                     array $addresses = []
    ): array {
        $addressId = $delivery->getLocation()->getAddress() ? $delivery->getLocation()->getAddress()->getId() : null;
        $shippingAddress = null;

        if ($addressId !== null && array_key_exists($addressId, $addresses)) {
            $shippingAddress = $addresses[$addressId];
        } elseif ($delivery->getLocation()->getAddress() !== null) {
            $shippingAddress = AddressTransformer::transform($delivery->getLocation()->getAddress());
        }

        $deliveryData = [
            'id' => self::getId($delivery),
            'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::DATE_FORMAT),
            'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::DATE_FORMAT),
            'shippingMethodId' => $delivery->getShippingMethod()->getId(),
            'shippingOrderAddress' => $shippingAddress,
            'orderStateId' => Defaults::ORDER_STATE_OPEN,
            'shippingCosts' => $delivery->getShippingCosts(),
            'positions' => [],
            'stateId' => $stateMachineRegistry->getInitialState(Defaults::ORDER_DELIVERY_STATE_MACHINE, $context)->getId(),
        ];

        $deliveryData = array_filter($deliveryData, function ($item) {
            return $item !== null;
        });

        /** @var DeliveryPosition $position */
        foreach ($delivery->getPositions() as $position) {
            $deliveryData['positions'][] = [
                'price' => $position->getPrice(),
                'orderLineItemId' => $lineItems[$position->getIdentifier()]['id'],
            ];
        }

        return $deliveryData;
    }

    public static function getId(Struct $struct): ?string
    {
        /** @var IdStruct|null $idStruct */
        $idStruct = $struct->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class);
        if ($idStruct !== null) {
            return $idStruct->getId();
        }

        return null;
    }
}
