<?php

namespace Clevis\FilesUpload;

use Nette\Application\UI\Presenter;
use Nette\Forms\Controls\BaseControl;
use Nette\Reflection\ClassType;
use Nette\Utils\Html;


/**
 * Formulářový prvek vykreslovaný Latte šablonou.
 *
 * Šablona se jmenuje stejně jako třída a je ve stejném adresáři. Předávají se
 * do ní proměnné:
 *
 * - `$control`
 * - `$presenter`
 */
abstract class TemplateFormControl extends BaseControl
{

	/**
	 * Vygeneruje ze šablony HTML controlu.
	 * @return Html
	 */
	public function getControl()
	{
		$this->setOption('rendered', true);
		$template = $this->createTemplate();
		ob_start();
		$template->render();
		$templateOutput = ob_get_clean();
		return Html::el(NULL)->add($templateOutput);
	}

	/**
	 * @return \Nette\Templating\FileTemplate
	 */
	protected function createTemplate()
	{
		$classType = ClassType::from($this);
		$templateFileName = dirname($classType->fileName) . DIRECTORY_SEPARATOR . $classType->getShortName() . '.latte';
		$presenter = $this->getPresenter();
		$template = $presenter->getContext()->getService('templateFactory')->createTemplate($templateFileName, $this->lookup('Nette\Application\UI\Control'));
		$template->presenter = $presenter;
		$template->control = $this;
		return $template;
	}

	/**
	 * @param bool $need
	 * @return Presenter
	 */
	public function getPresenter($need = true)
	{
		return $this->lookup('Nette\Application\UI\Presenter', $need);
	}

}
