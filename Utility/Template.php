<?php
namespace Copal\Utility;

use std;
use Closure;
use Exception;
use Copal\Base\Parser\Context;
use Copal\Base\Parser\Base\Token;
use Copal\Base\Parser\Tokens\Value;
use Copal\Base\Parser\Tokens\Operator;
use Copal\Base\Parser\Tokens\Scope;

class Template extends Context
{
	const FILE = "f";
	
	private static function Import($path)
	{
		$stream = "";
		if (($source = glob("$path.*")) && isset($source[0]) && ($ext = pathinfo($source[0], PATHINFO_EXTENSION)))
			$stream = trim(file_get_contents("$path.$ext"));
		
		$sequence = [];
		while ($ref = std\block_split($stream, "{{", "}}"))
		{
			$sequence[] = $ref[0];
			$sequence[] = [ $ref[1] ];
			$stream = $ref[2];
		}
		$sequence[] = $stream;
		
		return $sequence;
	}	

	protected $path;
	protected $context;
	
	public function __construct(string $path, array $context)
	{
		parent::__construct();
		
		$this->path = $path;
		$this->context = $context;
	}
	
	protected function specialize()
	{
		$this->register(Value::class, static::FILE, "/@([A-Z_a-z\\/]+)/s", function(string $value){
			return $value;
		});
		
		$this->register(Operator::class, "&", -2, function(Token $a = null, Token $b){
			return json_encode($b());
		});
		$this->register(Operator::class, "$", -2, function(Token $a = null, Token $b){
			return $b(static::VARIABLE) ? $b() : $this->resolve($b());
		});
		$this->register(Operator::class, "#", -2, function(Token $a = null, Token $b){
			if (!$a)
			{
				$result = [];
				
				if (std\is_assoc($b = $b()))
					foreach($b as $key => $val)
						$result[] = [ $key => $val ];
				else
					throw new Exception("Expected array, received ".get_class($b));
				
				return $result;
			}
		});
		$this->register(Operator::class, "+", 6, function(Token $a, Token $b){
			if (is_string($a = $a()))
				return $a . $b();
			else
				return $a + $b();
		});
		$this->register(Operator::class, "||", 15, function(Token $a, Token $b){
			return std\first($a(), $b());
		});
		
		$this->register(Scope::class, "[ ]", 2, $this, function(Token $a, Token $b = null, bool $cast){
			if (!$cast && $b)
			{
				if (!std\is_indexed($a = $a()))
					$a = [ $a ];

				if (is_string($b = $b->value))
				{
					$result = [];
					foreach ($a as $val)
						if (isset($val[$b]))
							$result[] = $val[$b];
					return $result;
				}
			}
		});
		$this->register(Scope::class, "{ }", 2, $this, function(Token $a, Token $b = null, bool $cast){
			if (!$cast && $b)
			{
				if (!std\is_indexed($a = $a()))
					$a = [ $a ];

				if (is_string($b = $b->value))
				{
					$result = [];
					foreach ($a as $val)
						$result[] = [ $b => $val ];
					return $result;
				}
			}
		});
		
		parent::specialize();
	}
	protected function format($value)
	{
		$result = "";
		
		if (is_string($value))
			$result .= $value;
		else if (std\is_indexed($value))
			foreach ($value as $_value)
				$result .= $this->format($_value);
		else
			$result .= (string)$value;

		return $result;
	}
	
	public function render()
	{
		$result = "";
		$sequence = self::Import($this->path);
		$context = std\is_indexed($this->context) ? $this->context : [ $this->context ];
		
		foreach ($context as $_context)
			foreach ($sequence as $unit)
				$result .= $this->format(is_array($unit) ? $this->evaluate($unit[0], $_context) : $unit);
		return $result;
	}
}

