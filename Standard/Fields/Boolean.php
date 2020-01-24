<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

class Boolean extends Field
{	
	public function __construct(bool $nullable = false)
	{
		parent::__construct($nullable);
		
		$this->conform("/(true)|(false)/");
	}
	
	public function format(string $value)
	{
		return $value === "true" ? "1" : "0";
	}
	
	public function store($value)
	{
		if (is_bool($value))
			return $value ? "1" : "0";
		else
			return null;
	}
	public function recall(string $value)
	{
		if ($value === "1")
			return true;
		else if ($value === "0")
			return false;
		else
			return null;
	}
}