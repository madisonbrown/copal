<?php
namespace Copal\Base\Parser\Base;

use Exception;

abstract class Target
{
	protected $types = [];
	
	public function __construct()
	{
		$this->specialize();
	}
	public function __sleep()
	{
		$this->types = null;
		return array_keys(get_object_vars($this));
	}
	public function __wakeup()
	{
		self::__construct();
	}
	
	protected abstract function specialize();
	
	protected function register(string $Base, string $type, ...$args)
	{
		$this->types[$type] = $Base::Factory($type, ...$args);
	}
	protected function step(string &$stream)
	{
		if ($_stream = trim($stream))
		{
			foreach ($this->types as $type => $factory)
			{
				if ($token = $factory($_stream))
				{
					$stream = trim($_stream);
					return $token;
				}
			}
		}
		else
			$stream = $_stream;
	}
	
	public function parse(string $stream)
	{
		$val = $this->step($stream);
		
		while ($stream)
			if (!($next = $this->step($stream)) || !($val = Token::Merge($val, $next)))
				throw new Exception("Unable to evaluate stream at '$val $stream'");
		
		return $val;
	}
}