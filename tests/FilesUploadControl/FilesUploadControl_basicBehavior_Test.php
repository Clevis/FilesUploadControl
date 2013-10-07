<?php

use Nette\Application\Request;

require_once __DIR__ . '/FilesUploadControl_TestCase.php';
require_once __DIR__ . '/FilesUploadControl_NonFinalCallback.php';

/**
 * Description of FileUploadControl_Test
 *
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
class FilesUploadControl_basicBehavior_Test extends FilesUploadControl_TestCase
{

	public function testFileUploadImplementsNetteFormsIControl()
	{
		$this->assertInstanceOf('Nette\Forms\IControl', $this->control);
	}

	public function testControlForcesFormToUseEnctypeMultipartFormData()
	{
		$this->assertSame('multipart/form-data', $this->form->getElementPrototype()->enctype);
	}

	public function testSetValueSetsValue()
	{
		$file = $this->createFileMock(1, 'filename');
		$this->control->setValue(array($file));
		$this->assertSame(array($file), $this->control->getValue());
	}

	public function testSetValueIsReplacedWithSubmittedValue()
	{
		$file = $this->createFileMock(1, 'filename');
		$this->control->setValue(array($file));
		$request = $this->createUploadSubmitRequest(
			array(
				$this->createNetteHttpFileUpload('', array('error' => UPLOAD_ERR_NO_FILE)),
			)
		);
		$this->runRequest($request);
		$this->assertEmpty($this->control->getValue());
	}

	public function testSubmittedFilesAreNotPersistedOnlyReturnedByGetValue()
	{
		$request = $this->createUploadSubmitRequest(
			array(
				$this->createNetteHttpFileUpload('file1_ííí'),
				$this->createNetteHttpFileUpload('file2_ááá'),
			)
		);
		$fileEntity1 = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$fileEntity2 = Mockery::mock('Clevis\FilesUpload\IFileEntity');

		// createNewFile expectations
		$this->expectFilesMock('createNewFile', array('file1_ííí', __DIR__ . DIRECTORY_SEPARATOR . 'file1_ííí'), $fileEntity1);
		$this->expectFilesMock('createNewFile', array('file2_ááá', __DIR__ . DIRECTORY_SEPARATOR . 'file2_ááá'), $fileEntity2);

		$this->runRequest($request);

		$controlValues = $this->control->getValue();
		$this->assertCount(2, $controlValues);
		$this->assertSame($controlValues[0], $fileEntity1);
		$this->assertSame($controlValues[1], $fileEntity2);
	}

	public function testSubmittedEmptyControlIsIgnored()
	{
		$request = $this->createUploadSubmitRequest(
			array(
				$this->createNetteHttpFileUpload('', array('error' => UPLOAD_ERR_NO_FILE)),
			)
		);
		$this->runRequest($request);
		$this->assertEmpty($this->control->getValue());
	}

	public function testUploadErrorAddsFormControlError()
	{
		$request = $this->createUploadSubmitRequest(
			array(
				$this->createNetteHttpFileUpload('', array(
					'name' => 'filename',
					'error' => UPLOAD_ERR_INI_SIZE
				)),
			)
		);
		$this->runRequest($request);
		$this->assertEmpty($this->control->getValue());
		$this->assertCount(1, $this->control->getErrors());
	}

	public function testSubmittedFilesAreRemovedFromTheSession()
	{
		$autoUploadedFile = $this->createFileMock(10, 'test file');
		$this->expectFilesMock('getById', array(10), $autoUploadedFile);

		$sessionSection = $this->createSessionSection();
		$sessionSection->autoIds = array(10, 123);
		$this->control->setAutoUploadsSessionSection($sessionSection);
		$request = $this->createUploadSubmitRequest(array(), array(10));
		$this->runRequest($request);

		$this->assertSame(array(123), array_values($sessionSection->autoIds));
	}

	public function testAutoUploadedFilesAreReturnedByGetValue()
	{
		$autoUploadedFiles = array(
			Mockery::mock('Clevis\FilesUpload\IFileEntity'),
			Mockery::mock('Clevis\FilesUpload\IFileEntity'),
		);
		$this->expectFilesMock('getById', array(1), $autoUploadedFiles[0]);
		$this->expectFilesMock('getById', array(2), $autoUploadedFiles[1]);

		$request = $this->createUploadSubmitRequest(array(), array(1, 2));
		$this->runRequest($request);
		$controlValues = $this->control->getValue();
		$this->assertCount(2, $controlValues);
		$this->assertContains($controlValues[0], $autoUploadedFiles);
		$this->assertContains($controlValues[1], $autoUploadedFiles);
	}

	public function testGetValueReturnsBothSubmittedAndAutoUploadedFiles()
	{
		$autoUploadedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$submittedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$submittedFileInfo = $this->createNetteHttpFileUpload('submitted');

		// getById(1) => $autoUploadedFile
		$this->expectFilesMock('getById', array(1), $autoUploadedFile);
		$this->expectFilesMock('createNewFile', array('submitted', __DIR__ . DIRECTORY_SEPARATOR . 'submitted'), $submittedFile);

		$request = $this->createUploadSubmitRequest(array($submittedFileInfo), array(1));
		$this->runRequest($request);

		$controlValues = $this->control->getValue();
		$this->assertCount(2, $controlValues);
		$this->assertContains($autoUploadedFile, $controlValues);
		$this->assertContains($submittedFile, $controlValues);
	}

	public function testUnknownSignalThrowsException()
	{
		$request = new Request('Test', 'get', array());
		$request->parameters = array(Nette\Application\UI\Presenter::SIGNAL_KEY => 'uploadForm-uploadControl-neumimejdetepryc');
		$this->setExpectedException('Nette\Application\UI\BadSignalException');
		$this->runRequest($request);
	}

	public function testUploadSignalRequiresHttpMethodPost()
	{
		$request = new Request('Test', 'get', array());
		$request->parameters = array(Nette\Application\UI\Presenter::SIGNAL_KEY => 'uploadForm-uploadControl-upload');
		$this->setExpectedException('Nette\Application\BadRequestException');
		$this->runRequest($request);
	}

	public function testUploadSignalStoresFileAndReturnsInfo()
	{
		$uploadedFile = $this->createFileMock(123, 'test file');
		$uploadedFileInfo = $this->createNetteHttpFileUpload('test file');
		$this->expectFilesMock('createNewFile', array('test file', __DIR__ . DIRECTORY_SEPARATOR . 'test file'), $uploadedFile);
		$this->expectFilesMock('saveFile', array($uploadedFile));

		$request = $this->createUploadSignalRequest(array($uploadedFileInfo));
		$response = $this->runRequest($request);

		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		/** @var $response Nette\Application\Responses\JsonResponse */
		$expectedPayload = array(
			'files' => array(
				array(
					'id' => 123,
					'name' => 'test file',
					'size' => 1,
					'type' => 'application/octet-stream',
					'delete_type' => 'DELETE',
					'delete_url' => 'uploadForm-uploadControl-delete!',
				),
			),
		);
		$this->assertSame($expectedPayload, $response->payload);
	}

	public function testUploadSignalReturnsThumbnailAndFullsizeLinksIfUrlProviderIsProvided()
	{
		$urlProvider = Mockery::mock('Clevis\FilesUpload\IFileUrlProvider');
		$urlProvider->shouldReceive('getThumbnailUrl')
			->once()
			->withAnyArgs()
			->andReturn('thumbnail url');
		$urlProvider->shouldReceive('getFullsizeUrl')
			->once()
			->withAnyArgs()
			->andReturn('full size url');
		$this->control->setUrlProvider($urlProvider);

		$uploadedFileInfo = $this->createNetteHttpFileUpload('test file');
		$uploadedFile = $this->createFileMock(123, 'test file');
		$this->expectFilesMock('createNewFile', array('test file', __DIR__ . DIRECTORY_SEPARATOR . 'test file'), $uploadedFile);
		$this->expectFilesMock('saveFile', array($uploadedFile));

		$request = $this->createUploadSignalRequest(array($uploadedFileInfo));
		$response = $this->runRequest($request);

		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		/** @var $response Nette\Application\Responses\JsonResponse */
		$expectedPayload = array(
			'files' => array(
				array(
					'id' => 123,
					'name' => 'test file',
					'size' => 1,
					'type' => 'application/octet-stream',
					'delete_type' => 'DELETE',
					'delete_url' => 'uploadForm-uploadControl-delete!',
					'thumbnail_url' => 'thumbnail url',
					'url' => 'full size url',
				),
			),
		);
		$this->assertSame($expectedPayload, $response->payload);
	}

	public function testAutouploadAddsFormControlErrorsIntoPayload()
	{
		$errorneousFile = $this->createNetteHttpFileUpload('test file', array(
			'error' => UPLOAD_ERR_NO_TMP_DIR,
		));
		$request = $this->createUploadSignalRequest(array($errorneousFile));
		$response = $this->runRequest($request);

		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		/** @var $response Nette\Application\Responses\JsonResponse */
		$this->assertCount(1, $response->payload['files']);
		$this->assertNotEmpty($response->payload['files'][0]['error']);
		$this->assertSame($this->control->errors[0], $response->payload['files'][0]['error']);
	}

	public function testOnAutoUploadEventIsRaised()
	{
		$uploadedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$uploadedFile->shouldIgnoreMissing();
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $uploadedFile);
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$autoUploadEventHandler = Mockery::mock('FilesUploadControl_NonFinalCallback');
		$autoUploadEventHandler->shouldReceive('__invoke')
			->with($uploadedFile, Mockery::type('\ArrayObject'))
			->once();
		$this->control->onAutoUpload[] = $autoUploadEventHandler;

		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);
	}

	public function testOnAutoUploadMayChangeFilePayload()
	{
		$uploadedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$uploadedFile->shouldIgnoreMissing();
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $uploadedFile);
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$this->control->onAutoUpload[] = function ($file, $filePayload)
		{
			$filePayload['foo'] = 'bar';
		};
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$response = $this->runRequest($request);
		/** @var $response \Nette\Application\Responses\JsonResponse */
		$this->assertSame(array('foo' => 'bar'), $response->payload['files'][0]);
	}

	public function testUploadSignalAddsIdToSessionSection()
	{
		$uploadedFile = $this->createFileMock(666, 'test file');
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $uploadedFile);
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$sessionSection = $this->createSessionSection();
		$this->control->setAutoUploadsSessionSection($sessionSection);
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);

		$this->assertSame(array(666), $sessionSection->autoIds);
	}

	public function testUploadSignalDoesnAddIdIntoSessionIfTheFileIdIsNull()
	{
		$uploadedFile = Mockery::mock('Clevis\FilesUpload\IFileEntity');
		$uploadedFile->shouldIgnoreMissing();
		$this->expectFilesMock('createNewFile', array(Mockery::any(), Mockery::any()), $uploadedFile);
		$this->expectFilesMock('saveFile', array(Mockery::any()));
		$sessionSection = $this->createSessionSection();
		$this->control->setAutoUploadsSessionSection($sessionSection);
		$request = $this->createUploadSignalRequest(array($this->createNetteHttpFileUpload('test file')));
		$this->runRequest($request);

		$autoIds = $sessionSection->autoIds;
		$this->assertEmpty($autoIds);
	}

	public function testControlIsRenderedWithoutError()
	{
		$this->runRequest($this->createUploadSubmitRequest());
		$controlHtml = $this->control->getControl();
		$this->assertInstanceOf('Nette\Utils\Html', $controlHtml);
		$this->assertContains('<input ', (string) $controlHtml);
	}

}
