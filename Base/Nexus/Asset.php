<?php
namespace Copal\Base\Nexus;

use Copal\App\Node;
use Copal\Utility\Template;
use Copal\Utility\Data\Dictionary;

abstract class Asset extends Template
{
	private static $Class;
	protected static $Root;
	
	protected static function Path(string $class)
	{
		if (is_dir($path = self::$Root.$class))
			$path .= "/index";
		if ($source = glob("$path.*"))
			return $path;
	}
	
	public static function Build(object $obj, Dictionary $dictionary)
	{
		if (self::$Class === null || self::$Root === null)
			throw new Exception("Class 'Asset' must be initialized.");
		else
			return self::$Class::Build($obj, $dictionary);
	}
	public static function Initialize(string $class, string $root)
	{
		self::$Class = $class;
		self::$Root = $root;
	}
}