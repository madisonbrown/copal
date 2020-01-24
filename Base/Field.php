<?php
namespace Copal\Base;

use std;
use Exception;

abstract class Field extends Struct
{
	const NIL = "";
	
	private $value = null;
	private $serial = null;
	
	protected $name;
	protected $nullable;
	protected $default;
	protected $rules = [];
	
	public $meta;
	
	protected function __construct(bool $nullable, $default = null)
	{
		$this->nullable = $nullable;
		if (($this->nullable = $nullable) || $default !== null)
			$this->value($default);
	}
	public function __get(string $prop)
	{
		if ($prop === "value" || $prop === "serial")
			return $this->active() ? $this->$prop : $this->error("Field in void state cannot be evaluated.");
		else
			return parent::__get($prop);
	}
	public function __toString()
	{
		return $this->name;
	}
	
	private function error($string)
	{
		throw new Exception($string);
		return null;
	}
	private function set($value = null, string $serial = null)
	{
		if ($value !== null && $serial !== null)
		{
			$this->value = $value;
			$this->serial = $serial;
			return $this;
		}
		else if ($this->nullable)
		{
			$this->value = null;
			$this->serial = self::NIL;
			return $this;
		}
		else
			return $this->error("Non-nullable field '$this->name' cannot be set to null.");
	}
	private function validate(string $value)
	{
		if ($value === self::NIL)
			return $this->nullable;
		foreach($this->rules as $regex)
		{
			if ($neg = ($regex[0] === '!'))
				$regex = substr($regex, 1);
			$match = preg_match($regex, $value) === 1;
			if ($match === $neg)
				return false;
		}
		return true;
	}
	
	protected abstract function store($value);
	protected abstract function recall(string $value);
	protected function format(string $value){ return $value; }
	
	public function active()
	{
		return $this->serial !== null;
	}
	public function value($value = null)
	{
		if ($value === null)
			return $this->set();
		else if (($serial = $this->store($value)) !== null)
			return $this->set($value, $serial);
		else
			return $this->error("Type mismatch. $this->name");
	}
	public function serial(string $serial = null)
	{
		if ($serial === self::NIL || $serial === null)
			return $this->set();
		else if (($value = $this->recall($serial)) !== null)
			return $this->set($value, $serial);
		else
			return $this->error("Improperly formatted input string '$serial'.");
	}
	public function parse(string $input = null)
	{
		if ($input === null)
			$input = self::NIL;
		return $this->validate($input) && $this->serial($this->format($input));
	}
	public function conform(...$rules)
	{
		if ($rules && std\is_indexed($rules[0]))
			$rules = $rules[0];
		foreach ($rules as $rule)
			if (is_string($rule))
				$this->rules[] = $rule;
		return $this;
	}
	public function adopt(string $name)
	{
		if (!$this->name)
		{
			$this->name = $name;
			return $this;
		}
		else
			throw new Exception("Field has already been adopted.");
	}
	
	public static function Build(string $name, ...$args)
	{
		return (new static(...$args))->adopt($name);
	}
}