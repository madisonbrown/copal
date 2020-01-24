<?php
namespace Copal\App;

use std;
use Exception;
use Serializable;
use ReflectionClass;
use Copal\Base\Nexus\Server;
use Copal\Base\Nexus\Database;
use Copal\Utility\Client;
use Copal\Utility\Data\Query;
use Copal\Utility\Library\Reflect;
use Copal\Utility\Library\Crypto;
use Copal\Utility\Version\Schema;
 
abstract class Resource extends Node
{
	const READ = 1;
	const WRITE = 2;
	
	const TABLE = self::class;
	const MODE = self::WRITE;
	
	private static $Transaction;
	protected static $Database;
	
	private static function Database()
	{
		if ($class = static::$Database)
			return $class::Load();
		else if ($class = static::class)
			throw new Exception("No database defined for class '$class'");
	}
	private static function Table()
	{
		if (is_a(static::class, static::TABLE, true))
			return str_replace("\\", "_", static::TABLE);
		else
			throw new Exception("Invalid table definition '".static::TABLE."' for class '".static::class."'.");
	}
	private static function Template()
	{
		if (std\keys_exist($record = static::Database()->template($table = self::Table()), '$id', '$time', '$'))
			return $record;
		else
			throw new Exception("Table '$table' not properly formatted for serialization.");
	}
	private static function Index($property)
	{
		if ($property === null)
			return null;
		else if ($property instanceof self && $property->id)
			return $property->id;
		else
			return (string)$property;
	}
	
	private $id;
	private $time;
	
	private $mode;
	private $cache;
	
	public function __destruct()
	{
		$this->release();
	}
	public function __get(string $prop)
	{
		if ($prop === "id")
			return $this->id;
		else
			return parent::__get($prop);
	}
	
	private function globals()
	{
		$data = parent::Deflate($this);
		std\key_shift($data, '$state', '$mode');
		return $data;
	}
	private function compare()
	{
		$result = [];
		
		if ($cache = $this->cache)
		{
			if (($globals = $this->globals()) != ($prev = json_decode($cache['$'], true)))
			{
				$globals['$time'] = time();
				$result['$'] = json_encode($globals);
			}
			
			//if ($this->mode != self::WRITE) var_dump([ "new" => std\array_diff_assoc_recursive($globals, $prev), "old" => std\array_diff_assoc_recursive($prev, $globals) ]);

			unset($cache['$']);
			unset($cache['$mode']);
			
			foreach ($cache as $prop => $val)
				if (($_val = self::Index($globals[$prop])) !== $val)
				{
					if ($_val === "")
						var_dump($globals[$prop]);
					$result[$prop] = $_val;
				}
		}
		
		return $result;
	}
	private function acquire(int $mode = null)
	{
		if (!$this->cache)
		{
			//static::Database()->select(self::Table(), Query::Default([ '$id' => $this->id ]), [ "$version" ]);
			
			if (($write = ($mode = $mode ?? static::MODE) === self::WRITE) && self::$Transaction !== null)
			{
				if (!isset(self::$Transaction["database"][$class = get_class($database = static::Database())]))
					self::$Transaction["database"][$class] = $database->begin();
				self::$Transaction["instance"][] = $this;
			}
			
			if (count($result = static::Database()->select(self::Table(), Query::Default([ '$id' => $this->id ]), null, $write)) == 1)
			{
				$data = json_decode(($this->cache = $result[0])['$'], true);
				if (!$write && Schema::Current(static::class) !== $data['$version'])
				{
					$this->cache = null;
					return $this->acquire(self::WRITE);
				}
				else
				{
					parent::Inflate($data, $this);
					$this->mode = $mode;
				}
			}
		}
		return $this;
	}
	private function release()
	{
		if ($this->cache)
		{
			$success = true;
			if ($record = $this->compare())
			{
				if ($this->mode != self::WRITE)
					throw new Exception("Read-only instances must not be altered.");
				else if (!($success = static::Database()->update(self::Table(), Query::Default([ '$id' => $this->id ]), $record)))
					echo static::Database()->error();
				else if (self::$Transaction !== null)
					self::$Transaction["update"][] = $this;
			}
			$this->cache = null;
			return $success;
		}
	}
	
	protected function resume(Node $parent, string $name)
	{
		$this->acquire();
		return parent::resume($parent, $name);
	}
	protected function load(Node $parent, string $name)
	{
		Server::Register($this);
		return parent::load($parent, $name);
	}
	protected function unload()
	{		
		return parent::unload();
	}
	
	public function unlock()
	{
		if ($this->mode === self::READ)
		{
			$this->release();
			return $this->acquire(self::WRITE);
		}
	}
	
	public static function Spawn(array $args)
	{
		return static::Secure(null, null, $args);
	}
	public static function Find($query, bool $multi = false)
	{
		if (std\is_assoc($query))
			$query = Query::Default($query);
		
		if ($query instanceof Query)
		{
			$result = [];
			foreach (static::Database()->select(self::Table(), $query, [ '$id' ]) as $record)
			{
				$instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
				$instance->id = $record['$id'];
				$instance->acquire();
				$result[] = $instance;
			}
			return $multi ? $result : (count($result) == 1 ? $result[0] : null);
		}
		else
			throw new Exception("Expected Query object or associative array.");
	}
	public static function Secure(string $key = null, object $domain = null, array $args = [])
	{
		$instance = null;
		if (!($id = ($domain && $key) ? Crypto::Srid($key, $domain) : $key) || !($instance = static::Find([ '$id' => $id ])))
		{
			$instance = std\is_indexed($args) ? new static(...$args) : parent::Inflate($args);
			
			$instance->id = $id ?? Crypto::Uuid();
			$instance->time = time();
			
			$record = self::Template();
			$record['$'] = json_encode($instance->globals());
			
			foreach (array_keys($record) as $prop)
				if ($_prop = $prop[0] === '$' ? substr($prop, 1) : $prop)
					$record[$prop] = self::Index($instance->$_prop);
			
			if (($result = static::Database()->insert(self::Table(), $record)) === true)
				$instance->acquire();
			else
			{
				var_dump($record);
				throw new Exception($result);
			}
		}
		return $instance;
	}
	
	public static function Open(Resource ...$init)
	{
		if (self::$Transaction === null)
		{
			self::$Transaction = [
				"instance" => [],
				"database" => [],
				"update" => []
			];
			
			foreach ($init as $instance)
				$instance->acquire();
			
			return true;
		}
		else
			throw new Exception("Transaction already started.");
	}
	public static function Close()
	{
		if (self::$Transaction !== null)
		{
			$success = true;
			foreach (self::$Transaction["instance"] as $instance)
				if ($instance->release() === false)
					$success = false;
			
			foreach (self::$Transaction["database"] as $database)
				$success ? $database->commit() : $database->revert();
			
			if ($success)
				foreach (self::$Transaction["update"] as $resource)
					Server::Broadcast($resource);
			
			self::$Transaction = null;

			return true;
		}
		else
			throw new Exception("No active transaction.");
	}
	
	public static function Initialize(string $database)
	{
		if (is_subclass_of($database, Database::class, true))
			static::$Database = $database;
		else
			throw new Exception("Expected Database subclass.");
	}
}