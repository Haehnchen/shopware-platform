<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\FieldExceptionStack;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var PasswordField
     */
    private $field;

    public function setUp()
    {
        $this->field = new PasswordField('password', 'password');
        $this->field->setConstraintBuilder($this->getContainer()->get(ConstraintBuilder::class));
        $this->field->setValidator($this->getContainer()->get('validator'));
    }

    public function testGetStorage(): void
    {
        static::assertEquals('password', $this->field->getStorageName());
    }

    public function testNullableField(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $passwordFieldHandler = new PasswordFieldSerializer(
            new ConstraintBuilder(),
            $this->getContainer()->get('validator')
        );

        $payload = $passwordFieldHandler->encode($field, $existence, $kvPair, new WriteParameterBag(
            UserDefinition::class,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue(),
            new FieldExceptionStack()
        ));

        $payload = iterator_to_array($payload);
        static::assertEquals($kvPair->getValue(), $payload['password']);
    }

    public function testEncoding(): void
    {
        $field = $this->field;
        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', 'shopware', true);

        $passwordFieldHandler = new PasswordFieldSerializer(
            $this->getContainer()->get(ConstraintBuilder::class),
            $this->getContainer()->get('validator')
        );

        $payload = $passwordFieldHandler->encode($field, $existence, $kvPair, new WriteParameterBag(
            UserDefinition::class,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue(),
            new FieldExceptionStack()
        ));

        $payload = iterator_to_array($payload);
        static::assertNotEquals($kvPair->getValue(), $payload['password']);
        static::assertTrue(password_verify($kvPair->getValue(), $payload['password']));
    }

    public function testValueIsRequiredOnInsert(): void
    {
        $field = clone $this->field;
        $field->addFlags(new Required());

        $existence = new EntityExistence(UserDefinition::class, [], false, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $exception = null;
        try {
            $handler = $this->getContainer()->get(PasswordFieldSerializer::class);

            $parameters = new WriteParameterBag(
                UserDefinition::class,
                WriteContext::createFromContext(Context::createDefaultContext()),
                '',
                new WriteCommandQueue(),
                new FieldExceptionStack()
            );

            $x = $handler->encode($field, $existence, $kvPair, $parameters);
            iterator_to_array($x);
        } catch (InvalidFieldException $exception) {
        }

        static::assertInstanceOf(InvalidFieldException::class, $exception);
        static::assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
    }

    public function testValueIsRequiredOnUpdate(): void
    {
        $field = clone $this->field;
        $field->addFlags(new Required());

        $existence = new EntityExistence(UserDefinition::class, [], true, false, false, []);
        $kvPair = new KeyValuePair('password', null, true);

        $exception = null;
        try {
            $handler = $this->getContainer()->get(PasswordFieldSerializer::class);

            $x = $handler->encode($field, $existence, $kvPair, new WriteParameterBag(
                UserDefinition::class,
                WriteContext::createFromContext(Context::createDefaultContext()),
                '',
                new WriteCommandQueue(),
                new FieldExceptionStack()
            ));
            iterator_to_array($x);
        } catch (InvalidFieldException $exception) {
        }

        static::assertInstanceOf(InvalidFieldException::class, $exception);
        static::assertNotNull($exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
    }
}
