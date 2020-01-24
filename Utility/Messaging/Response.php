<?php
namespace Copal\Utility\Messaging;

use std;
use Copal\Base\Struct;
use Copal\Base\Interfaces\Message;

class Response extends Struct implements Message
{
	protected $success;
	protected $message;
	
	public $data;
	
	public function __construct(bool $success, string $message = "", $data = null)
	{
		$this->success = $success;
		$this->message = $message;
		$this->data = $data;
	}
}