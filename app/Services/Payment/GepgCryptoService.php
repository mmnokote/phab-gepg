<?php

namespace App\Services\Payment;

use RuntimeException;

class GepgCryptoService
{
    public function sign(string $data): string
    {
        $privateKeyPath = config('gepg.private_key_path');
        $passphrase = config('gepg.private_key_passphrase', '');

        if (! $privateKeyPath || ! is_file($privateKeyPath)) {
            throw new RuntimeException('GePG private key file is missing or not readable.');
        }

        $privateKeyContent = file_get_contents($privateKeyPath);
        if ($privateKeyContent === false) {
            throw new RuntimeException('Unable to read GePG private key file.');
        }

        $privateKey = $this->loadPrivateKey((string) $privateKeyContent, (string) $passphrase);
        if (! $privateKey) {
            throw new RuntimeException('Unable to parse GePG private key. Check key format and passphrase.');
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (! $ok) {
            throw new RuntimeException('Failed to sign GePG payload using RSA SHA256.');
        }

        return base64_encode($signature);
    }

    public function verify(string $data, ?string $signature): bool
    {
        if (! config('gepg.verify_signature', false)) {
            return true;
        }

        if (! $signature) {
            return false;
        }

        $publicKeyPath = config('gepg.public_key_path');
        if (! $publicKeyPath || ! is_file($publicKeyPath)) {
            return false;
        }

        $publicKeyContent = file_get_contents($publicKeyPath);
        if ($publicKeyContent === false) {
            return false;
        }

        $publicKey = $this->loadPublicKey((string) $publicKeyContent);
        if (! $publicKey) {
            return false;
        }

        $decoded = base64_decode(trim($signature), true);
        if ($decoded === false) {
            return false;
        }

        $result = openssl_verify($data, $decoded, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        openssl_free_key($publicKey);

        return $result;
    }

    private function loadPrivateKey(string $content, string $passphrase): mixed
    {
        // PEM private key
        $key = openssl_pkey_get_private($content, $passphrase);
        if ($key) {
            return $key;
        }

        // DER PKCS#8 -> PEM "PRIVATE KEY"
        $pemPkcs8 = $this->toPem($content, 'PRIVATE KEY');
        $key = openssl_pkey_get_private($pemPkcs8, $passphrase);
        if ($key) {
            return $key;
        }

        // DER PKCS#1 -> PEM "RSA PRIVATE KEY"
        $pemPkcs1 = $this->toPem($content, 'RSA PRIVATE KEY');

        return openssl_pkey_get_private($pemPkcs1, $passphrase);
    }

    private function loadPublicKey(string $content): mixed
    {
        // PEM public key / certificate
        $key = openssl_pkey_get_public($content);
        if ($key) {
            return $key;
        }

        // DER X509 SubjectPublicKeyInfo -> PEM "PUBLIC KEY"
        $pemPub = $this->toPem($content, 'PUBLIC KEY');
        $key = openssl_pkey_get_public($pemPub);
        if ($key) {
            return $key;
        }

        // DER certificate -> PEM "CERTIFICATE"
        $pemCert = $this->toPem($content, 'CERTIFICATE');

        return openssl_pkey_get_public($pemCert);
    }

    private function toPem(string $binary, string $label): string
    {
        return "-----BEGIN {$label}-----\n"
            . chunk_split(base64_encode($binary), 64, "\n")
            . "-----END {$label}-----\n";
    }
}
