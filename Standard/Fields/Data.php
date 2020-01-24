<?php
namespace Copal\Standard\Fields;

use Copal\Base\Field;

//json_last_error() == JSON_ERROR_NONE
class Data extends Field
{
	public function __construct(bool $nullable = false)
	{		
		parent::__construct($nullable);
	}
	
	public function store($value)
	{
		return serialize($value);
	}
	public function recall(string $value)
	{
		return unserialize($value);
	}
}