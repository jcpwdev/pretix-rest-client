<?php


namespace Jcpw;

use Throwable;

class NoOrganizerException extends \Exception

{

    function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = $message ?: 'No Organizer found to connect with';

        parent::__construct($message, $code, $previous);
    }


}