<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

class Number extends Field
{
	public $min;
	public $max;
	
	public function __construct(int $min = null, int $max = null, bool $nullable = false, $default = null)
	{
		parent::__construct($nullable, $default);
		
		$this->min = $min;
		$this->max = $max;
	}
	
	public function store($value)
	{
		return $value === $value + 0 ? $value : null;
	}
	public function recall(string $value)
	{
		if ($value == $value + 0)
		{
			$value = $value + 0;
			return (($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max)) ? $value : null;
		}
		else
			return null;
	}
}