<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class RuleValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    /**
     * @var WriteContext
     */
    private $context;

    /**
     * @var RuleConditionRegistry
     */
    private $conditionRegistry;

    protected function setUp()
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());
        $symfonyValidator = $this->getContainer()->get('validator');
        $this->conditionRegistry = $this->createMock(RuleConditionRegistry::class);
        $this->ruleValidator = new RuleValidator($symfonyValidator, $this->conditionRegistry);
    }

    public function testInsertInvalidType()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            RuleConditionDefinition::class, ['type' => 'false'], ['id' => $id],
            $this->createMock(EntityExistence::class)
        );
        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This "type" value (false) is invalid.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateInvalidType()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            RuleConditionDefinition::class, ['id' => $id], ['type' => 'false'],
            $this->createMock(EntityExistence::class)
        );
        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This "type" value (false) is invalid.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertRequiredField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            RuleConditionDefinition::class, ['type' => 'type'], ['id' => $id], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This value should not be blank.', $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateRequiredField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            RuleConditionDefinition::class, ['id' => $id], ['type' => 'type'], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'This value should not be blank.', $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertOptionalField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            RuleConditionDefinition::class, ['type' => 'type'], ['id' => $id], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['=', '!='])]]
        );
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->ruleValidator->preValidate($commands, $this->context);
    }

    public function testUpdateOptionalField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            RuleConditionDefinition::class, ['id' => $id], ['type' => 'type'], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['=', '!='])]]
        );
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->ruleValidator->preValidate($commands, $this->context);
    }

    public function testInsertWithOptionalField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            RuleConditionDefinition::class, ['type' => 'type', 'value' => json_encode(['field' => 'invalid'])],
            ['id' => $id], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['valid'])]]
        );
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'The value you selected is not a valid choice.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testUpdateWithOptionalField()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            RuleConditionDefinition::class, ['id' => $id],
            ['type' => 'type', 'value' => json_encode(['field' => 'invalid'])],
            $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(
            ['field' => [new Type('string'), new Choice(['valid'])]]
        );
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        static::expectException(ConstraintViolationException::class);
        try {
            $this->ruleValidator->preValidate($commands, $this->context);
            $this->fail('Exception was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            static::assertCount(1, $constraintViolationException->getViolations());
            static::assertSame(
                'The value you selected is not a valid choice.',
                $constraintViolationException->getViolations()->get(0)->getMessage()
            );
            static::assertSame(
                '/conditions/' . Uuid::fromBytesToHex($id) . '/field',
                $constraintViolationException->getViolations()->get(0)->getPropertyPath()
            );
            throw $constraintViolationException;
        }
    }

    public function testInsertValid()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new InsertCommand(
            RuleConditionDefinition::class, ['type' => 'type', 'value' => json_encode(['field' => 'valid'])],
            ['id' => $id], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->ruleValidator->preValidate($commands, $this->context);
    }

    public function testUpdateValid()
    {
        $id = Uuid::uuid4()->getBytes();
        $commands = [];
        $commands[] = new UpdateCommand(
            RuleConditionDefinition::class, ['id' => $id],
            ['type' => 'type', 'value' => json_encode(['field' => 'valid'])], $this->createMock(EntityExistence::class)
        );

        $instance = $this->createMock(Rule::class);
        $instance->expects($this->once())->method('getConstraints')->willReturn(['field' => [new NotBlank()]]);
        $this->conditionRegistry->expects($this->once())->method('has')->with('type')->willReturn(true);
        $this->conditionRegistry->expects($this->once())->method('getRuleInstance')->with('type')->willReturn($instance);

        $this->ruleValidator->preValidate($commands, $this->context);
    }
}
