<?php

namespace Clevis\FilesUpload;

interface IFileUrlProvider
{

	/**
	 * @param IFileEntity $file
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @return string
	 */
	public function getThumbnailUrl(IFileEntity $file, $maxWidth, $maxHeight);

	/**
	 * @param IFileEntity $file
	 * @return string
	 */
	public function getFullsizeUrl(IFileEntity $file);
}
