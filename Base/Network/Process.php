<?php
namespace Copal\Base\Network;

use std;
use Exception;
use Closure;
use Copal\Base\Nexus\Server;
use Copal\Base\Nexus\Database;
use Copal\Utility\Library\Socket;

class Process extends Server
{
	private static function Tick(int $freq = null, int &$last)
	{
		return $freq && (($time = time()) - $last) >= $freq && std\shift($last, $time);
	}
	
	private $port;
	private $freq;
	
	private $input;
	private $output = [];
	
	protected function __construct(int $port, int $freq = null)
	{
		parent::__construct();
		$this->input = Socket::Create($this->port = $port);
		$this->freq = $freq;
	}
	public function __get(string $prop)
	{
		if ($prop === "port")
			return $this->port;
		else if ($prop === "freq")
			return $this->freq;
	}
	
	protected function onBegin(){}
	protected function onTick(){}
	protected function onMessage($id, string $data){}
	protected function onQuit($id){}
	protected function onEnd(){}

	private function sockets(int $id = null)
	{
		if (isset($id))
			return ($this->input && Socket::Id($this->input) === $id) ? $this->input : ($this->output[$id] ?? null);
		else
			return $this->input ? array_merge([ $this->input ], array_values($this->output)) : $this->output;
	}
	private function listen()
	{
		if (!$this->input)
			return Socket::Error();

		$this->onBegin();
	
		$update = time();
		while ($sockets = $this->sockets())
		{
			$events = Socket::Block($sockets, $this->freq ? $update + $this->freq : null);
			
			if ($this->input && ($client = Socket::Accept($this->input, $events)) && !$this->connect($client))
				Socket::Destroy($client);
			
			foreach ($events as $client)
			{
				if ($message = Socket::Read($client))
					$this->onMessage(Socket::Id($client), $message);
				else
					$this->onQuit($this->disconnect($client));
			}
			
			if (self::Tick($this->freq, $update))
				$this->onTick();
		}

		pcntl_wait($status);
		return $this->onEnd();
	}
	
	protected function getIp($socket)
	{
		if ($socket = $this->sockets($id = (int)$socket))
			return Socket::Ip($socket);
	}
	protected function active()
	{
		return array_keys($this->output);
	}
	protected function listening()
	{
		return $this->input !== null;
	}
	
	protected function connect($location, $data = null)
	{
		if (is_string($location) && [ $ip, $port ] = explode(":", $location))
			$location = Socket::Create($port, $ip);
		
		if (std\is_socket($location))
		{
			$this->output[$id = Socket::Id($location)] = $location;
			$this->push($id, $data);
			return $id;
		}
		else 
			return false;
	}
	protected function disconnect($socket)
	{
		if ($socket = $this->sockets((int)$socket))
		{
			Socket::Destroy($socket);
			unset($this->output[$id = Socket::Id($socket)]);
			return $id;
		}
	}
	protected function push($targets, string $data)
	{
		$count = 0;
		foreach (is_array($targets) ? $targets : [ $targets ] as $target)
			if (Socket::Write(std\is_socket($target) ? $target : $this->sockets((int)$target), $data))
				++$count;
		return $count;
	}
	protected function fork($socket)
	{
		if ($this->input && ($socket = $this->sockets($id = (int)$socket)) && ($pid = pcntl_fork()) != -1)
		{
			if ($pid)
			{
				//parent
				unset($this->output[$id]);
				return false;
			}
			else
			{
				//child
				$this->input = null;
				$this->output = [ $id => $socket ];
				return true;
			}
		}
		else
			throw new Exception("Unable to fork.");
	}
	protected function abort() 
	{
		$this->disconnect(std\shift($this->input));
		foreach ($this->output as $client)
			$this->disconnect($client);
	}
	
	public static function Build(int $port, int $freq = null)
	{
		echo (new static($port, $freq))->listen();
	}
}