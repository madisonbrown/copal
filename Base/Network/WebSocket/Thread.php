<?php
namespace Copal\Base\Network\WebSocket;

use std;
use Closure;
use Copal\App\Resource;
use Copal\Base\Network\Process;
use Copal\Base\Nexus\Database;
use Copal\Base\Nexus\Server;
use Copal\Utility\Client;

class Thread extends Process
{
	private static function Handshake(string $header, Closure $cookiev = null)
	{
		if ($header = self::Header($header))
			if ($key = $header['Sec-WebSocket-Key'] ?? null)
				if ($cookies = $cookiev ? $cookiev(self::Cookie($header["Cookie"] ?? null)) : true)
					return self::Header([
						0 => "HTTP/1.1 101 Switching Protocols",
						"Upgrade" => "websocket",
						"Connection" => "Upgrade",
						"Sec-WebSocket-Accept" => base64_encode(pack('H*', sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11"))),
						"Set-Cookie" => self::Cookie($cookies)
					]);
	}

	protected $controller;
	protected $clients = [];
	
	private function render(int $id, bool $async, $address = null, $query = "", $args = [])
	{
		$time = microtime(true);
		$client = $this->clients[$id];
		
		if (Resource::Open($client))
		{
			$class = null;
			if ($address && preg_match("/(?:.*\\/)?([^\\.]+)(?:\\..+)?/", $address, $ref))
				$class = "Criterion\Modules\\".std\strtocamel($ref[1], "-");

			$redirect = !$client->transform($class, $query, $args);
			$msg = [ $client->address(), $redirect ? "" : $client->render($async) ];
			
			if ($redirect || $msg[1])
				$this->push($id, $msg);
			
			Resource::Close();
		}
		//var_dump("Render ".(int)((microtime(true) - $time) * 1000)."ms");
	}
	
	protected function push($id, $data)
	{
		if (!is_string($data))
			$data = json_encode($data);

		if (isset($this->clients[$id]))
			$data = Frame::Mask($data);

		return parent::push($id, $data);
	}
	
	protected function onMessage($id, string $_data)
	{
		if ($id === $this->controller)
		{
			//control message
			if (is_array($data = json_decode($_data, true)))
			{
				if (isset($data["update"]))
					foreach (array_keys($this->clients) as $id)
						$this->render($id, true);
			}
		}
		else if (isset($this->clients[$id]))
		{
			//client message
			if (is_array($data = json_decode(Frame::Message($_data), true)))
			{
				$request = parse_url(key($data));

				$query = [];
				if (isset($request["query"]))
					parse_str($request["query"], $query);

				$this->render($id, isset($query["async"]), $request["path"] ?? null, array_keys($query)[0] ?? "", reset($data));
			}
		}
		else
		{
			//new client
			$client = function(array $cookies = null) use(&$client){
				if (($sid = $cookies[$key = md5(Server::class)] ?? null) && ($client = Client::Secure($sid)))
					return [ $key => $client->id ];
			};

			if (!$this->push($id, self::Handshake($_data, $client)) || !($this->clients[$id] = $client))
				$this->abort();
		}
	}
	protected function onQuit($id)
	{
		if (isset($this->clients[$id]))
			unset($this->clients[$id]);
		else if ($id === $this->controller)
			unset($this->controller);
		
		if (!$this->clients || !$this->controller)
			$this->abort();
	}
	
	protected function subscribe(Resource $resource)
	{
		$this->push($this->controller, [ "subscribe" => $resource->id ]);
	}
	protected function update(Resource $resource)
	{
		$this->push($this->controller, [ "update" => $resource->id ]);
	}
}