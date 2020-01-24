<?php
namespace Copal\Standard\Assets;

use std;
use Closure;
use Exception;
use Copal\App\Node;
use Copal\Base\Nexus\Asset;
use Copal\Base\Struct;
use Copal\Base\Interfaces\Mask;
use Copal\Base\Parser\Base\Token;
use Copal\Base\Parser\Tokens\Value;
use Copal\Base\Parser\Tokens\Operator;
use Copal\Base\Parser\Tokens\Scope;
use Copal\Utility\Library\Reflect;
use Copal\Utility\Data\Dictionary;

class Html extends Asset
{	
	protected $id;
	protected $name;
	protected $head = [];
	protected $dictionary;
	
	protected function __construct(string $name, array $context, Dictionary $dictionary, string $id = null)
	{
		if ($path = self::Path($name))
			parent::__construct($path, $context);
		else
			throw new Exception("Invalid asset name '$name'");
		
		$this->id = $id;
		$this->name = $name;
		$this->dictionary = $dictionary;
	}
	
	protected function directory()
	{
		if (preg_match("/.*\\//", $this->path, $dir) && strlen($dir[0]) > $len = strlen(self::$Root))
			return substr($dir[0], $len);
	}
	protected function reference(string $file)
	{
		if ($file && $file[0] === "/")
			return substr($file, 1);
		else
			return $this->directory().$file;
	}
	protected function specialize()
	{		
		$this->register(Operator::class, "extends", 8, function(Token $a = null, Token $b){
			if (($b = $b()) instanceof Html)
			{
				$b->id = $this->id;
				return $b->render(true);
			}
		});
		$this->register(Operator::class, "=>", 2, function(Token $a, Token $b){
			if (($a = $a()) instanceof Node && is_string($b = $b->value))
				return $a()->$b();
		});
		
		$this->register(Scope::class, "( )", 2, $this, function(Token $a, Token $b = null, bool $cast){
			if (!$cast && $b)
			{
				$file = $a(self::FILE);
					
				$a = $a();
				$b = $b();
				
				if ($file)
					return new static($this->reference($a), $b, $this->dictionary);
				else if (std\is_indexed($a) && std\is_indexed($b))
				{
					$result = [];
					foreach ($a as $i => $val)
						$result[] = new static($this->reference($val), $b[$i], $this->dictionary);
					return $result;
				}
			}
		});
		
		parent::specialize();
	}
	protected function capture(Html $that)
	{
		$result = $that->render();
		$this->head = array_merge_recursive($this->head, std\shift($that->head, []));
		return $result;
	}
	protected function format($value)
	{
		$result = "";
		
		if (std\is_assoc($value))
			$result .= json_encode($value);
		else if (is_object($value))
		{
			if ($value instanceof Mask)
				$result .= $this->dictionary->store($value);
			else if (($asset = $value) instanceof Html || ($asset = self::Build($value, $this->dictionary)))
				$result .= $this->capture($asset);
			else if (method_exists($value, "__toString"))
				$result .= (string)$value;
			else if ($value instanceof Struct)
				foreach ($value->to_array() as $name => $val)
					$result .= "<div>".$this->format($val)."</div>";
		}
		else if (is_bool($value))
			$result .= $value ? "true" : "false";
		else
			$result .= parent::format($value);
		
		return $result;
	}
	protected function compile()
	{
		$header = "";
		foreach (std\shift($this->head, []) as $tag => $types)
		{
			if ($tag === "script")
			{
				$script = "";
				foreach ($types as $class => $data)
					if ($data = std\parse_token("/function\\s*@\\s*(\\([^\\)]*\\)\\s*{.*})/s", $data))
						$script = ($script ? "$script, " : "")."$class: function$data";
				$header .= "<script>var init = { $script };</script>";
			}
			else if ($tag === "style")
			{
				$style = "";
				foreach ($types as $class => $data)
				{
					if (!is_string($data))
						throw new Exception("error");
					while (($ref = std\block_split($data, "{", "}")))
					{
						$med = null;
						$tar = (trim($ref[0]) !== "@") ? (($med = std\block_split($ref[1], "{", "}")) ? $med[1] : "") : $ref[1];
						
						$rule = "";
						if ($def = preg_match("/(.*?;)?([^;{]*{.*)/s", $tar, $all) ? $all[1] : $tar)
							$rule .= ".$class { $def }";
						if (isset($all[2]))
							while ($sub = std\block_split($all[2], "{", "}"))
							{
								$rule .= preg_replace("/([^,]+),?/s", ".$class $0", $sub[0])."{ $sub[1] }";
								$all[2] = $sub[2];
							}
						
						$style .= $med === null ? $rule : $ref[0]."{ $rule }";
						$data = $ref[2];
					}
				}
				$header .= "<style>$style</style>";
			}
			else
			{
				foreach ($types as $class => $data)
					if ($obj = std\parse_token("/(\\w+)/s", $tag))
						$header .= $data === null ? "<$obj $tag />" : "<$obj $tag>$data</$obj>";
			}
		}
		return "<head export>$header</head>";
	}
	
	public function render(bool $export = false)
	{
		$class = "t".$this->dictionary->store($this->name);
		$stream = parent::render();
		
		while (preg_match("/~(\\w+)~/s", $stream, $ref))
			$stream = preg_replace("/~(\\w+)~/s", "i".$this->dictionary->store("{$this->name}::{$ref[1]}"), $stream);
		
		$stream = std\extract_tag($stream, "head", function($attr, $head) use ($class, $export){
			if ($attr !== "export")
			{
				std\extract_tags($head, function($tag, $content) use($class){ $this->head[$tag][$class] = $content; });
				if ($export)
					return $this->compile();
			}
			else
				return false;
		});
		
		$stream = preg_replace("/id=[\"']#[\"']/s", "id=\"$this->id\"", $stream);
		$stream = preg_replace("/(class=[\"'])@/s", "$1{$class}", $stream);
		
		return std\strip($stream);
	}
	
	public static function Build(object $obj, Dictionary $dictionary)
	{
		if ($name = Reflect::Classes($obj, function($class){ if (self::Path($name = strtr($class, '\\', '/'))) return $name; }))
			return new static($name, [ "this" => $obj ], $dictionary, $obj instanceof Node ? "n".$dictionary->store($obj->path()) : null);
	}
}

