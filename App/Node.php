<?php
namespace Copal\App;

use std;
use Closure;
use Exception;
use Copal\Base\Struct;
use Copal\Base\Field;
use Copal\Utility\Data\Dictionary;
use Copal\Utility\Meta\Delegate;
use Copal\Utility\Meta\Action;
use Copal\Utility\Library\Reflect;

abstract class Node extends Struct
{
	private static function Crawl(array $arr, string $path = null)
	{
		$result = [];
		if ($path !== null)
		{
			foreach ($arr as $key => $val)
			{
				if ($val instanceof Node && !$val->lock())
					$result["$path/$key"] = $val;
				else if (is_array($val))
					$result = array_merge($result, self::Crawl($val, "$path/$key"));
			}
		}
		else
		{
			foreach ($arr as $key => $val)
			{
				if (!($val instanceof Node))
					$result[] = $val;
				else if (is_array($val))
					$result = array_merge($result, self::Crawl($val));
			}
		}
		return $result;
	}
	
	private $source;
	private $handle;
	private $state = "10";
	
	public function __invoke()
	{
		return new Delegate(
			null,
			null, 
			function(string $method, array $args){
				if (method_exists($this, $method))
					return new Action($this, $method, $args);
			}
		);
	}
	public function __call(string $method, array $args)
	{
		if (substr($method, 0, 2) === "on")
			return null;
	}
	
	private function children(Closure $fn)
	{
		$this->lock(true);
		
		$dependents = [];
		Reflect::Properties($this, function($key, $val, $class) use(&$dependents){
			if ($class !== self::class)
			{
				if ($val instanceof Node && !$val->lock())
					$dependents[$key] = $val;
				else if (is_array($val))
					$dependents = array_merge($dependents, self::Crawl($val, $key));
			}
		});
		
		$fn($dependents);
		
		$this->lock(false);
	}
	private function properties()
	{
		$properties = [];
		
		$derived = get_object_vars($this);
		foreach (get_class_vars(self::class) as $key => $val)
			unset($derived[$key]);
		
		foreach ($derived as $key => $val)
			if (is_array($val))
				$properties = array_merge($properties, self::Crawl($val));
			else if (!($val instanceof Node))
				$properties[$key] = $val;
		
		return $properties;
	}
	
	private function lock(bool $value = null)
	{
		if ($value === null)
			return $this->state[1] === "1" ? true : false;
		else
			return $this->state[1] = $value ? "1" : "0";
	}
	private function hash()
	{
		$delta = substr($this->state, 2) !== ($new = md5(json_encode($this->properties())));
		$this->state = ($delta ? "1" : "0").$this->state[1].$new;
	}
	
	protected function attach(Node $parent, string $name)
	{
		$this->source = $parent;
		$this->handle = $name;
	}
	protected function replace(string $name, Node $node)
	{
		$this->$name = $node;
	}
	
	protected function resume(Node $parent, string $name)
	{
		$this->attach($parent, $name);
		
		$this->children(function(array $dependents){
			foreach ($dependents as $key => $val)
				$val->resume($this, $key);
		});
		
		return $this->onResume();
	}
	protected function load(Node $parent, string $name)
	{
		$this->attach($parent, $name);
		
		$this->children(function(array $dependents) use($parent, $name){
			foreach ($dependents as $key => $val)
				$val->load($this, $key);
		});
		
		return $this->onLoad();
	}
	protected function update()
	{
		$this->children(function(array $dependents){
			foreach ($dependents as $key => $val)
				$val->update();
		});
		
		$result = $this->onUpdate();
		$this->hash();
		return $result;
	}
	protected function unload()
	{
		$this->children(function(array $dependents){
			foreach ($dependents as $key => $val)
				$val->unload();
		});
		
		$this->source = null;
		$this->handle = null;
		
		return $this->onUnload();
	}
	protected function pause()
	{
		$this->children(function(array $dependents){
			foreach ($dependents as $key => $val)
				$val->pause();
		});
		
		return $this->onPause();
	}
	
	public function root()
	{
		return $this->source ? $this->source->root() : $this;
	}
	public function path()
	{
		return $this->source ? "{$this->source->path()}/$this->handle" : static::class;
	}
	public function delta()
	{
		return $this->state[0] === "1";
	}
	
	public function search(string $path)
	{
		$_path = explode("/", $path);
		$abs = $_path && strpos($_path[0], "\\") !== false;
		
		if (!$abs || array_shift($_path) === static::class)
		{
			$active = $this;
			foreach ($_path as $prop)
			{
				if ($prop)
				{
					if (is_object($active) && isset($active->$prop))
						$active = $active->$prop;
					else if (is_array($active) && isset($active[$prop]))
						$active = $active[$prop];
					else
						return null;
				}
			}
			return $active;
		}
		else if ($this->root() !== $this)
			return $this->root()->search($path);
	}
	public function changed()
	{
		$changed = [];
		if ($this->delta())
			$changed[] = $this;
		else
			$this->children(function(array $dependents) use(&$changed){
				foreach ($dependents as $child)
					$changed = array_merge($changed, $child->changed());
			});
		return $changed;
	}
	
	public function getstate()
	{
		return ($this->state);
	}
}