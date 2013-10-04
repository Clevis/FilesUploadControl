<?php

use Nette\Application\UI\Presenter;
use Clevis\TemplateFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;
$configurator->setDebugMode(FALSE);
$configurator->setTempDirectory(__DIR__ . '/temp');
$systemContainer = $configurator->createContainer();
$systemContainer->addService('templateFactory', new \Clevis\FilesUpload\TemplateFactory($systemContainer->cacheStorage));
$GLOBALS['nette_container'] = $systemContainer;

/**
 * Presenter for testing purposes
 *
 * @author Jan Tvrdík
 */
class TestPresenter extends Presenter
{

	/** @var bool disable canonicalize() */
	public $autoCanonicalize = FALSE;

	/** @var array # => callback($presenter, $name) */
	public $onCreateComponent = array();

	public function renderDefault()
	{
		$this->terminate();
	}

	protected function createComponent($name)
	{
		$this->onCreateComponent($this, $name);

		if (!isset($this->components[$name]))
		{
			return parent::createComponent($name);
		}
	}

}
