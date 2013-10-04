<?php

namespace Clevis\FilesUpload;

use Nette;

class NotImplementedException extends \LogicException
{

}

class BadSignalException extends Nette\Application\UI\BadSignalException
{

}

class BadRequestException extends Nette\Application\BadRequestException
{

}
