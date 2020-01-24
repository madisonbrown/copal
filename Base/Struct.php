<?php
namespace Copal\Base;

use std;
use Closure;
use Exception;
use JsonSerializable;
use ReflectionClass;
use Copal\Base\Field;
use Copal\Utility\Meta\Delegate;
use Copal\Utility\Library\Reflect;
use Copal\Utility\Version\{Schema, Rule};
use Criterion\Resources\User;

use Copal\App\Node;

abstract class Struct implements JsonSerializable
{
	private static $Attributes = [];
	
	private static function Version()
	{
		if (!($version = static::Attribute("version")))
			$version = static::Attribute("version", Schema::Load(static::class));
		return $version;
	}
	private static function Fields()
	{
		if (!($fields = static::Attribute("fields")))
		{
			$fields = [];
			foreach (static::Version() as $rule)
				if ($rule->classname === static::class && $rule->mediator !== null)
					$fields[$rule->property] = $rule->mediator;
			static::Attribute("fields", $fields);
		}
		return $fields;
	}
	
	protected static function Attribute(string $name, $val = null)
	{
		if (func_num_args() == 1)
			return self::$Attributes[static::class][$name] ?? null;
		else
			return self::$Attributes[static::class][$name] = $val;
	}
	protected static function Field(Field $field = null)
	{
		return $_this = new Delegate(
			function(string $prop) use($field){
				if ($field || $field = clone self::Fields()[$prop] ?? null)
					return $field->adopt($prop);
			},
			null,
			function(string $prop, array $args) use(&$_this){
				if ($field = $_this->$prop)
					return (isset($args[1]) && $args[1] === true) ? $field->serial($args[0] ?? null) : $field->value($args[0] ?? null);
			}
		);
	}
	protected static function Upgrade(array $data, string $version)
	{
		foreach (Schema::Upgrades($version) as $upgrade)
			foreach (Schema::Load($upgrade) as $rule)
				if ($prop = std\key_shift($data, $rule->ancestor))
					$data[$rule->key] = $prop;
		return $data;
	}
	
	public function __get(string $prop)
	{
		if ($prop === "version")
			return Schema::Current(static::class);
		if (isset($this->$prop))
			return is_object($this->$prop) ? clone $this->$prop : $this->$prop;
		else
			return null;
	}
	public function jsonSerialize()
	{
		return self::Deflate($this);
    }
	
	protected function onDeflate()
	{
		
	}
	protected function onInflate()
	{
		
	}
	
	public function to_array()
	{
		$result = [];
		foreach (array_keys(get_object_vars($this)) as $name)
			$result[$name] = $this->__get($name);
		return $result;
	}
	
	public static function Deflate($that)
	{
		if ($that instanceof Struct)
		{
			$that->onDeflate();
			
			$Class = get_class($that);
			$result = [ '$version' => Schema::Current($Class) ];
			foreach ($Class::Version() as $meta)
			{
				$val = $meta->read($that);
				if ($field = $meta->mediator)
					$result[$meta->key] = ($_val = $field->value($val)->serial) === "" ? null : $_val;
				else
				{
					$_Class = $val instanceof Struct ? get_class($val) : self::class;
					$result[$meta->key] = $_Class::Deflate($val);
				}
			}
			return $result;
		}
		else if (is_array($that))
		{
			$result = [];
			foreach ($that as $key => $val)
			{
				$_Class = $val instanceof Struct ? get_class($val) : self::class;
				$result[$key] = $_Class::Deflate($val);
			}
			return $result;
		}
		else if (!is_object($that))
			return $that;
		else
			throw new Exception("Non-Struct objects cannot be deflated.");
	}
	public static function Inflate($data, object $instance = null, string $version = null)
	{
		if ($version)
		{
			$Class = explode("/", $version)[0];
			$data = $Class::Upgrade($data, $version);
			
			if (!$instance)
				$instance = Reflect::Default($Class);
			else if (get_class($instance) !== $Class)
				throw new Exception("Instance of class '$Class' cannot be inflated with data of version '$version'.");
			
			foreach ($Class::Version() as $meta)
			{
				$val = $data[$meta->key] ?? null;
				$_val = ($field = $meta->mediator) ? $field->serial($val)->value : Struct::Inflate($val);
				if ($_val !== null || $field !== null)
					$meta->write($instance, $_val);
			}
			
			$instance->onInflate();
			return $instance;
		}
		else if (is_array($data))
		{
			if (($version = std\key_shift($data, '$version')) && ($Class = explode("/", $version)[0]))
				return $Class::Inflate($data, $instance, $version);
			else if (($Class = static::class) !== self::class && ($version = Schema::Current($Class)))
				return $Class::Inflate($data, $instance, $version);
			else
			{
				$result = [];
				foreach ($data as $key => $val)
					$result[$key] = Struct::Inflate($val);
				return $result;
			}
		}
		else
			return $data;
	}
}