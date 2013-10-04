<?php

namespace Clevis\FilesUpload;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\ISignalReceiver;
use Nette\ComponentModel\IComponent;
use Nette\Forms\Form as NForm;
use Nette\Forms\Rules;
use Nette\Forms\Validator;
use Nette\Http\FileUpload;
use Nette\Http\SessionSection;
use Nette\Utils\Arrays;

class FilesUploadControl extends TemplateFormControl implements ISignalReceiver
{

	public $onAutoUpload = array();
	public $onBeforeDelete = array();
	public $onDelete = array();

	public $thumbnailMaxWidth = 80;
	public $thumbnailMaxHeight = 80;
	private $uploadedFiles = array();

	/** @var Rules */
	private $autoUploadRules;

	/** @var IFileUrlProvider */
	private $urlProvider;

	const AUTO_UPLOAD_HTMLNAME_SUFFIX = '-autoUploaded';
	const RULE_EXTENSION = ':extension';
	const RULE_FILE_EXISTS = ':fileExists';

	/** @var IFilesRepository */
	private $filesRepository;

	/** @var SessionSection */
	private $autoUploadsSessionSection;

	public function __construct($caption = NULL, IFilesRepository $filesRepository)
	{
		parent::__construct($caption);
		$this->filesRepository = $filesRepository;
		$this->autoUploadRules = new Rules($this);
	}

	/**
	 * @param IFileUrlProvider $urlProvider
	 */
	public function setUrlProvider(IFileUrlProvider $urlProvider)
	{
		$this->urlProvider = $urlProvider;
	}

	/**
	 * @param SessionSection $autoUploadsSession
	 */
	public function setAutoUploadsSessionSection(SessionSection $autoUploadsSession)
	{
		$this->autoUploadsSessionSection = $autoUploadsSession;
	}

	protected function createTemplate()
	{
		$template = parent::createTemplate();
		$template->uploadControl = $this;
		return $template;
	}

	/**
	 * @param IComponent $form
	 */
	protected function attached($form)
	{
		parent::attached($form);
		if ($form instanceof NForm)
		{
			$form->getElementPrototype()->enctype = 'multipart/form-data';
		}
	}

	/**
	 * @return IFileEntity[]
	 */
	public function getValue()
	{
		return $this->uploadedFiles;
	}

	/**
	 * @throws NotImplementedException
	 */
	public function setValue($value)
	{
		if ($value !== NULL)
		{
			throw new NotImplementedException;
		}
	}

	public function getAutoUploadedIdsHtmlName()
	{
		return $this->getHtmlName() . static::AUTO_UPLOAD_HTMLNAME_SUFFIX;
	}

	public function loadHttpData()
	{
		$httpData = $this->getForm()->getHttpData();
		/** @var FileUpload[] $fileUploadInfos */
		$fileUploadInfos = Arrays::get($httpData, $this->getHtmlName(), array());
		/** @var string[] $autoUploadedIds */
		$autoUploadedIds = Arrays::get($httpData, $this->getAutoUploadedIdsHtmlName(), array());
		$this->loadSubmittedFiles($fileUploadInfos);
		$this->loadAutoUploadedFiles($autoUploadedIds);
		foreach ($this->getValue() as $file)
		{
			if ($this->autoUploadsSessionSection)
			{
				$key = array_search($file->getId(), (array) $this->autoUploadsSessionSection->autoIds);
				if ($key !== false)
				{
					unset($this->autoUploadsSessionSection->autoIds[$key]);
				}
			}
		}
	}

	/**
	 * Zpracuje soubory normálně odeslané formulářem.
	 * @param FileUpload[] $fileInfos Array of Nette\Http\FileUpload
	 */
	private function loadSubmittedFiles(array $fileInfos)
	{
		foreach ($fileInfos as $fileUploadInfo)
		{
			/** @var \Nette\Http\FileUpload $fileUploadInfo */
			if ($fileUploadInfo->error === UPLOAD_ERR_OK)
			{
				$file = $this->filesRepository->createNewFile($fileUploadInfo->getName(), $fileUploadInfo->getTemporaryFile());
				$this->uploadedFiles[] = $file;
			}
			else
			{
				if ($fileUploadInfo->error !== UPLOAD_ERR_NO_FILE)
				{
					$errorMessages = array(
						UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.',
						UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
						UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
						UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
						UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
						UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
						UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
						UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
					);
					$description = isset($errorMessages[$fileUploadInfo->error]) ? $errorMessages[$fileUploadInfo->error] : $fileUploadInfo->error;
					$this->addError($description);
					$this->addRule(function () { return FALSE; }, $description);
				}
			}
		}
	}

	/**
	 * Načte soubory, které už byly předtím nahrány autouploadem.
	 * @param array $fileIds Array of int.
	 */
	private function loadAutoUploadedFiles(array $fileIds)
	{
		foreach ($fileIds as $fileId)
		{
			$file = $this->filesRepository->getById($fileId);
			$this->uploadedFiles[] = $file;
		}
	}

	public function addRule($operation, $message = NULL, $arg = NULL)
	{
		if (in_array($operation, array(NForm::MAX_FILE_SIZE, NForm::MIME_TYPE, NForm::IMAGE, self::RULE_EXTENSION)))
		{
			$this->autoUploadRules->addRule($operation, $message, $arg);
			return parent::addRule(callback(get_class($this), 'validate' . ucfirst(ltrim($operation, ':'))), $message, $arg);
		}
		else
		{
			return parent::addRule($operation, $message, $arg);
		}
	}

	public function signalReceived($signal)
	{
		if ($signal === 'upload')
		{
			$this->handleUpload();
		}
		else
		{
			if ($signal === 'delete')
			{
				$pathToControl = $this->lookupPath('Nette\Application\UI\Presenter');
				$parameters = $this->getPresenter()->popGlobalParameters($pathToControl);
				$file = $this->filesRepository->getById((array) Arrays::get($parameters, 'id'));
				$this->handleDelete($file);
			}
			else
			{
				throw new BadSignalException;
			}
		}
	}

	private function handleUpload()
	{
		$request = $this->getPresenter()->getRequest();
		if (strtolower($request->getMethod()) !== 'post')
		{
			throw new BadRequestException('Upload vyžaduje method POST.');
		}
		$requestFiles = $request->getFiles();
		/** @var FileUpload[] $fileInfos */
		$fileInfos = Arrays::get($requestFiles, $this->getHtmlName(), array());
		$this->loadSubmittedFiles($fileInfos);
		$payload = array('files' => array());
		foreach ($this->getValue() as $file)
		{
			$validationErrors = $this->getAutoUploadValidationErrors($file);
			if ($validationErrors)
			{
				$filePayload = array(
					'name' => $file->getFileName(),
					'error' => count($validationErrors) === 1 ? $validationErrors[0] : $validationErrors,
				);
			}
			else
			{
				$filePayload = $this->persistRaiseEventAndFlush($file);
				$this->storeFileIdInSession($file);
				if (!$filePayload)
				{
					$filePayload = $this->createFilePayload($file);
				}
			}
			$payload['files'][] = $filePayload;
		}
		foreach ($this->errors as $errorMessage)
		{
			$payload['files'][] = array(
				'name' => '',
				'error' => $errorMessage,
			);
		}
		$this->getPresenter()->sendResponse(new JsonResponse($payload, 'application/json'));
	}

	/**
	 * @param IFileEntity $file
	 */
	private function storeFileIdInSession($file)
	{
		if ($file->getId() !== NULL && $this->autoUploadsSessionSection)
		{
			$this->autoUploadsSessionSection->autoIds[] = $file->getId();
		}
	}

	/**
	 * @param IFileEntity $file
	 * @return array
	 */
	private function persistRaiseEventAndFlush(IFileEntity $file)
	{
		$this->filesRepository->saveFile($file);
		$filePayload = new \ArrayObject;
		$this->onAutoUpload($file, $filePayload);
		$filePayload = $filePayload->getArrayCopy();
		return $filePayload;
	}

	private function createFilePayload(IFileEntity $file)
	{
		if ($file->getId() === NULL)
		{
			return array(
				'name' => $file->getFileName(),
				'error' => 'Upload refused.',
			);
		}
		$pathPrefix = $this->lookupPath('Nette\Application\UI\Presenter') . self::NAME_SEPARATOR;
		$filePayload = array(
			'id' => $file->getId(),
			'name' => $file->getFileName(),
			'size' => $file->getFileSize(),
			'type' => $file->getContentType(),
			'delete_type' => 'DELETE',
			'delete_url' => $this->getPresenter()->link($pathPrefix . 'delete!', array($pathPrefix . 'id' => $file->getId())),
		);
		if ($this->urlProvider)
		{
			$filePayload['thumbnail_url'] = $this->urlProvider->getThumbnailUrl($file, $this->thumbnailMaxWidth, $this->thumbnailMaxHeight);
			$filePayload['url'] = $this->urlProvider->getFullsizeUrl($file);
		}
		return $filePayload;
	}

	private function getAutoUploadValidationErrors(IFileEntity $file)
	{
		$fileErrors = array();
		foreach ($this->autoUploadRules as $rule)
		{
			/** @var $rule \Nette\Forms\Rule */
			$operation = isset($rule->operation) // Nette 2.0: operation; Nette 2.1: validator
				? $rule->operation
				: $rule->validator;
			$isValid = self::isFileValid($file, $operation, $rule->arg);
			if (!$isValid)
			{
				$fileErrors[] = Validator::formatMessage($rule, false);
			}
		}
		return $fileErrors;
	}

	private function handleDelete(IFileEntity $file)
	{
		$this->onBeforeDelete($file);
		if ($this->autoUploadsSessionSection && in_array($file->getId(), (array) $this->autoUploadsSessionSection->autoIds))
		{
			$key = array_search($file->getId(), (array) $this->autoUploadsSessionSection->autoIds);
			unset($this->autoUploadsSessionSection->autoIds[$key]);
			$this->filesRepository->deleteFile($file);
		}
		$this->onDelete($file);
		$this->getPresenter()->sendResponse(
			new JsonResponse(array('success' => true))
		);
	}

	public static function validateFileSize(self $control, $maxAllowedSize)
	{
		return self::validateFilesByRule($control, NForm::MAX_FILE_SIZE, $maxAllowedSize);
	}

	public static function validateMimeType(self $control, $expectedMimeType)
	{
		return self::validateFilesByRule($control, NForm::MIME_TYPE, $expectedMimeType);
	}

	public static function validateImage(self $control)
	{
		return self::validateFilesByRule($control, NForm::IMAGE);
	}

	public static function validateExtension(self $control, $fileExtension)
	{
		return self::validateFilesByRule($control, self::RULE_EXTENSION, $fileExtension);
	}

	public static function validateFileExists(self $control)
	{
		return self::validateFilesByRule($control, self::RULE_FILE_EXISTS);
	}

	private static function validateFilesByRule(self $control, $operation, $argument = NULL)
	{
		foreach ($control->getValue() as $file)
		{
			if (!self::isFileValid($file, $operation, $argument))
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * @param IFileEntity $file
	 * @param string $operation
	 * @param mixed $argument
	 * @return bool
	 */
	private static function isFileValid(IFileEntity $file, $operation, $argument = NULL)
	{
		switch ($operation)
		{
			case NForm::MAX_FILE_SIZE:
				return $file->getFileSize() <= $argument;
			case NForm::MIME_TYPE:
				return in_array($file->getContentType(), (array) $argument);
			case NForm::IMAGE:
				return in_array($file->getContentType(), array('image/gif', 'image/png', 'image/jpeg'), true);
			case self::RULE_EXTENSION:
				return in_array($file->getExtension(), (array) $argument, true);
			case self::RULE_FILE_EXISTS:
				return file_exists($file->getFullPath());
		}
		return true;
	}

}
