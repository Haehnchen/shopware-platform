<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CustomerGroupDiscountCollection extends EntityCollection
{
    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (CustomerGroupDiscountEntity $customerGroupDiscount) {
            return $customerGroupDiscount->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (CustomerGroupDiscountEntity $customerGroupDiscount) use ($id) {
            return $customerGroupDiscount->getCustomerGroupId() === $id;
        });
    }

    public function getDiscountForCartAmount(float $totalPrice, string $customerGroupId): ?float
    {
        $discount = null;

        /** @var CustomerGroupDiscountEntity $discountData */
        foreach ($this->elements as $discountData) {
            if ($discountData->getMinimumCartAmount() > $totalPrice) {
                break;
            }
            if ($discountData->getCustomerGroupId() === $customerGroupId) {
                $discount = $discountData->getPercentageDiscount() * -1;
            }
        }

        return $discount;
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDiscountEntity::class;
    }
}
