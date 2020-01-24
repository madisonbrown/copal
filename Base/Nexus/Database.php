<?php
namespace Copal\Base\Nexus;

use Copal\Utility\Data\Query;
 
abstract class Database
{	
	private static $Instances = [];
	
	protected static function Connect()
	{
		return null;
	}
	
	public abstract function begin();
	public abstract function revert();
	public abstract function commit();
	public abstract function template(string $table);
	public abstract function insert(string $table, array $record);
	public abstract function select(string $table, Query $condition, array $fields = null, bool $lock = false);
	public abstract function update(string $table, Query $condition, array $data, bool $unique = true);
	public abstract function check();
	public abstract function close();
	public abstract function error();
	
	public static function Load(bool $reset = false)
	{
		if (!isset(self::$Instances[static::class]) || self::$Instances[static::class]->check() === false) //fix
			self::$Instances[static::class] = static::Connect();
		return self::$Instances[static::class] ?? null;
	}
}