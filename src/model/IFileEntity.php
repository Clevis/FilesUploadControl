<?php

namespace Clevis\FilesUpload;

interface IFileEntity
{

	/**
	 * @return int|NULL
	 */
	public function getId();
	public function getFileName();
	public function getFileSize();
	public function getContentType();
	public function getExtension();
	public function getFullPath();

}
