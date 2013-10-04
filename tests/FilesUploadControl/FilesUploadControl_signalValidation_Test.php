<?php

use Nette\Application\UI\Form;
use Nette\Application\Request;

require_once __DIR__ . '/FilesUploadControl_TestCase.php';

/**
 * Test validace souborů odeslaných signálem.
 *
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
class FilesUploadControl_signalValidation_Test extends FilesUploadControl_TestCase
{

	public function testRefuseInvalid_fileSize()
	{
		$file = $this->createFileMock(1, 'test.gif', 123);
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MAX_FILE_SIZE, 'Blah blah %d bytes.', 10);
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestResultsInFileError($request, 'test.gif', 'Blah blah 10 bytes.');
	}

	public function testAcceptValid_fileSize()
	{
		$file = $this->createFileMock(1, 'test.gif', 99);
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MAX_FILE_SIZE, 'Blah blah %d bytes.', 100);
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestSuccessfullyPersistsFile($request, $file);
	}

	public function testRefuseInvalid_mimeType()
	{
		$file = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MIME_TYPE, 'Required filetype %s.', 'text/brainfuck');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestResultsInFileError($request, 'test.gif', 'Required filetype text/brainfuck.');
	}

	public function testAcceptValid_mimeType()
	{
		$file = $this->createFileMock(1, 'test.gif', 1, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MIME_TYPE, 'Required filetype %s.', 'image/gif');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestSuccessfullyPersistsFile($request, $file);
	}

	public function testRefuseInvalid_image()
	{
		$file = $this->createFileMock(1, 'test file', 1, 'text/plain');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::IMAGE, 'Not an image.');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->assertRequestResultsInFileError($request, 'test file', 'Not an image.');
	}

	public function testAcceptValid_image()
	{
		$file = $this->createFileMock(1, 'test.gif', 1, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::IMAGE, 'Not an image.');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestSuccessfullyPersistsFile($request, $file);
	}

	public function testRefuseInvalid_extension()
	{
		$file = $this->createFileMock(1, 'test file', 1, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, '*.gif required', 'gif');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->assertRequestResultsInFileError($request, 'test file', '*.gif required');
	}

	public function testAcceptValid_extension()
	{
		$file = $this->createFileMock(1, 'test.gif', 1, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, '*.gif required', 'gif');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestSuccessfullyPersistsFile($request, $file);
	}

	public function testErrorMessageContainsAllInvalidationReasons()
	{
		$file = $this->createFileMock(1, 'test.gif', 666, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MAX_FILE_SIZE, 'Size %d.', 10);
		$this->control->addRule(Form::MIME_TYPE, 'Filetype %s.', 'text/plain');
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->assertRequestResultsInFileError($request, 'test.gif', array('Size 10.', 'Filetype text/plain.'));
	}

	private function assertRequestResultsInFileError(Request $request, $expectedFileName, $expectedMessage)
	{
		$response = $this->runRequest($request);
		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		/** @var $response Nette\Application\Responses\JsonResponse */
		$expectedPayload = array(
			'files' => array(
				array('name' => $expectedFileName, 'error' => $expectedMessage),
			)
		);
		$this->assertSame($expectedPayload, $response->payload);
	}

	private function assertRequestSuccessfullyPersistsFile($request, \Clevis\FilesUpload\IFileEntity $file)
	{
		$this->expectFilesMock('saveFile', array($file));
		$response = $this->runRequest($request);
		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		$this->assertSame($file->getId(), $response->payload['files'][0]['id']);
	}

}
