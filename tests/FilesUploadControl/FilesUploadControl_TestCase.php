<?php

use Clevis\FilesUpload\FilesUploadControl;
use Nette\Http\FileUpload;
use Nette\Application\UI\Form;
use Nette\Application\Request;

require_once __DIR__ . '/../common/TestCase.php';

/**
 * @author Václav Šír
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
abstract class FilesUploadControl_TestCase extends TestCase
{

	/** @var Form */
	protected $form;

	/** @var FilesUploadControl */
	protected $control;

	/** @var \Clevis\FilesUpload\IFilesRepository|\Mockery\MockInterface */
	protected $filesRepository;

	protected function setUp()
	{
		parent::setUp();
		$this->filesRepository = Mockery::mock('Clevis\FilesUpload\IFilesRepository');
		$this->form = new Form;
		$this->control = $this->form['uploadControl'] = new FilesUploadControl(NULL, $this->filesRepository);
		file_put_contents(__DIR__ . '/test file', 'x');
		file_put_contents(__DIR__ . '/test.gif', base64_decode('R0lGODlhCgAKAIABAAAAAP///yH+EUNyZWF0ZWQgd2l0aCBHSU1QACwAAAAACgAKAAACFIQdB5Hc/hJii8KrMsb5uPqFmlUAADs='));
	}

	protected function tearDown()
	{
		parent::tearDown();
		file_exists(__DIR__ . '/test file') && unlink(__DIR__ . '/test file');
		file_exists(__DIR__ . '/test.gif') && unlink(__DIR__ . '/test.gif');
		$_SESSION = array();
	}

	/**
	 * @param string $name
	 * @param array $lowLevelFileInfo
	 * @return FileUpload
	 */
	protected function createNetteHttpFileUpload($name = 'test.txt', $lowLevelFileInfo = array())
	{
		$lowLevelFileInfo += array(
			'name' => $name,
			'type' => 'application/octet-stream',
			'size' => 12345,
			'tmp_name' => __DIR__ . DIRECTORY_SEPARATOR . $name,
			'error' => UPLOAD_ERR_OK,
		);
		return new FileUpload($lowLevelFileInfo);
	}

	/**
	 * @param array $submittedFiles Array of Nette\Http\FileUpload
	 * @param array $autoUploadedIds Array of int
	 * @return \Nette\Application\Request
	 */
	protected function createUploadSubmitRequest(array $submittedFiles = array(), array $autoUploadedIds = array())
	{
		$request = new Request('Test', 'post', array());
		$request->files = array(
			$this->control->getHtmlName() => $submittedFiles,
		);
		$request->post = array(
			$this->control->getAutoUploadedIdsHtmlName() => $autoUploadedIds,
		);
		$request->parameters = array(Nette\Application\UI\Presenter::SIGNAL_KEY => 'uploadForm-submit');
		return $request;
	}

	protected function createUploadSignalRequest(array $submittedFiles = array())
	{
		$request = $this->createUploadSubmitRequest($submittedFiles);
		$request->parameters = array(Nette\Application\UI\Presenter::SIGNAL_KEY => 'uploadForm-uploadControl-upload');
		return $request;
	}

	/**
	 * - Má attachnutý formulář.
	 * - Mockuje link().
	 * @return TestPresenter
	 */
	protected function createPresenterWithUploadForm()
	{
		/** @var TestPresenter $presenter */
		$presenter = $this->getMock('TestPresenter', array('link'));
		$presenter->injectPrimary(
			$this->context, $this->context->application, $this->context->{'nette.httpContext'},
			$this->context->httpRequest, $this->context->httpResponse, $this->context->session,
			$this->context->user
		);
		$presenter->expects($this->any())->method('link')->withAnyParameters()->will($this->returnArgument(0));
		$form = $this->form;
		$presenter->onCreateComponent[] = function (TestPresenter $presenter, $name) use ($form)
		{
			if ($name === 'uploadForm')
			{
				$presenter->addComponent($form, 'uploadForm');
			}
		};
		return $presenter;
	}

	/**
	 * @param \Nette\Application\Request $request
	 * @return \Nette\Http\Response
	 */
	protected function runRequest(Request $request)
	{
		return $this->createPresenterWithUploadForm()->run($request);
	}

	/**
	 * @return \Nette\Http\SessionSection
	 */
	protected function createSessionSection()
	{
		$session = Mockery::mock('Nette\Http\Session[start]', array($this->context->httpRequest, $this->context->httpResponse));
		$session->shouldReceive('start')->zeroOrMoreTimes();
		$session->shouldReceive('isStarted')->zeroOrMoreTimes()->andReturn(TRUE);
		$sessionSection = $session->getSection('foo');
		return $sessionSection;
	}

	protected function expectFilesMock($method, $arguments, $return = NULL, $times = 1)
	{
		$this->filesRepository
			->shouldReceive($method)
			->withArgs($arguments)
			->times($times)
			->andReturn($return);
	}

	/**
	 * @param int $id
	 * @param string $fileName
	 * @param int $fileSize
	 * @param string $contentType
	 * @return \Mockery\MockInterface|\Clevis\FilesUpload\IFileEntity
	 */
	protected function createFileMock($id, $fileName, $fileSize = 1, $contentType = 'application/octet-stream')
	{
		$uploadedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$uploadedFile->shouldReceive('getId')->zeroOrMoreTimes()->andReturn($id);
		$uploadedFile->shouldReceive('getFileName')->zeroOrMoreTimes()->andReturn($fileName);
		$uploadedFile->shouldReceive('getFileSize')->zeroOrMoreTimes()->andReturn($fileSize);
		$uploadedFile->shouldReceive('getContentType')->zeroOrMoreTimes()->andReturn($contentType);
		$uploadedFile->shouldReceive('getExtension')->zeroOrMoreTimes()->andReturnUsing(function () use ($uploadedFile)
		{
			/** @var \Clevis\FilesUpload\IFileEntity $uploadedFile */
			$fileName = $uploadedFile->getFileName();
			$dotPosition = strrpos($fileName, '.');
			$extensionPosition = $dotPosition !== FALSE ? $dotPosition + 1 : strlen($fileName);
			return substr($fileName, $extensionPosition);
		});
		return $uploadedFile;
	}

}
