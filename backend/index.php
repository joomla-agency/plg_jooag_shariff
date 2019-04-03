<?php
require_once __DIR__.'/vendor/autoload.php';
use Heise\Shariff\Backend;
/**
* Demo Application using Shariff Backend
*/
class Application
{
	public static function run()
	{
		$file = file_get_contents(__DIR__ . '/shariff.json');
		$configuration = json_decode($file, true);
		header('Content-type: application/json');
		$url = isset($_GET['url']) ? $_GET['url'] : '';
		
		if($url)
		{
			$shariff = new Backend($configuration);
			echo json_encode($shariff->get($url));
		}
		else
		{
			echo json_encode(null);
		}
	}
}
Application::run();