<?php

namespace Clevis\FilesUpload;

interface IFilesRepository
{

	/**
	 * Create a new file entity.
	 *
	 * @param string $fileName
	 * @param string $tempPath
	 * @return IFileEntity
	 */
	public function createNewFile($fileName, $tempPath);

	/**
	 * Get a file entity by ID.
	 *
	 * @param int|array $id
	 * @return IFileEntity
	 */
	public function getById($id);

	/**
	 * Persist and flush.
	 *
	 * @param IFileEntity $file
	 */
	public function saveFile(IFileEntity $file);

	/**
	 * Remove and flush.
	 *
	 * @param IFileEntity $file
	 */
	public function deleteFile(IFileEntity $file);

}
