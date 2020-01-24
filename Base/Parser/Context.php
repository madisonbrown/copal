<?php
namespace Copal\Base\Parser;

use std;
use Copal\Base\Parser\Base\Target;
use Copal\Base\Parser\Base\Token;
use Copal\Base\Parser\Tokens\Value;
use Copal\Base\Parser\Tokens\Operator;
use Copal\Base\Parser\Tokens\Scope;

class Context extends Target
{
	const NUMBER = "n"; 
	const STRING = "s";
	const VARIABLE = "v";
	
	private $context;
	
	protected function resolve(string $var)
	{
		if (isset($this->context[$var]))
			return $this->context[$var];
	}
	
	protected function specialize()
	{
		$this->register(Value::class, static::NUMBER, "/([0-9]+(?:\\.[0-9]+)?)/s", function(string $value){
			return $value + 0;
		});
		$this->register(Value::class, static::STRING, "/(?:'([^']*)')|(?:\"([^\"]*)\")/s", function(string $value){
			return $value;
		});
		$this->register(Value::class, static::VARIABLE, "/([a-z_A-Z]+)/s", function(string $value){
			return $this->resolve($value);
		});
		
		$this->register(Operator::class, ".", 2, function(Token $a, Token $b){
			if (is_string($b = $b->value))
			{
				if (is_array($a = $a()))
					return $a[$b];
				else if (is_object($a))
					return $a->$b;
			}
		});
		$this->register(Operator::class, ":", 15, function(Token $a = null, Token $b = null){
			if (!$a && !$b)
				return null;
			else if ($a === null && is_array($arr = $b()))
				return array_values($arr);
			else if ($b === null && is_array($arr = $a()))
				return array_keys($arr);
			else if (is_string($a = $a(static::VARIABLE) ? $a->value : $a()))
				return [$a => $b()];
		});
		$this->register(Operator::class, ",", 16, function(Token $a, Token $b){
			if (!is_array($a = $a())) $a = [$a];
		
			if (std\is_indexed($a))
				$a[] = $b();
			else if (std\is_assoc($b = $b()))
				$a = array_merge($a, $b);
			else
				throw new Exception("Expected associative array.");

			return $a;
		});
	}
	protected function evaluate(string $stream, array $context = [])
	{
		$this->context = $context;
		$result = parent::parse($stream);
		$result = $result();
		$this->context = null;
		return $result;
	}
}