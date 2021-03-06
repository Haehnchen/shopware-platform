<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\_fixtures;

use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;

class LaguageFileMock implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'only for unit tests';
    }

    public function getPath(): string
    {
        return __DIR__ . '/test_Unit_TEST.json';
    }

    public function getIso(): string
    {
        return 'unit_TEST';
    }

    public function isBase(): bool
    {
        return SnippetFileInterface::BASE_LANGUAGE_FILE;
    }

    public function getAuthor(): string
    {
        return 'unitTests';
    }
}
