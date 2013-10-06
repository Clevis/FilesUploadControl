<?php

namespace Clevis\FilesUpload;

interface IFileEntity
{

	/**
	 * ID of entity, if it's already persisted, or NULL.
	 *
	 * @return int|NULL
	 */
	public function getEntityId();

	/**
	 * User file name.
	 *
	 * @return string
	 */
	public function getFileName();

	/**
	 * @return int
	 */
	public function getFileSize();

	/**
	 * MIME content type.
	 *
	 * @return string
	 */
	public function getContentType();

	/**
	 * File name extension.
	 *
	 * @return string
	 */
	public function getExtension();

	/**
	 * Full path to the file on filesystem.
	 *
	 * @return string
	 */
	public function getFullPath();

}
