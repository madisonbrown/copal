<?php
namespace Copal\Utility\Library;

use std;

class Socket
{
	
	public static function Ip($socket)
	{
		$ip = null;
		if ($socket)
			socket_getpeername($socket, $ip);
		return $ip;
	}
	public static function Id($socket)
	{
		return (int)$socket;
	}
	public static function Create(int $port, string $ip = null)
	{
		if (!($socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) return null;
		if ($ip === null)
		{
			if (!@socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) return null;
			if (!@socket_bind($socket, 0, $port)) return null;
			if (!@socket_listen($socket)) return null;
		}
		else
		{
			if (!@socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0))) return null;
			if (!@socket_connect($socket, $ip, $port)) return null;
		}
		
		return $socket;
	}
	public static function Read($socket)
	{
		if ($socket)
		{
			$message = "";
			while (($block = @socket_recv($socket, $buf, 1024, MSG_DONTWAIT)) >= 1 && ($message .= $buf));
			return $block === 0 ? false : $message;
		}
	}
	public static function Write($socket, string $data)
	{
		if ($socket && $data)
		{
			while (($sent = @socket_send($socket, $data, strlen($data), MSG_DONTROUTE)) && ($data = substr($data, $sent)));
			return $sent !== false;
		}
	}
	public static function Block(array $sockets, int $until = null)
	{
		$null = null;
		socket_select($sockets, $null, $null, isset($until) ? (($next = $until - time()) >= 0 ? $next : 0) : null);
		return $sockets;
	}
	public static function Accept($socket, array &$events)
	{
		if (($index = array_search($socket, $events)) !== false && ($client = socket_accept($socket)))
		{
			unset($events[$index]);
			return $client;
		}
	}
	public static function Destroy($socket)
	{
		if ($socket)
		{
			@socket_shutdown($socket);
			@socket_close($socket);
		}
	}
	public static function Error()
	{
		return socket_strerror(socket_last_error());
	}
}