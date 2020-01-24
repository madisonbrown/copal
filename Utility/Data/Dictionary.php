<?php
namespace Copal\Utility\Data;

use std;
use Exception;
use Copal\Base\Struct;

class Dictionary extends Struct
{
	protected $entries = [];
	
	public function store($data)
	{
		$key = hash("fnv132", $serial = $_data = json_encode($data));
		while (isset($this->entries[$key]) && json_encode($this->entries[$key]) !== $_data)
			$key = hash("fnv132", $serial .= "#");
		if (!isset($this->entries[$key]))
			$this->entries[$key] = $data;
		return $key;
	}
	public function retrieve($key)
	{
		return $this->entries[$key] ?? null;
	}
}