<?php
namespace Copal\Utility\Library;

use std;

class Crypto
{
	public static function Uuid()
	{
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	public static function Srid(string $key, object $domain)
	{
		return "--".md5(get_class($domain)."::$key")."--";
	}
	
	public static function Encrypt(string $data, string $key)
	{
		$l = strlen($key);
		if ($l < 16)
			$key = str_repeat($key, ceil(16/$l));
		if ($m = strlen($data)%8)
			$data .= str_repeat("\n",  8 - $m);
		$val = openssl_encrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);

		return $val;
	}
	public static function Decrypt(string $data, string $key)
	{
		$l = strlen($key);
		if ($l < 16)
			$key = str_repeat($key, ceil(16/$l));
		$val = openssl_decrypt($data, 'BF-ECB', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
		return $val;
	}
	public static function Encode($data, string $key)
	{
		return strtr(base64_encode(self::Encrypt(serialize($data), $key)), '+/=', '~_-');
	}
	public static function Decode(string $data, string $key)
	{
		return $data ? unserialize(self::Decrypt(base64_decode(strtr($data, '~_-', '+/=')), $key)) : null;
	}
}