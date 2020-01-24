<?php
namespace Copal\Utility\Library;

use std;
use Closure;
use ReflectionClass;
use ReflectionProperty;

class Reflect
{
	public static function Default(string $class)
	{
		return (new ReflectionClass($class))->newInstanceWithoutConstructor();
	}
	public static function Properties(object $target, Closure $fn, $filter = null)
	{
		$ref = new ReflectionClass(get_class($target));
		do {
			foreach ($ref->getProperties($filter) as $property) 
			{
				if ($property->class == $ref->getName() && !$property->isStatic())
				{
					$property->setAccessible(true);
					if (($res = $fn($property->getName(), $property->getValue($target), $ref->getName())) !== null)
						return $res;
				}
			}
		} while ($ref = $ref->getParentClass());
	}
	public static function Property(object $target, string $class, string $name, $value = null)
	{
		$ref = new ReflectionClass($class);
		$prop = $ref->getProperty($name);
		$prop->setAccessible(true);
		if (func_num_args() == 4)
			$prop->setValue($target, $value);
		return $prop->getValue($target);
	}
	public static function Internal(object $target, string $name, $value = null)
	{
		foreach (array_reverse(self::Classes($target)) as $class)
		{
			if (($ref = new ReflectionClass($class))->hasProperty($name))
			{
				$prop = $ref->getProperty($name);
				$prop->setAccessible(true);
				if (func_num_args() == 3)
					$prop->setValue($target, $value);
				return $prop->getValue($target);
			}
		}
	}
	public static function Classes(object $target, Closure $fn = null)
	{
		if ($fn == null)
			$res = [];
		
		$ref = new ReflectionClass(get_class($target));
		do {
			if (!$fn)
				$res[] = $ref->getName();
			else if (($res = $fn($ref->getName())) !== null)
				return $res;
		} while ($ref = $ref->getParentClass());
		
		return $fn ? null : $res;
	}
}