<?php

declare(strict_types=1);

namespace Tests\Levacic\Exceptions;

use Levacic\Exceptions\ExceptionWithContext;
use RuntimeException;
use Throwable;

class DummyExceptionWithContext extends RuntimeException implements ExceptionWithContext
{
    /**
     * A Foo value.
     */
    private string $foo;

    /**
     * @param string         $foo      A Foo value.
     * @param Throwable|null $previous The previous exception.
     * @param int            $code     The internal exception code.
     *
     * @return void
     */
    public function __construct(string $foo, ?Throwable $previous = null, $code = 0)
    {
        parent::__construct('A dummy error has occurred.', $code, $previous);

        $this->foo = $foo;
    }

    /**
     * @inheritDoc
     */
    public function getContext(): array
    {
        return [
            'foo' => $this->foo,
        ];
    }
}
