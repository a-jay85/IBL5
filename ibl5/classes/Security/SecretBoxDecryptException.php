<?php

declare(strict_types=1);

namespace Security;

/**
 * A SecretBox ciphertext has an unknown format or fails to authenticate.
 *
 * Messages never carry the ciphertext or a key.
 */
class SecretBoxDecryptException extends \RuntimeException
{
}
