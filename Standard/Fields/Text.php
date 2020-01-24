<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

class Text extends Field
{
	public $min;
	public $max;
	
	public function __construct(int $min = null, int $max = null, $default = null)
	{
		parent::__construct(!$min, $default);
		
		$this->min = $min;
		$this->max = $max ? $max + 1 : $max;
		
		if ($this->min !== null) 
			$this->conform("/.{{$this->min}}/");
		if ($this->max !== null) 
			$this->conform("!/.{{$this->max}}/");
	}
	
	public function store($value)
	{
		return is_string($value) ? $value : null;
	}
	public function recall(string $value)
	{
		return $value;
	}
}