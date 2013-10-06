<?php

require_once __DIR__ . '/FilesUploadControl_TestCase.php';

/**
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
class FilesUploadControl_csrf_Test extends FilesUploadControl_TestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->context->removeService('session');
		$this->context->session = $this->getMock(
			'Nette\\Http\\Session',
			array('start', 'getSection'),
			array($this->getMock('Nette\\Http\\IRequest'), $this->getMock('Nette\\Http\\IResponse'))
		);
		$sessionSection = new \Nette\Http\SessionSection($this->context->session, 'x');
		$this->context->session->expects($this->any())->method('getSection')->will($this->returnValue($sessionSection));
	}

	public function testForbid_MissingTokenOnProtectedForm()
	{
		$this->form->addProtection();
		$uploadedFiles = array($this->createNetteHttpFileUpload('x.txt'));
		$request = $this->createUploadSignalRequest($uploadedFiles);

		$this->setExpectedException('Nette\Application\BadRequestException');
		$this->runRequest($request);
	}

	public function testForbid_WrongTokenOnProtectedForm()
	{
		$this->form->addProtection();
		$uploadedFiles = array($this->createNetteHttpFileUpload('x.txt'));
		$request = $this->createUploadSignalRequest($uploadedFiles);
		$tokenName = 'uploadForm-uploadControl-' . \Nette\Forms\Form::PROTECTOR_ID;
		$request->setParameters($request->parameters + array($tokenName => 'aaaaa'));

		$this->setExpectedException('Nette\Application\BadRequestException');
		$this->runRequest($request);
	}

	public function testAllow_CorrectTokenOnProtectedForm()
	{
		$protector = $this->form->addProtection();
		$this->form->httpRequest = $this->context->httpRequest;
		Access($protector, '$session')->set($this->context->session);
		$uploadedFiles = array($this->createNetteHttpFileUpload('x.txt'));
		$request = $this->createUploadSignalRequest($uploadedFiles);
		$tokenName = 'uploadForm-uploadControl-' . \Nette\Forms\Form::PROTECTOR_ID;
		$request->setParameters($request->parameters + array($tokenName => $protector->getToken()));

		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $this->createFileMock(1, 'x.txt'));
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$this->runRequest($request);
	}

	public function testAllow_UnprotectedFormWithoutToken()
	{
		$uploadedFiles = array($this->createNetteHttpFileUpload('x.txt'));
		$request = $this->createUploadSignalRequest($uploadedFiles);

		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $this->createFileMock(1, 'x.txt'));
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$this->runRequest($request);
	}

	public function testAllow_UnprotectedFormWithToken()
	{
		$uploadedFiles = array($this->createNetteHttpFileUpload('x.txt'));
		$request = $this->createUploadSignalRequest($uploadedFiles);
		$tokenName = 'uploadForm-uploadControl-' . \Nette\Forms\Form::PROTECTOR_ID;
		$request->setParameters($request->parameters + array($tokenName => 'aaaaa'));

		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $this->createFileMock(1, 'x.txt'));
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$this->runRequest($request);
	}

}
