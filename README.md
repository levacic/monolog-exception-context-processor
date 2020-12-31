# Exception-with-context processor for Monolog

![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/levacic/monolog-exception-with-context-processor)
[![Latest Stable Version](https://poser.pugx.org/levacic/monolog-exception-with-context-processor/v)](https://packagist.org/packages/levacic/monolog-exception-with-context-processor)
![Packagist Downloads](https://img.shields.io/packagist/dt/levacic/monolog-exception-with-context-processor)
![Packagist License](https://img.shields.io/packagist/l/levacic/monolog-exception-with-context-processor)
![CI](https://github.com/levacic/monolog-exception-with-context-processor/workflows/CI/badge.svg)
[![Code Coverage](https://codecov.io/gh/levacic/monolog-exception-with-context-processor/branch/main/graph/badge.svg?token=UPxy6f3T1z)](https://codecov.io/gh/levacic/monolog-exception-with-context-processor)

This package processes exceptions passed via the log record's `context` and extracts the exception chain with context into a key within the log record's `extra` section.

The chain includes all chained exceptions, but the context is only extracted from those that implement the [ExceptionWithContext](https://github.com/levacic/exception-with-context) interface.

## Requirements

- PHP >= 7.0

## Installation

```sh
composer require levacic/monolog-exception-with-context-processor
```

## Usage

### Configuration

The following should be done wherever you normally configure your logging infrastructure.

```php
// Assuming a Monolog\Logger instance:
$monolog->pushProcessor(new ExceptionWithContextProcessor());
```

That's all it takes to setup the processor.

### Why would I do this?

This processor is pretty useless on its own. The main benefit of using it is when also using the [ExceptionWithContext](https://github.com/levacic/exception-with-context) interface to attach additional context to your exceptions.

This processor checks whether the log record context has an `exception` key set, and if its value is an exception (or rather, a `Throwable`). If it is, it will traverse that exception's chain (using `$exception->getPrevious()`), and extract the context for each of the exceptions in the chain that implement `ExceptionWithContext`.

The whole chain of exceptions with their contexts will be placed into an `exception_chain_with_context` key in the `extra` part of the log record. Each exception will have an `exception` key which is the class name of the exception, and a `context` key which is either the context returned by the exception if it implements `ExceptionWithContext`, or `null` otherwise.

### Example

Assume the following exception class:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Levacic\Exceptions\ExceptionWithContext;
use RuntimeException;
use Throwable;

class UserNotActivated extends RuntimeException implements ExceptionWithContext
{
    /**
     * The ID of the non-activated user.
     */
    private int $userId;

    /**
     * @param int            $userId   The ID of the non-activated user.
     * @param Throwable|null $previous The previous exception.
     * @param int            $code     The internal exception code.
     */
    public function __construct(int $userId, ?Throwable $previous = null, int $code = 0)
    {
        parent::__construct('The user has not been activated yet.', $code, $previous);

        $this->userId = $userId;
    }

    /**
     * @inheritDoc
     */
    public function getContext(): array
    {
        return [
            'userId' => $this->userId,
        ];
    }
}
```

Now assume you have a `$logger` which is a `Monolog\Logger` instance configured with this processor.

```php
// Create an exception chain where a RuntimeException wraps an instance of the
// UserNotActivated exception.
$exception = new RuntimeException(
    'An error has occurred',
    0,
    new \App\Exceptions\UserNotActivated(1234),
);

$logger->error(
    $exception->getMessage(),
    [
        'exception' => $exception,
    ],
);
```

This processor would add a new key in the `extra` part of the log record which looks like this:

```json
'exception_chain_with_context' => [
    [
        'exception' => 'RuntimeException',
        'context' => null
    ],
    [
        'exception' => 'App\Exceptions\UserNotActivated',
        'context' => [
            'userId' => 1234
        ],
    ]
],
```

This basically allows you to have the context of each exception in the chain logged wherever you log stuff. This is useful even if you just log stuff in files (although the readability of such logs for PHP applications is generally questionable), but it's _really_ helpful when logging into external systems capable of formatting and nicely displaying the additional information passed with logged messages, or performing searches/filtering/aggregation across your log data.

## License

This package is open-source software licensed under the [MIT license][LICENSE].
