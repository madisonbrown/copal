<?php
namespace Copal\Standard\Databases;
 
use std;
use mysqli;
use Exception;
use Copal\Base\Nexus\Database;
use Copal\Utility\Data\Query;

class MySql extends Database
{
	const HOST = null;
	const USER = null;
	const PASS = null;
	const NAME = null;
	
	const OP = [
		Query::AND => "AND",
		Query::OR => "OR",
		Query::GREATER	 => ">",
		Query::LESS => "<",
		Query::LEAST => ">=",
		Query::MOST => "<=",
		Query::EQUALS => "=",
		Query::NOT => "!="
	];

	private static function Parse(array $query)
	{
		$statement = "";
		$values = [];
		
		if (count($query) == 1 && (($op = key($query)) === Query::AND || $op === Query::OR))
		{
			$op = self::OP[$op];
			foreach (reset($query) as $entry)
			{
				[ $s, $v ] = self::Parse($entry);
				$statement .= ($statement ? " $op " : "").$s;
				$values = array_merge($values, $v);
			}
			$statement = "($statement)";
		}
		else if (count($query) == 1 && ($op === Query::GREATER || $op === Query::LESS || $op === Query::LEAST || $op === Query::MOST || $op === Query::EQUALS || $op === Query::NOT))
		{
			$op = self::OP[$op];
			$statement .= key($pair = reset($query))." $op ?";
			$values[] = reset($pair);
		}

		return [ $statement, $values ];
	}
	private static function Flatten(array $arr, bool $quotes = false, string $delim = ", ")
	{
		$result = "";
		if (std\is_indexed($arr)) 
			foreach ($arr as $value)
				$result .= ($result ? $delim : "").($value === null ? "NULL" : ($quotes ? "'$value'" : $value));
		else foreach ($arr as $key => $val)
			$result .= ($result ? $delim : "")."$key=?";
		return $result;
	}
	
	protected static function Connect()
	{
		if ($connection = new mysqli(static::HOST, static::USER, static::PASS, static::NAME))
		{
			if ($error = $connection->connect_error)
				throw new Exception($error);
			else
			{
				$instance = new static();
				$instance->mysqli = $connection;
				return $instance;
			}
		}
	}
	
	private $active = false;
	
	protected $mysqli;
	
	public function begin()
	{
		if (!std\shift($this->active, true))
		{
			$this->mysqli->query("START TRANSACTION;");
			return $this;
		}
		else
			throw new Exception("Transaction mismatch.");
	}
	public function revert()
	{
		if (std\shift($this->active, false))
		{
			$this->mysqli->query("ROLLBACK");
			return $this;
		}
		else
			throw new Exception("Transaction mismatch.");
	}
	public function commit()
	{
		if (std\shift($this->active, false))
		{
			$this->mysqli->query("COMMIT");
			return $this;
		}
		else
			throw new Exception("Transaction mismatch.");
	}
	public function template(string $table)
	{
		$record = [];
		if ($query = $this->mysqli->query("DESC $table"))
			while ($col = $query->fetch_assoc()) 
				$record[$col["Field"]] = null;
		return $record;
	}
	public function insert(string $table, array $record)
	{
		if (std\is_assoc($record))
		{
			$values = array_values($record);
			$fields = self::Flatten(array_keys($record));
			$mask = self::Flatten(array_fill(0, count($values), "?"));
				
			if (!($stmt = $this->mysqli->prepare($str = "Insert INTO $table($fields) VALUES($mask)")))
				throw new Exception("Error in statment '$str'");
			$stmt->bind_param(str_repeat("s", count($values)), ...$values);
			$stmt->execute();
			
			$output = !$this->mysqli->error ? true : $this->mysqli->error;
			
			$stmt->close();
			return $output;
		}
		else
			throw new Exception("Expected associative array.");
	}
	public function select(string $table, Query $query, array $fields = null, bool $lock = false)
	{
		$fields = std\is_indexed($fields) ? self::Flatten($fields) : "*";
		[ $condition, $values ] = self::Parse($query->data);
		$lock = $lock ? "FOR UPDATE" : "";
		
		if (!($stmt = $this->mysqli->prepare($str = "SELECT $fields FROM $table WHERE $condition $lock")))
			throw new Exception("Error in statment '$str'");
		$stmt->bind_param(str_repeat("s", count($values)), ...$values);
		$stmt->execute();
		
		$output = [];
		if (($result = $stmt->get_result())->num_rows)
		{
			while ($record = $result->fetch_assoc())
			{
				foreach ($record as $key => $val)
					$record[$key] = $val === null ? null : (string)$val;
				$output[] = $record;
			}
		}
		
		$stmt->close();
		return $output;
	}
	public function update(string $table, Query $query, array $data, bool $unique = true)
	{
		$fields = self::Flatten($data, true);
		[ $condition, $values ] = self::Parse($query->data);
		$values = array_merge(array_values($data), $values);
		
		if (!($stmt = $this->mysqli->prepare($str = "UPDATE $table SET $fields WHERE $condition")))
			throw new Exception("Error in statment '$str': {$this->error()}");
		$stmt->bind_param(str_repeat("s", count($values)), ...$values);
		$output = $stmt->execute();
		
		$stmt->close();
		return $output;
	}
	public function check()
	{
		$this->mysqli->query('SELECT LAST_INSERT_ID()');
		return $this->mysqli->errno != 2006 ? $this->mysqli->thread_id : false;
	}
	public function close()
	{
		$this->mysqli->close();
	}
	public function error()
	{
		return $this->mysqli->error; 
	}
}