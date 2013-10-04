<?php

use Nette\Application\Request;

require_once __DIR__ . '/FilesUploadControl_TestCase.php';
require_once __DIR__ . '/FilesUploadControl_NonFinalCallback.php';

/**
 * @covers Clevis\FilesUpload\FilesUploadControl
 */
class FilesUploadControl_deleteSignal_Test extends FilesUploadControl_TestCase
{

	public function testOnBeforeDeleteIsRaisedBeforeRemove()
	{
		$fileToDelete = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('getById', array(array(1)), $fileToDelete);
		$this->expectFilesMock('deleteFile', array($fileToDelete));
		$session = $this->createSessionSection();
		$session->autoIds = array($fileToDelete->getId());
		$this->control->setAutoUploadsSessionSection($session);
		$beforeDeleteEventHandler = Mockery::mock('FilesUploadControl_NonFinalCallback');
		$beforeDeleteEventHandler->shouldReceive('__invoke')
			->with(Mockery::on(function (\Clevis\FilesUpload\IFileEntity $file)
			{
				return $file->getFileName() === 'test.gif' && $file->getId();
			}))
			->once();
		$this->control->onBeforeDelete[] = $beforeDeleteEventHandler;

		$this->sendDeleteSignal($fileToDelete);
	}

	public function testOnDeleteEventIsRaisedAfterRemove()
	{
		$fileToDelete = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('getById', array(array(1)), $fileToDelete);
		$this->expectFilesMock('deleteFile', array($fileToDelete));
		$session = $this->createSessionSection();
		$session->autoIds = array($fileToDelete->getId());
		$this->control->setAutoUploadsSessionSection($session);
		$deleteEventHandler = Mockery::mock('FilesUploadControl_NonFinalCallback');
		$deleteEventHandler->shouldReceive('__invoke')
			->with(Mockery::on(function (\Clevis\FilesUpload\IFileEntity $file)
			{
				$this->filesRepository->mockery_getExpectationsFor('deleteFile')->verify();
				return $file->getFileName() === 'test.gif';
			}))
			->once();
		$this->control->onDelete[] = $deleteEventHandler;

		$this->sendDeleteSignal($fileToDelete);
	}

	public function testWithoutSessionDeleteSignalJustConfirmsSuccess()
	{
		$fileToDelete = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('getById', array(array(1)), $fileToDelete);
		$this->expectFilesMock('deleteFile', array($fileToDelete), NULL, 0); // Signál entitu nesmaže, pokud nemá její ID v session
		$response = $this->sendDeleteSignal($fileToDelete);
		/** @var $response \Nette\Application\Responses\JsonResponse */

		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		$this->assertSame(array('success' => TRUE), $response->payload);
	}

	public function testDeleteSignalRemovesFileEntitiesListedInSession()
	{
		$fileToDelete = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('getById', array(array(1)), $fileToDelete);
		$this->expectFilesMock('deleteFile', array($fileToDelete));
		$sessionSection = $this->createSessionSection();
		$sessionSection->autoIds = array($fileToDelete->getId());
		$this->control->setAutoUploadsSessionSection($sessionSection);

		$response = $this->sendDeleteSignal($fileToDelete);
		/** @var $response \Nette\Application\Responses\JsonResponse */

		$this->assertInstanceOf('Nette\Application\Responses\JsonResponse', $response);
		$this->assertSame(array('success' => TRUE), $response->payload);
	}

	public function testDeleteSignalRemovesRequestedIdFromSession()
	{
		$fileToDelete = $this->createFileMock(1, 'test.gif');
		$this->expectFilesMock('getById', array(array(1)), $fileToDelete);
		$this->expectFilesMock('deleteFile', array($fileToDelete));
		$sessionSection = $this->createSessionSection();
		$sessionSection->autoIds = array($fileToDelete->getId());
		$this->control->setAutoUploadsSessionSection($sessionSection);

		$response = $this->sendDeleteSignal($fileToDelete);
		/** @var $response \Nette\Application\Responses\JsonResponse */

		$this->assertEmpty($sessionSection->autoIds);
	}

	/**
	 * @param \Clevis\FilesUpload\IFileEntity $fileToDelete
	 * @return Nette\Application\IResponse
	 */
	private function sendDeleteSignal(\Clevis\FilesUpload\IFileEntity $fileToDelete)
	{
		$request = new Request('Test', 'post', array());
		$request->parameters = array(
			Nette\Application\UI\Presenter::SIGNAL_KEY => 'uploadForm-uploadControl-delete',
			'uploadForm-uploadControl-id' => $fileToDelete->getId(),
		);
		$response = $this->runRequest($request);
		return $response;
	}

}
