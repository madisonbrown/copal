<?php
namespace Copal\Standard\Servers;

use std;
use Exception;
use Copal\Base\Struct;
use Copal\App\Resource;
use Copal\Base\Nexus\Server;
use Copal\Utility\Client;
use Copal\Utility\Library\Crypto;
use Copal\Utility\Library\Socket;

class Http extends Server
{
	private $controller;
	
	protected function __construct(string $module)
	{
		parent::__construct();
		
		$this->controller = Socket::Create(8080, "127.0.0.1");
		
		Resource::Open();
		
		$client = Client::Secure($id = isset($_COOKIE[$key = md5(Server::class)]) ? $_COOKIE[$key] : null);
		$error = $client->error;
		$redirect = !$client->transform($module, $request = $_GET ? array_keys($_GET)[0] : "", $_POST);

		header("Cache-Control: no-store");
		setcookie($key, $client->id, 0, "/");
		
		if ($async = isset($_GET["async"]))
			$buffer = json_encode([ $client->address(), $redirect ? "" : $client->render($async) ]);
		else if ($request || $redirect)
			$buffer = header("Location: {$client->address()}");
		else
			$buffer = $client->render();
		
		Resource::Close();
		
		if ($this->controller)
			Socket::Destroy($this->controller);
		
		if ($error === Client::BAD_REQUEST)
			echo "Sorry, something went wrong! Please try again.";

		echo $buffer;
	}
	
	public function update(Resource $resource)
	{
		if ($this->controller)
			Socket::Write($this->controller, json_encode([ "update" => $resource->id ]));
	}
	public static function Build(string $module)
	{
		new static($module);
	}
}