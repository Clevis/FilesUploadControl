<?php

class TestCase extends PHPUnit_Framework_TestCase
{

	/** @var SystemContainer */
	protected $context;

	protected function setUp()
	{
		parent::setUp();
		$this->context = $GLOBALS['nette_container'];
	}

	protected function tearDown()
	{
		parent::tearDown();
		Mockery::close();
	}

}
