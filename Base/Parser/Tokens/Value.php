<?php
namespace Copal\Base\Parser\Tokens;

use Closure;
use Copal\Base\Parser\Base\Token;

class Value extends Token
{
	protected $output;
	
	public function __construct(string $type, string $value, Closure $output)
	{
		parent::__construct($type, $value, 0);
		
		$this->output = $output;
	}
	public function __toString()
	{
		return "$this->type($this->value)";
	}
	
	protected function evaluate()
	{
		return ($this->output)($this->value);
	}
	
	public static function Factory(string $type, string $pattern, Closure $output)
	{
		return function(string &$stream) use($type, $pattern, $output){
			if (preg_match($pattern, $stream, $ref) && !strpos($stream, $ref[0]))
			{
				$stream = substr($stream, strlen(array_shift($ref)));
				return new static($type, implode($ref), $output);
			}
		};
	}
}