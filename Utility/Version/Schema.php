<?php
namespace Copal\Utility\Version;

use std;
use Exception;
use Copal\Base\Field;
use Copal\Base\Parser\Base\{Target, Token};
use Copal\Base\Parser\Tokens\{Value, Operator, Scope};

class Schema extends Target
{
	private static $Root;
	
	const NILL = "x";
	const BOOLEAN = "b";
	const NUMBER = "n";
	const STRING = "s";
	const TYPE = "t";
	const WORD = "w";
	const PROPERTY = "p";
	
	protected $version;
	
	protected function __construct(string $version)
	{
		parent::__construct();
		
		$this->version = $version;
	}
	
	protected function specialize()
	{
		$this->register(Value::class, self::NILL, "/(null|NULL)(?!\\w)/s", function(string $value){
			return null;
		});
		$this->register(Value::class, self::BOOLEAN, "/(true|false|TRUE|FALSE)(?!\\w)/s", function(string $value){
			return strtolower($value) === "true" ? true : false;
		});
		$this->register(Value::class, self::NUMBER, "/([0-9]+(?:\\.[0-9]+)?)/s", function(string $value){
			return $value + 0;
		});
		$this->register(Value::class, self::STRING, "/(?:'([^']*)')|(?:\"([^\"]*)\")/s", function(string $value){
			return $value;
		});
		$this->register(Value::class, self::TYPE, "/((?:[A-Za-z]+)?\\\[A-Z\\\a-z]+)/s", function(string $value){
			return $value;
		});	
		$this->register(Value::class, self::WORD, "/([a-z_A-Z]+)/s", function(string $value){
			return $value;
		});
		
		$this->register(Operator::class, ",", 17, function(Token $a, Token $b){
			if (!is_array($a = $a())) $a = [$a];
		
			if (std\is_indexed($a))
				$a[] = $b();
			else if (std\is_assoc($b = $b()))
				$a = array_merge($a, $b);
			else
				throw new Exception("Expected associative array.");

			return $a;
		});
		$this->register(Operator::class, ":", -16, function(Token $a, Token $b){
			if (is_array($a = $a(self::WORD) ? [ "key" => $a = $a(), "ancestor" => $a ] : $a()) && is_array($b = $b()))
			{				
				if (std\keys_exist($result = array_merge($a, $b), "key", "property"))
					return new Rule($this->version, $result["key"], $result["property"], $result["mediator"] ?? null, $result["ancestor"] ?? null);
			}
		});
		$this->register(Operator::class, "<<", 7, function(Token $a, Token $b = null){
			if ($a(self::WORD))
				return [ "key" => $a = $a(), "ancestor" => $b && $b(self::WORD) ? $b() : null ];
		});
		$this->register(Operator::class, "/", 3, function(Token $a, Token $b){
			if ($a(self::TYPE) && $b(self::NUMBER))
				return [ "version" => $a()."/".$b->value ];
		});
		$this->register(Operator::class, "...", -3, function(Token $a = null, Token $b){
			if (!$a && is_array($b = $b()) && ($version = $b["version"] ?? null))
			{
				if (is_subclass_of(explode("/", $this->version)[0], explode("/", $version)[0], true))
					return ($data = self::Load($version)) == [null] ? [] : $data;
				else
					throw new Exception("Invalid version inheritance: $this->version : $version.");
			}
		});
		$this->register(Operator::class, '$this->', -3, function(Token $a = null, Token $b){
			if (!$a && $b(self::WORD))
				return [ "property" => $b() ];
		});
		
		$this->register(Scope::class, "[ ]", [ 2, -3 ], $this, function(Token $a, Token $b = null, bool $cast){
			if (!$b)
			{
				if (!std\is_indexed($a = $a()))
					$a = [ $a ];
				return $a;
			}
		});
		$this->register(Scope::class, "{ }", [ 2, -3 ], $this, function(Token $a = null, Token $b = null, bool $cast){
			if (!$b)
			{
				if (!a)
					return [];
				else if (!is_array($a = $a()))
					$a = [ $a ];
				return $a;
			}
		});
		$this->register(Scope::class, "( )", [ 2, -3 ], $this, function(Token $a, Token $b, bool $cast){
			if ($cast)
			{
				if (($a = $a()) instanceof Field && is_array($b = $b()) && isset($b["property"]))
					return [ "mediator" => $a, "property" => $b["property"] ];
			}
		});
		$this->register(Scope::class, "< >", [ 2, -3 ], $this, function(Token $a, Token $b = null, bool $cast){
			if (!$cast)
			{
				if (($a(self::TYPE) || $a(self::WORD)) && is_subclass_of($a = $a(), Field::class, true))
				{
					if (!$b)
						$b = [];
					else if (!is_array($b = $b()))
						$b = [ $b ];
					return new $a(...$b);
				}
			}
		});
	}
	
	public static function Current(string $class)
	{
		$dir = self::$Root.str_replace("\\", "/", $class);
		if (is_dir($dir) && ($all = scandir($dir)))
			return "$class/".str_replace(".jx", "", array_pop($all));
	}
	public static function Upgrades(string $version)
	{
		[ $class, $version ] = explode("/", $version);
		if (is_dir($dir = self::$Root.str_replace("\\", "/", $class)))
		{
			$result = [];
			
			$all = scandir($dir);
			while (($file = array_shift($all)) && $file !== "$version.jx");
			
			while ($file = array_shift($all))
				$result[] = "$class/".str_replace(".jx", "", $file);
			
			return $result;
		}
	}
	public static function Load(string $version)
	{
		if (count($args = explode("/", $version)) == 2 || ($args = explode("/", self::Current($args[0]))))
			[ $class, $name ] = $args;
		
		$dir = self::$Root.str_replace("\\", "/", $class);
		if (($file = "$name.jx") && !file_exists("$dir/$name.jx"))
			return null;
		
		if (!is_array($result = ((new static($version))->parse(file_get_contents("$dir/$file")))() ?? []))
		{
			json_encode($result = $result);
			throw new Exception("'$result' is not a Rule set in version '$version'.");
			return null;
		}
		else
		{
			$keys = [];
			foreach ($result as $rule)
			{
				if ($rule instanceof Rule)
				{
					if (!isset($keys[$key = $rule->key]))
						$keys[$key] = true;
					else
					{
						throw new Exception("Key '$key' defined more than once in version '$version'.");
						return null;
					}
				}
				else
				{
					$rule = json_encode($rule);
					throw new Exception("'$rule' is not a Rule in version '$version'.");
					return null;
				}
			}
		}
		
		return $result;
	}
	
	public static function Initialize(string $root = null)
	{
		self::$Root = $root;
	}
}