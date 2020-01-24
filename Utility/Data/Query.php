<?php
namespace Copal\Utility\Data;

use std;

class Query
{
	const GREATER = ">";
	const LESS = "<";
	const LEAST = ">=";
	const MOST = "<=";
	const EQUALS = "=";
	const NOT = "!=";
	
	const AND = "&&";
	const OR = "||";
	
	protected $data = [];
	
	protected function __construct(array $data)
	{
		$this->data = $data;
	}
	public function __get(string $prop)
	{
		if ($prop === "data")
			return $this->data;
	}
	
	private static function Intra(array $data, string $op)
	{
		if (std\is_assoc($data))
		{
			$result = [];
			foreach ($data as $key => $val)
				$result[] = [ $op => [ $key => $val ] ];
			return $result;
		}
	}
	private static function Extra(array $data, string $op)
	{
		if (std\is_indexed($data))
		{
			$union = [];
			foreach ($data as $val)
				$union = array_merge($union, $val instanceof Query ? [ $val->data ] : $val);
			return [ $op => $union ];
		}
	}
	
	public static function Equals(array $data)
	{
		return self::Intra($data, self::EQUALS);
	}
	public static function Not(array $data)
	{
		return self::Intra($data, self::NOT);
	}
	public static function Greater(array $data)
	{
		return self::Intra($data, self::GREATER);
	}
	public static function Less(array $data)
	{
		return self::Intra($data, self::LESS);
	}
	public static function Least(array $data)
	{
		return self::Intra($data, self::LEAST);
	}
	public static function Most(array $data)
	{
		return self::Intra($data, self::MOST);
	}
	
	public static function And(...$data)
	{
		return new static(self::Extra($data, self::AND));
	}
	public static function Or(...$data)
	{
		return new static(self::Extra($data, self::OR));
	}
	
	public static function Default(array $data)
	{
		return self::And(self::Equals($data));
	}
}