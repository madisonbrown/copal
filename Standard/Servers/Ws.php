<?php
namespace Copal\Standard\Servers;

use std;
use Closure;
use Copal\Resource;
use Copal\Base\Nexus\Database;
use Copal\Base\Nexus\Server;
use Copal\Utility\Client;
use Copal\Base\Network\WebSocket\Thread;

class Ws extends Thread
{
	private static $Auto = "localhost";
	private static $Peers = [];

	protected $peers = [];
	protected $threads = [];
	protected $subscriptions = [];

	private function mirror(string $ip = null)
	{
		return ($ip ?? "127.0.0.1").":$this->port";
	}

	protected function onBegin()
	{
		foreach (self::$Peers as $ip)
			if (array_search($ip, $this->peers) === false && ($id = $this->connect($this->mirror($ip))))
				$this->peers[$id] = $ip;
	}
	protected function onMessage($id, string $_data)
	{
		if (!$this->listening())
			return parent::onMessage($id, $_data);

		$data = json_decode($_data, true);

		if (!isset($data["anon"]) && !isset($this->threads[$id]) && !isset($this->peers[$id]))
		{
			if (($ip = $this->getIp($id)) === "127.0.0.1" && isset($data["callback"]))
			{
				//new thread
				$this->threads[$id] = true;
			}
			else if (array_search($ip, self::$Peers) !== false)
			{
				//new peer
				if (($_id = array_search($ip, $this->peers)) !== false)
					$this->onQuit($this->disconnect($_id));
				$this->peers[$id] = $ip;
			}
			else if ($this->fork($id))
			{
				//new client
				if ($_id = $this->connect($this->mirror(), [ "callback" => true ]))
				{
					$this->controller = $_id;
					parent::onMessage($id, $_data);
				}
				else
					$this->abort();
			}
		}

		if (isset($data["anon"]) || isset($this->threads[$id]) || isset($this->peers[$id]))
		{
			if (isset($this->threads[$id]) && ($resource = $data["subscribe"] ?? null))
			{
				if (!isset($this->subscriptions[$resource]))
					$this->subscriptions[$resource] = [];
				$this->subscriptions[$resource][$id] = true;
			}
			else if ($resource = $data["update"] ?? null)
			{
				if (!isset($this->peers[$id]))
					foreach (array_keys($this->peers) as $peer)
						$this->push($peer, [ "update" => $resource ]);
				
				if (isset($this->subscriptions[$resource]))
					foreach (array_keys($this->subscriptions[$resource]) as $subscriber)
					{
						if (!isset($this->threads[$subscriber]))
							unset($this->subscriptions[$resource][$subscriber]);
						else if ($subscriber != $id)
							$this->push($subscriber, [ "update" => $resource ]);
					}
			}
		}
	}
	protected function onQuit($id)
	{
		if (!$this->listening())
			return parent::onQuit($id);

		if (isset($this->threads[$id]))
		{
			unset($this->threads[$id]);
			if (!$this->threads)
				$this->abort();
		}
		else
			unset($this->peers[$id]);
	}

	public static function Initialize(string $database_class, string $versions_path, string $asset_class = null, string $assets_path = null, array $nodes = [], string $ip = null)
	{
		parent::Initialize($database_class, $versions_path, $asset_class, $assets_path);

		if (($i = array_search($ip, $nodes)) !== false)
			unset($nodes[$i]);

		self::$Peers = $nodes;
		self::$Auto = $ip;
	}
}