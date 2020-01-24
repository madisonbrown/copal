<?php
namespace Copal\Utility\Meta;

use std;
use Exception;
use Copal\App\Node;
use Copal\App\Module;
use Copal\Base\Interfaces\Mask;

class Action extends Node implements Mask
{
	protected $target;
	protected $method;
	protected $args;
	
	public function __construct($target, string $method = null, array $args = [])
	{
		$this->target = $target;
		$this->method = $method;
		$this->args = $args;
	}
	public function __get(string $prop)
	{
		if ($prop === "target")
			return $this->target instanceof Node ? $this->target->path() : (string)$this->target;
		else
			return parent::__get($prop);
	}
	public function __invoke(...$args)
	{
		if (!($this->target instanceof Node))
			throw new Exception("Action node must be restored before use.");
		else if (method_exists($this->target, $method = $this->method))
			return $this->target->$method(...array_merge($this->args, $args));
	}
	
	protected function attach(Node $parent, string $name)
	{
		parent::attach($parent, $name);
		$this->restore($this->root());
	}
	
	public function restore(Node $ref)
	{
		if ($this->target instanceof Node || (is_string($this->target) && ($this->target = $ref->search($this->target))))
			return $this;
	}
}