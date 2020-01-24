<?php
namespace Copal\Utility;

use std;
use Closure;
use Copal\App\Node;
use Copal\App\Resource;

class Client extends Resource
{
	const BAD_REQUEST = 1;
	
	protected $active;
	protected $dormant = [];
	protected $error;

	protected function replace(string $name, Node $node)
	{
		$this->recall($node, true);
	}
	protected function recall($target, bool $destructive = false)
	{
		if (is_string($target))
		{
			if ($this->active instanceof $target)
				return $this->active;
			if (isset($this->dormant[$target]))
				return $this->recall($this->dormant[$target], $destructive);
		}
		else if ($target instanceof Node)
		{			
			if ($this->active)
			{
				if ($this->active === $target)
					return $this->active;
				else
				{
					$this->active->unload();
					if (!$destructive)
						$this->dormant[get_class($this->active)] = std\shift($this->active);
				}
			}
			
			unset($this->dormant[$class = get_class($target)]);
			if ($override = $target->load($this, "active"))
				return $this->recall($override);
			else
				return $this->active = $target;
		}
	}
	
	public function address()
	{
		return std\class_handle($this->active, "-");
	}
	public function active(string $path = null)
	{
		if ($path)
			return $this->search($path) or false;
		else if ($this->active)
			return get_class($this->active);
	}
	public function transform(string $module = null, string $request = "", array $data = [])
	{
		$error = null;

		if ($module)
			$this->recall($module) ?? $this->recall(new $module());
		else if ($this->active)
			$module = get_class($this->active);
		else 
			return false;
		
		$this->active->resume($this, "active");
		
		if ($this->active instanceof $module && $request)
		{
			if ($request = $this->active->handle($request, $data))
				$this->recall($request, true);
			else if ($request === false)
				$error = self::BAD_REQUEST;
		}
		
		if ($this->active instanceof $module)
			$this->recall($this->active->update(true), true);
		
		$this->active->pause();
		
		return $this->active instanceof $module && !($this->error = $error);
	}
	public function render(bool $update = false)
	{
		return $this->active->render($update);
	}
}

//construct =>
//             > invoke => update => pause => render
//   resume =>