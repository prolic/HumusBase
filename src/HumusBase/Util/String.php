<?php

namespace HumusBase\Util;

use COM,
    HumusBase\Util\Exception\RuntimeException;

class String
{
    /**
     * Get random bytes
     *
     * returns X random raw binary bytes
     *
     * @param int $byteLength
     * @return string
     * @throws RuntimeException
     */
    public static function getRandomBytes($byteLength)
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes($byteLength);
        } elseif (is_readable('/dev/urandom')) {
            $fp = fopen('/dev/urandom','rb');
            if ($fp !== false) {
                $data = fread($fp, $byteLength);
                fclose($fp);
            }
        } elseif(function_exists('mcrypt_create_iv') && version_compare(PHP_VERSION, '5.3.0', '>=')) {
            $data = mcrypt_create_iv($byteLength, MCRYPT_DEV_URANDOM);
        } elseif (class_exists('COM')) {
            try {
                $capi = new COM('CAPICOM.Utilities.1');
                $data = $capi->GetRandom($byteLength,0);
            } catch (\Exception $ex) {} // Fail silently
        }
        if(empty($data)) {
            throw new RuntimeException(
                'Unable to find a secure method for generating random bytes.'
            );
        }
        return $data;
    }
}
