<?php
namespace App\Services\WhatsApp;
class AssistantFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason) { parent::__construct('Assistant request failed.'); }
}
