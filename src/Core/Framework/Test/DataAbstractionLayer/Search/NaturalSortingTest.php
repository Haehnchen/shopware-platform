<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class NaturalSortingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $groupRepository;

    /**
     * @var RepositoryInterface
     */
    private $optionRepository;

    protected function setUp()
    {
        parent::setUp();
        $this->groupRepository = $this->getContainer()->get('configuration_group.repository');
        $this->optionRepository = $this->getContainer()->get('configuration_group_option.repository');
    }

    /**
     * @dataProvider sortingFixtures
     */
    public function testSorting(array $naturalOrder, array $rawOrder)
    {
        $groupId = Uuid::uuid4()->getHex();
        //created group with provided options
        $data = [
            'id' => $groupId,
            'name' => 'Content',
            'options' => array_map(function ($name) {
                return ['name' => $name];
            }, $naturalOrder),
        ];

        $context = Context::createDefaultContext();
        $this->groupRepository->create([$data], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('configuration_group_option.groupId', $groupId));

        //add sorting for none natural
        $criteria->addSorting(
            new FieldSorting('configuration_group_option.name', FieldSorting::ASCENDING, false)
        );

        $options = $this->optionRepository->search($criteria, $context);
        //check all options generated
        static::assertCount(\count($naturalOrder), $options);

        //extract names to compare them
        $actual = $options->map(function (ConfigurationGroupOptionEntity $option) {
            return $option->getName();
        });

        static::assertEquals($rawOrder, array_values($actual));

        //check natural sorting
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('configuration_group_option.groupId', $groupId));
        $criteria->addSorting(new FieldSorting('configuration_group_option.name', FieldSorting::ASCENDING, true));

        $options = $this->optionRepository->search($criteria, $context);
        $actual = $options->map(function (ConfigurationGroupOptionEntity $option) {
            return $option->getName();
        });

        static::assertEquals($naturalOrder, array_values($actual));
    }

    public function sortingFixtures()
    {
        return [
            [
                ['1,0 liter', '2,0 liter', '3,0 liter', '4,0 liter', '10,0 liter'], //nat sorting
                ['1,0 liter', '10,0 liter', '2,0 liter', '3,0 liter', '4,0 liter'], //none nat
            ],
            [
                ['1,0', '2,0', '3,0', '4,0', '10,0'], //nat sorting
                ['1,0', '10,0', '2,0', '3,0', '4,0'], //none naturla
            ],
            [
                ['1', '2', '3', '4', '5', '6', '100', '1000', '2000', '3100'], //nat sorting
                ['1', '100', '1000', '2', '2000', '3', '3100', '4', '5', '6'], //none naturla
            ],
            [
                ['0.1', '0.2', '0.3', '1.0', '1.2', '1.4', '1.4', '1.6', '2.0', '2.0', '2.3'], //nat sorting
                ['0.1', '0.2', '0.3', '1.0', '1.2', '1.4', '1.4', '1.6', '2.0', '2.0', '2.3'], //none naturla
            ],
            [
                ['test1', 'test2', 'test3', 'test4', 'test10'], //nat sorting
                ['test1', 'test10', 'test2', 'test3', 'test4'], //none naturla
            ],
            [
                ['1', '10', '1.0', '1.1', '1.3', '1.5', '2.22222'], //nat sorting
                ['1', '1.0', '1.1', '1.3', '1.5', '10', '2.22222'], //none naturla
            ],
        ];
    }
}
