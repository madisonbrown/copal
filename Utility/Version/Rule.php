<?php
namespace Copal\Utility\Version;

use std, Exception, ReflectionClass;
use Copal\Base\Field;

class Rule
{
	private $reflector;
	
	protected $key;
	protected $property;
	protected $mediator;
	protected $ancestor;
	protected $classname;
	
	public function __construct(string $version, string $key, string $property, Field $mediator = null, string $ancestor = null)
	{
		if (($ref = new ReflectionClass($class = explode("/", $version)[0])) && ($prop = $ref->getProperty($property)))
		{
			$prop->setAccessible(true);
			$this->reflector = $prop;
			
			$this->key = $prop->isPrivate() ? "$$key" : $key;
			$this->property = $property;
			$this->ancestor = $ancestor;
			$this->mediator = $mediator;
			$this->classname = $class;
		}
	}
	public function __get(string $prop)
	{
		if ($prop !== "reflector" && isset($this->$prop))
			return is_object($this->$prop) ? clone $this->$prop : $this->$prop;
		else
			return null;
	}
	
	public function read($instance)
	{
		$property = $this->property;
		return $this->reflector->isPrivate() ? $this->reflector->getValue($instance) : $instance->$property;
	}
	public function write($instance, $value)
	{
		$this->reflector->setValue($instance, $value);
		return $value;
	}
}