<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

class Location extends Field
{
	public $max;
	
	public function __construct(int $max = null, bool $nullable = false)
	{
		parent::__construct($nullable);
		
		if ($max !== null) 
			$this->conform("!/.{{$max}}/");
		
		$this->max = $max;
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