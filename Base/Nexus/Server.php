<?php
namespace Copal\Base\Nexus;

use std;
use Exception;
use Copal\App\Resource;
use Copal\Base\Nexus\Database;
use Copal\Utility\Version\Schema;

abstract class Server
{	
	public static function Header($data)
	{
		if (!$data)
			return "";
		else if (is_string($data))
		{
			$header = [];
			foreach (preg_split("/\r\n/", $data) as $line)
				if (preg_match('/\A(\S+): (.*)\z/', chop($line), $ref))
					$header[$ref[1]] = $ref[2];
			return $header;
		}
		else if (is_array($data))
		{
			$header = "";
			foreach ($data as $key => $val)
				$header .= is_string($key) ? "$key: $val\r\n" : "$val\r\n";
			return $header."\r\n";
		}
	}
	public static function Cookie($data)
	{
		if (is_string($data))
		{
			$result = null;
			parse_str(strtr($data, array('&' => '%26', '+' => '%2B', ';' => '&')), $result);
			return $result;
		}
		else if (is_array($data))
		{
			$result = "";
			foreach ($data as $key => $val)
				$result .= "$key=$val; ";
			return $result;
		}
	}

	protected static $Instance = null;
	
	protected function __construct()
	{
		self::$Instance = $this;
	}
	
	protected function subscribe(Resource $resource)
	{
		
	}
	protected function update(Resource $resource)
	{
		
	}
	
	public static function Broadcast(Resource $resource)
	{
		self::$Instance->update($resource);
	}
	public static function Register(Resource $resource)
	{
		self::$Instance->subscribe($resource);
	}
	public static function Initialize(string $database_class, string $versions_path, string $asset_class = null, string $assets_path = null)
	{
		Resource::Initialize($database_class);
		Schema::Initialize($versions_path);
		if ($asset_class && $assets_path)
			Asset::Initialize($asset_class, $assets_path);
	}
}