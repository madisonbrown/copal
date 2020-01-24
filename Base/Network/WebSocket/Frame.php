<?php
namespace Copal\Base\Network\WebSocket;

use std;

class Frame
{
	const TEXT = 1;
	const CLOSE = 8;
	const PING = 9;
	const PONG = 10;
	
	protected $fin;
	protected $rsv;
	protected $op;
	protected $mask;
	protected $length;
	protected $key;
	protected $data;
	
	public function __get(string $prop)
	{
		return $this->$prop;
	}
	
	private static function Parse(string &$stream, array &$p, int $l)
	{
		if ($l < 0)
		{
			if (strlen($stream) >= $p[0] + ((float)abs($l) + $p[1]) / 8)
			{
				$output = (ord($stream[$p[0]]) & bindec(str_repeat("0", $q = std\shift($p[1], $r = $p[1] + ($l = abs($l)))).str_repeat("1", $l).str_repeat("0", $s = 8 - $r))) >> $s;
				if (($q + $l) == 8 && ++$p[0])
					$p[1] = 0;
				return $output;
			}
		}
		else if (strlen($stream) >= $p[0] + $l)
			return substr($stream, std\shift($p[0], $p[0] + $l), $l);
	}
	public static function Mask(string $stream)
	{
		$b1 = 0x80 | (0x1 & 0x0f);
		if (($length = strlen($stream)) <= 125)
			$header = pack('CC', $b1, $length);
		else if ($length > 125 && $length < 65536)
			$header = pack('CCn', $b1, 126, $length);
		else if ($length >= 65536)
			$header = pack('CCNN', $b1, 127, $length);
		return $header.$stream;
	}
	public static function Unmask(string $stream, string $key)
	{
		$result = "";
		for ($i = 0; $i < strlen($stream); ++$i)
			$result .= $stream[$i] ^ $key[$i%4];
		return $result;
	}
	public static function Build(string $stream)
	{
		if (strlen($stream) >= 6)
		{
			$pos = [ 0, 0 ];
			$frame = new self();
			
			$frame->fin  = self::Parse($stream, $pos, -1);
			$frame->rsv  = self::Parse($stream, $pos, -3);
			$frame->op   = self::Parse($stream, $pos, -4);
			$frame->mask = self::Parse($stream, $pos, -1);
			$frame->len  = self::Parse($stream, $pos, -7);
			
			if ($frame->len > 125 && ($l = $frame->len == 126 ? 2 : 8))
				$frame->len = unpack($l == 2 ? "n" : "J", self::Parse($stream, $pos, $l))[1];
			if ($frame->mask)
				$frame->mask = self::Parse($stream, $pos, 4);
			if (($frame->data = self::Parse($stream, $pos, $frame->len)) && $frame->mask)
				$frame->data = self::Unmask($frame->data, $frame->mask);
			
			return $frame;
		}
	}
	public static function Message(string $stream)
	{
		if (($frame = self::Build($stream)) && $frame->op == self::TEXT)
			return $frame->data;
	}
}