<?php
namespace Copal\App;

use std;
use Copal\Base\Nexus\Asset;
use Copal\Utility\Data\Dictionary;
use Copal\Utility\Meta\Action;

abstract class Module extends Node
{
	private $dictionary;

	public function handle($request, $args)
	{
		if ($this->dictionary && ($action = $this->dictionary->retrieve($request)) instanceof Action && $action->restore($this))
			return $action($args);
		else
			return false;
	}
	public function render(bool $update = false)
	{
		if (!$this->dictionary)
			$this->dictionary = new Dictionary();

		$result = "";

		if (!$update && ($asset = Asset::Build($this, $this->dictionary)))
			$result = $asset->render(true);
		else foreach ($this->changed() as $node)
			if ($asset = Asset::Build($node, $this->dictionary))
				$result .= $asset->render(false);

		return $result;
	}
}