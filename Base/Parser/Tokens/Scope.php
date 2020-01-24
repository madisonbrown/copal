<?php
namespace Copal\Base\Parser\Tokens;

use std;
use Closure;
use Copal\Base\Parser\Base\Token;
use Copal\Base\Parser\Base\Target;

class Scope extends Token
{
	protected $invoke;
	
	public function __construct(string $type, ?Token $value, $order, Closure $invoke)
	{
		parent::__construct($type, $value, $order);
		
		$this->invoke = $invoke;
	}
	public function __toString()
	{
		if ($this->prefix)
			return "$this->type($this->prefix, $this->value)";
		else if ($this->suffix)
			return "$this->type($this->value, $this->suffix)";
		else
			return "$this->type($this->value)";
	}
	
	protected function evaluate()
	{
		if ($this->prefix && $this->suffix)
			return null;
		else if ($this->prefix)
			return ($this->invoke)($this->prefix, $this->value, false);
		else if ($this->suffix)
			return ($this->invoke)($this->value, $this->suffix, true);
		else
			return $this->value;//($this->invoke)($this->value, null, false);
	}
	
	public static function Factory(string $wrapper, $order, Target $parser, Closure $invoke = null)
	{
		return function(&$stream) use($wrapper, $order, $parser, $invoke){
			$wrapper = explode(" ", $wrapper);
			if (!strpos($stream, $wrapper[0]) && ($ref = std\block_split($stream, $wrapper[0], $wrapper[1])))
			{
				$block = $ref[1];
				$stream = $ref[2];
				return new static(implode($wrapper), $parser->parse($block), $order, $invoke);
			}
		};
	}
}