<?php
namespace Copal\Base\Parser\Base;

use std;

abstract class Token
{
	protected $type;
	protected $value;
	protected $order;
	
	protected $source;
	protected $prefix;
	protected $suffix;

	public function __construct(string $type, $value, $order)
	{
		$this->type = $type;
		$this->value = $value;
		if (std\is_indexed($order) && count($order) == 2)
			$this->order = $order;
		else if (is_int($order))
			$this->order = [ $order, $order ];
		else
			$this->order = [ 0, 0 ];
	}
	public function __invoke(string $type = null)
	{
		if ($type)
			return $this->type === $type;
		else
		{
			$val = $this;
			while (($val = $val->evaluate()) instanceof Token);
			return $val;
		}
	}
	public function __get(string $prop)
	{
		return $this->$prop;
	}
	
	protected abstract function evaluate();
	
	public static function Merge(Token $left, Token $right)
	{
		$high = null;
		$low = $left;
		$r = abs($right->order[0]);
		while ($low && ($l = abs($low->order[1])) && ($low->order[1] < 0 ? $r <= $l : $r < $l))
			$high = std\shift($low, $low->suffix);
		
		if ($high || $right->order[0])
		{
			$right->prefix = $low;
			if ($low)
				$low->source = $right;
			
			if ($high)
			{
				$high->suffix = $right;
				$right->source = $high;
				return $left;
			}
			else
				return $right;
		}
	}
}