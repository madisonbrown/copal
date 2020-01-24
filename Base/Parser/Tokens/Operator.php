<?php
namespace Copal\Base\Parser\Tokens;

use Closure;
use Copal\Base\Parser\Base\Token;

class Operator extends Token
{
	protected $calc;
	
	public function __construct(string $type, $order, Closure $calc)
	{
		parent::__construct($type, "", $order);
		
		$this->calc = $calc;
	}
	public function __toString()
	{
		return "$this->type($this->prefix, $this->suffix)";
	}
	
	protected function evaluate()
	{
		return ($this->calc)($this->prefix, $this->suffix);
	}
	
	public static function Factory(string $symbol, $order, Closure $calc)
	{
		return function(string &$stream) use($symbol, $order, $calc){
			if (strpos($stream, $symbol) === 0)
			{
				$stream = substr($stream, strlen($symbol));
				return new static($symbol, $order, $calc);
			}
		};
	}
}