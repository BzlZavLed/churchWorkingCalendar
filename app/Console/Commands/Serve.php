<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class Serve extends ServeCommand
{
    protected function serverCommand()
    {
        $command = parent::serverCommand();
        // These must be set before PHP parses multipart requests, not with ini_set.
        array_splice($command, 1, 0, ['-d', 'upload_max_filesize=20M', '-d', 'post_max_size=25M']);

        return $command;
    }
}
