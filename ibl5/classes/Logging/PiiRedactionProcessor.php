<?php

declare(strict_types=1);

namespace Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class PiiRedactionProcessor implements ProcessorInterface
{
    private const REDACTED_KEYS = ['password', 'token', 'secret', 'raw_key'];

    private const EMAIL_PATTERN = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';

    private const API_KEY_PATTERN = '/\bibl_[a-zA-Z0-9]{8,}/';

    private const PRESERVED_KEYS = ['client_ip', 'username'];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactArray($record->context, $record->channel),
            extra: $this->redactArray($record->extra, $record->channel),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data, string $channel): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $keyStr = is_string($key) ? $key : (string) $key;

            if (in_array($keyStr, self::PRESERVED_KEYS, true)) {
                $result[$key] = $value;
                continue;
            }

            if (in_array(strtolower($keyStr), self::REDACTED_KEYS, true)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<array-key, mixed> $value */
                $result[$key] = $this->redactArray($value, $channel);
            } elseif (is_string($value)) {
                $result[$key] = $this->redactString($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function redactString(string $value): string
    {
        $value = (string) preg_replace(self::EMAIL_PATTERN, '***@***.***', $value);

        $value = (string) preg_replace_callback(
            self::API_KEY_PATTERN,
            static fn (array $m): string => substr($m[0], 0, 8) . '***',
            $value,
        );

        return $value;
    }
}
