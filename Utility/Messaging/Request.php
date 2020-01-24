<?php
namespace Copal\Utility\Messaging;

use std;
use Closure;
use Copal\Base\{Struct, Field};
use Copal\Base\Interfaces\Message;

class Request extends Struct implements Message
{
	private $map = [];
	
	protected $title = "";
	protected $children = [];
	
	protected function __construct(string $title = null, array &$map = null)
	{
		$this->title = $title;
		$this->map = &$map;
	}
	public function __invoke(string ...$keys)
	{
		if (!$keys)
			$keys = array_keys($this->map);
		
		$result = [];
		foreach ($keys as $key)
			if (isset($this->map[$key]))
				$result[$key] = $this->map[$key]->value;
		return $result;
	}
	public function __get(string $prop)
	{
		if (isset($this->map[$prop]))
			return $this->map[$prop];
		else
			return parent::__get($prop);
	}
	
	protected function onInflate()
	{
		foreach ($this->children as $child)
		{
			if ($child instanceof Request)
			{
				$child->map = &$this->map;
				$child->onInflate();
			}
			else if ($child instanceof Field)
				$this->map[$child->name] = $child;
		}
	}
	
	public function &group(string $title)
	{
		$this->children[] = new Request($title, $this->map);
		return $this->children[count($this->children) - 1];
	}
	public function entry(Field $field, string $title = null)
	{
		$field->meta = $title;
		$this->children[] = $this->map[$field->name] = $field;
		return $this;
	}
	
	public function validate(array $args)
	{
		foreach (array_keys($this->map) as $key)
			if (!array_key_exists($key, $args) || !$this->map[$key]->parse($args[$key]))
				return false;
		return true;
	}
	
	public static function Build(string $title = null, Closure $fn = null)
	{
		$request = new Request($title);
		if ($fn) $fn($request);
		return $request;
	}
}