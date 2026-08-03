<?php

/**
 * @file tests/secret-cipher.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Self-check for SecretCipher: round-trip encryption, legacy
 * plaintext backward-compatibility, and wrong-key failure behaviour.
 *
 * Usage: php tests/secret-cipher.php   (exit code 0 = pass)
 */

require_once dirname(__DIR__) . '/classes/SecretCipher.php';

use APP\plugins\paymethod\paystack\classes\SecretCipher;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$key = 'test-key-material-not-a-real-secret';
$otherKey = 'a-completely-different-key';

// Round-trip encrypt/decrypt.
$plaintext = 'fake-secret-key-abcdef1234567890';
$encrypted = SecretCipher::encrypt($plaintext, $key);
assertTrue($encrypted !== $plaintext, 'Encrypted value must differ from plaintext');
assertTrue(SecretCipher::isEncrypted($encrypted), 'Encrypted value must be tagged as encrypted');
assertSameValue($plaintext, SecretCipher::decrypt($encrypted, $key), 'Round-trip decrypt must recover the original plaintext');

// Two encryptions of the same plaintext must not be identical (random nonce/IV).
$encrypted2 = SecretCipher::encrypt($plaintext, $key);
assertTrue($encrypted !== $encrypted2, 'Encrypting the same plaintext twice must produce different ciphertext (random nonce)');
assertSameValue($plaintext, SecretCipher::decrypt($encrypted2, $key), 'Second ciphertext must also decrypt correctly');

// Empty string round-trips as empty (nothing to hide).
assertSameValue('', SecretCipher::encrypt('', $key), 'Encrypting an empty string returns an empty string');
assertSameValue('', SecretCipher::decrypt('', $key), 'Decrypting an empty string returns an empty string');
assertSameValue(null, SecretCipher::decrypt(null, $key), 'Decrypting null returns null');

// Legacy plaintext (pre-existing rows written before encryption existed)
// must still be readable, unchanged.
$legacyPlaintext = 'fake-legacy-secret-value';
assertSameValue($legacyPlaintext, SecretCipher::decrypt($legacyPlaintext, $key), 'Legacy plaintext values decrypt to themselves (backward compatibility)');
assertTrue(!SecretCipher::isEncrypted($legacyPlaintext), 'Legacy plaintext must not be reported as encrypted');

// Wrong key must fail to decrypt (returns null), not silently return garbage as success.
assertSameValue(null, SecretCipher::decrypt($encrypted, $otherKey), 'Decrypting with the wrong key must fail (return null), never return wrong plaintext');

echo "SecretCipher tests passed\n";
