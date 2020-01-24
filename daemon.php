<?php 
if (!isset($argc) && ($n = count($scripts = get_included_files()) - 2) >= 0)
{
	exec('bash -c "exec nohup setsid php '.$scripts[$n].' > /dev/null 2>&1 &"'); 
	die(); 
}
