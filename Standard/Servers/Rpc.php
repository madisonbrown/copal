<?php
namespace Copal\Standard\Servers;

use std;
use Exception;
use Copal\Base\Nexus\Server;

class Rpc extends Server
{
	protected $buffer;
	
	protected function __construct(array $path, string $dir)
	{
		parent::__construct();
		
		$class = null;
		$instance = null;
		$command = key($_GET);
		$data = $_POST;
		
		if ($path && class_exists($class = $dir.std\strtocamel(array_shift($path))))
		{
			foreach ($path as $name)
			{
				if ($instance)
				{
					if (property_exists($instance, $name) && is_object($instance->$name))
					{
						$instance = $instance->$name;
						$class = get_class($instance);
					}
					else
						throw new Exception("Field '$name' does not exist in class '$class'.");
				}
				else if ($name[0] === "~")
				{
					if (!($instance = $class::Find([ "id" => $id = substr($name, 1) ])))
						throw new Exception("Object $class::$id does not exist.");
				}
				else
					throw new Exception("Invalid accessor '$name'.");
			}
			
			if ($data && $command = key($_GET))
			{
				if (method_exists($target = $instance ? $instance : $class, $command))
				{
					if ($result = $target::Interface()->$command($data))
						echo $result;
					else
						echo $target::Interface()->$command();
				}
				else
					throw new Exception("Method '$command' does not exist in class '$class'.");
			}
			else if ($instance)
			{
				$result = [];
				foreach ($_GET as $name => $val)
					if (property_exists($instance, $name))
						$result[$name] = $instance->$name;
				echo $result ? json_encode($result) : $instance;
			}
		}
	}
	
	public static function Build(array $path, string $dir)
	{
		echo (new static($path, $dir))->buffer;
	}
}


//	GET /user?find { email: "a@b.c" }
//		{}
//	POST /user?spawn { email: "a@b.c", password: "abc123" }
//		true
//	GET /user?find { email: "a@b.c", password: "abc123" }
//		{ id: "a7g4" }
//	POST /user/a7g4?advance { phase: 1 }
//		true