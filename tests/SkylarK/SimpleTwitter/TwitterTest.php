<?php

class TwitterTest extends PHPUnit_Framework_TestCase
{
	private static $_twitter;

	public static function setUpBeforeClass() {
		// Get config from root
		require_once(__dir__ . "/../../../config.php");
		global $twitter_config;
		self::$_twitter = new SkylarK\SimpleTwitter\Twitter($twitter_config['consumer_key'], $twitter_config['consumer_secret'], $twitter_config['access_token'], $twitter_config['access_secret']);
	}

	public function testConnect() {
		$tweets = self::$_twitter->tweets("twitter");
		$this->assertTrue(count($tweets) > 0);
		$this->assertTrue(!isset($tweets->errors));
	}
}