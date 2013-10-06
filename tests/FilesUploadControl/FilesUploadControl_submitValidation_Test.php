<?php

use Nette\Application\UI\Form;

require_once __DIR__ . '/FilesUploadControl_TestCase.php';

/**
 * Test validace souborů odeslaných normálně formulářem.
 *
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
class FilesUploadControl_submitValidation_Test extends FilesUploadControl_TestCase
{

	public function testAcceptValid_fileSize()
	{
		$file = $this->createFileMock(1, 'test file', 1);
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MAX_FILE_SIZE, 'Max. file size %d bytes.', 10);
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testRefuseInvalid_fileSize()
	{
		$file = $this->createFileMock(1, 'test file', 123);
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MAX_FILE_SIZE, 'Max. file size %d bytes.', 10);
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertTrue($this->control->hasErrors());
		$this->assertSame(array('Max. file size 10 bytes.'), $this->control->getErrors());
	}

	public function testAcceptValid_mimeType()
	{
		$file = $this->createFileMock(1, 'test file', 123, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MIME_TYPE, 'Mime type %s.', 'image/gif');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testAcceptValid_mimeType_array()
	{
		$file = $this->createFileMock(1, 'test file', 123, 'image/gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MIME_TYPE, 'Mime type %s.', array('text/qbasic', 'image/gif'));
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testRefuseInvalid_mimeType()
	{
		$file = $this->createFileMock(1, 'test file', 123, 'text/fortran');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::MIME_TYPE, 'Mime type %s.', 'image/gif');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);
		$this->assertTrue($this->control->hasErrors());
		$this->assertSame(array('Mime type image/gif.'), $this->control->getErrors());
	}

	public function testAcceptValid_image()
	{
		$file = $this->createFileMock(1, 'test.gif', 123, 'image/png');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::IMAGE, 'Img.');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testRefuseInvalid_image()
	{
		$file = $this->createFileMock(1, 'test.gif', 123, 'text/html');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(Form::IMAGE, 'Img.');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);
		$this->assertTrue($this->control->hasErrors());
		$this->assertSame(array('Img.'), $this->control->getErrors());
	}

	public function testAcceptValid_extension()
	{
		$file = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, 'Extension.', 'gif');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testAcceptValid_extension_array()
	{
		$file = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, 'Extension.', array('php', 'gif'));
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

	public function testRefuseInvalid_extension()
	{
		$file = $this->createFileMock(1, 'test.exe');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, 'Extension.', 'gif');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);
		$this->assertTrue($this->control->hasErrors());
		$this->assertSame(array('Extension.'), $this->control->getErrors());
	}

	public function testAcceptValid_caseInsensitiveExtension()
	{
		$file = $this->createFileMock(1, 'test.GIF');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $file);
		$this->control->addRule(\Clevis\FilesUpload\FilesUploadControl::RULE_EXTENSION, 'Extension.', 'gif');
		$request = $this->createUploadSubmitRequest(array($this->createNetteHttpFileUpload('test.gif')));
		$this->runRequest($request);
		$this->assertFalse($this->control->hasErrors());
	}

}
