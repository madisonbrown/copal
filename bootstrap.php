<?php
namespace std;

use Closure;
use Exception;

require_once __DIR__."/../autoload.php";

function is_socket($val)
{
	return is_resource($val) && get_resource_type($val) === "Socket";
}

function is_indexed($arr)
{
	return is_array($arr) && (!count($arr) || array_keys($arr) === range(0, count($arr) - 1)); 
}
function is_assoc($arr)
{
	return is_array($arr) && array_keys($arr) !== range(0, count($arr) - 1); 
}
function keys_exist(array $arr, string ...$key)
{
	foreach ($key as $k)
		if (!key_exists($k, $arr))
			return false;
	return true;
}
function key_shift(array &$arr, string ...$keys)
{
	if (count($keys) == 1)
	{
		if (key_exists($key = $keys[0], $arr))
		{
			$ret = $arr[$key];
			unset($arr[$key]);
			return $ret;
		}
	}
	else
	{
		$result = [];
		foreach ($keys as $key)
			$result[$key] = key_shift($arr, $key);
		return $result;
	}
}
function array_diff_assoc_recursive($array1, $array2)
{
    foreach($array1 as $key => $value)
    {
        if(is_array($value))
        {
              if(!isset($array2[$key]))
              {
                  $difference[$key] = $value;
              }
              elseif(!is_array($array2[$key]))
              {
                  $difference[$key] = $value;
              }
              else
              {
                  $new_diff = array_diff_assoc_recursive($value, $array2[$key]);
                  if($new_diff != FALSE)
                  {
                        $difference[$key] = $new_diff;
                  }
              }
          }
          elseif(!array_key_exists($key, $array2) || $array2[$key] != $value)
          {
              $difference[$key] = $value;
          }
    }
    return !isset($difference) ? 0 : $difference;
}

function first(...$args)
{
	foreach ($args as $val)
		if ($val) return $val;
	return $args[count($args) - 1];
}
function shift(&$var, $val = null)
{
	$ret = $var;
	$var = $val;
	return $ret;
}


function strip(string $str = null)
{
	return $str ? preg_replace("/\\s+/", " ", $str) : "";
}
function strtocamel(string $str, string $delim = "_")
{
	if ($str[0] !== strtoupper($str[0]) && ($str = "$delim$str"))
		while (preg_match("/$delim([a-z])/", $str, $ref))
			$str = str_replace($ref[0], strtoupper($ref[1]), $str);
	return $str;
}
function class_handle(object $obj, string $delim = "_")
{
	preg_match("/(?:.*\\\)?([^\\\]+)/", get_class($obj), $ref);
	return substr(strtolower(preg_replace("/[A-Z]/", "$delim$0", $ref[1])), 1);
}
function to_bool(&$var)
{
	if (isset($var) && $var === "true")
		return true;
	else
		return false;
}

function block_split(string $stream, string $open, string $close)
{
	if (($l = strlen($stream)) && ($po = -($lo = strlen($open))) && ($pc = -($lc = strlen($close))))
	{
		$no = $nc = 0;
		while (($pc = strpos($stream, $close, $pc + $lc)) !== false && ($nc += 1))
		{
			while (($o = strpos($stream, $open, $po + $lo)) !== false && $o < $pc)
				($no += 1) and ($po = $o);
			if ($no === $nc)
				return array(substr($stream, 0, ($po = strpos($stream, $open))), substr($stream, $po + $lo, $pc - ($po + $lo)), substr($stream, $pc + $lc));
		}
	}
	return false;
}
function parse_token(string $regex, string &$stream, bool $commit = true)
{
	if (($_stream = trim($stream)) && preg_match($regex, $_stream, $ref) && !strpos($_stream, $ref[0]))
	{
		$len = strlen(array_shift($ref));
		if ($commit)
			$stream = trim(substr($_stream, $len));
		return implode($ref);
	}
}
function extract_tag(string $stream, string $tag, Closure $handler)
{
	$_stream = $stream;
	if ($ref = block_split($stream, "<$tag", "</$tag>"))
	{
		if (($result = $handler(parse_token("/([^>]*)>/s", $ref[1]), $ref[1])) !== false)
			$_stream = $ref[0].$result.$ref[2];
	}
	else if (preg_match("/<$tag([^>]*)\\/>/s", $stream, $ref))
	{
		if (($result = $handler($ref[1], null)) !== false)
			$_stream = str_replace($ref[0], $result, $stream);
	}
	return $_stream;
}
function extract_tags(string $stream, Closure $handler)
{
	$_stream = "";
	while (preg_match("/<(\\w+)/s", $stream, $ref))
	{
		if (preg_match("/$ref[0]([^>]*)\\/>/s", $stream, $data))
		{
			$_stream .= $handler($ref[1].$data[1], null);
			$stream = str_replace($data[0], "", $stream);
		}
		else if (($block = block_split($stream, $ref[0], "</$ref[1]>")) && preg_match("/([^>]*)>(.*)/s", $block[1], $data))
		{
			$_stream .= $handler($ref[1].$data[1], $data[2]);
			$stream = $block[0].$block[2];
		}
	}
	return $_stream;
}

function http_request($method, $url, $data = null)
{
	$request = curl_init();
	curl_setopt($request, CURLOPT_URL, $url);
	if ($method === "POST")
	{
		curl_setopt($request, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($request, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($request, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($request, CURLOPT_POSTREDIR, 3);
	}
	curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

	$response = curl_exec($request);
	curl_close ($request);

	return $response;
}
function redirect($path)
{
	die(header("Location: ".$path));
}

function project_file($path)
{
	return $_SERVER['PROJECT_ROOT'].$path;
}
function abs_path($path)
{
	list($context) = get_included_files();
	return dirname($context).DIRECTORY_SEPARATOR.$path;
}
function project_service($path)
{
	return $_SERVER['PROJECT_URL'].$path;
}
function this_service()
{
	return "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
}

function schedule($time, $method, $url, $data)
{
	$task = array(
		"url" => $url,
		"method" => $method,
		"data" => $data,
		"time" => $time
	);
	return http_request("POST", "https://www.immersify.org/api/cronjobs/schedule.php", $task);
}
function build_message($path, $_DATA)
{
	ob_start();
	include $path;
	$file = str_replace("<body>", "<body><![CDATA[", 
		str_replace("</body>", "]]></body>", 
		ob_get_clean()));
	return simplexml_load_string($file, null, LIBXML_NOCDATA);
}
function send_message($message)
{
	if (http_request("POST", "https://www.immersify.org/api/sendmail", array('message' => $message, 'password' => $_SERVER['SMTP_PASS'])) === false)
		return false;
	else
		return true;
}