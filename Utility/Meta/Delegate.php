<?php
namespace Copal\Utility\Meta;

use std;
use Closure;
use Copal\Base\Struct;

class Delegate
{
	protected $get;
	protected $set;
	protected $call;
	
	public function __construct(Closure $get = null, Closure $set = null, Closure $call = null)
	{
		$this->get = $get;
		$this->set = $set;
		$this->call = $call;
	}
	
	public function __call(string $name, array $args)
	{
		return ($this->call)($name, $args);
	}
	public function __get(string $prop)
	{
		return ($this->get)($prop);
	}
	public function __set(string $prop, $val)
	{
		return ($this->set)($prop, $val);
	}
}