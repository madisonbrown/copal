<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

class Enum extends Field
{
	const OPTIONS = [];
	
	public function __construct(bool $nullable = false)
	{
		parent::__construct($nullable);
		
		$this->conform("/.+/");
	}
	public function __get(string $prop)
	{
		if ($prop === "options")
			return static::OPTIONS;
		else
			return parent::__get($prop);
	}
	
	public function store($value)
	{
		return (is_string($value) && isset(static::OPTIONS[$value])) ? $value : null;
	}
	public function recall(string $value)
	{
		return isset(static::OPTIONS[$value]) ? $value : null;
	}
}