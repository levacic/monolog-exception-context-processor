<?php

declare(strict_types=1);

namespace Levacic\Exceptions;

use Levacic\Exceptions\ExceptionWithContext;
use Monolog\Processor\ProcessorInterface;
use Throwable;

class ExceptionWithContextProcessor implements ProcessorInterface
{
    /**
     * Process the log record.
     *
     * If there is an exception object in the `exception` key of the record's
     * context, it will add the complete exception chain into the `extra` data
     * part of the log record. For any exception that carries its own context,
     * it will also add that context along with the exception, in the relevant
     * `extra` data entry.
     *
     * The resulting `extra` data will have an `exception_chain_with_context`
     * array, with each element having an `exception` key with the name of the
     * exception in the exception chain, and a `context` key with that
     * particular exception's context, if that exception carries its own
     * context, or `null`, if it doesn't.
     *
     * @param array $record The processed record.
     *
     * @return array
     */
    public function __invoke(array $record): array
    {
        // If the log record doesn't have a context, or the context doesn't have
        // an exception attached, or the exception key is not actually an
        // exception - there's nothing we can do, so we'll just return the
        // record as it was.
        if (
            !isset($record['context'])
            || !isset($record['context']['exception'])
            || !($record['context']['exception'] instanceof Throwable)
        ) {
            return $record;
        }

        // Extract the exception into a variable for readability.
        $exception = $record['context']['exception'];

        // Attach the exception chain with context to the record's "extra" data.
        $record['extra']['exception_chain_with_context'] = $this->getExceptionChainWithContext($exception);

        // If the exception itself is carrying context, we'll append it to the
        // record's existing context - but without overwriting anything already
        // there, so as to enable users to still pass custom data if needed.
        $record['context'] = $this->mergeContextWithExceptionContext($record['context'], $exception);

        return $record;
    }

    /**
     * Extract the complete exception chain with each exception's context.
     *
     * If an exception's context exists, it will be included for that exception,
     * otherwise it will be `null`.
     *
     * @param Throwable $exception The exception for which to extract the chain.
     *
     * @return array
     */
    private function getExceptionChainWithContext(Throwable $exception): array
    {
        $exceptionChainContext = [];

        do {
            $exceptionChainContext[] = [
                'exception' => get_class($exception),
                'context' => ($exception instanceof ExceptionWithContext) ? $exception->getContext() : null,
            ];

            $exception = $exception->getPrevious();
        } while ($exception);

        return $exceptionChainContext;
    }

    /**
     * Merge existing context with exception-specific context.
     *
     * If the exception is not carrying its own context, the original context
     * will just be returned as-is. Otherwise, the exception's context will
     * *NOT* overwrite the original context's keys.
     *
     * @param array     $context   The original context.
     * @param Throwable $exception The exception being logged.
     *
     * @return array
     */
    private function mergeContextWithExceptionContext(array $context, Throwable $exception): array
    {
        // If the exception is not carrying context, just return the original
        // context as-is.
        if (!($exception instanceof ExceptionWithContext)) {
            return $context;
        }

        return array_merge(
            $exception->getContext(),
            $context,
        );
    }
}
